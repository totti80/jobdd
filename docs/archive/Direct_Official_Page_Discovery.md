# Direct公式ページ候補探索（review前段）

Master v3.3 §52–55、Decision Log v2.7 D-054–056に沿う限定バッチ。探索結果は公式性・求人の公開状態・直接応募可否の証明ではない。

## 現状と責務

- BuildDirectLookupQueue：DB台帳から企業を選定。company × region × occupation単位でconfirmed除外、企業単位でQueue集約。
- DiscoverDirectOfficialPages（今回）：DB保存URLとQueueの保存URLを起点に候補発見。JSONファイルへの出力のみ。DBのcompanies / candidates / jobs / routes / sourcesは変更しない。
- ProcessDirectReverseLookupQueue：人間が確認した結果JSONの保存。既存実装はクロールを行わない。未確認入力はスキップする。
- candidatesはcompany_id経由で企業と関係する。jobsとroutesはjob_posting_id、公式SourceはURLで保存される。探索結果はこの関係を変更しない。
- 既存Pythonはrequests/BeautifulSoup、タイムアウト・待機・限定リトライを利用。共通robots/termsチェックは存在しなかった。今回はLaravel HTTPクライアントとDOMDocumentを利用し、追加依存はない。
- Schedulerは既存のMHI 05:00 Asia/Tokyoのみ。今回追加登録なし。

## 実行

```bash
./vendor/bin/sail artisan jobdd:build-direct-lookup-queue --limit=5
./vendor/bin/sail artisan jobdd:discover-direct-official-pages --limit=5
```

デフォルト入力：`storage/app/private/crawler/direct_lookup_queue.json`

デフォルト出力：`storage/app/private/crawler/direct_lookup_discovery_review.json`

`--input` / `--output`は上記privateディレクトリ直下のJSONのみ。入力上書き・シンボリックリンク不可。`--limit`は1〜5社（デフォルト5）。レビュー用ファイルは生成時に原子的に置換されるので、人が編集するレビュー済み結果は別ファイルへ保存する。

## 探索ルール

1. companies.website_url → Queueのofficial_site_url / official_recruit_url → discovery_candidates内の保存URLを起点にする。
2. HTMLの採用・career・recruit・jobs・求人等のリンク、既知ATSドメインへのリンクを抽出。canonical/og:urlも参考候補として抽出する。JSON-LD抽出は今回は未実装。
3. HRMOS / HERP / Greenhouse / Lever / Workday / SmartHR / Talentio / JobcanのホストをATS候補として分類。独自採用サイトも採用リンクとして記録。いずれも公式性の判定ではない。
4. URLなし・不適格URLのみならnot_resolved。検索APIや会社名からのURL生成は行わない。
5. 同一企業内の正規化URLを重複排除し、複数発見元はprovenanceへ保存。fragment・代表的tracking parameterを除去する。意味が異なり得るscheme・末尾slash・求人ID等のqueryは維持し、実際のredirectを確認した場合のみ統合する。

## robots・取得制限

- 明示User-Agent：JobDDDiscovery/0.1。
- 各originでrobots.txtを先に確認。404は制限なし、それ以外の不明な状態（リダイレクト、HTTPエラー等）はblocked。robots取得自体の通信失敗はcrawl_failed。
- MVPでは全User-agentグループのDisallowを保守的に尊重し、Allowで上書きしない。ワイルドカード・終端指定対応。許可ページを過剰にスキップする場合がある。
- 2秒を超えるCrawl-delayまたは不正な値はblockedにする。robotsの完全なRFC準拠パーサーではない。
- 同一ホストのリクエスト開始間隔は最低2秒（robotsも含む）。接続5秒／全体15秒、再試行0回、redirect最大3回。
- 1社あたり取得最大5ページ、depth 2、候補30URL、各HTMLでリンク走査300個まで。redirect・robotsはページ数と別だが上記の上限で有限。
- HTML/XHTMLのみ解析、レスポンス上限2MiB。認証URL・一般的な添付ファイル拡張子・既知Agent/Platformドメインは除外。
- HTTP(S)のみ、認証情報・非標準port不可。DNSが非公開IPv4に解決する場合は接続しない。検査済みIPをcURLへ固定し、redirect先も検査。IPv6のみのホストは今回は対象外。
- 利用規約らしいリンクはterms_candidatesへ保存し、人間確認待ちにする。規約の自動解釈・許可判定はしない。定期運用へ進む前に、対象サイトごとの利用条件を人間が確認する。

## review JSON

トップレベルは企業レコードの配列。company_id / company_name / source_candidate_ids / discovery_candidates / 主対象region・occupationを保持。未確認セルを落とさない。

- company_website_candidates
- recruitment_page_candidates
- job_page_candidates
- ats_candidates

各URLを上記いずれか1分類に格納し、url / source_url / found_via / provenance / page_title / http_status / checked_at / confidence / reason / status=candidate / review_status=needs_review / fetch_statusを記録する。ページ上でリンク発見のみ・未取得の場合はtitleとHTTP statusはnull。

企業のdiscovery_statusはdiscovered / not_resolved / crawl_failed / blocked。1ページでも取得できた企業はdiscoveredになり、部分的な失敗・制限はfetch_attemptsに残る。review_statusは常にneeds_review。not_foundやconfirmedは生成しない。

Processor向けstatusは常にunverified、official_site_urlとofficial_recruit_urlの確定欄はnull。レビュー待ちJSONを誤ってProcessorに渡してもEvidence保存・status更新は起きない。

同じ入力・同じHTTP結果なら候補集合は同じで、再実行しても追記増殖しない。checked_atは実行のたびに更新する（テストでは時刻を固定してJSON全体の冪等性を検証）。

## 人間レビューと次段

人間が企業同定・公式サイトとATSの関係・利用条件を確認する。その後、対象求人・職種・地域・応募URL・現在公開中の公式Evidenceを確認して、既存slice形式の**別のレビュー済みJSON**を作る。今回のreview_statusを書き換えただけではProcessorのconfirmed必須項目は揃わない。

confirmed結果は既存Processorの必須フィールド（company_name / region / occupation / official_site_url / official_recruit_url / status / external_id / title / description / employment_type / source_url / application_url）を満たす必要がある。確認結果を同一企業の別セルへ伝播しない。

求人本文抽出・職種地域照合・自動confirmed・ATS固有解析・Search API・AI検索・UI・Scheduler接続は実装対象外。

## 実データVertical Slice（2026-09-13）

既存Queueファイルが未生成だったため、Build Commandの選定順で最大5社を生成してDiscoveryを実行した。対象はBREXA Technology、メイテックフィルダーズ、メイテック、デンソーテン、パーソルクロステクノロジー。既存Sliceの5社はこのQueueに含まれず、入れ替えやURLの補作はしていない。

- 処理：5社
- 公式トップ候補／採用候補／求人候補／ATS候補：すべて0件
- not_resolved：5社、crawl_failed：0社、blocked：0社
- needs_review：5社
- ページ取得試行：0回（起点URL欠損）

保存先はデフォルトのprivate review JSON。これは実DBのURL欠損経路の確認であり、実サイトでのリンク探索成功は未検証。HTTP fakeテストでは採用・ATS抽出、robots・redirect・深さ・件数制限、既存Command互換性を検証した。
