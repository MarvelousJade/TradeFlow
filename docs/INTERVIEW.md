# Walkthrough, verification and interview notes

## Five-minute demonstration

1. Run `npm ci`, then `npm run demo`. Open `http://127.0.0.1:4173/demo/`. Explain the synthetic-data warning: this is the real widget, but not a persisted booking.
2. Click Continue with an empty postal code: expect inline validation. Enter `L5B 1M2` with Toronto selected: expect outside-coverage feedback and no advancement.
3. Choose Mississauga, Continue, enter a 12+ character job description. Continue, select the sample arrival window, Continue.
4. Fill fictitious name, email and phone, tick consent, and Send request. Expect “Your request is in.” and `TF-DEMO`. No email, persistence, export, or actual appointment is implied.
5. Show the recovery and stale-response browser tests: `npx playwright install chromium`, then `npm run test:widget`. For visible browser interaction use `npm run test:widget -- --headed --project=desktop`. These tests simulate startup failure, coverage rejection, delayed approval, and a failed write followed by explicit retry. Contact, consent, details and slot survive navigation within the mounted widget.

Backup: if Chromium is unavailable, run `npm test`, open the captured text evidence and walk through `BookingWidget.tsx`, then the deferred-promise tests. If WordPress is available, use the separate README Docker setup and `npm run test:e2e`; the demo does not verify staff assignment or persistence.

## 30-second explanation

“TradeFlow is a service-request funnel for local trades teams: customers check coverage, describe a job, request a window and receive a reference; staff confirm requests in WordPress. The reliability improvement prevents slow coverage responses from validating an address the customer has already changed. It also provides in-place recovery and deterministic desktop/mobile tests using the actual React widget with disposable data.”

## Two-minute technical walkthrough

Start at the plugin/theme boundary: PHP owns validation and stored leads; React owns the four-step, in-memory draft. `api.ts` handles JSON/multipart encoding and structured API errors. Explain why an async closure can retain a valid old postal code while its completion changes today's UI. In `next()`, a request takes a revision. Edits invalidate that revision. Success, errors and finalizers must all own the current revision before touching UI state.

Show `docs/evidence/eligibility-before.txt`: three real failing regressions before the fix. Then `eligibility-after.txt`: nine passing tests including overlapping checks and startup retry. Show a browser test using the real widget/theme against a development-only API. End with limits: these mocks don't prove database atomicity or email delivery, and drafts do not survive refresh.

## Architecture and tradeoff questions

- **Why not replace WordPress?** The plugin already provides content, admin and persistence integration. This is a localized frontend consistency problem; replacing the stack would increase risk without solving it better.
- **Why revision ownership rather than just disabling Continue?** Inputs remain editable. A disabled button prevents another click but doesn't freeze the request's meaning.
- **Why not only AbortController?** Cancellation can save work but doesn't establish result ownership after settlement. The revision guards all three completion paths. Transport cancellation is a reasonable follow-up.
- **Why not persist drafts?** Personal/contact/photo data needs retention, privacy and expiry decisions. This release guarantees only in-memory preservation.
- **Why not auto-retry POST?** A lost response is not proof of failed creation. Explicit retry plus backend dedupe is safer than blindly repeating writes; full idempotency remains a separate design topic.
- **Why an isolated demo API?** It gives reproducible browser tests and an offline presentation without production data or Docker. It intentionally does not emulate all WordPress validation.

## Debugging story and evidence

TF-001 in [INVESTIGATIONS.md](INVESTIGATIONS.md) records discovery, reproduction, rejected hypothesis, root cause, repair and remaining risk. Resolve its fix with `git log --all --oneline --grep='bind eligibility'`; read `BookingWidget.test.tsx` for exact regression assertions. The initial design conflated completion with relevance. The fix makes ownership explicit, including cleanup. This is a useful discussion of asynchronous invariants rather than a generic “race condition” claim.

## Testing strategy and limits

- Vitest/Testing Library: user-visible steps and feedback under controlled promise ordering; no real HTTP or visual rendering.
- Playwright: real Chromium page, actual React/theme, disposable development HTTP API and route-injected failures; desktop and mobile emulation, not physical mobile hardware.
- TypeScript and Vite build: static checking and production bundling, not integration correctness.
- Existing WordPress E2E, PHP tests and Lighthouse remain separate. This environment lacks PHP/Docker; those checks were not performed for this release. No measured LCP, interaction latency, throughput or production capacity claim is made.
- Browser tests were run programmatically, not by human interaction. No rendered visual inspection is claimed by the recorded passing counts.

## Current verification

Baseline source `b93463a`: 4 component tests passed, build and typecheck passed. After the eligibility fix: 9 passed, 0 failed (2.26 s). On the demo-stage working tree based on `8ed337f`: 8 browser cases passed, 0 failed/skipped (14.2 s), typecheck passed. See `docs/evidence/widget-browser.txt`. Node v22.23.3, Vitest 3.2.7, Playwright 1.62.0, Chromium 151.0.7922.34, React 19.2.8, Vite 7.3.6, Windows Git Bash. Browser binaries initially absent; `npx playwright install chromium` resolved setup. An initial JSON fixture import was incompatible with the runner's Node loader; a TypeScript fixture removed that dependency. These setup failures were not application defects.

The script and CI changes are versioned with their tests; commit-specific final verification will be recorded after integration. Test counts are separate runs, never aggregated into a larger claim.

## Next improvements

Audit upload ownership and cleanup when lead creation fails; tighten server slot validation and time-zone boundaries; add real-WordPress failure tests; add keyboard focus/error-summary handling; decide a privacy-safe draft policy; measure performance in an actual deployment before optimizing.

These are project evidence and suggested talking points, not statements that the repository owner personally performed these steps. Reproduce and adapt the explanations before presenting them as personal experience.
