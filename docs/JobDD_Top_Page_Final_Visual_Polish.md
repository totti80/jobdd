# Top Page Final Visual Polish

参照：Master v5.5 / Decision Log v4.3 / AI Coding Rules / Top Page Final Revision / 提示された `docs/トップページ修正依頼.pdf` / 現在のトップ・`/jobs/start`。
依頼文の「(2).pdf」という表記に対し、実際に提示されたファイル名は上記。1ページを画像と抽出テキストで確認し、赤字は「左右の間隔を等間隔に」「大きく（3か所）」の2項目。PDF内の指定をレビュー資料として扱い、今回のユーザー依頼の範囲内で反映した。

Scope：トップ専用spacing・文字密度、既存ピクトグラム表示オプション、共通Job Cardの表示クラス、期待値更新。
Do Not Change：構成、入力・検索、業務ロジック、DB、Fit / Score / Ranking、公開境界、Compare、UserQuery保存、CSRF / Auth、route契約、企業・審査・Mail・Provenance・Fact・応募経路、本番設定。Carousel JSも変更しない。

## A. Verdict

**PASS。** PDF赤字2項目と人間レビューのVisual Polishを反映。既存構成・業務処理を維持し、全864テスト・3画面幅・DB不変を確認。

## B. Red Mark Review

| PDF赤字 | 対応・実測 |
|---|---|
| Hero左右の間隔を等間隔に | 既存1.1:1列比率を維持し、外側paddingと中央gapを共通tokenで調整。1440pxでは左右外側約48px・文字末尾から画像まで約54px、1200pxは外側・文字〜画像とも約40px。元の外側24pxに対し中央の見た目の空き約51〜70pxという偏りを縮小 |
| 3つの見方のピクトグラムを大きく | 共通`pictogram-feature`の既存large表示を3件に適用。tile 40→64px、SVG 20→32px（1.6倍）。6項目のアイコンとサイズ定義を共有 |

Heroの50/50固定は行わず、見出しの行数と存在感を維持。390pxは従来どおり縦積み。

## C. Human Review

- **Hero密度**：Mobileだけsection padding 40→32px、画像とのgap 28→20px、Lead・CTA・注記の余白を調整。内容を消さず、高さ890.8→838.8px（52px短縮）。PCのHero高は1440 / 1200とも不変、見出しサイズも43.92 / 36.6pxを維持。
- **Compact Form**：トップ専用variantでfield gap 24→20px、form内段間28→20px、見出し下・CTA上・Tool周辺余白を縮小。高さは1440 / 1200で824→766px、390で1286→1226px。入力・選択肢・ボタンの操作寸法は維持。`/jobs/start`のフォーム寸法は3サイズとも変更前後で一致。共有field / option / validation / POST markupは変更なし。
- **Job Card**：共通部品の専用クラスでCompanyを14px・600→13px・500へ。Titleは18px・3行を維持し、line-height 1.6→1.7、3行分の高さを確保。Metadataは14→13px・line-height 1.65。Companyは2行分、Titleは3行分の領域を揃え、既存flexと下端CTAを維持。card幅268px・gap16px・Carousel動作は変更なし。
- **Company CTA**：淡青bandを維持し、上下面borderをblue-200、paddingをPC64px / Mobile56pxへ。PC見出し32px、CTA最小高52px・左右24pxにし、軽い影を付けて終盤のまとまりを強調。濃紺背景・gradient・新規装飾は追加しない。

全9構成とHeader / Footerの6リンク・構造を維持。

## D. Changed Files

今回の開始時点からの増分は以下。前回までの未コミット差分とは区別する。

| File | 変更 |
|---|---|
| `resources/css/app.css` | 共通spacing token、Hero・トップフォーム・Job Card・Company CTAの密度調整 |
| `resources/views/public/home.blade.php` | 3つの見方へ既存largeオプション指定 |
| `resources/views/components/new-job-card.blade.php` | Company / Titleを共通の表示クラスへ |
| `tests/Feature/LandingPageTest.php` | 6項目と3つの見方のlarge指定をそれぞれ検査 |
| `docs/JobDD_Top_Page_Final_Visual_Polish.md` | 本報告 |

