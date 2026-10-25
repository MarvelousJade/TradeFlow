# Controlled resume-impact measurement — 2026-10-25

## Question and result

Does the current widget prevent an obsolete eligibility response from affecting a postal address edited while the request was pending?

**Yes, for the three controlled scenarios tested:** the baseline widget failed all 3, and the current widget passed the same 3. These outcomes reproduce an existing repair; this session did not add a product fix.

| Scenario | Baseline widget | Current widget |
| --- | --- | --- |
| Old eligible response after postal edit | Failed | Passed |
| Old ineligible response after postal edit | Failed | Passed |
| Old network error after postal edit | Failed | Passed |

Six unrelated cases were intentionally filtered out in each targeted run. They were not skipped in the separate full current-suite run.

## Provenance and method

- UTC observation window: 2026-10-25 22:24:00 through 22:25:01.
- Current source: `820764737187546c9737ca1c5aa54a8d3b151ae7`.
- Baseline widget source: `b93463a20279519c91bdddc12d83bf2814c31edc`.
- Environment: Windows x64; AMD Ryzen 5 3600; Node v22.23.3; React 19.2.8; Vitest 3.2.7; Vite 7.3.6; TypeScript 5.9.3; Playwright 1.62.0.
- One run per version, with deterministic deferred-response ordering and synthetic data.
- Copied the current frontend source and Vite config into an ignored temporary sandbox, excluding nested `node_modules`. Used the same installed root dependencies for both versions.
- For the before run, replaced **only `BookingWidget.tsx`** with its baseline version. For the after run, restored the current widget in that sandbox. This isolates the widget implementation; it is not a historical full-checkout/dependency comparison.
- Ran the identical current test file with the filter `ignores an obsolete eligibility` for both versions. Compared named case outcomes, not merely process exit codes.
- Separately ran the full current unit suite, typecheck, build, and desktop/mobile browser suite against the current checkout.
- Measured tracked source/config files matched HEAD. New documentation and the measurement runner were untracked at measurement time, as recorded in `summary.json`.
- No application source changes or dependency installations; the temporary sandbox was removed. Ignored build/test outputs were regenerated.

## Separate current-checkout verification

| Check | Result | Evidence |
| --- | --- | --- |
| Component and attribution tests | 9 passed, 0 failed/skipped | `current-unit.json` |
| TypeScript | Exit 0 | `current-typecheck.txt` |
| Production build | Exit 0; JS 207.68 kB / gzip 65.31 kB | `current-build.txt` |
| Chromium browser suite | 8 passed, 0 failed/skipped; runner duration 23.9 seconds | `current-browser.txt`, `current-browser.json` |

The browser cases are four workflows repeated in desktop and Pixel 7 emulation: completion, read recovery/coverage rejection, obsolete approval, and failed-write draft retention/retry. They use the actual React widget and disposable local API. No real bookings, customer records, or emails were produced.

## Reproduce

From the repository root, with dependencies and Playwright Chromium already installed:

```bash
node scripts/measure-resume-impact.mjs docs/evidence/resume-impact-rerun
```

Choose a new output directory; existing evidence is never overwritten. The script uses Node entry points for Vitest, TypeScript, Vite and Playwright. The browser server runs the repository's `npm run demo` command. Each exact argument vector, exit code and process wall time is captured in `summary.json` and the corresponding text log. The temporary config path in those logs no longer exists; rerunning the script creates an equivalent new sandbox.

`race-before.json` contains the expected failing assertions, including the rendered DOM. `race-after.json` contains matching passing case names. JSON files are raw reporter outputs; text logs capture stdout/stderr. Tool warnings are retained, not treated as application failures.

## Interpretation and limitations

- **Valid:** 3 reproduced stale-response failures resolved in the tested scenarios; 9 current component/attribution tests and 8 browser cases pass in their separate runs.
- **Not valid:** “100% fewer production failures,” “increased conversion,” “saved X hours,” or a measured performance improvement. No live usage, usability study, Lighthouse, PHP, WordPress, database or email-delivery verification was performed.
- Do not combine targeted and full-suite counts: the three targeted cases are already included in the nine-case suite. Browser cases overlap behavior too; do not imply 17 independent features.
- Test durations are diagnostics, not product latency or developer time saved. Before/after process times are not comparable performance benchmarks because cache/startup conditions were not controlled.
- Emulation is not physical-device testing. Programmatic assertions are not a human visual or comprehensive accessibility audit.
- Existing development-toolchain security findings were not remediated or re-audited. This run is not a production-readiness certification.
- Resume wording must reflect the owner's actual contribution. Execution by a coding agent does not establish personal authorship of historical work.

## Suggested resume wording

> Fixed a React/TypeScript booking race using request-revision guards, resolving all 3 reproduced stale-response regression failures while passing the full 9-test component/attribution suite.

> Automated 8 Chromium browser cases across desktop/mobile emulation to verify booking completion, coverage checks, stale-response handling, and draft-preserving retries after an injected HTTP 503.
