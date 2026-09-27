# Top Page Final Implementation

参照：JobDD_Master v5.5 / Decision Log v4.3 / AI Coding Rules / Production UI Alignment / V5 PC・Mobile PDF（各7ページ）/ 既存 `/jobs/start`。

Scope：トップのBlade、共通Header / Footer、関連CSS / JS、表示件数、テスト。
Do Not Change：schema / migration、Fit / Score / Ranking、UserQuery保存、Compare、公開境界 / Snapshot / Controlled Publish / Review、Company Authoring、Mail、Provenance / Fact、応募経路、Crawler / Importer、Auth、本番設定。
完了条件：指定構成、公開境界維持、全テストPASS、1440 / 1200 / 390pxの実ブラウザ検証、DB照合。

## A. Verdict

**PASS。** 指定構成、公開境界の維持、全854テスト、3画面幅のブラウザ確認を完了。ローカルsession 1件増加を業務データと分けて記録（P参照）。

## B. Changed Files

| File | 変更 |
|---|---|
| `app/Http/Controllers/PublicPageController.php` | 新着上限を6→10件。取得境界・並び順・投影は維持 |
| `resources/views/public/home.blade.php` | 正式構成、Hero、Before/After、短いNote、カルーセル操作 |
| `resources/views/components/site-header.blade.php` | ロゴ横のタグライン |
| `resources/views/components/site-footer.blade.php` | 指定タグライン |
| `resources/views/components/pictogram-feature.blade.php` | 6項目と3つの見方で9回再利用する小さな部品 |
| `resources/views/components/resource-teaser.blade.php` | 既存アイコン付きのコンパクトな非リンク表示 |
| `resources/css/app.css` | `/jobs/start`の既存token・button・icon・note再利用、旧トップCSS整理、レスポンシブ |
| `resources/js/public-navigation.js` | 横並びナビの境界を1024pxへ |
| `resources/js/homepage-jobs.js` | 自動送り、停止、ループ、手動・キーボード操作、reduced motion |
| `tests/Feature/LandingPageTest.php` | 公開10件・非公開除外・構成・削除・DOMの確認 |
| `tests/Feature/PublicNavigationTest.php` | 6導線、タグライン、現在地の確認 |
| `tests/Feature/JobUiV5Test.php` | 共通Footerの文言期待値のみ更新 |
| `docs/JobDD_Top_Page_Final_Implementation.md` | 本報告 |

## C. Removed Sections

- 「仕事を探す」4項目
- Pickup
- 大きな4-step Decision Support
- 求人A / Bの重複サンプル、Heroの「説明用サンプル」ラベル

`pickup-preview.blade.php` は他画面にも呼出しがなく、今後の不要コード削除候補。今回トップからの呼出しと専用CSSを除去し、将来のEditorial / Pickupの意図を記した既存部品は保留した。将来もFit・検索順・Evidenceとは独立させ、有償制作の明示を伴うという構想を消していない。

## D. Hero

指定の「求人を探すだけでは、わからない。仕事の中身まで比べて、選ぶ。」、対象badge、3状態の整理、卒業制作版の対象範囲を表示。スマホは語句のまとまりで改行。
既存 `jobdd-hero-kinki.png` を再利用し、工程・Tool・情報源・未確認を載せた小さなUIカードを1枚だけ重ねる。新規画像なし。
主CTAは `/jobs/start`、副CTAは既存の企業向け入口。

## E. Header / Footer

既存の共通Bladeを維持。トップ／かんたん入力／詳細条件／お役立ち情報／企業向け／お問い合わせの6項目。Headerに新着・比較を追加しない。PC横並び、Mobileはnative detailsのMenu。Escapeで閉じてsummaryへfocus復帰。Footerは「根拠とともに、仕事を選ぶ。」とCopyright。

## F. 6 Features

