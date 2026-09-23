# JobDD v5.3 Phase A Batch 7 実装報告

参照：JobDD_Master v5.3（18.1 / 20 / Batch 7）、JobDD_Decision_Log v4.1（D-089）、JobDD_AI_Coding_Rules、V5 PC / スマホ画面資料、Batch 6実装報告。
開始時：`new-jobdd-v4` / `2a6b74f Add Seeker Decision View v2`、作業ツリーclean。

Scope：任意の詳細希望入力・同一UserQueryへの保存と再編集・v2詳細での並列表示。
Do Not Change：簡易入力の保存仕様、Fit / Runnerの判定、ランキング、Score、企業Authoring、Publish / Review / Snapshot / Fact / Provenance生成、Application Route、Compare v2、Crawler / Importer。本番操作・migration・依存追加・commitなし。

## A. 変更ファイル

| 区分 | ファイル | 役割 |
|---|---|---|
| 追加 | `app/Support/SeekerPreferences.php` | 詳細希望のkey・検証・表示・Fit入力との分離 |
| 追加 | `app/Http/Controllers/UserQueryPreferenceController.php` | 認可、入力、保存、元の画面への復帰 |
| 追加 | `resources/views/query/preferences.blade.php` | 任意の詳細希望入力フォーム |
| 追加 | `resources/views/query/partials/preference-link.blade.php` | 一覧・詳細からの共通導線 |
| 変更 | `routes/web.php` | 詳細希望のGET / PATCH |
| 変更 | `app/Http/Middleware/JobDecisionSession.php` | 既存sessionを読む詳細希望ルートへの対応 |
| 変更 | `app/Services/JobDecisionUseCaseService.php` | 一覧のFit入力から詳細希望だけ除外 |
| 変更 | `app/Services/JobSelectionUseCaseService.php` | 詳細・比較のFit入力から詳細希望だけ除外 |
| 変更 | `app/Services/JobDetailUseCaseService.php` | 表示用の詳細希望をPresenterへ渡す |
| 変更 | `app/Support/PublishedJobDecisionPresenter.php` | 希望と公開版Level 2の比較行を生成 |
| 変更 | `resources/views/query/jobs.blade.php` | 一覧に任意入力の入口を追加 |
| 変更 | `resources/views/query/job-show-v2.blade.php` | 希望と仕事の内容を並列表示、再編集導線 |
| 変更 | `resources/views/query/job-show.blade.php` | 既存求人からも詳細希望を編集可能にする |
| 追加 | `tests/Feature/SeekerProgressiveInputTest.php` | 新規33ケースのFeature Test |
| 追加 | `docs/JobDD_Batch7_Implementation.md` | 本報告 |

## B. UX Flow

既存`/jobs/start`の職種・地域・希望年収・CAD / Tool・その他Tool入力はそのまま。詳細希望を入力せず求人一覧へ進める。

一覧の「もっと詳しく比較する」から任意の詳細入力へ進み、保存後は同じUserQueryの一覧へ戻る。求人詳細から開いた場合は同じ求人詳細へ戻る。ページ番号と選択ToolをURLで引き継ぐ。

詳細希望があるv2求人では、基本情報の後に「あなたの詳細希望と、この求人の仕事」を表示する。未入力時はこのセクションを出さず、任意入力のリンクだけを置く。既存の比較・応募方法CTAは維持する。

## C. Storage

既存のnullable JSON列`user_queries.detailed_skills`に、専用namespace `seeker_preferences`を追加する。Modelの既存array castを使用し、schemaは変更しない。

```json
{
  "custom_tools": "iCAD SX",
  "seeker_preferences": {
    "design_phases": ["detailed_design", "testing"],
    "customer_contact": "want_less",
    "manufacturing_relation": "want_more",
    "site_relation": "no_preference",
    "work_style": "チームで相談しながら進めたい"
  }
}
```

既存の`custom_tools`はTool自由記述専用のため、仕事の進め方へ転用しない。`priorities`は既存Fitで項目順に使われるため、詳細希望の保存先にしない。

専用namespace以外の既存JSON値、簡易条件、raw_text、public_id、session_token、priorities等を保持する。実DBで確認した既存42行はSQL NULL 41行、JSON object 1行。実データの書換えは行っていない。

## D. Internal Keys

工程は企業側の`StructuredJobOptions::PHASES`をそのまま再利用する。

| 内部key | 表示 |
|---|---|
| concept | 構想 |
| basic_design | 基本設計 |
| detailed_design | 詳細設計 |
| drafting | 製図 |
| analysis | 解析 |
| testing | 試験・評価 |
| manufacturing_support | 製造対応 |
| site_support | 現地対応 |
| other | その他 |

顧客・製造・現場の保存keyは、それぞれ`customer_contact` / `manufacturing_relation` / `site_relation`。

| 内部値 | 各関係の見出しの下に表示する希望 |
|---|---|
| want_more | 積極的に関わりたい |
| neutral | どちらでもよい |
| want_less | 関わりは少なめがよい |
| no_preference | 特に希望なし |

