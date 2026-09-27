# JobDD 求人一覧 Final UI / UX Revision

参照：Master v5.5 / Decision Log v4.3 / AI Coding Rules / トップ・`/jobs/start` / Top Page Final Revision・Visual Polish報告 / 今回の依頼文と追加許可。
指定PDFは未提供。ユーザーが「チャット本文で十分共有済み、PDFなしで進行可」と明示したため、依頼文を基準とする。

Scope：一覧表示、コンパクトカード、比較パネルの表示、既存20件内の3件切替、表示用total metadata、関連テスト。
Do Not Change：検索・候補条件・並び順・Fit / Score / Ranking・PublishedJobQuery・公開境界・DB保存 / schema・UserQuery・詳細希望・Compare state・Evidence / Provenance・Application Route・Auth・企業 / 審査 / Mail・本番設定。

## A. Verdict

**PASS。** 表示用total、3件切替、コンパクト一覧、比較パネルを実装。最終Full suite **867 PASS / 6,110 assertions**、関連・ブラウザ・SQL同一性・DB照合もPASS。

## B. Total Count

画面最上部の「該当求人 XX件」に、ルート階層の表示用`total`を表示。既存`pagination`の`page / has_previous / has_next`は完全に維持。

`JobDiscoveryService`がもともと取得している軽量projectionを、既存の`forPublic`・候補条件・掲載元URLチェックを通した直後、`slice`前に`count()`する。既存Collectionの返却は維持し、任意の出力引数で呼出側へ件数を渡す。取得し直し・COUNT SQL・追加SQL・新しい絞り込みなし。totalは表示用途のみ。

25件のfixtureでpage 1は20件、page 2は5件、空のpage 3でも総数25件。母集団自体が空なら0件。除外対象URL・draft / paused / closed・対象外地域 / 職種・unavailable・未対応Snapshot・Snapshot側の掲載元欠落を件数へ含めない。Authoringが対象外へ編集されても、公開Snapshotが対象なら引き続き数える。

## C. Three Job UX

`jobdd-result-window.js`で取得済みの一覧カードをDOM順のまま3件ずつ表示。`1〜3 → 4〜6 → … → 19〜20`。「次の3件を見る」でページ内切替は通信・reloadなし。「前の3件を見る」で戻れる。

Rangeを更新し、切替時にRangeへfocus / scrollを戻す。非表示カードは`hidden`でキーボード・アクセシビリティツリーから除外。Evidenceの非表示温存は行わず、カードHTMLから除去。

## D. Ranking Safety

- 並べ替え・推薦・新しいScoreなし。「上位3件」と表示しない。
- サーバーの希望地域優先・グループ内ID昇順・ページoffsetを維持。
- 変更前コードの控えと新コードを、通常ローカルDBでSELECTのみ実行して照合。機械設計 / 電気設計それぞれoffset 0 / 20で、SQL・bindings・全モデル属性・Facts・ID順が完全一致。各3 SQL。
- 一覧HTTPテストは従来と同じ5 SELECT、DB session利用時6 SELECT。writeなし。
- ブラウザで全7グループを連結し、サーバーの20 IDと同順・欠落重複なしを確認。

証跡：`/tmp/jobdd-list-revision/sql-audit.json`。実データ総数は機械設計1,053 / 電気設計229（検証時点の既存対象条件）。これは新しい求人抽出結果ではなく、既存母集団の件数。

## E. Compact Job Card

残す：会社名、求人タイトル、勤務地、掲載年収、Fit summaryの3件数、全既存Fit軸のラベル・status icon・status文言、詳細・比較操作。

外す：各軸の長い理由、Evidence件数ボタン・本文、求人提供元・外部求人URL・最終取得日時、一覧で必須でない雇用形態。根拠・掲載元は既存詳細へ。詳細・比較のEvidence表示は変更なし。

Fit値の再計算や置換はせず、既存`summary`と`axes`をそのまま表示。会社名を補助色、タイトルを20px / 行高32px、基本情報を14pxへ。軸はPC2列・Mobile1列。既存green / amber / neutral badgeとiconを再利用。

## F. Compare Panel

