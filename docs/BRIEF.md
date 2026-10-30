# Task brief and goal template

Copy this when starting a task. Describe the outcome, not the steps; project rules already live in `CLAUDE.md`.

## 1. Brief

```
Task: <what to build or fix>
Guardrails: CLAUDE.md boundaries apply; don't touch <files or areas>; no new dependencies.
Done: <acceptance criterion in docs/ENGINEERING.md> is covered by a test, and /verify passes.
```

For booking behaviour, write the acceptance criterion in `docs/ENGINEERING.md` first. `/prove-fix` stops if it is missing.

## 2. Goal

The goal judge only sees the conversation, not files or commands, so the goal must name a check whose output is printed in the chat.

Feature:

```
/goal /verify passes with every row green and its output shown; no files under docs/evidence/ or tests/ are deleted or skipped; stop after 15 turns.
```

Bug fix:

```
/goal /prove-fix shows the new test failing without the fix and passing with it, /verify passes with its output shown, and a new docs/evidence/ folder is recorded; no existing evidence or tests are changed or skipped; stop after 15 turns.
```

Avoid goals like "the feature is finished": the judge can't confirm them and may stop early.

## 3. Review afterwards

- The `/verify` table and the "not verified locally" list. PHP tests, PHP lint and `tests/e2e/` run locally; Lighthouse CI is CI only.
- The evidence folder, for bug fixes.
- For larger changes, ask for a one-page visual of what changed and what was assumed.