設計対象、担当工程、CAD / Tool、関係者、Typical Day、Evidence。既存青ピクトグラム＋見出し＋1行説明。PCは3列×2段、390pxは2列。枠線カードではなく上辺の軽い区切り。

## G. 3 Views

根拠を確認／同じ軸で比較／地図で見る。PC3列、Mobile縦積み。代表地点は実際の勤務地ではない旨も既存思想に合わせて保持。

## H. Before / After

求人票の「機械設計業務」から、設計対象・工程・Tool・顧客・製造・Typical Day・Evidenceの7項目へ。求人A/B表ではなく`dl`を使用。PC左右、Mobile縦積み。

## I. Decision Support Note

Before/After直後に既存 `jobdd-decision-note` の淡いGreenを使用。「選ぶのは、あなた。」「未確認」は合わない意味ではないことを短く説明。

## J. New Jobs Carousel

`forPublic()`、published、募集終了除外、対応Snapshotのみという既存取得を維持。`COALESCE(Snapshot.published_at, published_at, created_at)` DESC＋id DESC、最大10件。Self-service優遇なし。

vanilla JSのrequestAnimationFrameで右から左へ約25px/秒。1周分の視覚用コピーを追加し、境界で同じ見え方の位置へ戻す。コピーは`aria-hidden`・tabindex=-1で、Tabは元の10件だけを辿る。focus中は位置を巻き戻さない。手動scroll / touch / swipe、前後ボタン、Arrow / Home / Endを維持。

hover / focus-within / pointer操作中は停止。ユーザー停止ボタンを追加。操作直後は2.5秒待って再開。reduced motionでは自動処理とコピーを取り除き、手動操作を維持。JSなしでもnative横scrollと求人リンクを利用可能。外部ライブラリなし。

Company・Title・Location・Salary・Occupation・詳細を見るを維持。長いTitleは3行、Companyは2行で揃え、詳細リンクのアクセシブル名には求人名全文を残す。

## K. Useful Info

CAD・設計職／転職ノウハウ／求人の読み方の3項目。準備中、非リンク。CMSや架空記事は追加しない。

## L. Company CTA

淡青band＋既存青CTA。濃紺全面背景を除去。既存企業向け入口の認証・振り分けは変更しない。

## M. Common Components

既存Public Layout / Header / Footer / Job Card / Resource Teaser / JobDD Iconを再利用。小さなPictogram Featureを追加。button・radius・border・青・icon tile・info noteは既存の共通CSSを利用。トップ専用の大きなsectionは部品化しない。依存・lockfile変更なし。

## N. Responsive / Accessibility

Chromium実ブラウザで通常の `http://localhost:8081/` と `/jobs/start` を1440 / 1200 / 390pxで確認。各画面のページ全体横overflowなし、console error / pageerrorなし。Mobile Menu開閉・Escape復帰、PC横並びを確認。

カルーセル表示量は1440px：約4.76枚、1200px：約4.06枚、390px：約1.26枚。3サイズすべてで自動送り、focus停止、停止／再開ボタン、ループ境界、元の10件を辿るTab、Arrow / Home、reduced motion OFF＋手動操作を確認。PCのhover停止、390pxのCDP touchによるスワイプも確認。

nav landmark / aria-current / 単一h1 / 見出し階層 / img alt / 装飾SVGのaria-hidden / focus-visibleを維持。状態は色だけでなく文言で示す。画像・Before/After・6ピクトグラム・新着・情報・企業CTA・Footerのスクリーンショットを目視確認。

ブラウザ検証コードは `/tmp/jobdd-top-final-browser.py`、結果は `/tmp/jobdd-top-final/browser-results.json`。検証用Chromium・ライブラリ・日本語フォントは/tmpのみ。アプリ依存には追加しない。

## O. Tests

