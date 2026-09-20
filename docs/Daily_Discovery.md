# Daily Discovery operation (Batch 15)

`php artisan jobdd:discover-daily` orchestrates Careerjet, Recruit Agent, then Meitec Next. Each visits 機械設計 then 電気設計, each in 兵庫県 / 大阪府 / 京都府 / 滋賀県 / 奈良県 / 和歌山県 order.

## Before enabling real collection

The schedule is registered for **04:00 Asia/Tokyo**, initially disabled. No OS cron is installed or modified. Existing MHI 05:00 schedule is unchanged.

- Provision the existing `crawler/requirements.txt` into the Python interpreter configured by `CRAWLER_PYTHON`.
- Confirm each provider's current access/storage terms and search endpoint before adding its key to `JOBDD_DISCOVERY_APPROVED_PROVIDERS` (comma-separated `careerjet,recruit_agent,meitec_next`). This explicit configuration records the operator's approval; the pipeline does not infer permission from an HTTP 200.
- Careerjet uses the existing `CAREERJET_API_KEY`, Basic Auth, referer, 20s timeout, at most 3 pages × 20 results and at least 1s interval. User IP / User Agent retain the existing crawler environment contract.
- Agent cells require an exact approved URL via `JOBDD_DISCOVERY_SEARCH_URLS`, a JSON object indexed by provider / occupation / region. For example, the existing Recruit mechanical/Hyogo URL can be configured under `{"recruit_agent":{"機械設計":{"兵庫県":"https://www.r-agent.com/job_search/area-hyogo/kw/%E6%A9%9F%E6%A2%B0%E8%A8%AD%E8%A8%88/"}}}`. This example is existing code evidence, not verification of current permission or availability. Other cell URLs are deliberately not guessed. Meitec URLs must be on `www.m-next.jp/job/s/`, Recruit on `www.r-agent.com/job_search/`.
- Preview one cell before any broad run:

```sh
php artisan jobdd:discover-daily --dry-run --provider=careerjet --occupation=機械設計 --region=兵庫県
php artisan jobdd:discover-daily --dry-run
```

Dry-run permits fetch and temporary file/report creation, but never database writes (including cache locks, routes or Facts). Failed/unapproved cells are reported as failures, not empty successful results. A report can exist for a failed run; read its status and errors. Non-success exits with code 1.

After terms/rate/one-cell validation, a normal one-cell run uses the same options without `--dry-run`. Enable automated execution separately with `JOBDD_DISCOVERY_ENABLED=true`; `JOBDD_DISCOVERY_TIME=04:00` controls time. No live collection was activated by Batch 15.

## Contracts and reports

Reports: `storage/app/jobdd/discovery/YYYY-MM-DD-<run_uuid>[-preview].json` and `.md`. UUID names preserve retries instead of overwriting the day's history. Files are internal (0600); do not serve this directory publicly. The raw temporary input is removed after importer invocation. No API key or process/exception response text is forwarded to reports.

The command uses an OS `flock` for manual/scheduled overlap prevention without DB writes or lock expiry during long runs, plus Laravel `withoutOverlapping`. This assumes a single scheduler host with shared local storage; multi-host orchestration is not implemented. Scheduler's own mutex follows the existing Laravel cache configuration.

Each existing importer has an opt-in `--daily-input` path used by the adapter. Read-only providers reject the legacy default path before any database write; persistent providers retain their legacy behavior. Do not mix legacy bulk import and daily runs concurrently; the daily lock cannot guard older commands.

For persistent providers only, daily identity is the existing unique `(provider_key, external_id)` pair; missing IDs use the existing provider URL/hash fallback. A legacy route attached to a posting owned by another provider is reported for review, never stolen. No cross-provider fuzzy deduplication is introduced. `first_seen_at` survives updates; only observed accepted records refresh `last_seen_at`. Company, content, salary, occupation, region, Source URL, availability and supplied provider dates determine NEW/UPDATED/UNCHANGED; clock refresh alone does not.

