# 企業公式サイトSeed候補の根拠付き抽出

Master v3.3、Decision Log v2.7、Direct Lookup Queue / Official Page Discoveryの前提を維持する。既定のCommandは**取得済みデータを読むだけ**であり、HTTP・検索・DB更新・公式性の自動確認は行わない。未解決企業向けの検索Provider接続点は追加したが、無料Providerの採用はOPENで、既定実装は必ずskipする。詳細は[Search fallback](Direct_Company_Website_Search.md)。

## 現状調査

- companies / agencies / platformsにはwebsite_urlがある。ただしcompaniesには公式性の確認済みフラグはない。agencies / platformsのURLは求人企業のURLではないので代用しない。
- 調査時companiesは499件。website_urlがあるのは3件（三菱重工業、三菱電機エンジニアリング神戸事業所、ダミーA製作所）。既存MHI Importerには固定URL保存があり、DB保存値も今回新たに公式確認した事実とは扱わない。
- sourcesはsource_type / publisher / url / fetched_at等を持つが、company_idやHTML・JSON-LDの保存欄はない。大半はCareerjetのjobviewtrack URL等。official_siteという種別でもAgent自身の公式ページの場合があり、種別だけでは企業URLと判断できない。
- job_postingsはcompany_idとdescription・source_urlを保持。application_routesはjob_posting_idで対応する。Agent / Platform routeのURLは応募媒体URLとして除外に利用する。
- Careerjet / Recruit / Meitec Importerは企業名一致で企業を作成・再利用し、新規website_urlはnull。法人名の曖昧一致・ブランド／事業所の統合はない。
- Recruit / Meitec crawlerはhiringOrganization.nameを読んでいるが、現行保存JSONにはurl / sameAs / HTMLを残していない。既存JSONにはcompany_url相当フィールドも見つからなかった。
- 取得済み求人descriptionには一部URLが残る。現在のQueue内ではメイテックの求人本文にトップURLが明記されている。BREXAの本文URLはブログ記事なのでトップへ変換しない。

## 実行

```bash
./vendor/bin/sail artisan jobdd:resolve-direct-company-websites --limit=5
```

入力：`storage/app/private/crawler/direct_lookup_queue.json`

出力：`storage/app/private/crawler/direct_company_website_review.json`

`--input` / `--output`はprivate/crawler直下の別JSONファイルのみ。limitは1〜5社。入力はBuildDirectLookupQueue形式のunverified Queue。企業IDとDB企業名の一致を確認する。

## 候補抽出と企業同定

優先順はDB企業website_url → 台帳website_url → 企業名とpublisherが一致するofficial / official_site Source → 企業に紐づく求人description → 取得済みcrawler JSON。

- JSON-LD / 構造化配列のOrganization / Corporation / LocalBusiness / hiringOrganizationについて、nameが対象企業名と一致する場合にurl / sameAsを採用する。媒体のOrganizationや求人自体のurl・canonicalを企業URLへ流用しない。
- crawler行はcompany_name / companyの一致、または既存の対象企業求人・Source URLとの一致が必要。別の企業名・IDが明記されている行は除外する。
- 明示フィールド：company_website_url / company_url / employer_url / official_site_url / website_url。source_urlも必要。
- 保存済みdescription / html / source_htmlから「企業公式サイト」「コーポレートサイト」「Corporate website」等の明示リンクを抽出する。HTMLは既存JSONに保存されている場合だけ読む。Source.urlを開き直すことはしない。
- 求人本文がプレーンテキストの場合、文字として記録されたルートURL（pathが空または `/`、queryなし）を未確認候補にできる。所属企業との関係はレビュー対象で、取引先等のURLである可能性を否定しない。本文抜粋を必ず残す。記事・製品・PDF等のURLからルートURLを生成しない。
- 既存official_recruiting SourceとURLが一致する保存HTMLの企業公式リンクはats_htmlとして扱う。ATS自身のURLは企業トップ候補にしない。

