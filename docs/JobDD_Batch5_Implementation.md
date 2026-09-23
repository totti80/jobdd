# JobDD v5.3 Phase A Batch 5 実装報告

## A. 変更ファイル

開始時：`new-jobdd-v4`、作業ツリーclean。直近は`7f0dbdf` / `7872a04` / Batch 4 `7618395`。Master v5.3、Decision Log v4.1、AI Coding Rules、V5 PC/mobile設計を確認。今回commitなし。schema・依存package・実際の.envは変更なし。

追加：

- `app/Http/Controllers/CompanyJobPreviewController.php`
- `app/Http/Controllers/JobReviewController.php`
- `app/Services/CompanyJobAuthoringData.php`
- `app/Services/CompanyJobPublishValidator.php`
- `app/Services/CompanyJobReviewService.php`
- `app/Services/CompanyJobPublishService.php`
- `app/Services/CompanyJobFactTransformer.php`
- `app/Services/PublishedJobQuery.php`
- `app/Mail/CompanyJobReviewRequested.php`
- `config/jobdd.php`
- `resources/views/company/jobs/preview.blade.php`
- `resources/views/company/jobs/partials/preview-content.blade.php`
- `resources/views/company/jobs/partials/review-feedback.blade.php`
- `resources/views/admin/job-reviews/index.blade.php`
- `resources/views/admin/job-reviews/show.blade.php`
- `resources/views/jobs/provenance.blade.php`
- `resources/views/mail/company-job-review-requested.blade.php`
- `resources/views/components/company-card.blade.php`
- `resources/views/components/company-action.blade.php`
- `tests/Feature/CompanyControlledPublishTest.php`
- `tests/Unit/CompanyPublishConcurrencyTest.php`
- `tests/Support/PublishFixture.php`
- 本報告書。

変更：

- `routes/web.php`、`.env.example`
- `app/Models/JobPosting.php`
- `app/Http/Controllers/CompanyJobBasicController.php`、`CompanyStructuredJobController.php`
- `app/Http/Controllers/RouteComparisonController.php`、`UserQueryController.php`
- `app/Services/JobFactDictionary.php`、`JobFitService.php`
- `app/Services/JobDiscoveryService.php`、`JobSelectionUseCaseService.php`、`JobDetailUseCaseService.php`、`RouteSummaryService.php`
- `resources/views/company/dashboard.blade.php`、`company/jobs/basic.blade.php`、`company/jobs/structured.blade.php`、`components/company-layout.blade.php`
- `tests/Feature/CompanyLevelOneTest.php`、`CompanyStructuredJobTest.php`、`JobDiscoverySliceTest.php`

## B. Preview

`GET /company/jobs/{jobPosting}/preview`。既存auth / EnsureCompanyMember / JobPostingPolicyのpreview権限を利用。Owner・Editorは自社のみ、Platform Ownerは全求人、他社403、Guestはlogin。

`CompanyJobAuthoringData`が現在のLevel 1、Structured Profile、Tool、Typical Day、代表案件、hard_to_conveyを読み取り、企業Previewと管理審査で共通の表示部品を利用する。PREVIEW・「未公開または編集中の内容です」を明示。GETではFact・Snapshot・review/statusを書き換えない。private/no-store。

STEP 5の「保存してPreviewへ」、Dashboardの「Preview・公開申請」から接続。

## C. Publish Validator

`CompanyJobPublishValidator`を申請時と承認時の両方で実行。

Level 1：title / occupation / region / employment_type / description / source_url / application_requirementsを必須。年収は上下限いずれか必須、整数1〜10,000万円、両方ある場合は上限≧下限。応募URLはhttp/https。

Level 2 Core：設計対象、工程1件以上、初期担当、協働相手1件以上、仕事の進め方、難しさ。顧客・製造・現場の各関係はfrequencyとnoteの両方。Tool1行以上、全行の名称・使用文脈・経験要件。Typical Day1行以上、全行の時間帯・活動。内部keyを既存Authoring選択肢で検証。

任意項目は申請条件へ追加しない。「未定」は明示された状態として有効。Draftの途中保存は従来どおり可能。Completion %とは独立。

## D. Review Request

`POST /company/jobs/{jobPosting}/review-request`。親求人をTransactionでロックし、所有権を再確認。Validator通過後にpending_review、review_requested_atを保存し、旧reviewed_at / reviewer / noteをクリアする。statusは変更しない。

同じpending申請への再POSTは状態・申請日時を変更せず、メールも重複送信しない。編集中でもreview_statusを自動変更しない。審査中に内容が変わった場合は承認時の確認tokenで検出する。

## E. Mail

`config('jobdd.review_notification_email')` → `JOBDD_REVIEW_NOTIFICATION_EMAIL`、既定値`postmaster@jobdd.jp`。`.env.example`のみ追加。既存Mail driverを利用。

件名「【JobDD】求人の公開申請が届きました」。企業名、求人名、申請日時、`/admin/job-reviews/{jobPosting}`へのリンクを含む。メールのリンクはGETで確認画面を開くのみ。

