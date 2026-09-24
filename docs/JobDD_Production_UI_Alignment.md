# Production UI Alignment 実装報告

参照：JobDD_Master v5.5 / Decision Log v4.3、AI Coding Rules、V5 PC・スマホPDF、Batch 6〜9実装報告、Production Readiness。
開始：`new-jobdd-v4` / `e78b298`、作業ツリーclean。

Scope：Public共通UI、正式トップ、簡易入力の表示、新着求人、既存検索状態への入口、回帰・ブラウザ検証。
Do Not Change：schema / migration、Fit / Score / Ranking、公開投影・Snapshot・審査・Mail、企業Authoring、Fact / Provenance生成、応募経路ロジック、ログ契約、認証設計、Crawler / Importer。本番・Gate 2・commit / tag / pushは実行しない。

## A. Verdict

**REVISE（read-only検証条件に例外）。UI実装・機能・表示検証はPASS。** ローカルsessionsが2件増加したため、依頼全体を無条件PASSとはしない。詳細はN。

## B. Changed Files

- `app/Http/Controllers/PublicPageController.php`：公開トップ取得、詳細条件・比較・企業・新着求人の入口。
- `app/Http/Controllers/JobSearchController.php`：GETで公開求人名の案内を取得。POSTの検証・保存・遷移は不変。
- `app/Http/Middleware/JobDecisionSession.php`：既存の読取専用session経路にhome / public.*を追加。新しいcookieや状態保存は追加しない。
- `routes/web.php`：homeの表示先と6つのGET入口を追加。旧`/query`を含め既存routeは保持。
- `resources/views/components/{site-header,site-footer,public-layout,page-hero,new-job-card,coming-soon-page}.blade.php`：共通部品。
- `resources/views/public/{home,coming-soon,company,compare}.blade.php`：新規Publicページ。
- `resources/views/query/{start,jobs,job-show,job-show-v2,job-compare,preferences,agencies,input-error,create,results}.blade.php`、`query/partials/selection-header.blade.php`：共通chrome・簡易入力表示。既存本文・フォーム契約を維持。
- `resources/views/jobs/provenance.blade.php`、`resources/views/routes/{show,action}.blade.php`：共通chrome。応募経路・ログ処理は不変。
- `resources/css/app.css`：Public用の共有クラス。
- `resources/js/public-navigation.js`、`resources/js/app.js`：native detailsの画面幅追従・Escapeキー。
- `resources/js/jobdd-ui.js`：既存compare storageの読取関数を再利用した比較入口。
- `tests/Feature/{PublicNavigationTest,LandingPageTest}.php`：新規20テスト。
- `tests/Feature/{AgencyDecisionPageTest,JobSearchEntryTest,JobUiV5Test}.php`：意図したUI変更に表示期待値を更新。Evidenceのdetails数・初期開閉状態はmain内に限定。フォーム・アクセシビリティ・判定の検証は維持。
- 本報告。

## C. Public Layout

Header / Footerは単一Blade component。新規ページとProvenanceは`public-layout`を利用。既存求職者画面は本文全体を書き換えず、Header / Footerだけ共通化。selection-headerは共通Headerに続くページ見出し・希望条件の表示を担当する。

## D. Navigation

| Menu | Route / fallback |
|---|---|
| トップ | `/` |
| かんたん入力 | `/jobs/start` |
| 詳細条件 | `/preferences` → 認可済みqueryの既存preferences。なければ入力＋案内 |
| 新着求人 | `/#new-jobs` |
| 求人を比較 | `/compare` → 既存query別sessionStorageに2〜3件あれば比較リンク。選択を既存Selectionで検証後、既存Compareへ。なければ一覧、queryなしなら入力 |
| お役立ち情報 | `/resources`、準備中 |
| 企業向け | `/for-companies` → guestは登録、owner/editorはDashboard、企業未所属Platform Ownerは既存審査一覧。通常未所属Userはアカウント確認の案内 |
| お問い合わせ | `/contact`、準備中 |

求職者画面では現在のpublic_id・page・toolsを引き継ぐ。他画面では既存sessionの`jobdd_query_token_{public_id}`を読み、tokenを照合できた最新の対応queryを使う。明示されたqueryが他人・不正なら別queryへすり替えずfallbackする。新しいcurrent-query状態、cookie、DB列は作らない。tokenはURLに出さない。

