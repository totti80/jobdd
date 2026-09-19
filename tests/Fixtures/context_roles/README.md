# Context role evaluation — Batch 4.2 / 4.3

This remains an offline prototype. Batch 4.3 is CONDITIONAL GO for discussing responsibility-only integration in the next batch; no application integration has been made. Batch 4.2 had one unsafe responsibility prediction (Job 324 / fa); Batch 4.3 fixes it. Unit test success is not evidence of production classification accuracy.

Run from the repository root (no database connection):

```sh
php tests/Support/evaluate-context-roles.php tests/Fixtures/context_roles/batch41.json
php tests/Support/evaluate-context-roles.php tests/Fixtures/context_roles/independent.json
php vendor/bin/pest tests/Unit/ContextRoleClassifierTest.php
```

`batch41.json` preserves the prior audit's 48 labels across 31 jobs. Those labels are ground-truth candidates; some were based on saved first Evidence, while this classifier aggregates all aliases in Raw.

`independent.json` contains 33 Facts from 33 other jobs. Expected labels were fixed before running the classifier on this set; labels were assigned by the coding agent using the supplied human-audit criteria, not by an independent human reviewer. In Batch 4.2 neither the classifier nor labels were adjusted after seeing independent predictions. In Batch 4.3 this is a known regression set, not unseen validation. The fixture records the classifier hash and label timestamp.

Selection is deterministic on the Batch 4 database snapshot: exclude every Batch 4.1 job, sort remaining Facts by numeric job_posting_id then fact_key, and select one Fact per job. In the order of the eight `selection` patterns recorded in the fixture, match saved evidence_text and select four candidates, preferring providers recruit_agent, careerjet, meitec_next, recruit_agent; fall back to the first available candidate if the preferred provider is absent. Supplement with the first remaining Fact whose literal matched value is immediately followed by 部門/部署 and へ/と. Candidate strata are search hints, not expected labels. Freeze labels after reviewing saved Raw/Evidence and occurrences.

The set covers 3 providers, 5 regions, 17 fact keys and both occupations, but is intentionally stratified and skewed (28 mechanical / 5 electrical). It does not estimate population accuracy. No independent expected collaboration, other_department or product_context cases survived labeling; those roles remain covered only by the regression/synthetic sets. After improving rules, use fresh blind samples rather than treating this set as unseen validation again.

Batch 4.2 evaluated classifier: `4d9fcac5001da7e3b72c86624ebbd78764a65d6d8b987e1eb43b2c1a9b3ec3ea`.
Results: regression 42/48 correct, responsibility precision 12/12, recall 12/17, unsafe 0, unknown 7/48. Independent 15/33 correct, responsibility precision 8/9, recall 8/16, unsafe 1, unknown 17/33.

Batch 4.3 addresses the known section boundaries and local preferred qualifiers. Raw text fixtures are saved source snapshots, not new crawling results.


## Batch 4.3 fresh blind validation

```sh
php tests/Support/evaluate-context-roles.php tests/Fixtures/context_roles/batch43_fresh.json
python3 tests/Support/select-context-role-fresh.py /tmp/jobdd-b43-before.json /tmp/fresh-unlabeled.json
```

The selection script consumes a read-only snapshot with `facts` and `jobs` arrays; it never runs the classifier. It excludes all 64 old jobs and all old Fact IDs, restricts to active Kinki 12-cell jobs, and selects 40 distinct jobs. Five rounds over eight evidence-pattern strata choose the least represented occupation, provider, fact key, then region (lexicographic counts), with job ID/key/Fact ID as tie breakers. The regexes and source hash are saved in the fixture. Selection reproducibility was checked byte-for-byte against the same snapshot.

The fresh set covers mechanical 20 / electrical 20, 3 providers, all 6 prefectures and 23 fact keys. Review considered saved Raw, Evidence, headings and all dictionary alias occurrences, without classifier predictions. Frozen expected labels are coding-agent audit candidates, not a human gold standard. `batch43_freeze.json` records the pre-evaluation timestamp and hashes; `fresh_evaluation_started: false` describes that historical freeze moment. The fixture and classifier remained unchanged after the first evaluation. Repeated evaluation produced identical JSON.

Results after safety fixes:

| Set | Correct | Responsibility precision | Recall | Unsafe | Unknown |
|---|---:|---:|---:|---:|---:|
| Known Batch 4.1 | 42/48 | 12/12 | 12/17 | 0 | 7/48 |
| Known Batch 4.2 | 17/33 | 8/8 | 8/16 | 0 | 17/33 |
| Fresh Batch 4.3 | 23/40 | 6/6 | 6/20 | 0 | 20/40 |

CONDITIONAL GO is limited to designing the next integration: only responsibility is a candidate for confirmed-duty handling, retain its actual matched context, and show unknown as unconfirmed. A tool, assembly or procurement responsibility must not be reworded as design responsibility. No JobFitService, score or UI integration is authorized by these results alone.

Limitations: only six positive predictions; intentional nonrandom strata; frozen labels need separate human adjudication; template duplicates can exist despite disjoint job IDs (including fresh Jobs 1170/1171); company_context/collaboration/other_department have no fresh expected cases. Candidate strata are not ground-truth quotas. Main remaining errors include conservative truncation handling, nominal duty lists, unknown subheadings, title/experience aggregation, and local welcome text incorrectly binding across a tool list. Do not tune on this set and continue calling it fresh; subsequent safety changes need a new blind sample.
