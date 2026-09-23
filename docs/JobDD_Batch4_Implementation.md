# JobDD v5.3 Phase A Batch 4 実装報告

## A. 変更ファイル

追加：

- `app/Http/Controllers/CompanyStructuredJobController.php`：全5STEPの表示・保存・遷移。
- `app/Http/Requests/CompanyStructuredJobRequest.php`：STEP別の型・長さ・内部key検証。
- `app/Support/StructuredJobOptions.php`：表示ラベル、選択肢、STEP別保存項目の単一管理。
- `app/Services/StructuredProfileCompletionService.php`：保存済み入力の動的集計。
- `resources/views/company/jobs/structured.blade.php`：共通STEP画面、入力支援用要約。
- `resources/views/company/jobs/partials/structured-field.blade.php`：Profile入力欄。
- `resources/views/company/jobs/partials/structured-row.blade.php`：Tool / Typical Day入力行。
- `resources/js/company-authoring.js`：行追加・削除、ツール候補の名称補完。
- `tests/Feature/CompanyStructuredJobTest.php`：保存・認可・検証・表示の回帰テスト。
- 本報告書。

変更：`routes/web.php`、`CompanyJobBasicController.php`、`CompanyJobBasicRequest.php`、`resources/views/company/jobs/basic.blade.php`、`resources/js/app.js`、`tests/Feature/CompanyLevelOneTest.php`。

開始時点のMaster / Decision Logの未コミット変更は保持。今回それらの文書は編集していない。Batch 3 checkpointは`8515d08`。今回commitなし。

## B. Routes / Controllers

`GET /company/jobs/{jobPosting}/structured/step-{step}` → `CompanyStructuredJobController@edit`。

`PATCH` 同URL → `@update`。`step`は1〜5のみ。route名は`company.jobs.structured.edit` / `update`。

Level 1の「保存してSTEP 1へ進む」から接続。下書き保存は同STEP、次へ／前へは保存後に遷移。STEP 1の前はLevel 1、STEP 5の次は同画面で「公開前確認は準備中」。GETはAuthoringデータを作成しない。

## C. Authoring Mapping

下表のProfileは`job_structured_profiles`。UIラベルの正本は`StructuredJobOptions::FIELDS`。

| STEP | UI項目 → 保存先 |
|---|---|
| 1 | 設計対象→`design_target`、用途・背景→`product_context`、担当工程→`design_phases` JSON配列、初期担当→`initial_assignment`、将来範囲→`future_scope` |
| 2 | 必要経験→`required_experience`、歓迎経験→`preferred_experience`。ツール候補／名称／使用場面／経験期待／補足→`job_tool_usages.tool_key/tool_name/usage_context/experience_expectation/usage_notes` |
| 3 | 協働相手→`collaborators` JSON配列、顧客→`customer_contact_frequency/note`、製造→`manufacturing_relation_frequency/note`、現場→`site_relation_frequency/note`、進め方→`work_style` |
| 4 | 期間→`project_duration`、並行案件→`concurrent_projects`、難しさ→`difficult_points`、入社後の課題→`onboarding_challenges`、仕事スタイルの相性→`fit_work_style/misfit_work_style` |
| 5 | 時間帯／作業→`job_typical_day_items.time_label/activity`、代表案件→`representative_project` JSON、伝わりにくいこと→`hard_to_convey` |

代表案件は1オブジェクト：`what_made`、`phases`（工程key配列）、`duration`、`team`、`difficult_point`。時間帯は自由文字列。行順は両テーブルの`sort_order`へ0始まりで保存。

## D. Internal Keys

- `design_phases`：`concept`, `basic_design`, `detailed_design`, `drafting`, `analysis`, `testing`, `manufacturing_support`, `site_support`, `other`
- `collaborators`：`design_team`, `other_engineering`, `manufacturing`, `quality`, `sales`, `customer`, `partner_company`, `site_staff`, `other`
- frequency：`almost_daily`, `several_times_week`, `several_times_month`, `rarely`, `depends_on_project`, `undecided`
- `usage_context`：`primary`, `occasional`, `other_department`, `not_used`, `undecided`
- `experience_expectation`：`required`, `preferred`, `not_required`, `undecided`
- `tool_key`：`autocad`, `inventor`, `solidworks`, `catia`, `creo`, `nx`, `electrical_cad`, `other_tool`。自由入力はkeyなしでも保存可能。

各選択肢は日本語ラベルと分離し、未知keyは保存拒否。JobFactDictionaryには追加しない。

## E. Save Strategy

