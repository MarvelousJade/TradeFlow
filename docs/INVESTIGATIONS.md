# Booking investigations

## TF-001 — Obsolete eligibility result advances edited address

Origin: existing source `b93463a` (also present at planning commit `94ef7ef`); not deliberately injected. Found by reading `next()` alongside editable `ServiceStep` inputs, then confirmed with deferred network responses.

Invariant: a coverage result applies only to the input revision for which it was requested. Impact: a customer can enter an unqualified address and reach job details using a previous address's approval. The server still revalidates at submission, so this is a misleading funnel, not evidence of a backend bypass.

Reproduction:
1. Render widget and enter `M5V 2T6`.
2. Continue, leaving eligibility pending.
3. Change postal code to `L5B 1M2` before resolving the old response.
4. Resolve the old request as eligible; the old widget advances to job details. An old ineligible result or network error instead contaminates the edited form's feedback.

Initial hypothesis: async closure retains old form values, yet completion mutates current UI without an ownership check. Alternative: Continue disabled the entire form. DOM inspection rejected that: only navigation buttons were disabled. Deferred promises exclude timing flakiness and backend coverage policy as causes.

Evidence: `docs/evidence/eligibility-before.txt` records 3 failed / 4 passed on the original implementation plus new tests. Old success shows step 2; old rejection and failure render obsolete feedback. No production request was used. `eligibility-after.txt` records 9 passed / 0 failed after repair, including new area-change/finalizer ownership and bootstrap recovery cases.

Root cause: `next()` always applied the response and advanced; input updates cleared messages but not ownership of pending work. A disabled button prevents duplicate clicks, not changes in request meaning.

Repair: increment a monotonic revision when checking or changing service/area/postal code. Apply success, error, and pending cleanup only for the owning revision. An edit releases the obsolete pending indicator so a fresh check can start immediately. Effect cleanup also invalidates outstanding eligibility on unmount. Regression tests assert observable steps, fields and buttons rather than revision internals.

Tradeoffs: requests are ignored rather than aborted; transport work can continue. Backend validation remains mandatory. Drafts remain ephemeral. The broader prop-driven replacement of the entire booking context is not part of this release. Fix commit is the commit titled `fix: bind eligibility results to the current booking inputs` (resolve with `git log --oneline --all --grep='bind eligibility'`).

Design implication: every asynchronous operation needs explicit ownership, including error handlers and finalizers. Guarding only success is insufficient.

## TF-002 — Bootstrap failure cannot be retried in place

Existing source `b93463a`; observed in code: bootstrap catch rendered an alert without an action. A rejected fetch previously required page reload, losing page-level context. Hypothesis confirmed by inspecting the terminal alert branch. This release adds a retry button and an attempt dependency to the existing cleanup-guarded Effect. The regression rejects the first fetch, clicks Retry loading, resolves the next, and asserts the form returns without the old error. Included in the TF-001 fix commit. No historical failing test run was captured for this separate usability defect; do not claim otherwise.

## TF-003 — Failure cleanup erases the booking draft (controlled fault)

Provenance: intentionally introduced on local branch `investigation/draft-reset`, fault commit `ee34f06`, starting from verified `0274395`. This was not a production incident. Baseline: 9 component cases passed immediately before isolation; the existing browser suite had 8 passes. See `evidence/control-provenance.md` and `control-baseline.txt`.

Invariant: failed submission must preserve draft fields, chosen slot and consent; retry must send the same meaningful booking fields, without automatic POST retries.

Fault: one added `setForm(initialForm)` in the submission catch handler. This models an overly broad cleanup policy. Disposable demo data only.

Reproduction: `npm run test:widget -- --project=desktop --grep "failed submission"` on the fault commit. The test fills the funnel, intercepts lead creation with HTTP 503, then inspects the current contact form. `control-failing.txt` records 1 failed / 0 passed: email expected `demo@example.test`, received an empty string. Error alert still correctly displayed the server's message. Failure screenshot and trace were generated locally but remain ignored; the committed log preserves the decisive DOM observation.

Initial hypotheses: either response parsing/retry replaced the draft, or submission failure cleanup reset local state. The route count remained one and the expected error appeared, arguing against auto-retry or misrouted response. Code inspection found the explicit reset in catch, not in `api.ts`; the form's empty email and missing review values matched `initialForm`. No further speculative attempts were performed.

Repair: remove the injected reset and retain error/pending cleanup only. Strengthen the existing regression to inspect both first and retry multipart payloads for name, email, phone, postal code, job details, service/area IDs and exact slot timestamps. The strengthened test also failed on the fault (`control-strengthened-failing.txt`, 1 failed). After repair: `control-repaired-browser.txt`, 8 passed / 0 failed (15.9 s); `control-repaired-unit.txt`, 9 passed / 0 failed (2.02 s); typecheck/build passed. Diff against `0274395` confirms no net widget implementation change.

Completed integration therefore adds regression coverage and honest evidence, **not a new implementation fix**. Isolated repair commit: `2a6ff59`; integrated net result: `873b484`. After integration, the completed feature and investigation branches were removed. The investigation history was preserved in the ignored local-only archive `.tools/investigations/draft-reset.bundle`; it is not included in a clone. On the original workstation, restore it with `git fetch .tools/investigations/draft-reset.bundle refs/heads/investigation/draft-reset:refs/heads/investigation/draft-reset`. The committed evidence remains available without that archive. The intentionally broken intermediate commit is not an ancestor of main and will not be published.

Design implications: draft ownership and request lifecycle are separate. Cleanup should release resources and pending state, not delete user-owned data. In-memory preservation is not durable persistence, and an HTTP failure does not prove the server did not create a lead. Backend dedupe remains important. Remaining risks: no offline queue, refresh persistence, photo-payload comparison, or live WordPress failure verification.
