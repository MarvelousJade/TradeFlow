// PreToolUse hook: keep real environment files (.env, .env.local, ...) out of the
// agent's context. .env.example is a committed template and stays allowed.
// Exit code 2 blocks the tool call and shows stderr to Claude.
import { basename } from 'node:path';

const isSecretEnv = (name) => /^\.env(\..+)?$/.test(name) && name !== '.env.example';

let raw = '';
for await (const chunk of process.stdin) raw += chunk;

let input;
try {
  input = JSON.parse(raw);
} catch {
  process.exit(0);
}

const tool = input.tool_name;
const args = input.tool_input ?? {};
let blocked = false;

if (['Read', 'Edit', 'Write', 'NotebookEdit'].includes(tool)) {
  const path = String(args.file_path ?? args.notebook_path ?? '');
  blocked = isSecretEnv(basename(path.replace(/\\/g, '/')));
} else if (tool === 'Grep' || tool === 'Glob') {
  const target = String(args.path ?? '') + ' ' + String(args.glob ?? args.pattern ?? '');
  blocked = target.split(/[\s/\\]+/).some(isSecretEnv);
} else if (tool === 'Bash' || tool === 'PowerShell') {
  // Token-level check; a determined command can still evade this. It is a guard
  // against accidents, not a security boundary.
  blocked = String(args.command ?? '')
    .split(/[\s"'`;|&<>()=/\\]+/)
    .some(isSecretEnv);
}

if (blocked) {
  console.error('Blocked by .claude/hooks/block-env.mjs: .env files may hold secrets and must stay out of the agent context. Use .env.example for variable names.');
  process.exit(2);
}
process.exit(0);
