# Idempotent retry: server replay and conflict — 2026-10-29

## Question and result

Do the new WordPress E2E tests for acceptance criteria 3 and 4 (`docs/ENGINEERING.md`, "Idempotent booking retry") catch a missing server-side replay?

**Yes.** With the REST controller's replay handling reverted, both new tests failed for the reasons the problem describes. With it restored, both passed.

| Test | Without controller fix | With fix |
| --- | --- | --- |
| a retry with the same idempotency key replays the original reference | Failed: retry got `409` (`duplicate_lead`), expected `200` | Passed |
| a retry with the same key but changed contact details is refused | Failed: changed retry stored with `201`, expected `409 idempotency_conflict` | Passed |

## Provenance and method

- Source: working tree on branch `feature/idempotent-retry`, uncommitted, on top of `fba991d`.
- Environment: Windows x64, Node v22.23.3, Playwright 1.62.0, Chromium. WordPress from `docker-compose.yml` (`wordpress:php8.3-apache`, `mysql:8.4`) on Docker Desktop 29.9.2: a fresh install following `README.md`, with the plugin and theme mounted live from the working tree.
- Fix under test: the replay block in `class-tradeflow-rest-controller.php`. The repository, validator and installer changes stayed in place, so the database already had the `idempotency_key` column. Without the controller change, nothing calls `replay_for_key` and the key is never stored.
- Fail run: `git stash push -- wordpress/wp-content/plugins/tradeflow-core/includes/class-tradeflow-rest-controller.php`, then `npx playwright test --project=chromium --grep "a retry with the same" --reporter=list` with `WP_ADMIN_PASSWORD` set to the README's local value. Output: `failing.txt`, 2 failed.
- Pass run: `git stash pop`, then the same command. Output: `passing.txt`, 2 passed. The restored controller was byte-identical to a backup taken before the stash, and `git diff --stat` was unchanged.
- Data: synthetic only (`Retry Test`, `retry-<uuid>@example.test`, `416-555-0144`), stored in the disposable local stack.

## Separate full `/verify` run (same working tree, fix in place)

| Check | Result |
| --- | --- |
| `npm run typecheck` (includes `tests/e2e/`) | Exit 0 |
| `npm test` | 12 passed (3 files), 2.34 s |
| `npm run test:widget` | 10 passed, 17.9 s |
| `npm run build` | Exit 0; JS 208.07 kB / gzip 65.49 kB |
| Hook tests | `block-env` 12/12, `guard-boundaries` 30/30 |
| `composer test` | OK, 5 tests, 19 assertions (PHP 8.3.36) |
| PHP lint, `wordpress/wp-content` | 18 files, no syntax errors |
| `tests/e2e/`, Chromium | 6 passed, 7.5 s |

Captures above are copied from tool output and were not stored here. Do not add these counts to the targeted runs: the two new tests are included in the 6 E2E cases.

## Limitations

- "No second confirmation email" (criterion 3) is not asserted. Local mail goes to the WordPress debug log inside the container, which the tests can't read. That part rests on code review: `send_confirmation` is skipped on the replay path.
- "No insert" is inferred, not counted. A stored replay would return a new reference. A stored conflict attempt would make the follow-up fresh-key request return `409 duplicate_lead` instead of `201`.
- The race fallback (losing a concurrent unique-index insert) and the `dbDelta` upgrade path (criterion 6) are not exercised. The stack was a fresh install.
- Lighthouse CI was not run. It is CI only.
