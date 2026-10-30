---
name: verify
description: Run TradeFlow's local checks and report evidence against the acceptance criteria. Use before saying any change is done.
---

Run these in order from the repository root and capture the real output of each:

1. `npm run typecheck`
2. `npm test`
3. `npm run test:widget`
4. `npm run build`
5. If `.claude/` changed: `node .claude/hooks/block-env.test.mjs` and `node .claude/hooks/guard-boundaries.test.mjs`
6. If PHP changed: `composer test` and PHP lint over `wordpress/wp-content` (commands in `CLAUDE.md`)
7. If booking behaviour, REST routes or PHP changed and the Docker stack is up: `tests/e2e/` in Chromium (command in `CLAUDE.md`)

Then report:

- A table: command, pass/fail, test counts, duration. Copy numbers from the output; don't estimate.
- For each acceptance criterion of the current task, the specific test or manual check that proves it, and whether that check ran here.
- What was NOT verified locally. Lighthouse CI runs only in CI, and E2E counts as unverified if the Docker stack wasn't up. Name those as CI-only or needing review.
- Any failing check: show the failure and the likely cause. Don't edit tests to make them pass.

$ARGUMENTS
