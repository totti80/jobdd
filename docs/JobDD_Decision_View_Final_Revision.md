# JobDD 求人詳細 / Decision View v2 Final Revision

参照：Master v5.5、Decision Log v4.3、AI Coding Rules、Batch 6 / 8 / 9、正式トップ・/jobs/start・最新求人一覧。

Scope：求人詳細表示、画像URLのnullable列1本、代表画像メタデータ抽出、保存済みデータによるbackfill、fallback、応募CTA、検証。
Do Not Change：Fit axes / Score / Ranking / candidate order / UserQuery / Compare / Published Snapshot生成 / Review / Company Authoring / Application Route判定 / Source semantics / Mail / Auth / Production / Gate 2 / deploy。

## A. Verdict

**PASS（ローカル実装・検証）**。実provider画像の利用許可確認はOPEN。未確認providerにはfallbackを表示する。

## B. Migration

`2026_09_28_000001_add_eyecatch_image_url_to_job_postings.php` 1本。
`job_postings.eyecatch_image_url` nullable TEXT、default null。既存値のUPDATEはない。status / review_status / Snapshotは変更しない。

## C. Image Extraction

- メイテック：既存 `fetch_job_detail` が取得したHTMLから抽出。追加HTTPなし。`og:image` → JobPosting / WebPageのJSON-LD `image` の順。配列、@graph、ImageObject、相対URLに対応。任意の本文画像やOrganizationロゴは採用しない。
- CareerJet：既存APIには代表画像の確定契約がないため、任意のimage/logoを画像と推定しない。ページCrawlerは追加しない。代表画像URLが明示された保存済みpayloadの `eyecatch_image_url` の受け口のみ用意し、未取得ならnull。
- `JOBDD_EYECATCH_APPROVED_PROVIDERS` は初期空。画像URL保存・hotlinkの利用条件を確認済みのproviderのみ、config経由で明示的に有効化する。現時点で実providerの画像利用許可を確認したとは扱わない。既存ProviderCapabilitiesの永続保存制限も維持する。
- HTTP(S)以外は既存 `JobDecisionPresenter::safeUrl` で拒否。画像抽出失敗は求人取得成功を妨げない。Daily Discoveryの既存Process境界から同じ許可configをPythonへ明示的に渡す。外部画像ファイルの複製保存なし。

## D. Existing Data

```
php artisan jobdd:backfill-eyecatch --provider=meitec_next --input=/path/to/saved.json --limit=10 --dry-run
```

保存済みImporter JSONのみを処理する。provider / limit指定、nullのみ、source URLとexternal ID一致、重複防止、Snapshot求人除外、条件付きUPDATE。更新前後null件数、候補数、更新数をJSON表示。外部アクセス0・retry 0であり、新しい取得を繰り返さない。再実行で処理を継続できる。更新は画像列だけでupdated_atも保持する。

local / testingだけで実行可能。本番ではコマンドが拒否する。本番backfill未実行。実データbackfill未実行。

## E. Fallback Illustration

単一 `JobEyecatchResolver` で、公開職種・タイトルから PLC / 制御 → 生産技術 / 設備 → 電気設計 → 機械設計 → 共通 / その他の順に判定。AI分類なし。

正式fallback assetは `public/images/jobdd/eyecatch/` の以下5枚（各1672×941）。Resolverが分類に対応するassetを返し、Bladeに分類条件を分散させない。

| asset | 用途 |
|---|---|
| machine.png | 機械設計 |
| electrical.png | 電気設計 |
| production.png | 生産技術 / 設備 |
| plc.png | PLC / 制御 |
| generic.png | 共通 / その他 |

引用元画像が利用可能（承認済みprovider・安全なHTTP(S) URL・load成功）なら外部画像を優先し、「画像出典：掲載元求人ページ」と表示する。外部画像なし / provider未承認 / load失敗の場合は職種別fallbackを表示し、「JobDDイメージ画像」と明示する。実在企業の職場・社員写真として扱わない。Self-serviceのPublished Snapshot境界はK節のとおり維持する。

## F. Hero

正式デザインの色・角丸・button・最大幅を使用。PCは左に会社名・大見出し・基本条件・Actions、右に4:3画像。900px未満は会社名→title→条件→画像→Actions。画像はcontainで内容の切断を避け、固定枠でlayout shiftを防ぐ。