読み取り対象はstorage/app/private/crawlerとcrawler/data内のcareerjet*.json / recruit_agent*.json / meitec_next*.json / mhi_job.json / direct_reverse_lookup_slice.json。既存配列形式・jobs配列・単一行形式に対応する。新規の保存済みHTMLを検証する場合もこれらのcrawler行へsource_urlとともに格納する。再取得処理は今回追加していない。

生成Queue・review JSONを根拠として再入力しない。32MiB超・シンボリックリンク・不正JSONはwarningsを残してスキップする。

## URL除外と重複

既存URL正規化を利用しfragmentとtracking parameterを除去する。scheme・末尾slash・意味のあるqueryを勝手に統合しない。会社名からのURL生成や深いパスの切り落としは行わない。

既知Agent / Platform / SNS / 検索 / ATSドメイン、DBに保存されたagency / platform / Agent・Platform routeのホスト、求人・採用パス等を除外する。未知の媒体を網羅判定する仕組みではないため、残ったURLも公式認定しない。

## review JSONとEvidence

企業ごとにcompany_id / company_name / source_candidate_ids / discovery_candidates / website_candidatesを保持する。

各候補にurl / source_url / source_type / found_via / evidence_fieldまたはevidence_excerpt / evidence_locator / confidence_reason / review_status=needs_review / checked_atを記録する。DBはテーブルと行ID、JSONはファイル・行番号・行内容のSHA-256で追跡可能。同一URLの複数根拠はevidence配列に残す。

source_type：company_record / candidate_record / official_source / job_description / crawler_json / source_html / ats_html。

企業のresolution_statusはcandidates_foundまたはnot_resolved。statusはunverified、review_statusはneeds_review固定。存在しない・永久に解決不能という断定ではない。

毎回スナップショットを原子的に置換する。入力・DB・保存済み根拠が同じなら候補集合は同じで増殖しない。checked_atは実行日時に更新される。人が編集するレビュー済み結果は別ファイルに保存する。

## 人間レビューとDiscoveryへの接続

人間が企業同定・公式性・利用条件・根拠を確認してから、別のDiscovery入力Queueのofficial_site_urlに採用Seedを設定する。候補レビューJSONをDiscovery入力へ自動変換・自動実行しない。companies.website_urlも書き換えない。公式Seedの確認とDirect求人confirmedは別であり、後者の必須Evidence条件を維持する。

既存Processorへ誤って渡してもstatus=unverifiedのためスキップされる。既存Build・Discovery・Processor、Importer、Crawler、DB schemaは今回変更しない。

## OPEN

現行Crawlerが落としているhiringOrganization.url / sameAsや会社URL・取得元HTMLを、取得時に根拠付きで保存することが次の候補。現時点の抽出機能が未保存の情報を復元することはできない。Search API・再クロール・自動confirmed・Scheduler・UIは今回の対象外。

## 実データVertical Slice（2026-09-13）

既存Queue内の5社（BREXA Technology、メイテックフィルダーズ、メイテック、デンソーテン、パーソルクロステクノロジー）を処理。以前のMHI等5社はこのQueueに含まれず、対象の差し替えはしていない。

- 対象5社、候補あり1社、ユニーク候補URL1件、not_resolved 4社。
- メイテックの `https://www.meitec.co.jp/` を、保存済みRecruit求人本文から抽出。
- source_typeはjob_descriptionとcrawler_json。1つのURLにDB求人と元JSONの2根拠を保持。
- source_url：`https://www.r-agent.com/viewjob/jk8b9c5d5235a6c293/`。
- 全候補needs_review。推測URL生成0件、外部HTTP0回、DB更新0件。
- 出力：`storage/app/private/crawler/direct_company_website_review.json`。

これは既存データ内のURL観測を確認した結果であり、当該サイトの現在の公式性や公開求人・直接応募可否を確認した結果ではない。

検証：Sail全テスト85 passed / 318 assertions。差分自己レビューとgit diff --checkを実施。