MISSING requires explicit complete-snapshot and missing-detection capabilities and is **not unavailable**. Bounded persistent providers report `not_observed_in_window`; read-only providers report neither absence classification. Failed cells/rows do not infer absence; jobs and routes are never closed for either observation.

NEW/UPDATED invoke the existing dictionary and Fact extractor inside the per-job import transaction. Rule Facts belonging to the current dictionary but no longer found in the new text are removed; manual/other Facts remain. There is no dictionary, context classification, Fit or UI change. UNCHANGED skips extraction. A Fact failure rolls back that job import.

Direct candidates are report-only, deduplicated by company from NEW/UPDATED jobs. Stored company website/available direct-route presence is descriptive and does not prove a matching official vacancy. No direct crawl, route promotion or queue persistence occurs.

Coverage counts active saved postings in the 12 exact canonical cells, with SQL aggregates rather than all-row materialization. Evidence metrics describe Fact/Source URL/available route presence, not verified quality. Tier2-like counts use existing dictionary keys; Tier1-like is null because a safe Fact classification is unavailable. Agency layer metrics count Batch 14 key presence (including pending/test data) and are not agent evaluations.

## Remaining operational checks

Confirm Python runtime, API credentials, current provider terms and approved per-cell Agent URLs. Real fetch validation and first normal write run remain operator-controlled. Missing-to-unavailable policy, retry sophistication, broader deduplication, retention/pruning and multi-host locks are future work.

## Careerjet anonymous employers (Batch 15.2)

Missing/blank Careerjet company names use the single reserved Company name `[jobdd:anonymous:careerjet]`. This is a storage bucket, **not an employer identity**. Incoming named companies cannot claim this reserved name. Other providers do not reuse it. No company name, website or region is inferred. Daily import serializes placeholder creation using the existing Careerjet Platform row lock inside the job transaction; a locking lookup sees the latest committed placeholder. Dry-run never creates it.

The UI displays `Careerjet掲載・企業名未確認` and `企業名は掲載情報から確認できていません`. Job comparison/Fit and saved platform routes remain available. Anonymous companies are excluded before Direct candidate website checks, persisted candidate building, and website/page discovery. No company URL evidence is attached to the anonymous bucket.

`anonymous_company_jobs` counts accepted distinct anonymous jobs in that provider/cell run, not all Careerjet jobs. Coverage retains its existing `companies` count and adds `companies_total`, `anonymous_companies`, and `named_companies` for the same stored active 12-cell scope; these are storage identities, not verified unique employers. External ID fallback remains unchanged.

## Careerjet location and bounded absence (Batch 15.3)

Daily Careerjet normalization uses the provider's `locations` field only. `search_region` is collection context, never a job Fact. A supported prefecture must be explicit; city-only, missing and ambiguous/mixed-prefecture locations remain unknown. Multiple destinations are collapsed only when every destination explicitly names the same supported prefecture. Unknown/out-of-cell jobs are counted as skipped for the exact-cell import; they are not silently assigned the query prefecture. No city dictionary or description inference is used.

Careerjet has `supports_complete_snapshot=false`: successful bounded fetches report `not_observed_in_window` and the corresponding job IDs, with `missing=0`. This is not disappearance, closure or proof that a job lies outside the top window: an unstable identity can also cause non-observation. Failed rows/fetches produce no absence inference. Other providers retain their previous report behavior; this does not certify those providers as complete snapshots.

A repeated whole page is rejected even when its tracking URLs differ, using a content signature solely as a pagination guard. It is **not** a job identity or fuzzy merge rule. Distinct pages with partial overlap are not treated as a repeated whole page.

The Batch 15.3 live audit observed 1,436 hits, of which only the first 60 were requested. Two fetches returned the same 60 title/company/location tuples in the same order, but **zero full URL overlap**. No `id`, `job_id`, `external_id` or `ref` was present. Therefore full tracking-URL SHA256 is not safe as a daily identity in this observed response. The existing fallback and saved identities are left unchanged; normal Careerjet writes must remain stopped pending a verified stable provider identity and reconciliation of existing rows. Do not substitute title/company hashes or strip opaque URL segments by guesswork. Scheduler remains disabled.

