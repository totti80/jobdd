# JobDD 求人一覧 Sorting Final Revision

参照：Master v5.5 / Decision Log v4.3 / AI Coding Rules / JobDD_Job_List_Final_Revision / 既存Discovery、Decision UseCase、Fit、20件pagination、3件window。

Scope：一覧の明示的な表示順、sort URL引継ぎ、コンパクトselector、テストと検証。検索母集団・Fit計算・Evidence・Compare保存形式・公開Snapshot・Application Route・Auth・Mail・DB schema / 保存は変更しない。今回依頼により旧一覧仕様の「希望地域優先・ID順」を、ユーザーが選択できる順序へ変更する。

## A. Verdict

**PASS（実装・ローカル検証）**。

## B. Sort Options

`sort=fit`（希望条件に近い順）、`sort=newest`（新着順）、`sort=salary_desc`（年収が高い順）。未指定・不正値・配列はfitへfallback。表示範囲の横に「表示順」selectと「適用」を配置。Mobileでは表示範囲の下に置く。GET formで適用時は1ページ目へ戻る。JS無効でも利用可能。「おすすめ順」、地理的距離の並べ替え、AI推薦なし。

## C. Fit Sort Algorithm

既存JobFitServiceのsummaryを使い、次の辞書順で比較する。

1. confirmed_mismatches 昇順
2. confirmed_matches 降順
3. unknowns 昇順
4. 新着日時 降順（不明は最後）
5. ID 昇順

職種・勤務地・年収・CAD / Toolの既存判定だけを使う。Toolは既存仕様どおり選択したToolごとの判定件数を利用し、新たな集約ルールや重みを加えない。UNKNOWNをMISMATCHへ変換しない。詳細希望・prioritiesによる表示軸の順序・課金をsort keyに使わない。新しいScore・DB列・永続化なし。

## D. Newest Definition

`job_postings.published_at` → `first_seen_at`。Importerはproviderのdate_postedをpublished_atへ正規化する。Self-serviceのpublished_atは初回公開日時として既存Publishが保存する。更新・再取得のたびに新着扱いしないため、provider_updated_at / last_seen_at / updated_atは使わない。日時が両方nullまたは不正なら最後、同日時はID昇順。Snapshotの生成・公開projectionを変更しない。

## E. Salary Sort Definition

既存の万円単位の掲載年収を使用。下限ありを優先して下限降順、同下限なら上限降順。上限のみの求人は下限ありの後、年収なし・不正値は最後。負数・0・数値以外・下限>上限は不明扱い。完全同条件はID昇順。最大値だけ大きい求人を下限あり求人より優先しない。

## F. Pagination / URL

Source gate → 全対象のserver sort → 20件pagination（21件目でhas_next判定）→ 3件window。Discoveryのsort未指定呼出しは既存契約を保ち、一覧UseCaseだけが明示的にsortを渡す。sort後のID順でページモデルを復元するため、DBの返却順には依存しない。

次 / 前ページ、次の求人、詳細と一覧への戻り、Compare選択form・比較からの戻り、Tool変更、詳細希望編集でsortを保持。UserQueryのpublic id / session認可は変更なし。新しい検索条件を作る`/jobs/start`は別Queryとして従来どおり開始し、sortも初期値fitとする。sort自体はUserQueryへ保存しない。

## G. 3-item Window

既存JSは変更なし。取得した20件のDOM順で3件ずつ前後移動し、最後のwindowから既存次ページへ進む。window内だけでの並べ替えは行わない。

## H. Compare / Map

CompareのsessionStorage key・最大3件・ID順・解除・保存処理を変更しない。sort変更後も同じQueryの選択を保持。Mapはページ内求人の従来表示を維持し、ピンへの順位・番号付与なし。Mapからの詳細URLにもsortを渡す。

## I. Performance

全対象へSource / Evidence / Authoring relationのhydrationを行わない。fitでは既存判定に必要な公開projectionと選択ToolのFactだけを一括取得する。本文は職種の矛盾判定とTool文脈の判定に必要なため読み込む。JobFitServiceをそのまま使い、各候補の結果からsummaryだけ取り出しEvidence配列は保持しない。ページ内モデルとFactsは従来どおり取得する。