Compareは`jobdd:compare:{public_id}`の形式・既存validator・最大3件を維持。読取のみで、ナビのための保存や選択解除をしない。破損storage、storage不可、未公開・削除された求人は一覧から再選択できる。候補チェックに既存`JobSelectionUseCaseService`をそのまま再利用し、Fitや候補境界を複製しない。

## E. Landing Page

Hero → できること4項目 → Decision Support説明 → 新着6件 → Company CTA → Footer。
コピーは「仕事の中身を知って、比べて、自分で選ぶ。」。推薦・最適解・No.1を示さず、未確認と不適合の違いを説明する。トップの旧入力フォームは廃止し、入力は`/jobs/start`に集約。

## F. New Jobs

`JobPosting::forPublic()`＋`status=published`＋`unavailable_at IS NULL`、6件、会社relationをまとめて取得。未対応Snapshot除外、承認済み会社名・Level 1値は既存投影を使用。未承認のAuthoringを補完に使わない。

順序：`COALESCE(Snapshot.published_at, job_postings.published_at, job_postings.created_at) DESC`、同日時はid DESC。編集updated_atで新着扱いにしない。Self-serviceとLegacyは同じ日時軸で並び、Self-serviceを優先しない。

詳細入口`/new-jobs/{id}`では既存queryと候補境界に適合すれば既存詳細へ進む。それ以外は、公開求人名を示す案内付きの簡易入力へ。検索前専用の新しい詳細画面は作らない（とおる承認済み）。求人名はGET時に公開投影から再取得し、タイトルをURL・sessionへ保存しない。職種や公開状況によって検索一覧に出ない場合があることを案内する。

## G. Coming Soon

resources / contactは同じ`coming-soon-page`を使用。入力へのCTAとトップへの戻り先を用意。問い合わせフォーム、メール公開・送信、DB・CMSは追加しない。

## H. `/jobs/start`

「まずは4つの条件から求人を見てみる」「詳しい条件は、求人を見たあとから追加できます。」へ更新。右側に5段階の使い方を表示し、確認できた／異なる／未確認、Evidence・比較・地図の説明を維持。職種・地域・年収・7ツール・自由記述の項目、POST保存、validation、CSRFは維持。

## I. Common Components

Header / Footer / Public Layout / Page Hero / New Job Card / Coming Soonの6つを追加。リンク・CTA・section heading・カード・情報案内は共有CSSまたは既存`jobdd-card` / `jobdd-button` / `jobdd-decision-note`を使用。既存company-name・アイコン・Fit・Evidence部品を再利用。アプリ依存追加なし。

## J. Responsive

V5 PDFの企業画面を参照し、白いカード、淡い青背景、青い操作、見出しと余白、PCの主従2列／スマホの縦積みをPublic UIへ適用。ナビ項目はPDFの企業専用メニューではなく、今回指定されたPublic8項目を採用した。

PC1440pxは横並びナビ。1200px未満はnative detailsメニュー。390pxはカードと右カラムを縦積み。比較表の既存内部スクロールを維持する。

Chromium実ブラウザで、通常の`http://localhost:8081`のトップ・簡易入力・resources・contactをPC/390pxの8画面で確認。新着anchor・求人名付き入力案内・状態なし導線・mobile menu開閉／Escapeも確認。

企業登録・Dashboard・Admin・求職者一覧・v2詳細・Legacy詳細・詳細希望・比較・Provenance・応募方法の10画面×2サイズ＝20画面は、testing DB専用HTTPアプリをブラウザ内の8081へ接続して確認。静的HTMLモックではなく、既存Controller / middleware / Blade / assetsを実行した。実DBにUser・Company・求人・検索条件を作らないための隔離であり、通常8081で認証付き実データの保存E2Eをしたという意味ではない。

検証ブリッジは302先を新規ブラウザナビへ変換して隔離HTTPに閉じる。HTTP 302の正しいLocationと認可はFeature Testでも検証。最終28画面でページ全体横overflow・JS console / pageerrorなし。比較選択→共通ナビ→既存Compare、tools保持、破損storage、削除済み相当のIDの再選択案内をPC/390pxで確認。スクリーンショットは`/tmp/jobdd-ui-captures/`。企業登録・企業／Admin画面の既存専用レイアウトは維持。

## K. Accessibility / SEO