`DB::afterCommit`で送信。メール例外は求人ID・例外クラスとともにERROR logへ記録し、申請を維持する。テストはMail::fake()、失敗ケースはMail / Log mock。実メール送信なし。送信失敗の自動再送・outboxは今回未実装。

## F. Admin Review

- `GET /admin/job-reviews`：pending申請を申請日時・ID順で20件paginate。
- `GET /admin/job-reviews/{jobPosting}`：現在のAuthoring内容、公開／審査状態、必須不足、承認・差戻しフォーム。
- `POST /admin/job-reviews/{jobPosting}/approve`：Publish Serviceを呼ぶ。
- `POST /admin/job-reviews/{jobPosting}/changes-requested`：理由必須、最大10,000文字。

既存auth / EnsurePlatformOwner / verified境界を再利用。通常企業User403。サービスでもPlatform Ownerを再確認。POSTはWeb middlewareのCSRF対象。差戻しではreview項目のみ更新し、既公開版・Fact・Routeは保持。理由を企業Dashboard / Previewに表示。

## G. Published Edit

今回Publishされたversion 1 Snapshotのあるpublished求人は、Level 1 / Level 2ともAuthoring編集可能。保存だけではSnapshot・Fact・Route・review_statusを変更しない。

現行の求職者側がLevel 1を`job_postings`から直接読んでいたため、編集制限解除と同時に`forPublic()`による読取専用SQL projectionを導入した。Snapshotがある求人はLevel 1と公開版更新日時をSnapshotから読み、検索条件・並び順・表示・既存Fitに同じ公開値を渡す。対象はDiscovery、選択／詳細／比較、旧results、応募経路画面、経路要約。正式Seeker View v2は実装しない。

既存の外部求人など、version 1 Snapshotのないpublished求人は従来値で表示し、Authoring更新は409のまま。旧公開版を保存せずに編集解禁すると未承認情報が漏れるため。既存求人をSelf-service管理へ移す手順は別途必要。保存時に便宜的なSnapshot生成は行わない。

## H. Job Fact Dictionary

既存`app/Services/JobFactDictionary.php`を単一正本として拡張。既存Extractor用`definitions()`は保持し、企業用`companyDefinitions()` / `companyRole()`を追加。category/keyの組を識別し、既存keyの意味を上書きしない。

正式category：`job_content`, `design_phase`, `assignment`, `tool_usage`, `tool_expectation`, `experience`, `collaboration`, `work_style`, `work_reality`, `project_example`。

工程・協働相手・Tool keyは既存`StructuredJobOptions`を参照。フォームSTEP名をcategoryにしない。

## I. Fact Conversion

全企業Fact：`extraction_method=company_self_reported`、`verification_status=self_reported`、source_id / observed_at / context_roleを明示。

| Authoring | category / key | role・保存内容 |
|---|---|---|
| design_target | job_content / design_target | responsibility |
| product_context | job_content / product_context | product_context |
| design_phases | design_phase / 各工程key | responsibility、1選択1Fact |
| initial_assignment / future_scope | assignment / 同名key | responsibility |
| Tool使用 | tool_usage / Tool key | primary・occasional→responsibility、other_department→other_department、not_used・undecided→unknown |
| Tool経験 | tool_expectation / Tool key | required→required_experience、preferred→preferred_experience、その他→unknown |
| required_experience / preferred_experience | experience / 同名key | 同名role |
| collaborators | collaboration / 各協働相手key | collaboration、1選択1Fact |
| 顧客・製造・現場との関係 | collaboration / customer_contact・manufacturing_relation・site_relation | collaboration。fact_value=note、normalized_value=frequency、evidence_textにも両方 |
| work_style / fit_work_style / misfit_work_style | work_style / 同名key | company_context |
| project_duration / concurrent_projects / difficult_points / onboarding_challenges | work_reality / 同名key | company_context |
| representative_project | project_example / representative_project | project_example。FactはJSON文字列表現を最大10,000文字の要約とし、Snapshotは元JSON全体 |
| hard_to_convey | job_content / hard_to_convey | company_context |

自由入力Toolは`other_tool`、normalized_valueに名称。Toolのevidence_textには補足も含む行全体のJSONを保持。Typical DayはFact化せずSnapshotに順序付きで保持。ContextRoleClassifierで企業申告を再推定しない。

## J. Source / Provenance

求人ごとの予約済み内部URL`/jobs/{jobPosting}/provenance`をSource URLに使用。外部応募URLをSource identityへ流用しない。`source_type=company_self_reported`、publisher=企業名、title=「企業名によるJobDD登録情報」。Sourceは同じ求人の再Publishで再利用。

ProvenanceページはpublishedかつSnapshotがある場合のみ表示。前回承認されたSnapshot内の企業名・求人名・Provenanceを表示し、「公開確認は真偽の保証ではない」と明示。既存Importer / Crawlerは変更なし。

## K. Snapshot

`job_published_profiles`は求人ごとに1件、承認時だけupsert。

