// Run: node .claude/hooks/guard-boundaries.test.mjs
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const hook = fileURLToPath(new URL('./guard-boundaries.mjs', import.meta.url));
const root = fileURLToPath(new URL('../..', import.meta.url));

const bash = (command) => ({ tool_name: 'Bash', tool_input: { command } });
const edit = (file_path, old_string, new_string) => ({
  tool_name: 'Edit',
  tool_input: { file_path, old_string, new_string },
});
const write = (file_path, content) => ({ tool_name: 'Write', tool_input: { file_path, content } });

const widgetTest = 'tests/widget/booking.spec.ts';
const unitTest = 'wordpress/wp-content/themes/tradeflow/assets/src/api.test.ts';

const cases = [
  // Evidence
  ['edit existing evidence', 'deny', edit('docs/evidence/control-baseline.txt', 'a', 'b')],
  ['write evidence (absolute path)', 'deny', write(`${root}docs/evidence/final-browser.txt`, 'x')],
  ['rm evidence', 'deny', bash('rm docs/evidence/control-failing.txt')],
  ['append to existing evidence', 'deny', bash('npm test >> docs/evidence/control-baseline.txt')],
  ['record new evidence file', 'allow', bash('npm test > docs/evidence/retry-2026-10-29.txt')],
  ['edit engineering doc', 'allow', edit('docs/ENGINEERING.md', 'a', 'b')],
  ['write a new evidence capture', 'allow', write('docs/evidence/retry-2026-10-29/unit.txt', 'x')],
  ['update the evidence index', 'allow', edit('docs/evidence/README.md', 'a', 'b')],
  ['edit the hash list', 'ask', edit('docs/evidence/SHA256SUMS', 'a', 'a\nb')],
  ['overwrite an existing capture', 'deny', write('docs/evidence/final-unit.txt', 'x')],
  // Tests
  ['add test.skip', 'deny', edit(widgetTest, "test('x', async", "test.skip('x', async")],
  ['add it.only', 'deny', edit(unitTest, "it('sends", "it.only('sends")],
  ['add xit', 'deny', edit(unitTest, "it('sends", "xit('sends")],
  ['add markTestSkipped', 'deny', edit('tests/php/ValidatorTest.php', '{', "{ $this->markTestSkipped('later');")],
  ['write test file with new skip', 'deny', write(unitTest, "describe.skip('all', () => {});")],
  ['remove an existing skip', 'allow', edit(widgetTest, "test.skip('x'", "test('x'")],
  ['normal test edit', 'allow', edit(widgetTest, 'toHaveLength(2)', 'toHaveLength(3)')],
  ['skip word in app code', 'allow', edit('wordpress/wp-content/themes/tradeflow/assets/src/api.ts', 'a', 'it.skip')],
  ['rm a test file', 'deny', bash(`rm ${unitTest}`)],
  ['git rm a PHP test', 'deny', bash('git rm tests/php/ValidatorTest.php')],
  // Git and deploy
  ['git push', 'ask', bash('git push -u origin feature/idempotent-retry')],
  ['force push', 'deny', bash('git push --force origin main')],
  ['force push short flag', 'deny', bash('git push -f')],
  ['reset hard', 'ask', bash('git reset --hard HEAD~1')],
  ['edit render.yaml', 'ask', edit('render.yaml', 'a', 'b')],
  ['edit CI workflow', 'ask', edit('.github/workflows/quality.yml', 'a', 'b')],
  ['git status', 'allow', bash('git status')],
  ['git commit', 'allow', bash('git commit -m "feat: x"')],
  ['npm test', 'allow', bash('npm test')],
  ['read a test file', 'allow', { tool_name: 'Read', tool_input: { file_path: unitTest } }],
];

let failed = 0;
for (const [name, expected, input] of cases) {
  const { stdout, status } = spawnSync(process.execPath, [hook], {
    input: JSON.stringify({ ...input, cwd: root }),
    env: { ...process.env, CLAUDE_PROJECT_DIR: root },
  });
  const text = stdout.toString().trim();
  const actual = status !== 0 ? `exit ${status}` : text ? JSON.parse(text).hookSpecificOutput.permissionDecision : 'allow';
  const ok = actual === expected;
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}: expected ${expected}, got ${actual}`);
}
console.log(`${cases.length - failed}/${cases.length} passed`);
process.exit(failed ? 1 : 0);
