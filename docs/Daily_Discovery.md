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

Each existing importer has an opt-in `--daily-input` path used by the adapter. The legacy default path is unchanged, including its older missing/identity behavior. Do not mix legacy bulk import and daily runs concurrently; the daily lock cannot guard older commands.

Daily identity is the existing unique `(provider_key, external_id)` pair; missing IDs use the existing provider URL/hash fallback. A legacy route attached to a posting owned by another provider is reported for review, never stolen. No cross-provider fuzzy deduplication is introduced. `first_seen_at` survives updates; only observed accepted records refresh `last_seen_at`. Company, content, salary, occupation, region, Source URL, availability and supplied provider dates determine NEW/UPDATED/UNCHANGED; clock refresh alone does not.

For providers retaining the legacy absence branch, MISSING means absent from that successful search result, **not unavailable** or a complete-market disappearance. Careerjet instead reports `not_observed_in_window` (see Batch 15.3 below). Failed cells/rows do not infer absence; jobs and routes are never closed for either observation.

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