## Provider Onboarding Check (Batch 15.5 — current operating standard)

Use **five checks**, one provider × one canonical cell. Investigate further only when a check fails; do not broaden collection to compensate for an unresolved identity or access problem.

| Check | Record | Gate |
|---|---|---|
| 1. Identity | Native ID, stable detail/canonical URL, repeat overlap and collisions, anonymous cases, namespace/reposting meaning | Persistent mode requires a verified deterministic identity. Similar titles are not proof. |
| 2. Snapshot | Result total, requested window, pagination/next page, completeness | MISSING requires both complete snapshot and explicit missing support. Bounded persistent providers use not_observed_in_window only. |
| 3. Fields | At least 10 title/company/location/salary/source_url/description samples | Compare provider facts against normalization. Query conditions are not job facts; preserve unknown. |
| 4. Access / terms | Exact search/detail URLs, robots including queries, storage/reuse terms, interval, timeout, UA/auth | No bypass, blocked pagination or storage without established permission. Read access and persistence approval are separate. |
| 5. Validation | One-cell dry-run; 15-table hashes/write0; absence/duplicates; optional write; repeat idempotency; Facts/Direct | Write only after all preceding gates pass. Otherwise report the blocker and do not expand. |

Internal operations labels: **GREEN** = persistent approved after validation; **YELLOW** = read-only observations; **RED** = collection stopped by access/failure conditions. These are provider operation states, not user-facing Agent quality scores. Record actual evidence, date, URL, and untested conditions. Never turn a mock test into a real-network PASS.

### Capability contract

`config/discovery.php` explicitly defines `mode`, `supports_persistent_identity`, `supports_complete_snapshot`, `supports_missing_detection`, and `supports_direct_candidate_generation`. Unknown providers default to read-only/false. Persistent operation requires **both** persistent mode and persistent identity; missing requires **both** snapshot completeness and missing support. Direct candidates require persistent identity/mode plus Direct support, with anonymous/unknown names excluded. Source approval remains a separate access gate.

Careerjet is **YELLOW / read_only**. Both normal and dry-run daily commands fetch and normalize observations but never classify NEW/UPDATED/UNCHANGED/MISSING, query saved identities, upsert jobs/companies, save Sources/Facts/Routes, or generate Direct candidates. Historical fields are JSON null and Markdown N/A. Report fields are observed_in_window, accepted, skipped, anonymous, window_total, hits (unknown if not supplied), observation_time and current_discovery_candidates. The candidates have no stable identity and no full description dump. Existing saved UI/DB rows remain unchanged. The legacy Careerjet bulk command also rejects writes before creating crawl_runs. `--dry-run` is not required to obtain this protection.

Recruit Agent starts **read_only pending onboarding**; changing mode alone does not authorize persistence. Its daily window is the first search page and at most 10 details: the reviewed robots disallows cursor query pagination. The adapter rejects query-bearing Recruit requests, does not strip queries to bypass restrictions, disables automatic redirects, and does not request the forbidden next page. This is a bounded observation, never a complete snapshot. Storage/reuse conditions must be resolved before persistent approval. A one-off audit configuration does not grant ongoing collection approval.

Meitec's existing persistent importer capability is retained without new access approval or a new GREEN assessment; completeness/missing support remain false. It is not audited or executed in Batch 15.5.

### Standard result record

For each provider record: five check results (PASS / FAIL / UNKNOWN), observation scope and counts, effective mode, access approval scope, missing policy, write gate, idempotency, report paths, hashes, remaining blocker and next bounded action. Do not repeat broad investigations when the first five checks identify a decisive blocker.

The Batch 15.3/15.4 sections above are audit history. Their hypothetical Careerjet persistence/absence previews are superseded by this enforced read-only policy. Scheduler remains false; no OS cron or live UI integration is introduced.