JS有効時もfallbackを先に表示し、外部画像load成功後のみ切り替える。失敗時はfallbackと注記を維持。JS無効時もfallbackとnative応募リンクを表示する。

## G. Fit Section

Hero下に一覧と同じcompact希望条件。「あなたの希望条件との確認」に既存件数・各axisのstatus / reason / 折りたたみEvidenceを表示。計算処理は変更しない。

## H. Job Information

「この仕事について分かること」をLegacy / Snapshot共通見出しにする。設計対象、担当工程、CAD / Tool、関係者、Typical Dayを表示。Legacyは既存Fact categoryと文脈を表示し、欠損は未確認。技術名だけで本人担当と断定しない。Snapshotは既存公開PresenterからTool使用と経験要件、Timelineを表示する。

## I. Evidence / Provenance

Legacy技術・工程の全詳細は折りたたみに残す。Snapshotの追加Level 2詳細・詳細希望比較も折りたたみに保持する。根拠と掲載元では提供元・取得日時・Provenanceを表示し、保存本文と長い根拠はdetailsへ。詳細画面の掲載元URLは短いラベルにし、比較画面等の既存表示は維持。

## J. Application CTA

従来の通常button / 応募方法画面への導線から、詳細画面内の大きい青CTAへ。PCは経路カード幅の75%、mobileは100%。通常表示はavailable経路だけとし、未確認・利用不可の保存記録は補足・日時を含め折りたたみに残す。安全URLかつavailable経路だけ外部リンク化。`target=_blank` / `rel=noopener noreferrer` を維持。新規タブと最新掲載内容確認の補足を分離。

既存route-selected / contact-clicked APIへkeepaliveで各1回送信。nativeリンクだけが遷移を担当し、window.openや同一tab遷移を重ねない。ログ失敗でもリンクを妨げない。route_openedは従来の応募方法画面GETで記録する契約のまま。

## K. Self-service Boundary

既存PublishedJobDecisionPresenterと公開projectionを継続使用。Authoring relationを追加で読まない。Self-serviceの画像は編集可能な新列から取得せずfallbackとし、Snapshot生成を変更しない。再審査中の旧Snapshot維持を回帰確認する。

## L. Legacy Fallback

JobPosting / JobFacts / Evidenceの既存値だけを利用。設計専門分野の単語から設計対象を推定しない。担当文脈・検証状態・Evidence全文は詳細内に残す。

## M. Components / Changed Files

- migration / model：画像URL列とfillable。
- `app/Support/JobEyecatchResolver.php`：provider許可・URL検証・5カテゴリ判定。
- `app/Support/LegacyJobInformation.php`：Legacy共通枠への表示投影。
- `crawler/eyecatch.py` / `fetch_meitec_next_jobs.py`：既存HTMLからの任意メタデータ抽出。
- `DiscoveryProviderAdapter` / `DailyDiscoveryImporter` / `ImportMeitecNextJobs` / `ImportCareerjetJob`：安全な画像URLだけ保存。
- `BackfillEyecatch` / `config/eyecatch.php`：オフラインbackfillと利用確認済みprovider設定。
- `query/job-show*` / `detail-hero` / `detail-application` / `published-section-content` / `provenance`：詳細UIと既存表示の再利用。
- `resources/js/job-detail.js` / `app.js` / `resources/css/app.css`：画像状態、応募ログ、responsive styling。
- `JobEyecatchTest` / `crawler/tests/test_eyecatch.py`：新機能の検証。既存5テストファイルは指定された新見出し・リンク文言へ更新。

## N. Tests

- 関連6ファイル：171 PASS / 1,253 assertions（backfill継続テスト追加前）。
- Python：画像抽出・Meitec既存取得フロー・Daily / CareerJet / company URL evidenceの21テストPASS。HTTPはfixture/mock。実API疎通スクリプトは対象外。
- 初回全体：884 PASS / 1 FAIL。旧「一覧で比較する求人を選ぶ」の期待値を、今回の「比較に追加」へ更新。遷移先・公開境界・ログ等のassertionは維持。更新後の関連テストは全PASS。
- 2回目全体：885 PASS / 1 FAIL。利用不可の経路を折りたたみに整理した際の個別の長い補足detailsを復元。既存のDOM検証を弱めず修正。
- Eyecatch最終変更後のcommit前Full suite：**886 PASS / 6,202 assertions / 309.74秒**。`docker compose exec -T laravel.test php artisan test --compact`で実行。前回基準886 tests / 6,184 assertionsから18 assertions増加、失敗なし。
- Eyecatch / PublishedJobDecisionView関連：32 PASS / 264 assertions。
- build PASS。ホスト側の既存生成ファイル所有権により通常buildが失敗したため、従来どおりDocker内で実施。既存optional fontaine案内のみ、依存追加なし。
- Pint（変更PHP 16ファイル）/ git diff --check PASS。
- ホストPHPにはMySQL driverがないため、Feature TestはDocker内のtesting DBで実施。
- 初期Bladeのcompile errorは修正済み。ブラウザfixtureのLivewire配信先も実資産へ補正し、最終検証にconsole errorなし。

