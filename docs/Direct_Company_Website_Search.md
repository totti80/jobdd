# Seed URL Search fallback（無料限定MVP）

有料APIを使わないというユーザー方針に従い、実際の外部Provider採用はOPENとする。You.com有料REST実装・キー設定は採用しない。無料接続がなくても既存Resolverは動作する。

## Provider調査と判断

- Bing Web Search API：2025-08-11廃止。https://learn.microsoft.com/en-us/lifecycle/announcements/bing-search-api-retirement
- Brave公式API：$5/1,000リクエスト、月$5クレジット。保存権限が付与されるプランの確認が必要。無料クレジットを有料API非採用方針の代替とはしない。https://brave.com/search/api/ / https://api-dashboard.search.brave.com/terms-of-service
- You.com公式REST：$5/1,000リクエスト、初期$100クレジット。有料のため不採用。https://about.you.com/pricing
- You.com公式free MCP：キー不要、検索100回/日。RESTよりMCPセッション処理が必要。認証資料では評価用途とそれ以外を区別しており、継続利用条件も要確認。この環境でfree endpointへのinitializeがHTTP403となり、検索は実行していない。採用を見送る。https://you.com/docs/build-with-agents/mcp-server / https://you.com/docs/using-the-api/authentication

料金・条件は調査時点のもの。無料Providerを採用する際は、レビューJSONへの保存条件と稼働環境からの利用可否を再確認する。検索HTMLスクレイピングやLLM検索で代替しない。

## 今回の実装

CompanyWebsiteSearchProviderInterfaceと、必ずskipするDisabledCompanyWebsiteSearchProviderのみを追加。AppServiceProviderで後者をbindする。APIキー・有料依存・SDK・新規DB・Schedulerは追加しない。

優先順位は既存DB → 保存済みSource / 求人description / crawler JSON → 未解決の場合のみProvider。既存候補が1件でもあればProviderを呼ばない。現在のProviderはHTTPを送らず、search.status=skipped、reason=free_search_provider_not_configured、api_calls=0を返す。

将来Providerが返す検索結果は先頭最大5件だけ処理する。会社名を基本に「公式」を補助語とする。URLは検索結果に明示されたもののみで、企業名から作らない。既存URL正規化・媒体/SNS/ATS除外を共用し、Wikipediaと求人ボックスを追加除外。会社名がtitleに一致すれば2、snippetなら1をcandidate_scoreとし、同点は元rank順。同一URLは重複排除。これはJobDDの適合スコアではない。

## JSONと責務

既存配列形式・company_id / company_name / source_candidate_ids / discovery_candidates / website_candidatesを維持。検索候補にcandidate_url / title / snippet / rank / provider / query / searched_at / reason / candidate_scoreを追加し、既存url・source_url・source_type=search_api・found_via・evidence_field・evidence・checked_atも保持する。

status=unverified、review_status=needs_review固定。企業のresolution_statusはcandidates_found / not_resolved / search_failed。searchには実行状態とAPI呼出数を記録する。Provider例外は本文を出さずsearch_failedへ変換する。例外発生時のAPI呼出数は不明なのでnull。

人間レビューの後に別QueueへSeedを設定する。検索結果からのクロール、公式URLのDB確定保存、Direct confirmed更新は行わない。生成結果を次回の根拠として再入力しない。現在の既定Providerは毎回0呼出。同じ入力・根拠・検索応答なら候補集合は同じで、時刻のみ実行日時となる。将来の実Providerには別途API再呼出抑制が必要。

## 検証とOPEN

fake Providerで既存Seedのskip、未解決のみ呼出、除外、候補順位、最大5結果、企業limit、重複排除、冪等性、失敗、例外秘匿、Processor互換、非confirmedを検証する。HTTPは全ケース0で検証する。

実外部検索のVertical Sliceは未実施。優先する次工程は既存crawlerが取得時に落としている企業URL/構造化データの根拠付き保存、確認済みSeedからの既存Official Page Discovery。無料公式APIの接続・利用/保存条件が確認できるまで外部検索はOPEN。

## 実データ再検証（2026-09-13）

現在のQueue内の5社（BREXA Technology、メイテックフィルダーズ、メイテック、デンソーテン、パーソルクロステクノロジー）を使用。指定のMHI等5社は現在のQueueに含まれないため、この5社に限定した。

- 既存データで候補あり1社、候補URL1件（メイテック、求人本文とcrawler JSONの2根拠）。
- 既存データで未解決4社、その4社は検索skip。not_resolved 4、search_failed 0。
- Search API呼出0回、検索由来候補0件、費用0。無料MCPのinitialize疎通失敗とは区別する。
- 推測URL生成0、confirmed自動昇格0。外部実検索成功を示す検証ではない。
- 出力：storage/app/private/crawler/direct_company_website_search_review.json。

実行：
```bash
./vendor/bin/sail artisan jobdd:resolve-direct-company-websites --limit=5 --output=storage/app/private/crawler/direct_company_website_search_review.json
```
