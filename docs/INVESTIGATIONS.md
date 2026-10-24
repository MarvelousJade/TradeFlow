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

## Controlled investigation

A separate entry will record the isolated fault, verified starting commit, evidence and repair. Intentionally faulty code must never be merged into main.