証跡：`/tmp/jobdd-detail-related-final.log`、`/tmp/jobdd-detail-full-verified.log`、`/tmp/jobdd-detail-python.log`、`/tmp/jobdd-detail-artifacts/browser-results.json`。

## O. DB Safety

migration適用前の検証では、通常ローカルDB全29テーブルの件数・全列SHA-256が前後一致。適用後はmigration履歴を除く28テーブルで既存全列が一致（job_postingsは新列だけ除外）。求人1,591件を保持。証跡は `db-before.json` / `db-after.json`（適用前）/ `db-after-migration.json` / `db-final.json`（新列を除いた既存全列の照合、実データpreview後も一致）。testingでの確認後、依頼で許可されたmigration1本だけを通常ローカルjobdd_v4へ適用。全1,591件で新列null、既存全列ハッシュ一致。migration履歴のみ37→38件。実DBのbackfillは未実行。

本番接続・本番backfill・Gate 2・deployなし。検証fixtureの保存はtesting DBのみ。通常ローカルでは、未保存UserQueryとread-only transactionで既存Legacy求人1件のBlade表示も検証。業務データのUPDATEなし。

## P. Browser Screenshots

正式5枚接続後は1440 / 390pxで外部成功、load失敗、URLなし、provider未承認、Snapshotの計10ケースとJS無効390pxを確認。全ケースPASS、overflow / console errorなし。職種別fallbackの読込とattribution切替を確認。最新成果物は `/tmp/jobdd-eyecatch-final/`（`legacy-{success,broken}-{1440,390}.png`、`snapshot-fallback-{1440,390}.png`、`null-image-fallback-{1440,390}.png`、`unapproved-fallback-{1440,390}.png`、`browser-results.json`）。

以下はDecision View全体の検証記録。

Chromium 153でLegacy（画像成功 / 破損）とSnapshot（fallback）の各1440 / 1200 / 390px、計9状態＋390px JS無効を検証。全状態で横overflowなし、JS / console errorなし。閉じた詳細と全details展開後の横幅を確認。各CTAでPOST2件（既存event各1回）、CSRF header、新規tab1枚、元ページ維持を確認。実ログ保存はFeature Test。

成果物：`/tmp/jobdd-detail-artifacts/`。

- `legacy-{success,broken}-{1440,1200,390}.png`
- `snapshot-fallback-{1440,1200,390}.png`
- 同名の `-cta.png`：応募セクション
- `legacy-no-js-390.png`
- `legacy-real-fallback-{1440,1200,390}.png`：通常ローカルの公開対象Meitec求人1件。read-only transaction・未保存Query・メモリsessionで描画。3幅ともoverflow / JS errorなし、CTAはmockで各ログ1回・新規tab1枚。実providerへの遷移なし。

testing DBの正規route / Bladeから生成したHTMLに、実build CSS / JSを組み合わせて検証。外部画像と外部応募先はbrowser内でmockし、実提供元へアクセスしない。

## Q. Remaining Risks

- 実providerの画像保存・hotlink利用条件はOPEN。未確認providerはfallbackを使う。
- CareerJetの代表画像契約はOPEN。新しい独立Crawlerを追加しない。
- 正式PNGは各約1.7〜1.9MB。低速回線では読み込み時間がかかるが、固定枠でlayout shiftを防ぐ。
- Chromiumによる検証であり、実機Safari・全screen reader監査ではない。

## R. Git

commit前確認時は既存の未commit差分を保持。今回の確認では本書のみ更新し、コード・UI・DB変更なし（テストfixtureはtesting DB）。Full suite PASS、git diff --check PASS、git status確認済み。commit / tag / push / deploy / Gate 2なし。とおる確認後にcommitする。
