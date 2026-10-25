// Reproduce engineering evidence without editing application source or installing tools.
// Usage: node scripts/measure-resume-impact.mjs [new-evidence-directory]
import { cpSync, existsSync, mkdirSync, readFileSync, writeFileSync, rmSync, mkdtempSync } from 'node:fs';
import { resolve, relative, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import os from 'node:os';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
process.chdir(root);
const started = new Date().toISOString();
const output = resolve(process.argv[2] ?? `docs/evidence/resume-impact-${started.replace(/[:.]/g, '-')}`);
if (existsSync(output)) throw new Error(`Refusing to overwrite evidence: ${output}`);
const source = 'wordpress/wp-content/themes/tradeflow/assets/src';
const widget = `${source}/BookingWidget.tsx`;
function git(...args) {
  const result = spawnSync('git', args, { cwd: root, encoding: 'utf8' });
  if (result.status !== 0) throw new Error(result.stderr || `git failed: ${args.join(' ')}`);
  return result.stdout;
}
// New documentation/scripts are allowed; measured tracked files must match HEAD.
git('diff', '--exit-code', 'HEAD', '--', source, 'vite.config.ts', 'package.json', 'package-lock.json', 'tsconfig.json', 'playwright.widget.config.ts', 'demo', 'tests/widget');
const current = git('rev-parse', 'HEAD').trim();
const baseline = git('rev-parse', 'b93463a').trim();
mkdirSync(output, { recursive: true });
mkdirSync(resolve('.tools/resume-impact'), { recursive: true });
const sandbox = mkdtempSync(resolve('.tools/resume-impact/run-'));
const sha = (data) => createHash('sha256').update(data).digest('hex');
const results = [];
const versions = {};
for (const name of ['vitest', 'react', 'vite', 'typescript', '@playwright/test']) {
  versions[name] = JSON.parse(readFileSync(resolve('node_modules', name, 'package.json'), 'utf8')).version;
}
const metadata = {
  started, current, baseline, node: process.version, platform: process.platform,
  architecture: process.arch, cpu: os.cpus()[0]?.model, versions,
  workingTreeAtStart: git('status', '--short'),
  method: 'Same current regression tests and installed dependencies in one temporary sandbox; only BookingWidget.tsx is replaced by its baseline version for the before run. Then verify the full current checkout separately. No dependencies installed.',
  testSha256: sha(readFileSync(`${source}/BookingWidget.test.tsx`)),
  baselineWidgetSha256: sha(git('show', `${baseline}:${widget}`)),
  currentWidgetSha256: sha(readFileSync(widget)),
};
function run(name, cli, args, expectedExit = 0) {
  const command = [process.execPath, relative(root, cli), ...args];
  console.log(`Running ${name}`);
  const before = performance.now();
  const result = spawnSync(process.execPath, [cli, ...args], {
    cwd: root, encoding: 'utf8', maxBuffer: 20 * 1024 * 1024,
    timeout: 180_000,
    env: {
      ...process.env, NO_COLOR: '1', FORCE_COLOR: '0', PLAYWRIGHT_HTML_OPEN: 'never',
      ...(name === 'current-browser' ? { PLAYWRIGHT_JSON_OUTPUT_FILE: join(output, 'current-browser.json') } : {}),
    },
  });
  const record = {
    name, command, exitCode: result.status, expectedExit,
    matchedExpectation: result.status === expectedExit && !result.error,
    elapsedMs: Math.round(performance.now() - before),
    ...(result.error ? { error: result.error.message } : {}),
  };
  const strip = (text) => (text ?? '').replace(/\x1b\[[0-9;]*m/g, '');
  writeFileSync(join(output, `${name}.txt`), `${JSON.stringify(record, null, 2)}\n\nSTDOUT\n${strip(result.stdout)}\nSTDERR\n${strip(result.stderr)}`);
  results.push(record);
  console.log(`  exit ${record.exitCode}; expected ${expectedExit}`);
  return record;
}
const cli = (path) => resolve('node_modules', path);
try {
  cpSync(resolve(source), join(sandbox, source), {
    recursive: true,
    filter: (path) => !relative(resolve(source), path).split(/[\\/]/).includes('node_modules'),
  });
  cpSync(resolve('vite.config.ts'), join(sandbox, 'vite.config.ts'));
  writeFileSync(join(sandbox, 'package.json'), '{"type":"module"}\n');
  const target = join(sandbox, widget);
  const config = join(sandbox, 'vite.config.ts');
  const common = ['run', '--config', config, '-t', 'ignores an obsolete eligibility', '--reporter=json'];
  writeFileSync(target, git('show', `${baseline}:${widget}`));
  run('race-before', cli('vitest/vitest.mjs'), [...common, '--outputFile', join(output, 'race-before.json')], 1);
  cpSync(resolve(widget), target);
  run('race-after', cli('vitest/vitest.mjs'), [...common, '--outputFile', join(output, 'race-after.json')]);
  run('current-unit', cli('vitest/vitest.mjs'), ['run', '--reporter=json', '--outputFile', join(output, 'current-unit.json')]);
  run('current-typecheck', cli('typescript/bin/tsc'), ['--noEmit']);
  run('current-build', cli('vite/bin/vite.js'), ['build']);
  run('current-browser', cli('@playwright/test/cli.js'), ['test', '--config', 'playwright.widget.config.ts', '--reporter=list,json'], 0);
  // Vitest's process exit alone cannot prove the intended baseline failure.
  for (const name of ['race-before', 'race-after', 'current-unit']) {
    const path = join(output, `${name}.json`);
    if (existsSync(path)) {
      const report = JSON.parse(readFileSync(path, 'utf8'));
      const record = results.find((item) => item.name === name);
      record.tests = { passed: report.numPassedTests, failed: report.numFailedTests, pending: report.numPendingTests, total: report.numTotalTests };
      record.cases = report.testResults.flatMap((suite) => suite.assertionResults.map(({ fullName, status }) => ({ fullName, status })));
    }
  }
  const before = results.find((item) => item.name === 'race-before');
  const after = results.find((item) => item.name === 'race-after');
  metadata.raceComparisonVerified = before.tests?.failed === 3 && after.tests?.passed === 3
    && before.cases?.filter((item) => item.status === 'failed').every((item) => after.cases?.some((other) => other.fullName === item.fullName && other.status === 'passed'));
} finally {
  rmSync(sandbox, { recursive: true, force: true });
  metadata.finished = new Date().toISOString();
  writeFileSync(join(output, 'summary.json'), `${JSON.stringify({ ...metadata, results }, null, 2)}\n`);
}
if (!metadata.raceComparisonVerified || results.some((item) => !item.matchedExpectation)) process.exitCode = 1;
console.log(`Evidence: ${relative(root, output)}`);
