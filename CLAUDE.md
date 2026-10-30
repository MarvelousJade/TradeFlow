# TradeFlow

A small WordPress booking funnel for local trades teams. A customer picks an area, describes a job and a time window, and leaves contact details. Staff confirm later.

- `wordpress/wp-content/plugins/tradeflow-core/` — PHP plugin: stored leads, validation, REST routes, notifications, DB schema (`includes/`).
- `wordpress/wp-content/themes/tradeflow/assets/src/` — React widget. `BookingWidget.tsx` owns form state; `api.ts` owns requests and errors; `types.ts` mirrors REST payloads.
- `demo/` — development-only mocked API. `tests/widget/` runs against it; `tests/e2e/` needs a real WordPress stack; `tests/php/` is PHPUnit.

## Pointers

Open these when the task needs them. They are paths, not imports, so they don't load every session.

- `docs/ENGINEERING.md` — acceptance criteria, design decisions and verification journal. Read before changing booking behaviour.
- `docs/BRIEF.md` — task brief and `/goal` templates. Use when starting a new task.
- `docs/evidence/README.md` — index of recorded test runs and how evidence folders are laid out.
- `.claude/skills/verify/SKILL.md` — `/verify`: run every local check and report evidence.
- `.claude/skills/prove-fix/SKILL.md` — `/prove-fix`: show a regression test failing without the fix and passing with it, then record evidence.
- `.claude/hooks/` — the boundary hooks and their tests.
- `README.md` — local WordPress stack, Docker and deployment setup.
- `.github/workflows/quality.yml` — what CI runs, including PHP.

## Tools and commands

Verified on Windows, Node 22:

- `npm run typecheck`
- `npm test` — Vitest component tests
- `npm run test:widget` — Playwright on the mocked demo API, desktop + mobile
- `npm run build`
- `node .claude/hooks/block-env.test.mjs` and `node .claude/hooks/guard-boundaries.test.mjs` — hook tests
- `composer test` — PHPUnit on PHP 8.3 (same minor version as CI)
- `Get-ChildItem wordpress/wp-content -Recurse -Filter *.php | % { php -l $_.FullName }` — PHP lint (PowerShell)

- `$env:WP_ADMIN_PASSWORD='tradeflow_local'; npx playwright test --project=chromium` — `tests/e2e/` against the Docker stack (start and install it per `README.md`; `--project=mobile` also works, and skips the admin test by design)

PHP 8.3 (winget `PHP.PHP.NTS.8.3`) and Composer (Scoop) are installed. If `php` isn't found, the shell predates the install: open a new one. Docker Desktop is installed; `docker` works from PowerShell but not from Git Bash. Lighthouse CI still runs only in CI (`.github/workflows/quality.yml`).

Run `/verify` before reporting a change as done. For a bug fix, run `/prove-fix` too.

## How this project differs from the default

- Booking writes are never retried automatically. The customer chooses to retry.
- Each booking draft sends one `idempotency_key` on every attempt. The server replays the original reference for a matching retry. Don't rotate the key when fields change.
- Async responses only update the form if they still belong to the current request (revision guard in `BookingWidget.tsx`). Don't replace this with an Effect per input.
- Schema changes go in `TradeFlow_Installer::create_tables()` with a `TRADEFLOW_VERSION` bump. `maybe_upgrade()` applies them on update, because WordPress doesn't re-run activation.
- No Prettier config: the code uses single quotes. Format only the files you change, by hand. `npm run format:check` already fails on 11 existing files.

## Repeated gotchas

- `npx prettier --write` switches files to double quotes. Don't run it on existing files.
- Any shell command containing a `.env` filename is blocked by the hook, even in test data. Put such cases in a script file.
- Playwright needs Chromium installed (`npx playwright install chromium`) on a fresh machine.
- Git warns about LF/CRLF on PHP and docs files. That's expected. Files in `docs/evidence/` are forced to LF so their hashes stay stable.

## Boundaries

Enforced by hooks in `.claude/settings.json`:

- No reading or editing `.env*` files (`.env.example` is fine). — `block-env.mjs`
- No changing existing captures in `docs/evidence/`. They are hashed records of past test runs. Add a new dated folder instead; the index `README.md` may change, and `SHA256SUMS` edits need approval (add lines only). — `guard-boundaries.mjs`
- No skipping or focusing tests (`.skip`, `.only`, `fixme`, `markTestSkipped`) and no deleting test files. Fix the code or explain the failure. — `guard-boundaries.mjs`
- `git push`, deploy config (`render.yaml`, `deployment/`, `Dockerfile`, `.github/workflows/`) and `git reset --hard` need the user's approval. Force push is blocked. — `guard-boundaries.mjs`

Not enforceable by a hook, so follow them:

- Synthetic data only. No real names, emails or phone numbers in tests, fixtures or logs.
- Keep changes small. No dependency upgrades or new libraries without asking.