```text
schema_version: 1
company: {id, name}
level_one: {title, occupation, region, salary_min, salary_max,
            employment_type, description, source_url, application_requirements}
structured_profile: {全Authoring Profile項目、representative_project、hard_to_convey}
tool_usages: [{tool_key, tool_name, usage_context, experience_expectation, usage_notes, sort_order}]
typical_day_items: [{time_label, activity, sort_order}]
provenance: {source_id, source_type, url, publisher, title,
             verification_status, published_at, reviewed_by_user_id}
```

`job_postings.published_at`は初回公開日時を保持。`job_published_profiles.published_at`は最新版承認Publish日時。Authoring編集・再申請・差戻しでは旧公開版を保持する。

## L. Application Route

同一求人の`route_type=direct / agency_id=null / platform_id=null`をidentityにupdateOrCreate。応募URL、available、unavailable_at=nullを設定。親求人ロック下で処理し、再Publishや同時承認で増殖しない。既存DirectのIDを再利用し、Agent / Platform Routeを削除しない。

## M. Transaction / Rollback

承認ServiceでPlatform Owner確認 → DB Transaction → 親求人lockForUpdate → pending再確認 → 確認token照合 → Validator → Source / Facts / Snapshot / Route / status / review更新。

審査画面には、求人ID・申請日時・表示したAuthoring全体をAPP_KEYによるHMACで結びつけたtokenを埋め込む。画面確認後の編集や改ざんは409。再読込で再確認する。申請時点の履歴Snapshotを別保存する仕組みではなく、承認者が画面で確認した現在値を保証する方式。

二重承認はpendingでなくなった時点で409。別プロセス・別接続による競合テストで、先行Transactionのcommitまで後続が待機し、その後409になることを確認。初回・再公開とも意図的な途中例外で全体rollbackを検証。

## N. Existing Fit Compatibility

`JobFitService`はID・求人対応・重複などの検証を維持し、企業申告Factを旧Fit計算から除外する。未知categoryで落ちず、既存外部Factの辞書検証・計算は維持する。企業申告Toolだけの場合、現行Tool FitはUNKNOWN。これをMATCHへ昇格させる仕様は今回追加しない。

既存詳細画面のEvidenceには企業が明示したcontext_roleを渡し、Classifierを呼ばない。職種・地域・年収・既存CAD/Tool以外のFit軸、Score、rankingロジックは追加しない。

## O. Tests

追加49ケース（Feature 48 + 別接続Integration 1）。関連Feature：99 PASS / 785 assertions。別接続の同時承認Unit/Integration：PASS。最終全体：751 PASS / 4,675 assertions（100.09秒）。

Core各項目不足、途中入力、未知key、応募URL、Preview権限・GET無書込み、通知recipient・本文・リンク、メール障害、二重申請、審査権限、差戻し理由・旧版維持、承認・再公開、全公開読取境界、明示role、外部Source/Fact保護、Direct identity、stale token、初回／再公開rollback、既存Fit互換性を検証。

## P. DB Safety

テストはtesting DB。実DBには確認用データを作成していない。前後19テーブルの件数・全列SHA-256、公開／審査状態分布が一致。

| 主なテーブル | 前後件数 |
|---|---:|
| users | 0 |
| companies | 499 |
| job_postings | 1,591 |
| application_routes | 1,594 |
| sources | 465 |
| job_facts | 1,732 |
| job_structured_profiles / job_tool_usages / job_typical_day_items / job_published_profiles / company_user | 各0 |

migration・本番DB操作・実メール送信なし。

## Q. UI Verification

既存Edgeで、企業Preview、未完成・差戻しPreview、審査Queue、審査詳細の4画面をPCと実viewport390pxで確認。8画面すべて横はみ出しなし、JS error / unhandled rejectionなし。共通company-layoutとカード・アクション部品を利用。

未保存モデルからBladeを描画してブラウザ確認。HTTP操作・認証・保存はFeature Test、排他は別プロセスIntegration Testで確認しており、実DBログインを使ったブラウザE2Eではない。

`npm run build`成功。新規PHPおよび変更した整形済みPHPのPint PASS、変更PHP28ファイルのsyntax check PASS、git diff --check PASS。レガシー書式の既存ファイルは必要箇所のみ変更し、大規模な整形は行わない。Laravel logの追加ERRORは既存rollbackテストが意図した`testing.ERROR: simulated row failure`のみ。

## R. Remaining Risks / OPEN

- Batch 6：正式Seeker Decision View v2、Snapshot内のLevel 2を使う正式な表示・比較。
- 企業申告ToolをFitでどう評価するかは未昇格。現在は既存Fitから除外しUNKNOWNを維持。
- Snapshotのない既存公開求人のSelf-service管理への移行は別対応。今回既存データを一括変換しない。
- メール送信障害の自動再送は未実装。申請はQueueに残り、logで検知できる。
- 同一STEPを複数タブで編集した場合のAuthoring保存は従来どおり後勝ち。審査操作は確認tokenで古い内容への承認・差戻しを拒否する。
- 公開用SQL projectionは現行MySQLのJSON機能を利用。schema追加時は公開投影対象も点検する。
- Previewの実務上の読みやすさ、差戻し理由の運用文言、通知先の実運用設定は人間による確認対象。

## S. Verdict

PASS。Batch 5で終了。Batch 6には進んでいない。commitなし。