PC：右288px、青の上border・淡青の件数枠、sticky。`0 / 3件`・選択求人名・解除・既存GET比較ボタン。

Mobile：3件のカードと切替ボタンの後に通常フローで配置。固定バーは一覧から除去し、カードやFooterを覆わない。

既存sessionStorage key、ID・label形式、上限3、hidden submit IDの順序、ページ遷移時の保持を維持。2件未満はJS初期化後にnative disabled＋aria-disabled。追加・解除のstate処理は維持し、表示件数とfocus復帰のみ調整。別グループの求人を解除すると対象グループを表示してcheckboxへfocus。別ページの求人ならパネル見出しへfocus。

## G. Pagination

20件の取得契約、既存GET URLの`page / tools`、prev / nextを維持。通常paginationは控えめなリンク。最後のグループとページ末尾では「次の求人を見る（21〜25件）」のように次ページの実際の範囲を表示し、既存の次ページURLへ移動。開始番号は現在ページのoffset＋21、終了番号はoffset＋40とtotalの小さい方。最終ページの最終グループは終端noteを表示。

JS無効時は20件すべてを表示、ネイティブ比較checkbox・GET form・既存paginationで操作可能。JS無効時の件数表示は隠し、誤った0件表示を避ける。サーバーの比較validationも維持。

## H. Map Regression

Map JS / Partial / 座標 / 件数 / 検索 / 遷移先は変更なし。Mapへ3件制限を適用しない。ブラウザで20件のMapモデル、府県選択（3件を超える候補）、位置図の求人選択、比較stateの独立性、リスト復帰を確認。既存JobMapViewTestもPASS。

## I. Common Components / Changed Files

| File | 役割 |
|---|---|
| `app/Services/JobDiscoveryService.php` | 許可された位置で表示用totalを取得 |
| `app/Services/JobDecisionUseCaseService.php` | totalを画面へ受け渡し |
| `resources/views/query/jobs.blade.php` | 上部圧縮、件数、表示範囲、コンポーネント配置、pagination |
| `resources/views/components/job-result-card.blade.php` | 共通コンパクト一覧カード |
| `resources/views/query/partials/fit.blade.php` | compact variant。通常の詳細表示は維持 |
| `resources/views/query/partials/condition-summary.blade.php` | compact variant。通常表示は維持 |
| `resources/views/query/partials/compare-panel.blade.php` | 既存比較formを共通Partial化・視覚調整 |
| `resources/js/jobdd-result-window.js` | 3件window、範囲・focus・前後操作 |
| `resources/js/app.js` | window module読込み |
| `resources/js/jobdd-ui.js` | 比較件数・disabled・非表示カードへのfocus復帰 |
| `resources/css/app.css` | 共通palette / button / cardを使う一覧class・spacing |
| `tests/Feature/JobDecisionPageTest.php` | 総数・順序・SQL・公開境界・Fit表示・fallback検査、検証画面生成 |
| `tests/Feature/JobUiV5Test.php` | 一覧でEvidence詳細を出さない期待値へ更新 |
| `tests/Feature/SeekerProgressiveInputTest.php` | 指定された詳細条件リンク文言と既存遷移先を検査 |
| `docs/JobDD_Job_List_Final_Revision.md` | 本報告 |

`resources/views/query/start.blade.php`のリンク削除と`JobUiV5Test`のstartリンク期待値変更は前回から保持した差分。今回の変更と区別する。start Bladeは開始時の控えと完全一致。

## J. Responsive

Chromium 1440 / 1200 / 390pxで検証。PCはMain約73% / Compare約27%、Mobileは縦積み。総数が最初の画面内にあり、横overflow・console error・pageerrorなし。トップ / `/jobs/start`も通常ローカル8081のGETで3サイズ確認し、既存表示・直前のリンク削除を維持。

## K. Accessibility

buttonによる前後切替、aria-live / aria-atomicのRange、切替後focus・scroll、比較のaccessible name、状態を文字＋icon＋色で提示。animationは加えず、reduced motion時も同じ即時切替。キーボードEnter、比較解除時focus、非表示カードへの復帰、JS無効・空ページを確認。

## L. Tests

