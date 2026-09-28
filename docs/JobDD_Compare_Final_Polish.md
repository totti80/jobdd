# JobDD 求人比較画面 Final Polish

参照：Master v5.5 / Decision Log v4.3 / AI Coding Rules / 現行比較画面 / 求人一覧Sorting / 求人詳細Decision View v2。

指定PDF「選んだ求人を比較 _ JobDD.pdf」は添付領域に見当たらず未照合。確認を依頼し、依頼本文と現行画面を基準として実装・ローカル検証する。

Scope：比較画面の求人ヘッダー、上部希望条件summary、未確認表示の視覚調整のみ。大枠の構造・行項目・列幅・2〜3件比較・選択順・sessionStorage・Fit・Sorting・Evidence・Published Snapshot・Application Route・Auth・DB仕様は変更しない。

## A. Verdict

**PASS（実装・ローカル検証）**。指定PDFとの照合は未実施。

## B. Sticky Header

現行コードに既にある`thead th`のsticky（top:0）を維持し、単一のtheadへ`jobdd-comparison-header`を付与。1pxの控えめな下端shadowを追加し、比較表内でstacking contextを分離した。ヘッダーの複製・JSによる位置同期は追加しない。

既存の不透明blue-50背景、求人ヘッダーz-index 20、比較項目列10、左上交差セル30を維持。会社名・求人タイトル・詳細リンクは同じセル内に保持する。既存max-height 70svh、表内縦横scroll、列幅、scroll-padding、ResizeObserverによるヘッダー高さ測定を変更しない。

## C. Compact Summary

共有selection-headerのopt-in指定を比較画面だけで有効化し、一覧の`condition-summary` compact variantを再利用。職種・希望地域・希望年収・選択Toolを残し、「あなたの希望」を添える。上下paddingとspacingを小さくして比較表までの距離を短縮する。

「条件を変更」「詳細条件」「求人一覧へ戻る」を表示。既存のpage / tools / sortを引き継ぐ。ほかのselection-header利用画面は従来表示を維持。大きな条件カードは追加しない。

## D. UNKNOWN Visual Tone

比較表の未確認テキスト・badge・未確認件数だけに、`#64748b`（slate-500）とfont-weight 400を適用。badge背景は`#f8fafc`、ringは`#e2e8f0`。既存utility指定より優先させるため、比較表に限定したCSSをlayer外に置く。共有Partialには表示用data属性だけを追加し、一覧・詳細の配色や太さは変更しない。

未確認の文言・理由・根拠・項目数はそのまま。非表示・低評価への読み替えなし。MATCH / MISMATCHの表示は変更しない。

## E. Responsive / F. Compare Contract

1440 / 1200 / 390px、2件と3件の両方で確認する。PCとMobileの既存列幅を維持し、390pxでは横scrollを使用。比較項目列と求人列を同じtableで描画するため列ずれを作らない。

Compare関連Service / Controller / JS / sessionStorageには変更なし。最大3件・入力順・比較対象の追加解除・行項目・詳細リンク・勝者を決めない説明・UNKNOWN != MISMATCHを維持。

## G. Tests

- JobComparisonV2 / JobDetailCompare / JobUiV5：**64 PASS / 620 assertions**。
- 最終buildでの2件 / 3件構造検査：2 PASS / 54 assertions。
- 追加検査：2件 / 3件の選択順、theadが1つ、各列1つの詳細リンク、compact summary、未確認badge / 件数、既存比較行・文言、page / sort URL。
- 既存回帰：Legacy / Snapshot混在、編集内容の非公開境界、既存Fit結果、認可、SQL読取専用。
- build PASS。既存optional fontaine案内のみ。依存追加なし。
- Pint / git diff --check PASS。
- 小規模UI変更のためFull suiteは今回再実行しない。

## H. Browser Verification

Chromiumで正規routeが生成したtesting fixture HTMLと実buildを使用。外部への通信はブラウザー内でfixtureへ置換。通常DBへ検証求人・UserQueryを書き込まない。

成果物：`/tmp/jobdd-compare-polish/`。

- `compare-{2,3}-{1440,1200,390}.png`：全画面・上部summary・footer
- `sticky-{2,3}-{1440,1200,390}.png`：表内縦scroll
- `horizontal-{2,3}-390.png`：横scroll
- `browser-results.json`：sticky座標・列整列・focus・contrast・error確認

最終結果：2件 / 3件 × 1440 / 1200 / 390pxの6ケースPASS。縦scroll後もヘッダー位置を維持し、390pxの横scroll後も列整列・比較項目列固定を確認。keyboard focusとhit testで詳細リンクが覆われないことを確認。未確認badgeのcontrast比は4.55:1。page overflow / console errorなし。

確認範囲：会社 / title / 詳細リンクの固定、比較項目列、縦横scroll、ヘッダー重複なし、keyboard操作、focusがstickyに隠れないこと、未確認の読みやすさ、page overflow、console error。

## I. DB Safety

通常ローカルDB全29テーブルの件数・全列SHA-256が検証前後で一致（求人1,591件）。`db-before.json` / `db-after.json`に記録。migration / schema変更なし。通常ローカルDBはread-only transactionによる件数・全列SHA-256の取得のみ。テストfixtureはtesting DB限定。JobFact・Snapshot・UserQueryの保存処理を変更しない。

## J. Git Status / Changed Files

基準HEAD：`a257fd6 Add user-controlled sorting to JobDD job list`。開始時clean。

- `resources/views/query/job-compare.blade.php`：compact指定、thead hook、未確認の表示class。
- `resources/views/query/partials/selection-header.blade.php`：比較画面のみcompact表示。
- `resources/views/query/partials/status-badge.blade.php` / `fit.blade.php`：比較画面用data属性。
- `resources/css/app.css`：sticky補強、比較表内UNKNOWNの視覚調整。
- `tests/Feature/JobComparisonV2Test.php`：構造と表示の検査。
- 本書。

Production / Gate 2 / deploy / commitなし。ローカル検証後は追加変更を行わず確認待ちとする。Safari実機・スクリーンリーダー全端末監査は未実施。
