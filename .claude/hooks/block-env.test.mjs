// Run: node .claude/hooks/block-env.test.mjs
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const hook = fileURLToPath(new URL('./block-env.mjs', import.meta.url));

const cases = [
  ['Read .env', 2, { tool_name: 'Read', tool_input: { file_path: 'E:\\TradeFlow\\.env' } }],
  ['Read .env.local', 2, { tool_name: 'Read', tool_input: { file_path: '/repo/.env.local' } }],
  ['Edit .env.production', 2, { tool_name: 'Edit', tool_input: { file_path: '/repo/.env.production' } }],
  ['Read .env.example', 0, { tool_name: 'Read', tool_input: { file_path: '/repo/.env.example' } }],
  ['Read api.ts', 0, { tool_name: 'Read', tool_input: { file_path: '/repo/api.ts' } }],
  ['Bash cat .env', 2, { tool_name: 'Bash', tool_input: { command: 'cat .env' } }],
  ['Bash quoted ./.env', 2, { tool_name: 'Bash', tool_input: { command: 'cat "./.env" | head' } }],
  ['Bash cat .env.example', 0, { tool_name: 'Bash', tool_input: { command: 'cat .env.example' } }],
  ['Bash npm test', 0, { tool_name: 'Bash', tool_input: { command: 'npm test' } }],
  ['Grep inside .env', 2, { tool_name: 'Grep', tool_input: { pattern: 'KEY', path: '.env' } }],
  ['Grep source tree', 0, { tool_name: 'Grep', tool_input: { pattern: 'KEY', path: 'wordpress' } }],
];

let failed = 0;
for (const [name, expected, input] of cases) {
  const { status } = spawnSync(process.execPath, [hook], { input: JSON.stringify(input) });
  const ok = status === expected;
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}: expected ${expected}, got ${status}`);
}
const malformed = spawnSync(process.execPath, [hook], { input: 'not json' }).status;
if (malformed !== 0) failed++;
console.log(`${malformed === 0 ? 'PASS' : 'FAIL'}  malformed input is ignored: got ${malformed}`);
console.log(`${cases.length + 1 - failed}/${cases.length + 1} passed`);
process.exit(failed ? 1 : 0);
