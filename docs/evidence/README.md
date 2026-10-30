# Verification evidence

## Latest controlled measurement

[2026-10-29 idempotent retry](idempotent-retry-2026-10-29/README.md): the two new WordPress E2E tests for server replay and `idempotency_conflict` fail with the REST controller fix reverted and pass with it restored. A separate `/verify` run is recorded, including PHP and E2E.

[2026-10-25 resume-impact measurement](resume-impact-2026-10-25/README.md): same three stale-response cases fail on the baseline widget and pass on the current widget; separate current checks record 9 unit/component passes, 8 browser passes, typecheck and build success. Includes raw JSON/text logs and a reproducible runner. These are controlled engineering results, not measured business outcomes. The historical captures below remain unchanged.

Reviewed 2026-10-24. Text captures retain tool output and failures; terminal ANSI color sequences and trailing whitespace were removed for readability in the final documentation stage. Counts/timings/assertions are unchanged. Do not combine separate run totals.

| Capture | Source and command |
| --- | --- |
| `eligibility-before.txt` | `94ef7ef` implementation plus three new deferred-response regressions; `npm test`: 3 failed, 4 passed. |
| `eligibility-after.txt` | Working tree subsequently committed as `8ed337f`; `npm test`: 9 passed. |
| `widget-browser.txt` | Working tree subsequently committed as `0274395`; `npm run test:widget`: 8 passed. |
| `control-baseline.txt` | Clean `0274395`; `npm test`: 9 passed. |
| `control-failing.txt` | Deliberately faulty `ee34f06`; `npm run test:widget -- --project=desktop --grep "failed submission"`: 1 failed. |
| `control-strengthened-failing.txt` | `ee34f06` plus strengthened payload assertions; same targeted command: 1 failed. |
| `control-repaired-*.txt` | Working tree subsequently committed as `2a6ff59`; `npm test` 9 passed and `npm run test:widget` 8 passed, separate runs. |
| `final-*.txt` | Integrated `76e113b`: `npm ci`, `npm test`, `npm run typecheck`, `npm run build`, `npm run test:widget`; all exit 0. |
| `audit-production.json` | `76e113b`, `npm audit --omit=dev --json`, exit 0, zero findings. |
| `audit-summary.json` | Bounded summary of `npm audit --json` on `76e113b`, exit 1: 31 toolchain findings; includes original advisory links and npm remediation metadata, not an endorsement of suggested force changes. |

Environment: Windows Git Bash, Node v22.23.3, Vitest 3.2.7, React 19.2.8, Vite 7.3.6, TypeScript 5.9.3, Playwright 1.62.0, Chromium 151.0.7922.34. Browser tests launch a loopback-only disposable API; no real WordPress data is touched. `npm ci` warns that an esbuild install script was blocked by the environment's allow-scripts policy; typecheck, build and browser tests nevertheless completed successfully. This does not validate every machine's installation policy.

`SHA256SUMS` fingerprints normalized text/JSON captures. Screenshots and HTML reports are generated, ignored artifacts: rerun the browser suite to create `test-results/**/booking-success.png` and `playwright-report/`. Final desktop/mobile completion images were inspected; no human browser use, full accessibility audit, Firefox/WebKit test, PHP suite, live database test or Lighthouse measurement is claimed. Backend and PHP reproduction commands remain in the root README.
