# JobDD Eyecatch Final Revision — 2026-09-28

## Scope / Do Not Change

今回の差分はResolverの正式fallback assetパス、Heroの画像参照、関連テスト、提供済みPNG5枚。外部画像のprovider制御・load/error処理・抽出処理を維持。Heroのサイズ、4:3枠、radius、PC/Mobile順序を変更しない。Fit / Score / Ranking / Evidence / Application Route / Published Snapshot / Review / Authoring / Auth / Mailは変更しない。

## A. Verdict

PASS（実装・ローカル検証）。

## B. External image priority

利用承認済みproviderの保存済みsafe HTTP(S) URLが読込成功すると外部画像を表示。読み込み中はfallbackを表示し、成功時に置き換える。失敗時はfallbackを継続。同じ固定aspect ratioの枠を使用。外部画像の表示中は「画像出典：掲載元求人ページ」、fallbackでは「JobDDイメージ画像」。外部リンクの仕様は変更なし。

既存取得HTMLのog:image → JobPosting / WebPage JSON-LD imageを維持。追加取得・推定・外部画像ファイルのサーバー保存なし。

## C. Approved provider behavior

`JOBDD_EYECATCH_APPROVED_PROVIDERS`に含まれるproviderのみ許可。未承認・URLなし・危険なURL・読込失敗はfallback。実providerの承認設定を新規に有効化していない。

Legacyは保存済み画像URLを利用。Snapshotは従来どおり編集可能なJobPosting画像を読み出さずfallbackとし、公開境界を維持する。

## D–E. Assets / Resolver

`public/images/jobdd/eyecatch/`の提供済みPNGを加工せず正式利用（各1672×941）。Resolverだけで次の順に判定する。AI分類なし。

|優先順位|職種|asset|
|---|---|---|
|1|PLC / 制御 / シーケンサ|plc.png|
|2|生産技術 / 設備|production.png|
|3|電気 / 電装 / 回路|electrical.png|
|4|機械 / 機構|machine.png|
|5|その他|generic.png|

## F–G. Verification

成果物：`/tmp/jobdd-eyecatch-final/`。正規routeで生成したtesting fixture HTMLと既存buildを使用し、外部画像・リンクはブラウザー内でmockする。

- Feature tests: **32 passed / 264 assertions**（JobEyecatchTest、PublishedJobDecisionViewTest）。
- Pint: 2ファイルPASS。`git diff --check`: PASS。CSS / JS変更なしのため既存buildを利用。
- Chromium: 1440px / 390pxで外部成功、破損、URLなし、未承認、Snapshotの計10ケースPASS。追加でJS無効390pxもfallback表示PASS。
- 横overflow・console errorなし。fallbackの画像読込完了、attribution切替、Mobile順序、同じ4:3枠を確認。既存CTAの別tabとevent送信も維持。

|状態|1440px|390px|
|---|---|---|
|外部成功|[PC](/tmp/jobdd-eyecatch-final/legacy-success-1440.png)|[Mobile](/tmp/jobdd-eyecatch-final/legacy-success-390.png)|
|破損時fallback|[PC](/tmp/jobdd-eyecatch-final/legacy-broken-1440.png)|[Mobile](/tmp/jobdd-eyecatch-final/legacy-broken-390.png)|
|Snapshot|[PC](/tmp/jobdd-eyecatch-final/snapshot-fallback-1440.png)|[Mobile](/tmp/jobdd-eyecatch-final/snapshot-fallback-390.png)|

## H. DB impact

今回の調整によるmigration・通常DB更新なし。テストfixtureはtesting DBのみ。前回追加済みの画像URL列をそのまま使用する。本番backfill未実施。

## I. Remaining risks

- 実providerのhotlink利用確認・承認設定は別途必要。今回の外部成功テストはmock。
- 提供されたPNGは各約1.7〜1.9MB。低速回線での読み込み時間は残る。固定枠でレイアウトを維持する。
- ブラウザー検証はChromium。Safari実機は未検証。

## J. Git status

前回作業の未commit差分を保持。今回の編集はResolver、Hero、JobEyecatchTest、本報告と前回報告の追記、および正式PNG5枚。commit / deploy / Gate 2は未実施。