未入力は空欄。明示的な「特に希望なし」と区別する。`work_style`は任意の自由記述（2,000文字以内）。AIの意味分類は行わない。新規keyと検証ルールは`SeekerPreferences`に集約する。

## E. Re-edit

- `GET /query/{userQuery:public_id}/preferences`：保存済み値を再表示。
- `PATCH /query/{userQuery:public_id}/preferences`：詳細希望一式を置き換える。
- `public_id`だけではアクセスできず、既存`jobdd_query_token_{public_id}`とsession_tokenの一致が必要。新規アカウント認証は要求しない。
- CSRFを維持し、実CSRF有効化テストで不正PATCHの419と正常保存を確認。
- 行ロック付きtransactionで最新JSONを読み、専用namespaceだけを置き換える。工程を重複して送信した場合は拒否する。
- 空欄にして保存すると該当希望を解除。すべて空ならnamespaceを削除し、他のJSONがなければnullへ戻す。
- 不正入力は422でフォームを再表示し、既存データを変更しない。戻り先は内部routeと検証済みのページ・Tool・求人IDから生成し、外部URLを受け取らない。
- 既存JSONがscalar等で安全にmergeできない場合は409とし、既存値を保持する。通常のnull / object / legacy listは保持して保存可能。

GETでsessionのGC・更新・書込みを発生させない。PATCHもsessionの内容は変更せず、UserQueryへの保存だけを行う。既存のsession有効期間は延長しない。

## F. Decision View

追加した希望だけ、次の5項目を並列表示する。

| 比較項目 | 求人側の参照 |
|---|---|
| 担当工程 | Snapshotのdesign_phasesを日本語化 |
| 顧客との関わり | Snapshotのcustomer_contact_frequency / note |
| 製造との関わり | Snapshotのmanufacturing_relation_frequency / note |
| 現場との関わり | Snapshotのsite_relation_frequency / note |
| 仕事の進め方 | Snapshotのwork_style |

左側「あなたの希望」、右側「この求人の公開情報」。390pxでは上下に並ぶ。求人側情報が欠ける場合は「求人側の情報はまだ確認できていません」と表示し、希望から求人内容を補わない。

求人側は最後に承認されたSnapshotだけを使用する。Authoring変更後も未承認の工程・関係・仕事の進め方が表示されないことを検証した。長文は既存の全文展開、文字列はBlade escapeを使用する。

Snapshotなし求人は従来の詳細を維持し、詳細希望への編集リンクだけを追加する。Level 2を推定して比較欄を作らない。

## G. Fit Separation

`JobFitService` / `JobFitRunnerService`は無変更。詳細希望をconfirmed requirementsやprioritiesへ追加しない。

既存Fitは`detailed_skills`が空でない場合に補助noteを付けるため、一覧・選択UseCaseから渡すときだけ、UserQueryのメモリ上のcopyから`seeker_preferences`を除外する。元のUserQueryと既存custom_tools / legacy intentは保持する。

これによりaxes / status / summaryだけでなく、input_notesを含む公開フローのFit結果全体が保存前後で同一。新規比較行にMATCH / MISMATCH / UNKNOWNのbadgeや判定属性を付けない。工程・関係性・自由記述の意味推定も行わない。

## H. Ranking

Discovery・候補抽出・並び順・Score計算・ScoreResult保存は無変更。詳細希望を使った絞り込みや並べ替えは行わない。一覧の求人ID順、選択順、Fit全体、ScoreResultが保存前後で不変であることを検証した。

## I. Interaction Logs

新規イベントは追加しない。既存イベント名・応募経路等の記録処理は維持する。今回の詳細フォームGETはread-only、PATCHは希望保存に限定し、自由記述をAnalyticsへ複製しない。

## J. Tests

Compose内で`APP_ENV=testing DB_DATABASE=testing DB_URL=`を明示して実行。

- 新規Feature：**33 PASS / 247 assertions**。
- 関連（新規・簡易入力・公開詳細・詳細比較）：**139 PASS / 989 assertions**。
- Full：**799 PASS / 5,108 assertions**（116.51秒）。Batch 6 baseline 766 PASSから33ケース追加。
- Pint：変更PHP 9ファイルPASS。
- build成功、route:listでGET / PATCHと既存routesを確認、diff --check PASS。

検証範囲：簡易入力のみで開始、同一Query保存、複数工程・関係希望・自由記述、再表示・再保存・解除、未知key・重複・不正型・長文拒否、他session / token不一致拒否、実CSRF、DB session read-only、mass assignment / 外部redirect防止、XSS、未承認Authoring非表示、欠損値、Fit全体・順序・Scoreの不変性、既存fallback・Compare維持。

JSON objectのキー順はMySQLが正規化するため、保存内容はキー順に依存せず検証する。配列の工程順はdictionary順へ正規化する。

## K. DB Safety

schema / migration追加なし。実DBはread-only smokeと件数・hash確認だけ。主要19テーブルの全カラムをID順にSHA-256へ集約して前後比較する。