通常ローカル機械設計1,053件、read-only transactionで測定：

|sort|Toolなし|AutoCADあり|UseCase SQL|
|---|---:|---:|---|
|fit|737ms|1,069ms|4 / 5|
|newest|290ms|265ms|4 / 4|
|salary_desc|274ms|326ms|4 / 4|

PHPピーク40〜42MiB（同一プロセスの累積ピーク）。すべてSELECT。HTTPではQuery認可読込が1回加わる。Toolありfitは全候補の選択Toolを読むSELECTが1回増えるが、N+1なし。新着・年収では職種判定用本文の取得も省略する。キャッシュ追加なし。測定は開発環境の参考値でありSLAではない。

## J. Tests

- 関連7ファイル：231 PASS / 2,270 assertions。
- 追加確認後のSortingファイル最終：16 PASS / 124 assertions。Full suiteは今回再実行せず、変更に直接関連する上記の範囲を検証。
- 旧地域順・5 SELECTを固定していた既存5ケースは、今回のFit順とTool用一括読込1回を反映。並び順の期待値は既存フルFit評価から独立に計算し、軽量sortとの一致を確認。
- 45件を3ページ連結して各sortの全順序・重複なしを確認。
- 新着null / tie、年収不正 / 上限のみ、request不正値、詳細 / Compare URL、draft / paused / closed除外、Snapshot年収・職種・地域境界、Tool判定・詳細希望非利用を確認。
- 詳細希望編集のsort保持・非保存と画面生成：2 PASS / 14 assertions。テストのHTTP methodを既存PATCH契約へ修正してPASS。
- Pint（9ファイル）/ git diff --check PASS。
- build PASS（既存optional fontaine案内のみ）、依存追加なし。

## K. Browser

Chromium、1440 / 1200 / 390px × 3モードの9ケースPASS。select適用、3件windowの全20件連結・前後移動、次ページ、sort保持、ページ間重複なし、Compare2件保持、Map切替・府県選択、overflow / console errorなし。

正規routeで描画したtesting fixture HTMLと実buildを使用。ブラウザーのGETはfixtureへ対応させ、通常DBへUserQueryを保存しない。

成果物：`/tmp/jobdd-sort/`。

- `fit-{1440,1200,390}.png`
- `newest-{1440,1200,390}.png`
- `salary_desc-{1440,1200,390}.png`
- 各モードの`-page2-` / `-map-`画像
- `browser-results.json` / `performance.json`

## L. DB Safety

通常ローカルDB全29テーブルの件数・全列SHA-256が前後一致（`db-before.json` / `db-after.json`）。migrationなし。通常DBはread-only transactionのみ。testing fixture以外の保存なし。Source / JobFact書込みなし。

## M. Master Update Candidate（未反映）

> 求人一覧ではAI推薦順位ではなく、求職者自身が表示順を選択できるSortを提供する。

> 希望条件に近い順は、職種・勤務地・年収・CAD / Toolの既存Fit結果だけを用いたdeterministic orderingとし、詳細希望・課金・AI recommendationを順位へ使用しない。

Master / Decision Log本体は変更していない。

## N. Git Status / Changed Files / Remaining Risks

基準HEAD：`c165be8 Finalize JobDD decision view and eyecatch UI`。本作業のcommitなし。

- `JobListSort`新規：正規化・各sortのキー。
- `JobDiscoveryService` / `JobDecisionUseCaseService`：pagination前のsortとページモデル順序。
- `JobDecisionController` / `UserQueryPreferenceController`：URLのsort受渡し。
- `JobMapLocation`：詳細URLへのsort追加のみ。
- 一覧Blade / result card / compare panel / 詳細・比較・ナビゲーション・preferenceリンク：selectorとURL引継ぎ。
- `resources/css/app.css`：compact selector。
- `JobListSortingTest`新規、`JobDecisionPageTest` / `JobMapViewTest`：並び順・SQL数・回帰検証。
- 本書。

候補データの更新をまたぐ別リクエスト間の順序変動は既存pagination同様に起こり得る。大量候補のFit評価はO(N)、sortはO(N log N)。Chromiumでの確認でありSafari実機は未確認。Production / Gate 2 / deploy / commitなし。ローカル検証で停止する。