- 開始時HEAD：`9fa6b1e`（前回の未commit差分2ファイルを保持したworking treeで再実測）。baseline **864 PASS / 5,842 assertions / 266.64秒**。
- 関連 **248 PASS / 2,430 assertions**：JobDecisionPage / JobDiscoverySlice / JobDetailCompare / JobUiV5 / JobMapView / JobSearchEntry / PublishedJobDecisionView。
- Snapshotの総数確認を追加した後の対象テスト **1 PASS / 25 assertions**。
- 初回Full suiteは旧リンク文言を期待する1件のみ失敗（866 PASS）。`SeekerProgressiveInputTest`を今回指定の文言と既存遷移先の検査へ更新。アプリ処理の追加修正なし。
- 最終Full suite：**867 PASS / 6,110 assertions / 266.63秒**。全テストは隔離testing DBで実施。
- Pint / `git diff --check` PASS。build PASS。ホストbuildは既存生成資産の所有権で失敗したため、従来のDocker内で実行して成功。依存追加・設定変更なし。既存optional fontaine案内のみ。
- 実ブラウザ：3画面幅、25件total、3件切替、page 1最終2件 / page 2の3＋2件、全順序、Compare追加 / 解除 / max3 / disabled / ページ間保持 / GET内容、Map、詳細リンク、no-JS、empty、keyboard、reduced motion PASS。

証跡：`/tmp/jobdd-list-revision/`の`baseline.log` / `related.log` / `total-boundary.log` / `full.log` / `build.log` / `browser.py` / `browser-results.json` / `public-smoke.json`。

## M. DB Safety

通常ローカル`jobdd_v4`全29テーブルの件数・全列SHA-256を前後比較し一致。業務データwriteなし。session/cacheも分離確認し変更なし（sessions 3→3、cache / cache_locks 0→0）。

通常DBはSELECTと公開ページGETのみ。一覧ブラウザ検証は隔離testing DBの正規route / Bladeから生成したHTMLをPlaywrightで返し、実build CSS / JSを使用。実際のform GET内容・遷移先を検査し、サーバー認可・保存境界・Compare内容はFeature Testで検査。通常DBへのUserQuery作成・フォームPOST・fixture追加なし。

## N. Screenshots

`/tmp/jobdd-list-revision/`：

- `list-{1440,1200,390}.png`：先頭3件と比較パネル / Footer
- `list-next-{1440,1200,390}.png`：4〜6件
- `list-page2-{1440,1200,390}.png`：page 2、比較3件保持
- `map-{1440,1200,390}.png`：既存20件を母集団とする府県表示
- `parent-home-{1440,1200,390}.png` / `parent-start-{1440,1200,390}.png`：デザイン親の回帰確認

## O. Remaining Risks

Chromiumでの検証。実機Safari・スクリーンリーダーの全端末監査ではない。検証一覧の会社・求人はtesting fixtureであり、通常DBへの新規保存や実ユーザー認証操作は行わない。候補が同時更新される場合のページ間変動は既存挙動のまま。

## P. Git

開始時cleanではなく、前回の2ファイルのみ未commit。開始diffと控えを`/tmp/jobdd-list-revision/before*`に記録して保持。commit / tag / push / Gate 2 / deploy / 本番操作なし。とおる確認で停止。

## 最終文言調整（2026-09-28）

「他の求人を見る」を「次の3件を見る」へ変更。次ページは既存page / totalから範囲を組み立て、BladeとJSで同じラベルを使用。検索・順序・pagination契約・Fit・Compare・遷移動作の追加変更なし。変更は一覧Blade、window JS、関連テストと本報告のみ。

関連JobDecisionPageTest：**23 PASS / 504 assertions**。21〜23 / 21〜25 / 21〜40 / 41〜45件と最終ページの非表示を検査。1440 / 1200 / 390pxのChromiumで文言・実際の次ページ遷移・横overflowなし・console errorなしを確認。build / Pint / diff check PASS。今回の文言変更では指定どおりFull suiteは再実行していない。commitなし。

証跡：`/tmp/jobdd-list-copy/related.log`、`browser-results.json`、`first-{1440,1200,390}.png`、`next-page-label-{1440,1200,390}.png`。一覧HTMLは今回も隔離testing DBから生成し、通常DBへの保存なし。