開始時のファイル控えとの比較で、Controller / Middleware / Service / Model / routes / schema / persistence / JS / 共有入力Partialは変更なし。依存・lockfile・本番設定変更なし。

## E. Responsive

1440 / 1200 / 390pxの実ブラウザ確認。変更前後のトップ・入力画面を計測し、全体と各sectionを撮影。Hero、3アイコン、フォーム、Job Card、Company CTA、Footerを目視確認。

**3サイズすべてPASS**。ページ全体横overflow・console error・pageerrorなし。既存フォームの入力・生成POST内容、Menu / Escape、Carouselの自動送り・hover / focus停止・停止／再開・ループ・全10件のTab移動・reduced motion・Mobile touch swipeを確認。通常DBへフォームPOSTは送らず、ブラウザ内で捕捉。

証跡：`/tmp/jobdd-top-polish/browser.py`、`browser.log`、`browser-results.json`。

計測証跡：`/tmp/jobdd-top-polish/before-metrics.json` / `after-metrics.json`。`/jobs/start`のフォームはPC高1297px、390px高1455pxで前後一致。

## F. Tests

開始baseline：前回実測 **864 PASS / 5,841 assertions**（`/tmp/jobdd-top-revision/full.log`）。
関連：**91 PASS / 779 assertions**（LandingPage / PublicNavigation / JobSearchEntry / JobUiV5）。

**最終Full suite：864 PASS / 5,842 assertions / 269.00秒。** 求職者・企業・管理者・比較・公開境界を含む全テストを既存コンテナ内の隔離testing DBで実行。

Pintと`git diff --check`を確認。ビルド成功。既存optional fontaine未導入の案内のみで、依存追加なし。
証跡：`/tmp/jobdd-top-polish/related.log`、`full.log`、`build.log`。

## G. DB Safety

ローカル`jobdd_v4`の全29テーブルをSELECTのみで前後照合し、**件数・全列SHA-256がすべて一致**。companies / job_postings / job_facts / snapshots / user_queries / routes / interaction_logs等の業務データ変更なし。sessionsは3→3、cache / cache_locksは0→0。今回観測されたsession/cache writeなし。

証跡：`/tmp/jobdd-top-polish/db-before.json` / `db-after.json`。実フォームPOSTはブラウザ内で捕捉、テスト保存は隔離testing DB。本番接続・schema変更・実メール送信なし。

## H. Screenshots

`/tmp/jobdd-top-polish/`に保存。

- `home-{1440,1200,390}.png`：最終トップ全体
- `home-title-{1440,1200,390}.png`：Hero
- `features-title-{1440,1200,390}.png`：6項目
- `views-title-{1440,1200,390}.png`：3つの見方
- `preview-title-{1440,1200,390}.png`：Before/After
- `form-{1440,1200,390}.png`：Compact Form
- `new-jobs-title-{1440,1200,390}.png`：Job Card
- `resources-title-{1440,1200,390}.png`：Useful Info
- `company-cta-title-{1440,1200,390}.png`：Company CTA / Footer
- `start-{1440,1200,390}.png`：入力画面
- `before-home-*` / `after-home-*`、`before-start-*` / `after-start-*`：前後比較
- `review-0.png`：今回PDFの参照画像

ブラウザ：Chromium。実機Safari・スクリーンリーダーを含む全端末監査ではない。

## I. Git

開始時cleanではなく、前回までの未コミット実装とユーザー追加PDFが存在。`/tmp/jobdd-top-polish/before/`に控えを取り保持。ユーザーPDFの内容変更なし。
commit / tag / push未実施。Gate 2 / deploy / 本番操作なし。とおる確認で停止。
