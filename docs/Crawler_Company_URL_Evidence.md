# Crawler企業URL Evidence保存

Master v3.3、Decision Log v2.7に従う。企業URLの観測を保存する改善であり、企業公式性やDirect求人の確認ではない。有料API・Web Search・URL推測・追加リンクの巡回は行わない。

## 調査結果

| Provider | 従来の取得・保存 | 取りこぼし |
|---|---|---|
| Careerjet | API求人行をそのままJSON保存。保存済み1,080行にはcompany、求人url、site等のみ | raw JSONでは専用フィールドを捨てていないが、importerは企業URL情報をDBへ渡さない |
| Recruit | 詳細HTMLからJobPosting JSON-LDを抽出し、hiringOrganization.nameを保存 | url / sameAs等は選択項目に含まれず、descriptionもtext化でhrefを失う |
| Meitec | 詳細HTMLからJobPostingを抽出、企業名とdescription等を保存 | url / sameAs等、description以外のHTMLリンクは未保存 |

companies.website_urlには確認状態欄がないため、既存値を確認済みだとは断定しない。今回その値は一切更新しない。

sourcesにはHTML・metadata・company_idがなく、job_postingsにも既存JSON欄がない。application_routes.notesは応募経路用テキスト、interaction_logs.metadataはユーザー操作用である。これらを転用すると責務が混ざるため、job_postings.company_url_evidence（nullable JSON）1カラムのみ追加した。既存Company→JobPosting→ApplicationRouteの関連を維持する。

## 保存形式

各crawler求人行にcompany_url_evidence配列を追加する。未取得は空配列。企業名が一致するOrganization / hiringOrganization / organization / employer内のurl・sameAs、明示的company_url / company_website_url / employer_url / corporate_site_url / official_site_url / website_url / homepage_urlを抽出する。

取得済みHTMLおよびdescription内では、企業公式サイト・会社ホームページ・コーポレートサイト・corporate website等の明示ラベル付きhrefを対象にする。任意リンク・canonical・求人自体のurlを企業URLとして代用しない。媒体の会社リンクが含まれる可能性は残るため、Resolverで媒体除外し、残りもneeds_reviewとする。

各観測にsource_provider、source_url、company_name、raw_field、raw_value、fetched_at、review_status=needs_reviewを保持する。リンクの場合はlink_textも最大200文字保存する。

raw_valueは正規化せず保持し、deep URLからトップを作らない。現在は絶対http(s) URLのみ。相対URL・文字列以外の値は採用しない。再解析用に必要なフィールド名・元値・企業名・取得元・日時をJSONに残し、巨大HTML全体は追加保存しない。

新規抽出はHTML/description各2MiBまで、深さ12、URL各2,048文字まで、求人行あたり40 Evidenceまでに制限。これは全HTML解析ではなく明示項目の限定抽出であり、上限外・未知形式の網羅は保証しない。

## Importと冪等性

3 importerの既存求人transaction内でEvidenceを保存する。job_postings.idとcompany_idに紐づき、取得元URLとProviderを各観測に残すため、共通求人の複数Provider根拠が失われない。

Provider・企業名・raw_field・raw_value・source_urlが同じ観測は重複追加しない。再取得時刻が変わっても同じ観測を増殖させず、最初のfetched_atを維持する。URL競合は別観測として追加し、URL欠損の再importでも既存Evidenceを消さない。最新の存在確認を保証する台帳ではない。

JobPostingの企業とimport行の企業が異なる場合、そのURL Evidenceを他社へ付与しない。Evidence更新もtransaction失敗時にrollbackする。既存partial/full判定、未取得経路の無効化条件は変更していない。

## Resolver

既存DB企業URL → 既存公式Source → import済みURL Evidence / 保存済み求人・crawler → 外部Provider（既定skip）の順。台帳URLの既存扱いも維持する。

source_type=imported_company_url、evidence_locator=job_postings:<id>.company_url_evidence、raw_field / raw_value / fetched_atをreviewへ引き継ぐ。crawler JSONだけからも同じEvidenceを利用できる。既存正規化・媒体/SNS/ATS除外を適用し、全候補needs_review。各region×occupationセルのconfirmed条件や除外ルールは変更しない。

## 検証

追加migrationは2026_09_13_000001_add_company_url_evidence_to_job_postings.php。ローカルSail DBにも適用した。

Laravelは ./vendor/bin/sail artisan test で全件検証。

Python自動テストはPYTHONPATH=crawlerを設定し、crawler/.venv/bin/python -m unittest discover -s crawler/tests -p test_company_url_evidence.py と、同じコマンドの -p test_careerjet_fetch.py を実行する。

既存test_careerjet_api.pyは環境変数を要求して実HTTPを送る疎通スクリプトであり、mock自動テストとは分ける。一括unittest discoveryはUSER_IP未設定で失敗したため、自動テスト2ファイルを個別実行した。

## 実データVertical Slice（2026-09-13）

- Recruit：既存BREXA Technology求人を1件再取得成功。企業URL Evidence 0件。
- Meitec：既存株式会社イシダ求人を1件再取得成功。企業URL Evidence 0件。
- Careerjet：crawler環境とSail環境の既存キー・USER_IP未設定を確認。API再取得は未実施。保存済み1,080行には企業URL専用フィールドなし。
- 再取得した求人行はprivate/crawler/recruit_agent_url_evidence_slice.json、meitec_next_url_evidence_slice.jsonへ保存。各completed=false。元の通常取得JSONを置換していない。
- 実取得で新規保存したURL Evidenceは0件、今回のEvidenceからResolver Seed候補になった件数も0件。URL Evidenceが返るケースのDB保存はfixtureによる3 importerテストで検証した。
- 推測URL生成0、Direct confirmed自動昇格0。有料API・検索API呼出0。
- 既存Queue5社の再判定結果はprivate/crawler/direct_company_website_evidence_review.json。

## OPEN

実レスポンスで企業URL Evidenceが返る求人の少数検証が必要。値が返らないProviderを追加大量取得しても解決するとは限らない。Careerjetの再取得は既存の安全な実行環境設定が整った後に1件から行う。未知の企業リンクラベル、相対URLや上限外の形式は、具体例を観測してから対応を判断する。

最終検証：Laravel全103 tests / 443 assertions passed。Python mockテストは新規5件＋既存pagination 1件がpassed。既存Queue5社はSeed候補あり1社（20%）、not_resolved 4社で変化なし。git diff自己レビュー・diff --checkを実施した。