変更前baseline：**853 PASS / 5,749 assertions**（`/tmp/jobdd-top-baseline-clean.log`）。
ホストPHPはMySQL driver未導入のため、テストは既存Laravelコンテナ＋隔離`testing` DBで実施。
最初のbaseline試行は編集中のViewを読んだため4件失敗した。実装を/tmpへ退避して開始時のtracked filesへ戻し、上記baselineを取り直した。

| 検証 | 結果 |
|---|---|
| 関連：Landing / PublicNavigation / JobUiV5 | 30 PASS / 392 assertions（Hero改行修正前） |
| 最終full：Seeker / Company / Admin / JobComparison / PhaseAHardeningを含む | **854 PASS / 5,784 assertions / 262.33秒** |
| Pint：変更PHP 4ファイル | PASS |
| npm run build | PASS |
| git diff --check | PASS |
| Chromium：1440 / 1200 / 390、トップ＋入力画面 | PASS |

最終fullは、Hero改行変更後の期待値を含めて実行。途中のfullは変更前の期待値をロード済みだったため1件失敗し、コードを固定して取り直した。公開境界・比較・保存・Fitの検査を弱めていない。

証跡：`/tmp/jobdd-top-related.log`、`/tmp/jobdd-top-full-final.log`、`/tmp/jobdd-top-build.log`、`/tmp/jobdd-top-browser.log`。ビルド時に既存のoptional fontaine未導入の案内が出るが、ビルドは成功。追加依存なし。

## P. DB Safety

ローカル `jobdd_v4` の全29テーブルをSELECTのみで前後照合。**sessions以外の28テーブルは件数・全列SHA-256一致**。companies / job_postings / facts / snapshots / user_queries / routes / interaction_logs等の業務データに観測された変更なし。cache / cache_locksも0件のまま。

**sessionsは2→3件**。増加した記録の最終活動時刻は11:59:06 UTC、前画面パスは`/company/register`、HeadlessChrome以外のブラウザ。これは今回のChromium検証開始前で、当方のブラウザ検証による書込みと断定できない。作業時間帯のsession増加として記録し、削除していない。

照合証跡：`/tmp/jobdd-top-db-before.json`、`/tmp/jobdd-top-db-after.json`、`/tmp/jobdd-top-db-final.json`。最終再照合でも追加変化なし。トークン・認証情報・session payloadは報告に出していない。最初の一時session監査スクリプトは保存形式をunserializeと誤認してエラーになり、JSON読取へ修正した。監査はSELECTのみ、アプリ実装の変更なし。

Laravelログは、テストで意図的に発生させる`simulated row failure`と上記一時監査の形式エラーを確認。今回確認した画面の未解決500なし。テストの書込みは隔離testing DB、通常8081でフォーム送信・公開操作なし。

## Q. Browser Screenshots

保存先：`/tmp/jobdd-top-final/`。
- `home-1440.png` / `home-1200.png` / `home-390.png`：トップ全体
- `home-title-{1440,1200,390}.png`：Hero
- `features-title-{1440,1200,390}.png`：6項目
- `preview-title-{1440,1200,390}.png`：Before/After
- `new-jobs-title-{1440,1200,390}.png`：カルーセル
- `resources-title-{1440,1200,390}.png`：お役立ち情報
- `company-cta-title-{1440,1200,390}.png`：企業CTA・Footer
- `menu-390.png`：Mobile Menu
- `start-{1440,1200,390}.png`：共通Header / Footer反映後の入力画面
- `start-parent.png` / `home-before.png`：開始時のデザイン参照

## R. Remaining Risks

- Chromiumによる実ブラウザ確認。実機Safari・VoiceOver等の全端末監査ではない。
- UIの完了はProduction Releaseの判定を代替しない。
- 新着詳細への導線は従来どおり。検索条件未入力なら条件入力へ案内する。
- `pickup-preview.blade.php` は未使用の整理候補として残した（C参照）。

## S. Git

開始時clean。commit / tag / push未実施。Gate 2 / deploy / 本番接続 / メール送信なし。ここで停止し、とおるの確認を待つ。
