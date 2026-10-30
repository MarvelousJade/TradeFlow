---
name: prove-fix
description: Prove a TradeFlow bug fix with a regression test that fails without the fix and passes with it, then record the evidence. Use after implementing a fix, before calling it done.
---

A test that has never failed proves nothing. Show it failing without the fix, then passing with it.

1. **Criterion first.** Confirm the bug and its acceptance criteria are written in `docs/ENGINEERING.md`. If not, write them and stop for the user's review.
2. **Regression test.** Write a test that reproduces the real failure through user-visible behaviour or request data, not only an error message. Use deferred promises or Playwright routes, never sleeps. Synthetic data only.
3. **Fail without the fix.** Revert only the fix's source files, keeping the test:
   `git stash push <fix source files>`
   Run only the new test. It must fail for the reason the bug describes. If it passes, the test is wrong: fix the test, not the code.
4. **Pass with the fix.** `git stash pop`, then rerun the same test. It must pass. Check `git diff --stat` matches what it was before step 3.
5. **Full checks.** Run the `verify` skill.
6. **Record evidence** in a new folder, `docs/evidence/<topic>-<YYYY-MM-DD>/`. Never edit an existing capture (a hook blocks it):
   - `failing.txt` and `passing.txt`: raw output of steps 3 and 4.
   - `README.md`: commit or working tree, exact commands, counts, and what was not verified.
   - Add one line to `docs/evidence/README.md` and new hash lines to `docs/evidence/SHA256SUMS` (`sha256sum` of each new file). Never change existing lines.
7. **Report** the before/after counts, the evidence folder, and anything only CI or a reviewer can confirm (PHP, WordPress stack).

$ARGUMENTS