**19テーブルすべて件数・hash一致。** status / review_status分布も不変。

| テーブル | 前後件数 | SHA-256（前後同一） |
|---|---:|---|
| `job_structured_profiles` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `job_tool_usages` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `job_typical_day_items` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `job_published_profiles` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `company_user` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `users` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `companies` | 499 | `3a6a169948e2659fe66762e8b4200b7fedde39e34d5184295031a553d2e6fe9d` |
| `job_postings` | 1591 | `87b0914dfe2dd06f702bc6bdcc7af35d81ee119cc9df3bd1d50af037e8164d08` |
| `application_routes` | 1594 | `c8a69d39a2b1c42ed777c1be47d48cd730068eb08709ed7d60a18e3fd445ffb3` |
| `sources` | 465 | `874728d3ddaccde3dcecd66549608ffaab2cce40b5158cb8a65b52e5c2cb8877` |
| `job_facts` | 1732 | `a8f04e78c6e1873008990c390ab6f96c9668bbbb13b1362f44eea462faa289d4` |
| `agencies` | 9 | `c3eda0fd942f9a82d9564ca49b0e519f33433029f7abddbdd15c04892effeb55` |
| `agency_facts` | 27 | `44e5f1963b0ed1db348c308d851e0d447a3587b124381aac057f1848f788864c` |
| `platforms` | 3 | `f2610e63b6ef9fb629c2a88e1c89c05b06e428854793aaf1a6e63c072e69c7d1` |
| `user_queries` | 42 | `054e87bc53597cd87e9a951648f5c9b9684874a77096312cf4264a6d77848cdf` |
| `score_results` | 151 | `8cdfb9c19376021367096c58048ff6127cbe48482a9c6e5426a23f9af37896cc` |
| `interaction_logs` | 196 | `c48406ef07ad1b14603fa6a15e68ba6e33495e1c0bb2259b6501976dbd67cd1c` |
| `crawl_runs` | 18 | `80bc955ab0a0768fd1e5b69d433283310764406f1bd8596d2f292c84f69365cf` |
| `direct_reverse_lookup_candidates` | 362 | `55c0d066d726e6b5730b7cc9fc98b0511cd104c2d8231323bb4e5908dd816d78` |

既存公開求人1件を実DBから読み、詳細希望をメモリ上で追加するsmokeでもFit結果全体の不変と書込SQL 0件を確認。希望保存のテストはtesting DBのみ。

## L. UI Verification

V5資料を参照し、現行求職者UIのheader、condition-summary、card、button、decision-section / fieldsを再利用。新規JavaScriptやframeworkは追加していない。

- PC：1,440pxのEdge headless。実測viewport 1,416px、content幅はそれ以下。
- Mobile：390px iframe。通常入力・未入力・エラー・希望比較・長文展開・一覧で横overflowなし。
- 保存値のcheckbox / select / textareaへの反映、PATCH form data、エラー時のsummary focusをブラウザで確認。
- 既存比較の自動選択、2件で有効、3件上限、解除もPC / mobileで確認。
- JavaScript error / unhandled rejection 0件。
- `npm run build`成功。

ブラウザには未保存fixtureから生成したBladeを使用した。実際のHTTP認可・保存・再保存・CSRFはFeature Testで検証しており、実DBへログインして保存するブラウザE2Eは実施していない。

Laravel logの新規実行時エラーは0件。追加ERRORは既存`CompanyStructuredJobTest`が意図的に発生させたrollback例外`testing.ERROR: simulated row failure`の1件のみ。

確認用一時ファイル：`/tmp/jobdd-batch7-ui/`、`/tmp/jobdd-batch7-related-final.log`、`/tmp/jobdd-batch7-full.log`、`/tmp/jobdd-batch7-before.json`、`/tmp/jobdd-batch7-after.json`、`/tmp/jobdd-batch7-smoke.json`。一時成果物をリポジトリへ追加していない。

## M. Remaining Risks / OPEN

- 詳細希望は表示材料に限定。Fit昇格・自由記述の意味分類・推薦・ランキング利用は未実装。
- Batch 8のCompare v2列追加、ページをまたぐ比較選択保持は未着手。
- 再編集は従来のsessionでQueryを識別できる期間に限る。別端末共有・アカウントによる検索履歴の復元は追加していない。
- 選択CAD / Toolは従来どおりURLで引き継ぐ。今回の保存対象は詳細希望だけ。
- 同じ詳細希望を複数タブから保存した場合は最後の保存を採用する。他namespaceを失わないための行ロックはあるが、詳細希望の版管理は行わない。
- JSON scalar等の非標準legacyデータは既存値を保護して409にする。確認した実DB42行には該当なし。
- Snapshotなし求人には対応するLevel 2がないため、詳細希望の並列表示は追加していない。保存・再編集は可能。

## N. Verdict

**PASS**。Batch 7のみで終了。Batch 8へ進まず、commitしていない。
