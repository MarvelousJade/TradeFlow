# Resume impact log — TradeFlow

## Rule: measure first, write the claim second

Use **problem → personal contribution → measured result → evidence**.

Engineering results (correctness, latency, task time) are valid project outcomes. Revenue, conversion, customers served, and staff time saved require real usage or a clearly labeled user study. Do not substitute test counts for customer impact.

This document includes a fresh controlled reliability comparison and checkout verification, performed on 2026-10-25, plus clearly labeled historical evidence. Only claim work you personally performed or can accurately explain your contribution to.

## Measured results — 2026-10-25

Source checked: `820764737187546c9737ca1c5aa54a8d3b151ae7`. Baseline widget: `b93463a20279519c91bdddc12d83bf2814c31edc`.

| Check | Measured result | Meaning |
| --- | --- | --- |
| Same 3 stale-response scenarios, before | 3 failed, 0 passed | Old success, rejection, and network-error responses incorrectly affected the edited address |
| Same 3 scenarios, after | 0 failed, 3 passed | Current widget satisfies those three regression assertions |
| Full component/attribution suite | 9 passed, 0 failed/skipped | Separate full-suite run, including the 3 scenarios above; do not add the counts together |
| Browser suite | 8 passed, 0 failed/skipped | 4 workflows × 2 Chromium emulation projects (desktop/mobile) |
| TypeScript / production build | Both passed | Static and bundling verification, not production integration |
| Current JavaScript bundle | 207.68 kB; gzip 65.31 kB | Current size only; no new before/after performance improvement established |

**Supported primary bullet:**

> Fixed a React/TypeScript booking race using request-revision guards, resolving all 3 reproduced stale-response regression failures while passing the full 9-test component/attribution suite.

**Supported secondary bullet:**

> Automated 8 Chromium browser cases across desktop/mobile emulation to verify booking completion, coverage checks, stale-response handling, and draft-preserving retries after an injected HTTP 503.

These describe the existing implementation and tests verified in this measurement session, not newly implemented product features. Adjust first-person ownership to your actual work.

Full protocol, limitations, raw reports, and exact commands: [measurement record](evidence/resume-impact-2026-10-25/README.md). This is one deterministic run per version, not a statistical estimate of user-facing reliability. No performance speedup, business conversion increase, or staff time savings was measured.

Reproduce into a **new** output directory:

```bash
node scripts/measure-resume-impact.mjs docs/evidence/resume-impact-rerun
```

Requires the installed project dependencies and Playwright Chromium. The runner rejects an existing evidence directory, checks measured tracked files match HEAD, and removes its temporary source sandbox after execution. It does not install dependencies or modify application source. It does regenerate ignored build/test artifacts. The baseline failure exit is expected; the runner also checks that the same three named cases pass afterward. Source revisions, tool versions, case outcomes, and checksums are saved in `summary.json`.

## Historical evidence you can use

