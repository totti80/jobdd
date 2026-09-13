# Direct Lookup Queue 前段

`JobDD_Master_v3.3.md` §52–55 / `JobDD_Decision_Log_v2.7.md` D-054–056 に沿ったDB候補選定。検索時のクロール、公式確認、Scheduler接続は行わない。

```bash
./vendor/bin/sail artisan jobdd:build-direct-lookup-queue --limit=10
./vendor/bin/sail artisan jobdd:process-direct-reverse-lookup-queue --input=storage/app/private/crawler/direct_lookup_queue.json
```

出力は `storage/app/private/crawler/direct_lookup_queue.json`。配列形式、1企業1レコード。`--limit` は正の整数、デフォルト10社。

## 選定

- 対象statusは `unverified`（未確認）と `crawl_failed`（取得失敗、再確認候補）。`not_found`（確認時に対象を発見できず）やその他statusは今回の自動選定から除外する。存在しないという断定ではない。
- `confirmed` があるcompany_id × region × occupationのセルだけをDiscovery Sourceに関係なく除外する（台帳のregion・occupationの一致で判定）。同じ企業でも別の未確認セルは候補に残し、企業単位のQueueレコード内に保持する。
- 地域なし、対象3職種以外、matching_job_countが0、既存ダミー企業A製作所、企業名なしを除外。
- 大阪府・兵庫県 × 機械設計・電気設計・施工管理を優先。市区町村を含む地域も都道府県へ集約して優先度を計算するが、出力の地域文字列は元の値を保持する。
- 次にDirect Coverageが0件、1〜2件、3件以上の順。その中でunverified、crawl_failedの順、未チェック優先、最後にcandidate ID昇順。
- Coverageは公開中Direct routeがある有効なJobPostingのユニーク件数。職種は既存OccupationNormalizer、地域は都道府県で判定。route重複で水増ししない。
- company_idでまとめ、最優先の候補をトップレベルの対象セルとする。他の対象候補は `discovery_candidates` にID・企業ID・地域・職種・Source・元status・保存済みURLを保持する。企業名の類似性による法人統合は行わない。

## 冪等性と公式確認の境界

Queueは追記型ではなく、DBから毎回生成する作業対象のスナップショット。DBとlimitが同じなら同一JSONになる。ファイルを原子的に置換し、DBは変更しない。処理後にDBがconfirmed等へ更新されれば次回選定から外れる。未確認のままなら次回も同じ候補が残る。レビュー済み結果は別ファイルに保存すること（生成先を編集しても次回生成で置き換わる）。

URLはDB保存値のみを転記し、欠損はnull。転記したURLも公式性の新たな証明にはならない。生成statusは常に `unverified`。

既存Processorは公式サイトをクロールする実装ではなく、レビュー済みJSONを保存するImporterである。今回追加したunverified対応では、生成Queueを読んでも公式確認待ちとしてスキップし、checked_at・status・JobPosting・ApplicationRoute・Sourceを更新しない。既存confirmed/not_found/crawl_failedの入力と検証は維持する。`discovery_candidates` の他セルへ確認結果を伝播しない。

公式URL探索・公式Evidence確認からレビュー済みJSONを作る段階は未実装のまま。未確認Queueを実行しただけでDirect confirmedが増えることはない。

候補台帳の再生成Commandは、既存の確認結果をunverifiedへ戻さないようにした。新規行はDB既定値unverifiedを利用する。

## 現状の関連構造

台帳の一意キーはcompany_id / region / occupation / discovery_source。company_idはcompaniesへの外部キー。候補と求人・Sourceの直接FKはない。JobPostingはcompany_idを持ち、ApplicationRouteがjob_posting_idで紐づく。Processorは公式求人をprovider_key / external_idでupsertし、Sourceをsource_urlでupsertする。

現行Schedulerは `crawler:run-mhi` の毎日05:00（Asia/Tokyo、withoutOverlapping）のみ。今回のCommandは登録していない。
