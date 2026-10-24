# Controlled draft-reset fault

Starting commit: `0274395` on feature branch. Verification immediately before isolation: 9 component tests passed (`control-baseline.txt`); desktop/mobile browser suite had 8 passes (`widget-browser.txt`). Isolated local branch: `investigation/draft-reset`.

Invariant: a failed submission must not erase the draft. One intentional fault added: `setForm(initialForm)` in the submission catch handler, simulating over-broad cleanup on failure. No production service or user data involved. This is deliberately introduced, not an unexpected incident.

Fault commit: `ee34f06`. Existing browser regression failed with email reset to empty (`control-failing.txt`). Strengthened the same regression to inspect multipart booking fields on both attempts; it also failed while the fault remained (`control-strengthened-failing.txt`). Removed the reset; 8 browser cases and 9 component cases passed in separate runs, typecheck/build passed. Full investigation: `docs/INVESTIGATIONS.md`, TF-003. Net widget diff against the starting commit is empty. Only the completed test/evidence changes are eligible for squash integration.