| Area | Recorded result | Evidence | Honest interpretation |
| --- | --- | --- | --- |
| Stale eligibility responses | 3 new regression cases failed before the repair; post-repair component suite had 9 passes and 0 failures | [Before](evidence/eligibility-before.txt), [after](evidence/eligibility-after.txt), [investigation TF-001](INVESTIGATIONS.md) | Fixed reproduced stale-response behavior in controlled tests, not a measured production incident rate |
| Component regression checks | Baseline suite: 4 tests; repaired suite: 9 tests | [Verification notes](INTERVIEW.md#current-verification) | Added 5 component tests; test count is not code coverage or proof of all failure paths |
| Browser verification | Recorded final run: 8 cases passed, 0 failed/skipped, in 17.4 seconds across desktop/mobile emulation | [Final browser log](evidence/final-browser.txt) | Automated Chromium verification against a disposable API; not physical-device testing or full WordPress integration |
| Failed-submission recovery | Strengthened browser assertions compare meaningful multipart fields on initial submission and retry | [TF-003](INVESTIGATIONS.md), [repaired browser log](evidence/control-repaired-browser.txt) | Regression protection for draft preservation; the defect was deliberately injected, not discovered in production |
| Bundle overhead | Recorded baseline gzip JS: 65.22 kB; final: 65.31 kB | [Verification notes](INTERVIEW.md#current-verification), [final build](evidence/final-build.txt) | Roughly 0.09 kB additional gzip JS; not evidence of faster loading |

### Candidate resume bullets

Use only after verifying the evidence and matching the wording to your own contribution:

- Fixed a stale-response race in a React/TypeScript booking flow using request-revision guards, resolving 3 reproduced regression failures and expanding the component suite from 4 to 9 tests.
- Automated 8 Chromium browser cases across desktop and mobile emulation to verify booking completion, eligibility checks, and failure recovery against a disposable API.
- Strengthened failed-submission regression tests to verify booking fields survive an injected HTTP 503 and remain consistent in the retry payload.

Pick one or two strong bullets rather than repeating test counts. Do not describe TF-003 as a production bug fix or present suite execution time as developer time saved.

## How to obtain stronger measurable results

Choose one reliability metric and one performance or task-efficiency metric. Record the baseline before optimizing.

### 1. Reliability: stale-response scenarios

- Baseline: use the exact pre-fix revision in a separate worktree and the same regression tests.
- After: use the repaired revision with identical test inputs and response ordering.
- Measure: incorrect advancement, stale feedback, or incorrect pending-state cleanup per named scenario.
- Record raw case-level outcomes, test commands, revisions, and logs.
- Report: “All N tested stale-response scenarios passed after the fix.” A deterministic suite is not an estimate of real-world failure probability.

### 2. Page performance: LCP, CLS, JavaScript size

- Use a real WordPress deployment or the same local WordPress setup for both versions. A disposable demo does not measure backend performance.
- Fix URL, seeded content, build mode, browser/tool versions, throttling, machine, and cache policy. Avoid concurrent workloads.
- Run at least 5 samples per version and URL; retain every sample and report the median plus range. More samples may be needed if results vary substantially.
- Record LCP in milliseconds, CLS as a unitless value, and gzip JS in kB. Lighthouse scores are secondary diagnostics.
- Existing commands: `npm run build`, `npm run lighthouse`, and `npm run lighthouse:ci`. Inspect `scripts/lighthouse.mjs` and `lighthouserc.json` before running; the configured CI collection uses 3 samples, so adjust or repeat collection for your chosen protocol.
- Current budgets include LCP < 2.5 seconds and CLS < 0.1. **A configured budget is not an achieved measurement.** Lab results are not field Core Web Vitals or production INP evidence.
- Improvement formula for lower-is-better metrics: `(baseline - after) / baseline * 100`.
- Hypothetical wording, not a TradeFlow result: “Reduced median lab LCP from 3.0 s to 2.1 s (30%) across 5 runs per version under fixed test conditions.”

### 3. Task efficiency: staff booking administration

- Define a task, such as opening a lead, assigning a technician, and updating its status.
- Compare the actual prior workflow with TradeFlow using matched fictional bookings. If no prior workflow exists, report task completion time without claiming savings.
- Use consenting participants; record participant count, task count, successes, errors, and completion time. Keep personal data out of committed evidence.
- Counterbalance workflow order to reduce practice effects; use the same task instructions.
- Report median time and completion rate with the sample size. A small study supports a usability result, not a company-wide productivity claim.
- Hypothetical wording: “Reduced median assignment task time from X to Y seconds in a Z-participant usability study.”

### 4. Business impact: only after real deployment

- Define an eligible booking start and a server-confirmed successful booking; do not count a button click or demo success as a persisted lead.
- Record an explicit observation window, unique starts, confirmed bookings, exclusions, and traffic source/device mix. Minimize analytics data and obtain required consent.
- Completion rate = confirmed bookings / eligible starts. Deduplicate repeated events and retries.
- Compare comparable periods or use a properly designed experiment. Report both percentage-point change and relative change when useful.
- Example arithmetic only: 40% to 50% is +10 percentage points and +25% relative; it is not +10% relative.
- A before/after association alone does not establish that your change caused the difference. Without live data, write “implemented attribution” rather than “increased conversion.”

## Copy this record for each measurement

```text
ID / title:
Status: planned | measured | reproduced | invalidated
Measurement date and observation window:
Problem and affected workflow:
My specific contribution:
Metric, unit, and definition of success:
Baseline commit / deployment:
After commit / deployment:
Environment, tool versions, and dataset:
Exact commands or study procedure:
Sample size and exclusions:
Baseline raw results:
After raw results:
Summary statistic and absolute / relative change:
Evidence paths (logs, reports, anonymized data):
Limitations and alternative explanations:
Reviewer / independent reproduction, if available:
Resume bullet supported by this evidence:
```

Store sanitized logs and small result tables under `docs/evidence/`. For example, use `docs/evidence/performance-YYYY-MM-DD/` with a procedure file and CSV containing revision, URL, run number, LCP, CLS, and tool versions. Keep private reports, credentials, customer information, and participant identities out of Git. Capture the source revision before the run with `git rev-parse HEAD` and note any uncommitted changes with `git status --short`.

## Before adding a number to your resume

- Can I reproduce or inspect the source evidence?
- Does the number describe an outcome, a capability, or merely a target?
- Did I state the environment and sample size where they matter?
- Is the baseline comparable, and is the percentage calculation correct?
- Did I separate simulated failures and lab studies from real usage?
- Can I explain my own contribution and the important limitations in an interview?
