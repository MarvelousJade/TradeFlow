// PreToolUse hook enforcing the Boundaries section of CLAUDE.md.
// "deny" blocks the call; "ask" pauses it for the user's approval.
import { existsSync, readFileSync } from 'node:fs';
import { isAbsolute, relative, resolve } from 'node:path';

let raw = '';
for await (const chunk of process.stdin) raw += chunk;

let input;
try {
  input = JSON.parse(raw);
} catch {
  process.exit(0);
}

const root = process.env.CLAUDE_PROJECT_DIR ?? input.cwd ?? process.cwd();
const tool = input.tool_name;
const args = input.tool_input ?? {};

const repoPath = (path) => {
  const absolute = isAbsolute(path) ? path : resolve(root, path);
  return relative(root, absolute).replace(/\\/g, '/');
};

// Captures are frozen once written. The folder index may change, and the hash list
// gains lines for new captures, so it needs approval rather than a block.
const evidenceIndex = 'docs/evidence/README.md';
const evidenceHashes = 'docs/evidence/SHA256SUMS';
const isEvidence = (path) =>
  path.startsWith('docs/evidence/') && path !== evidenceIndex && path !== evidenceHashes;
const isTest = (path) =>
  /\.(test|spec)\.(ts|tsx|js|mjs)$/.test(path) || /^tests\/php\/.+Test\.php$/.test(path);
const isDeployConfig = (path) =>
  path === 'render.yaml' ||
  path === 'Dockerfile' ||
  path.startsWith('deployment/') ||
  path.startsWith('.github/workflows/');

// Markers that disable or narrow a test run.
const skipMarker =
  /\b(?:it|test|describe)\.(?:skip|only|todo|fixme)\b|\b(?:xit|xdescribe|fit|fdescribe)\s*\(|markTestSkipped|markTestIncomplete/g;
const countSkips = (text) => (String(text ?? '').match(skipMarker) ?? []).length;

function decide(decision, reason) {
  console.log(
    JSON.stringify({
      hookSpecificOutput: {
        hookEventName: 'PreToolUse',
        permissionDecision: decision,
        permissionDecisionReason: `${reason} (CLAUDE.md Boundaries, .claude/hooks/guard-boundaries.mjs)`,
      },
    }),
  );
  process.exit(0);
}

if (['Edit', 'Write', 'NotebookEdit'].includes(tool)) {
  const path = repoPath(String(args.file_path ?? args.notebook_path ?? ''));

  if (isEvidence(path) && existsSync(resolve(root, path))) {
    decide('deny', `${path} is recorded evidence. Add a new evidence folder instead of changing it.`);
  }
  if (path === evidenceHashes) {
    decide('ask', 'SHA256SUMS fingerprints past evidence. Only new lines should be added.');
  }

  if (isTest(path)) {
    let before = '';
    let after = '';
    if (tool === 'Edit') {
      before = args.old_string;
      after = args.new_string;
    } else if (tool === 'Write') {
      const absolute = resolve(root, path);
      before = existsSync(absolute) ? readFileSync(absolute, 'utf8') : '';
      after = args.content;
    }
    if (countSkips(after) > countSkips(before)) {
      decide('deny', `This change skips or focuses a test in ${path}. Fix the code or explain the failure instead.`);
    }
  }

  if (isDeployConfig(path)) {
    decide('ask', `${path} controls deployment or CI.`);
  }
}

if (tool === 'Bash' || tool === 'PowerShell') {
  const command = String(args.command ?? '');

  if (/\bgit\s+push\b[^\n;&|]*(?:\s--force\b|\s-f\b|\s--force-with-lease\b)/.test(command)) {
    decide('deny', 'Force push rewrites shared history.');
  }
  if (/\bgit\s+push\b/.test(command)) {
    decide('ask', 'Pushing publishes the branch.');
  }
  if (/\bgit\s+reset\s+--hard\b/.test(command)) {
    decide('ask', 'git reset --hard discards uncommitted work.');
  }

  const deletes = /\b(?:rm|del|Remove-Item|git\s+rm)\b/.test(command);
  if (deletes) {
    const tokens = command.split(/[\s"'`;|&<>()]+/).filter(Boolean).map(repoPath);
    if (tokens.some(isEvidence)) decide('deny', 'Deleting recorded evidence is not allowed.');
    if (tokens.some(isTest)) decide('deny', 'Deleting a test file is not allowed. Explain why it should go.');
  }

  // New evidence files are fine; overwriting or appending to existing ones is not.
  for (const [, target] of command.matchAll(/>{1,2}\s*["']?((?:\.\/)?docs[\\/]evidence[\\/][^\s"';|&]+)/g)) {
    if (existsSync(resolve(root, target))) {
      decide('deny', `${repoPath(target)} is recorded evidence. Write to a new file instead.`);
    }
  }
}

process.exit(0);
