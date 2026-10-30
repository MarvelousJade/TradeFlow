# Booking reliability release

## Purpose and scope

TradeFlow helps local trades teams collect qualified service requests without a phone call. Customers currently choose an area, describe a job, request a window, and supply contact details; staff confirm later. This release improves that existing funnel, not dispatch or calendar capacity management.

Required acceptance criteria:
- Editing postal code or area during an eligibility request invalidates that response. Old success, rejection, or failure must not advance or overwrite the current form.
- A fresh check can start without waiting for an obsolete request. Only its owner may clear the pending state.
- Failed submission retains job/contact details and supports an explicit retry; never automatically retry a write with an uncertain outcome.
- Bootstrap failure has an explicit retry. No blank or permanently loading state after a recoverable network failure.
- Run deterministic component regressions and desktop/mobile browser workflows with disposable mocked APIs, without requiring production credentials.

Follow-up: server upload rollback, stricter slot validation, accessible focus management, and a persisted draft policy. Outside scope: new backend, payment, real-time capacity, production deployment, dependency upgrade, automatic write retries, durable browser drafts.

## References reviewed before implementation

Reviewed 2026-10-24. Only documentation and release information were inspected; no third-party code/assets copied. These are pattern references, not claims of parity.

| Reference and direct URL | Observed problem/users and useful decision | Relevance evidence and limits | Adopt / differ |
| --- | --- | --- | --- |
| [Jobber Online Booking](https://help.getjobber.com/en/articles/online-booking/) and [settings](https://help.getjobber.com/en/articles/requests-and-bookings-settings/) | Trades businesses qualify requests with a postal/ZIP service-area gate and provide contact alternatives outside coverage. | Current official help search excerpts describe per-form service-area controls. Direct page fetch returned HTTP 403; publication/update date and actual product UI were not verified. No internal architecture inferred. | Preserve early eligibility checking; focus on consistency while editing. TradeFlow remains a small request funnel, not a full scheduling/CRM suite. |
| [React useEffect](https://react.dev/reference/react/useEffect) | UI developers must ignore obsolete asynchronous results; documentation demonstrates cleanup guards and out-of-order responses. | Official page fetched; no page update date. [React 19.2 release](https://react.dev/blog/2025/10/01/react-19-2) search result dated 2025-10-01 supports continuing framework activity, not a requirement to upgrade. | Adapt result ownership to an event-driven request revision, not an Effect for every input edit. Existing bootstrap cleanup remains. |
| [Playwright network](https://playwright.dev/docs/network) and [releases](https://github.com/microsoft/playwright/releases/latest) | Browser testers can fulfill/abort routes and exercise recovery deterministically in isolated contexts. | Both fetched. Latest release page resolved to v1.64.1, displayed 14 Oct 11:51 (year not displayed). Installed lockfile version, not latest release, governs our tests. | Use actual React code in Chromium with mocked REST responses. Keep existing WordPress E2E separately; mocks do not prove DB transactions or email delivery. |

No source reuse means no new attribution/license obligation from these references. Existing repository has no root LICENSE: public redistribution rights remain an owner decision. Documentation examples are re-expressed as project-specific tests, not copied implementation. Jobber's exact fallback UX and server consistency strategy are unknown. React has no built-in domain eligibility policy; Playwright mocks cannot establish backend correctness.

## Architecture decision

Keep the plugin/theme split: PHP owns persisted leads, validation, notifications and WordPress integration; React owns ephemeral form state and request presentation; `api.ts` owns wire encoding and errors. A monotonic eligibility revision identifies the current request. Relevant edits invalidate it synchronously, release the pending UI, and clear stale feedback. Results, errors and finalizers check ownership. Aborting alone is not a correctness guarantee; a response may already have resolved. Retaining uncancelled reads until settlement is a small bounded resource tradeoff; no new dependency or broad state-machine abstraction is justified here.

Bootstrap retry reruns the existing guarded Effect using an explicit attempt counter, preserving entered form state. Network errors on writes stay visible and require user choice; a server may have accepted an interrupted submission. No persistence is added: refresh/unmount still discards the draft.

## Baseline and journal

- Starting source: `b93463a`, branch `agent/free-deployment`, clean tracked working tree; origin exists but no publish authorization. Existing local main is retained until completed integration.
- Inspected README, React/API/types, PHP REST/validator, tests, Vite, CI and recent deployment history. Strengths: separated plugin/theme, unique dedupe key, transaction-oriented repository, existing component and WordPress E2E layers. Coverage gap: only two widget happy/validation tests.
- Windows Git Bash, Node v22.23.3. Baseline `npm test`: 4 passed, 0 failed (19.22 s); `npm run build`: passed (1.21 s), JS 207.40 kB / gzip 65.22 kB; `npx tsc --noEmit`: passed. Sizes are build output, not runtime performance measurements.
- PHP and Docker commands unavailable; no local WordPress/backend verification claimed. Existing generated reports and `.tools` are ignored and not treated as evidence for this release.
- Code inspection identified a plausible stale eligibility response: input fields remain editable during `checkEligibility`, while its completion unconditionally advances the step. Next stage will first reproduce this with a deferred response test.

- Demo/browser stage: added development-only Vite HTTP middleware and synthetic fixture, separate from production build. Actual widget/theme run with desktop and Pixel 7 emulation; 8 passed / 0 failed in 14.2 s, typecheck passed. Tests cover empty postal, outside coverage, startup failure/retry, delayed approval after edit, successful request and draft-preserving retry. No WordPress calls or stored lead data. First runner setup exposed JSON import requirements and absent browser binaries; moved fixture to TypeScript and installed Chromium, then reran successfully.
- Local main contains an existing merge commit (`fcf62c7`) not on the starting branch. Final integration must merge the completed feature branch into existing main, not reset it or rewrite history.

## Integrated outcomes (2026-10-24)

- `94ef7ef`: requirements/research; `8ed337f`: reproduced and fixed obsolete eligibility plus startup recovery; `0274395`: disposable demo and browser tests; `873b484`: strengthened retry payload regressions; `20bf3e3`: merge into existing main; `76e113b`: regenerate completion screenshots.
- Controlled fault `ee34f06` was intentionally introduced and repaired (`2a6ff59`) on an isolated local branch. Targeted browser run failed on empty email. Only final coverage/evidence was squash-integrated; no net widget implementation change was claimed. `git merge-base --is-ancestor ee34f06 main` returned 1: faulty commit is not on main.
- Final source `76e113b`: `npm ci` passed, 9 component tests passed (2.03 s), typecheck/build passed, 8 desktop/mobile browser cases passed (17.4 s), zero failures/skips. See `docs/evidence/final-*.txt`. Production output 207.68 kB / gzip 65.31 kB. Reviewed generated desktop/mobile success images; no complete visual or accessibility audit or human interaction claimed.
- Final dependency review: 31 development-toolchain audit findings, including 3 critical; production-only npm audit zero. Tooling upgrade is a priority follow-up, not silently forced into this release. Loopback-only demo is not a deployment server. No PHP/WordPress audit was performed.
- Credential filename/private-key/token-pattern checks found no matches among tracked files; this limited scan is not a security certification. Existing ignored `.tools`, reports, dependencies and output remain untracked. No licence was added on the owner's behalf. No remote push, tag or account operation performed.

## Verification approach

Component tests isolate ordering using deferred promises, not sleep-based races. Browser tests run actual React modules and theme styles with route-controlled APIs in desktop and mobile Chromium. Keep raw run summaries in `docs/evidence/`; do not count separate runs as one suite. PHPUnit and PHP lint run locally on PHP 8.3, the same version as CI. WordPress integration tests (`tests/e2e/`) run locally against the documented Docker stack. Lighthouse CI runs only in CI. No visual or human testing claim without explicit evidence.

## Idempotent booking retry (2026-10-29)

### Problem

A submission can be stored by the server while the response is lost (network drop, proxy 503 after commit). The widget keeps the draft and offers a manual retry, as intended. That retry then hits the daily `dedupe_key` constraint and receives `409 duplicate_lead`: the customer sees an error and never gets their reference, although the booking exists. Client tests and PHP tests each pass because neither exercises the two halves together.

### Decisions

- The widget creates one random idempotency key (UUID v4) per draft and sends it as `idempotency_key` on every submission attempt of that draft. It is not derived from personal data. Success ends the draft; unmount discards it, as before.
- The key is kept when the customer edits fields between attempts. The server, not the client, decides whether a stored attempt matches.
- Server: a known key with the same `dedupe_key` replays the original result (`200`, original reference and status URL). No new row, no second confirmation email, no photo upload. A known key with a different `dedupe_key` (contact, service or area changed) returns `409 idempotency_conflict` telling the customer an earlier version was received. No new row.
- Missing or malformed key: previous behaviour, so cached older widget scripts keep working.
- A concurrent duplicate insert that loses the unique-index race re-reads by key and replays.
- Schema: nullable unique `idempotency_key` column. Plugin version bumps to 1.1.0, and `plugins_loaded` re-runs `dbDelta` when the stored DB version differs, so existing installs gain the column without reactivation.
- Still no automatic retry of writes.

### Acceptance criteria

1. Every attempt within one draft sends the same valid UUID key. (component test)
2. Accepted-but-lost submission, then manual retry: success screen with the original reference, no error, exactly two requests. (browser test with a route that remembers keys)
3. Same key and same details: `200` replay, no insert, no email. (E2E test for replay and no insert; no email by review only)
4. Same key, changed contact, service or area: `409 idempotency_conflict`, no insert. (E2E test)
5. Missing or invalid key: unchanged behaviour. (validator unit test, plus the E2E duplicate test for the no-key path)
6. Existing installs migrate on upgrade. (code review; needs an upgrade run on a WordPress stack to prove)

Outside scope: key expiry or cleanup policy, server-side storage of the full response body, and changing the daily dedupe rule.

### Verification (2026-10-29, branch `feature/idempotent-retry`)

- Baseline before changes: typecheck passed, 9 component tests, 8 browser cases passed.
- After: typecheck and build passed (JS 208.07 kB / gzip 65.49 kB); 12 component tests passed (3 new in `api.test.ts`); 10 browser cases passed (new desktop and mobile "retry after a lost response returns the original reference").
- The new browser test was run with only the client change reverted: 2 of 2 failed (success screen never appeared because the retry got 409). With the change restored, both passed.
- First pass: PHP and Docker were unavailable, so criteria 3–6 were reviewed, not executed. Superseded by the next entry.
- Local PHP and WordPress run (PHP 8.3.36, Composer 2.11.0, Docker Desktop 29.9.2, fresh stack from `docker-compose.yml`): `composer test` 5 tests / 19 assertions passed, including `testIdempotencyKeysAcceptOnlyUuidV4`. PHP lint 0 errors in 18 files. `tests/e2e/` in Chromium 4 passed; mobile 3 passed and 1 skipped (the admin test skips itself on mobile by design). Criterion 5 is now executed.
- Added two WordPress E2E tests for criteria 3 and 4 (`tests/e2e/booking.spec.ts`). With only the REST controller's replay block stashed, both failed in Chromium: the replay retry got `409` and the changed retry was stored with `201`. Restored, both passed. Full `/verify` then passed, including E2E in Chromium (6 passed). Evidence: `docs/evidence/idempotent-retry-2026-10-29/`.
- Still not verified: "no second email" (local mail goes to the container's debug log, which the tests can't read) and the race fallback. The stack was a fresh install, so the `dbDelta` upgrade path (criterion 6) is not proven. Lighthouse CI was not run.
- Known gap: if two attempts with the same key race, the losing request has already uploaded its photos before the insert fails. It returns the correct replay, but those uploads are orphaned. This belongs with the existing upload-rollback follow-up.
- `npm run format:check` already reported 11 files before this change (the repo has no Prettier config and uses single quotes). It was not changed here.