親求人をTransaction内でロックし、Policyとdraft状態を再確認。現在STEPに属するProfile項目だけを`updateOrCreate`する。現在STEPの省略項目はクリア、他STEPの項目は保持する。

STEP 2/5は当該求人の行だけを削除・再作成する。全項目空欄の行は除外。最大30行、表示順をサーバー側で採番し、row idや他求人idの入力は受け付けない。同じ入力の再保存で内容・行数は増殖しないが、内部row idとtimestampsは更新される。途中失敗はProfileを含め全体rollback。

保存時に求人の更新日時を更新する。公開・審査状態、Evidence、Fact、Snapshot、応募経路は変更しない。同じSTEPを複数タブで編集した場合は後の保存が優先される。

## F. Completion

保存済みLevel 1の8項目とCore13項目、計21項目を同じ重みで集計し、`round(充足数 / 21 * 100)`。

Level 1：title、occupation、region、salary（上下限いずれか）、employment_type、description、source_url、application_requirements。

Core：設計対象、工程、初期担当、ツール名、使用場面、経験期待、協働相手、顧客関係、製造関係、現場関係、進め方、難しさ、Typical Day。

関係性はfrequencyとnoteの両方、ツールは1行以上かつ全行の該当欄、Typical Dayは1行以上かつ全行の時間帯と作業が必要。明示的な「未定」は入力済みとして扱う。任意項目は加点しない。DB保存・Fit・ランキング・Publish判定には使用しない。Core不足でも下書き保存可能。

## G. Authorization

既存のauth / EnsureCompanyMember / JobPostingPolicyを利用。Owner・Editorは自社draft、Platform OwnerはPolicy権限でdraft編集可能。他社・所属なし・所属解除後は403、Guestはloginへ。公開済みは閲覧のみ、更新409。UI非表示だけに依存しない。

## H. Tests

GET無書込み、5STEPの途中保存、他STEP保持、保存後遷移、Fortifyによるログアウト・再ログイン後の再取得、行置換・削除・順序・他社行保護、validation後再表示、XSS escaping、内部key・型・長さ・row id注入、Transaction rollback、充足率、各権限、Level 1接続を検証。

実行19テーブルすべての件数・全列hashが一致。求人状態分布も一致。companies 499、job_postings 1,591、application_routes 1,594、sources 465、job_facts 1,732。Authoring 3テーブル、Snapshot、company_user、usersはいずれも0件のまま。

## I. DB Safety

自動テストは`APP_ENV=testing / DB_DATABASE=testing / DB_URL=`で実行。実DBへmigrationや確認用データの追加は行わない。ブラウザ確認には未保存モデルから生成したHTMLを使用。

開始前後の19テーブル全列SHA-256・件数、および求人の公開／審査状態分布を比較。19テーブルすべての件数・全列hashが一致。求人状態分布も一致。companies 499、job_postings 1,591、application_routes 1,594、sources 465、job_facts 1,732。Authoring 3テーブル、Snapshot、company_user、usersはいずれも0件のまま。

## J. UI Verification

V5 PC/mobile設計を参照し、既存company-layout / Blade / Tailwindを利用。新規依存なし。

既存Edgeで5STEPをPC / 実viewport390pxのiframeで描画。実際のビルド済みJSで行追加・削除を検証。表示は静的HTMLでの確認、認証・HTTP保存はFeature Testで検証しており、実ログインを含むブラウザE2Eではない。

全10画面で横はみ出しなし、JS error / unhandled rejectionなし。STEP 2/5の追加・削除はPC/mobileともPASS。`npm run build`成功、対象PHPのPint PASS。ログの新規ERRORはrollbackテストで意図した`testing.ERROR: simulated row failure`のみ。

コード差分の`git diff --check`はPASS。リポジトリ全体では開始時から存在するMaster / Decision LogのMarkdown末尾空白が検出されるため、その差分は保持した。

## K. Remaining Risks / OPEN

Batch 5以降：正式Preview、Controlled Publish、公開時Core Validator、Snapshot生成と公開側参照、Fact変換、公開済み求人の再編集。今回これらは実装しない。

下書きは不完全なまま保存できる。充足率100%でも公開可能を意味しない。同一STEPの競合検出や最終編集STEPの保存は未実装。再ログイン後は保存済みの各STEPを再表示できる。

## L. Verdict

PASS。Batch 4で終了。Batch 5には進んでいない。commitなし。人間による次の確認は、下書き求人での各STEPの入力文言・保存後遷移・業務説明の書きやすさ。