nav landmark、現在地aria-current、ロゴalt、native summary、Escapeで閉じてsummaryへfocus復帰、focus-visible、各入力label、単一h1を維持。メニューはJavaScriptなしでも操作できる。トップにtitle・meta descriptionを設定。他の検索画面のnoindex / no-referrerを維持。

## L. Regression

Seeker / Company / Admin、Fit・Ranking、公開境界、比較、応募経路の既存Feature Testを含むfull suiteで検証。HTTPブラウザで保存や公開承認を行わず、認証画面はtesting fixtureだけを使用する。

## M. Tests

| 検証 | 結果 |
|---|---|
| 初回関連（既存フロー6種） | 122 PASS / 1,321 assertions |
| 新規Public / Landing | 20 PASS / 149 assertions |
| 最終関連（Public / Landing / 既存UI・入力・Agency） | **113 PASS / 811 assertions** |
| 最終full | **852 PASS / 5,708 assertions / 116.61秒** |
| Pint | PASS、変更PHP 9ファイル |
| npm run build | PASS |
| git diff --check | PASS |
| 実ブラウザ | 最終28画面・PC1440/390px PASS（J参照） |

初回fullは848 PASS / 4 FAIL。旧Hero文言・画像、Header内に追加されたdetailsを含む件数の期待値が原因。新UIへ期待値を更新し、Evidence数と開閉状態の検査はmainに限定した。保存・Fit・公開境界の検査を削除していない。

Laravelログでは、意図したtransaction失敗テストの`simulated row failure`と、検証用一時スクリプトのsession形式を調べる際の`unserialize`エラーを確認。後者はJSON形式へ修正済み。アプリに未解決の500はない。ログ削除なし。

PDF変換・ブラウザ確認ツールは/tmpの独立環境のみ。composer / npm依存とlockfileは変更していない。

証跡：`/tmp/jobdd-ui-related.log`、`jobdd-ui-new-tests.log`、`jobdd-ui-related-final.log`、`jobdd-ui-full-final.log`、`jobdd-ui-build-final.log`、`jobdd-ui-pint-final.log`、`jobdd-ui-browser.log`、`jobdd-ui-auth-browser.log`、`jobdd-ui-before.json`、`jobdd-ui-after.json`、`jobdd-ui-after-final.json`。

## N. DB Safety

schema / migration変更なし。新しいPublic入口のGETは既存session読取専用経路を使用し、書込SQLがないことをFeature Testで確認。ブラウザ入力送信は実DBへ実施しない。

ローカル実DB `jobdd_v4` の全29テーブルを作業前後で読取照合。業務データを含む28テーブルの全列hash・件数は同一。**sessionsだけ31→33件となった。** 初期のブラウザ検証ブリッジで、認証fixture形式の不一致によりログインへ302となり、そのリダイレクト先が通常8081へ抜けた影響とみられる。JSON session fixtureと、全遷移を隔離HTTPへ閉じるブリッジに修正した。アプリの認証を緩める変更はしていない。

最終再照合でもsessions以外は不変で、31→33件の検出後に追加変化はない。この2件は「実DB書込みなし」とは報告できない。追加の実DB書込み・削除は行わず、事実を残す。schema・求人・Company・UserQuery・Fact・公開版・業務ログは不変。本番接続・設定変更・メール配送なし。Gate 2へ進んでいない。

## O. Remaining Risks / OPEN

- ローカルsessionsが2件増加した検証上の例外がある（N参照）。read-only smoke要件を完全には満たせなかった。
- 本番Public Releaseは引き続きNO-GO。UIの完了はbackup / restore rehearsal、通知配送、Owner、実在Company / 求人、Production Smokeの承認を代替しない。
- 新着から選んだ求人は検索条件を入力するための案内であり、新しい継続状態として保存しない。検索後の既存一覧から確認する。
- sessionが失効した場合は簡易入力からやり直す。比較対象は既存仕様どおりタブ別sessionStorage。
- 実機Safari・スクリーンリーダーを含む全端末監査ではない。

## P. Git

commit / tag / push未実施。変更はとおるのレビュー待ち。Gate 2・deployは実行しない。

## Q. Verdict

**REVISE。** Production UI Alignmentの実装・28画面の表示・全852テストはPASS。ローカル実DBのsessions書込み例外を残しているため、read-only条件まで含めた無条件PASSにはしない。とおるのレビュー前にcommit、本番操作、Gate 2へ進まない。
