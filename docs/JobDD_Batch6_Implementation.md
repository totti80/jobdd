# JobDD v5.3 Phase A Batch 6 実装報告

参照正本：JobDD_Master v5.3、JobDD_Decision_Log v4.1、JobDD_AI_Coding_Rules、V5 PC / スマホ画面資料、Batch 5実装報告。
開始時：`new-jobdd-v4`、`239876a`、作業ツリーclean。

Scope：既存求職者詳細へのPublished Snapshot表示接続、表示用Presenter、既存比較・応募方法への導線、回帰検証。
Do Not Change：入力画面・UserQuery schema・Fit計算・比較項目・応募経路ロジック・Publish / Review / Mail / Admin・Snapshot / Fact生成・Crawler / Importer・Ranking。migration、依存追加、実DB書込み、deploy、commitなし。

## A. 変更ファイル

| 区分 | ファイル | 目的 |
|---|---|---|
| 追加 | `app/Support/PublishedJobDecisionPresenter.php` | 公開データだけを表示用に整形 |
| 変更 | `app/Services/JobDetailUseCaseService.php` | Snapshot / Fact Source取得、一貫した読取、未対応版のガード |
| 変更 | `app/Http/Controllers/JobDecisionController.php` | v2 / 既存詳細のView選択 |
| 追加 | `resources/views/query/job-show-v2.blade.php` | 正式Seeker Decision View v2 |
| 追加 | `resources/views/components/decision-section.blade.php` | セクションカード共通化 |
| 追加 | `resources/views/components/decision-fields.blade.php` | 項目表示・長文展開共通化 |
| 変更 | `resources/views/query/partials/evidence-block.blade.php` | 原文と日本語整形済み根拠の見出しを区別 |
| 変更 | `resources/js/jobdd-ui.js` | 詳細から一覧へ戻った際の比較対象選択 |
| 追加 | `tests/Feature/PublishedJobDecisionViewTest.php` | 公開境界・各表示・Fit・互換性のFeature Test |
| 変更 | `tests/Feature/JobDetailCompareTest.php` | Snapshot確認SELECT追加分の読取件数を反映 |
| 追加 | `docs/JobDD_Batch6_Implementation.md` | 本報告 |

## B. Data Source

| 表示 | データ元 |
|---|---|
| 企業名、タイトル、基本情報、応募条件 | `job_published_profiles.profile_data.company / level_one` |
| Level 2、Tool、Typical Day、案件例 | 同Snapshotの`structured_profile / tool_usages / typical_day_items` |
| 項目別Evidence | 公開済みJobFactsと各FactのSource |
| 公開確認日時・公開元 | SnapshotのProvenanceと`job_published_profiles.published_at` |
| Fit | 既存Selection / JobFitServiceの結果 |
| 応募方法 | 保存済みApplicationRoute、既存`routes.show` |
| Snapshotなし既存求人 | 従来JobPosting / Facts / Evidence / Routes、従来View |

Snapshot欠損項目を編集中の値から補わない。v2の会社名もSnapshot内の値を使用する。共通Selectionが既存互換用に取得する現在の会社名はv2の表示に使用しない。

## C. Published Boundary

既存`query.jobs.show`を使用し、求職者Queryのセッショントークン確認、`status = published`、既存候補条件、`private, no-store`を維持。

- draft / 初回pending_review：404。
- published + pending_review / changes_requested：前回承認版を表示。
- 再承認：新しいSnapshot、Facts、Sourceへ更新。
- Snapshotなし：従来詳細。
- Snapshotあり・未対応schema_version：404。編集可能なJobPostingへfallbackしない。

既存公開投影とSnapshot / Facts / Sources / Routesの読取を同一DBトランザクションにまとめた。現行MySQLのREPEATABLE READとBatch 5の原子的Publishを利用し、承認処理の途中の版を混ぜない。

会社名・基本情報・Level 2・Tool・Typical Dayを編集した後、未申請・審査待ち・差戻し時の公開詳細本文HTMLが同一であることを検証。再承認後だけ変更を表示する。

## D. Presenter / ViewModel

`JobDetailUseCaseService`が読取を担当し、`PublishedJobDecisionPresenter`へロード済みデータを渡す。Presenter自身はDBアクセスしない。ControllerはViewを選択し、Bladeはセクション種別ごとに描画する。

共通`StructuredJobOptions`で工程・使用文脈・経験要件・関係者・頻度を日本語化。未知の選択肢keyはそのまま露出せず、未確認表示とする。要点は構造化情報を3〜5件に整理し、長い項目を省略表示する。情報不足時は公開済みの職種・地域等を使う。推薦文や評価点は生成しない。

## E. Fit

職種・勤務地・年収・選択されたCAD / Toolのみ。既存JobFitService、軸、reason、summary、判定ルールは変更なし。

企業Self-report Toolは引き続きUNKNOWN。UNKNOWNをMISMATCHへ変更しない。担当工程、必須経験、関係者、Typical Day、難しさ、働き方、案件例は表示材料に限定する。

年収MATCH / UNKNOWN / MISMATCHの3ケース、職種・地域MATCH、Tool UNKNOWNを検証。Level 2を変更して再公開しても既存Fitのaxes / summaryは不変。自由記述の希望ツールは既存headerを通して表示し、判定対象には追加しない。

## F. Sections

1. この求人の要点
2. あなたの希望との照合
3. 何を設計する仕事か
4. どの工程を担当するか
5. 入社直後 → 将来
6. CAD / Tool
7. 誰と仕事をするか
8. 仕事の進め方
9. 代表的な1日
10. この仕事の難しいところ（入社後につまずきやすい点を含む）
11. 合いやすい働き方
12. 合いにくい可能性がある働き方
13. 代表的な案件
14. 情報源と根拠
15. 応募方法

補足として、最低限の応募条件・必要／歓迎経験・求人票では伝わりにくいことを表示。「向いている人／向いていない人」は使わない。将来の範囲は「将来的な担当可能性（確約ではありません）」と表示する。

## G. CAD / Tool

ツール名、仕事での使用、応募時の経験要件、使用の補足、あなたとの照合を分離して表示。使用と応募要件を混同しない。

選択済みツールは既存Fitの状態を使用。未選択ツールは照合対象に選ばれていない旨を表示する。企業申告が存在していても現在の判定ルールで確認できなければ未確認と説明する。

## H. Typical Day

Snapshot内の`sort_order`順に、時刻・業務内容を縦のTimelineで表示。Authoringの並べ替えや再編集を直接参照しない。

必須注記：「代表的な1日の例です。毎日同じ業務内容を保証するものではありません。」

## I. Evidence / Provenance

通常表示は「企業提供情報」「JobDD公開確認済み」。公開可能な状態の確認と、企業申告の真実性の保証を区別する。

「情報源を見る」で、情報源タイトル、企業による申告であること、Publisher、公開確認・公開日時（日本時間）、対象情報、Source URLを表示。再審査中の`reviewed_at`ではなく、最後に承認されたSnapshotの日時を使用する。

「項目別の根拠を見る」で公開JobFactsのEvidence、文脈、記録日時、各Sourceを表示。構造化JSONのTool / 案件例は日本語へ整形し、「保存された根拠（日本語表示）」と明記する。原文を加工していない項目だけ「根拠の原文」とする。文字列はBladeでescapeし、Sourceリンクは既存のHTTP(S) URL検証を使用する。

## J. Existing Job Fallback

Snapshotのない既存求人は`query.job-show`をそのまま使用。本文・既存Evidence・経路・比較の回帰テストを維持し、一括Snapshot変換は行わない。

詳細の読取はSnapshot確認のためSELECTが1件増加。既存詳細はHTTP全体で6 SELECT、database session利用時7 SELECT。v2通常ケースは7 SELECTで、件数が増えるFactごとのSource N+1は発生させない。SQL記録でLevel 2 Authoringテーブルへの問い合わせと書込SQLがないことを検証した。

## K. Compare / Application Route

上部・下部に「比較に追加」「応募方法を見る」を配置。

比較は既存一覧のページ内2〜3件選択を使用。ページ番号・希望Toolを保持して戻り、`select_job`がそのページの求人IDと一致した場合に選択する。比較項目・保存方式・比較APIは変更しない。JavaScript無効時は一覧で手動選択する旨を表示する。

応募方法は既存`routes.show`へ遷移。経路取得・並び順・利用可否・既存`route_opened`記録処理を変更しない。detail自体には新しいAnalytics書込を追加していない。

## L. UI Reuse

既存selection-header、condition-summary、company-name、fit / axis、status-badge、evidence-block、provenance、jobdd-card、jobdd-button、jobdd-detailsを再利用。

追加共通部品は繰り返し使用するsection cardと項目表示の2つだけ。長文は全文展開、欠損は「この情報はまだ確認できていません」。PCは項目欄を2列、mobileは縦積み。新しいCSSフレームワークや依存追加なし。

## M. Tests

実行環境：ComposeのLaravel / MySQL、`APP_ENV=testing DB_DATABASE=testing DB_URL=`。実DBをテスト対象にしない。

- 関連3ファイル：**103 PASS / 673 assertions**。
- Full：**766 PASS / 4,861 assertions**（110.34秒）。Batch 5 baseline 751 PASSから15ケース追加。
- 新規Feature Test：15ケース。公開・再編集・再審査・再公開、Draft非公開、未対応Snapshot、セッション認可、Authoring非参照・読取専用、Fit維持、日本語化、欠損・XSS・不正URL、Timeline順、既存fallback、比較・応募経路を検証。
- 既存詳細のSQL件数期待値は追加SELECTだけ反映。既存assertionを削除していない。

全体実行でLivewireが挿入する補助assetの有無に差が出るため、再編集による不変性は公開詳細の`main` HTML全体を比較する。求人情報を比較する条件を弱めず、frameworkのrequest-localな補助要素を対象から除外した。

## N. DB Safety

schema / migration変更なし。実DB `jobdd_v4` は読取のみ。19テーブルについて全カラムをID順にSHA-256へ集約し、作業前後で件数・hash・status / review_status分布が完全一致。

| テーブル | 前後件数 | SHA-256（前後同一） |
|---|---:|---|
| `job_structured_profiles` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `job_tool_usages` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `job_typical_day_items` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `job_published_profiles` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `company_user` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `users` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `companies` | 499 | `3a6a169948e2659fe66762e8b4200b7fedde39e34d5184295031a553d2e6fe9d` |
| `job_postings` | 1591 | `87b0914dfe2dd06f702bc6bdcc7af35d81ee119cc9df3bd1d50af037e8164d08` |
| `application_routes` | 1594 | `c8a69d39a2b1c42ed777c1be47d48cd730068eb08709ed7d60a18e3fd445ffb3` |
| `sources` | 465 | `874728d3ddaccde3dcecd66549608ffaab2cce40b5158cb8a65b52e5c2cb8877` |
| `job_facts` | 1732 | `a8f04e78c6e1873008990c390ab6f96c9668bbbb13b1362f44eea462faa289d4` |
| `agencies` | 9 | `c3eda0fd942f9a82d9564ca49b0e519f33433029f7abddbdd15c04892effeb55` |
| `agency_facts` | 27 | `44e5f1963b0ed1db348c308d851e0d447a3587b124381aac057f1848f788864c` |
| `platforms` | 3 | `f2610e63b6ef9fb629c2a88e1c89c05b06e428854793aaf1a6e63c072e69c7d1` |
| `user_queries` | 42 | `054e87bc53597cd87e9a951648f5c9b9684874a77096312cf4264a6d77848cdf` |
| `score_results` | 151 | `8cdfb9c19376021367096c58048ff6127cbe48482a9c6e5426a23f9af37896cc` |
| `interaction_logs` | 196 | `c48406ef07ad1b14603fa6a15e68ba6e33495e1c0bb2259b6501976dbd67cd1c` |
| `crawl_runs` | 18 | `80bc955ab0a0768fd1e5b69d433283310764406f1bd8596d2f292c84f69365cf` |
| `direct_reverse_lookup_candidates` | 362 | `55c0d066d726e6b5730b7cc9fc98b0511cd104c2d8231323bb4e5908dd816d78` |

実DBの既存公開求人1件でもDiscovery → Detailのread-only smokeを実施。書込SQL 0件、従来fallbackを確認。DB分離レベルは`REPEATABLE-READ`を実測した。

statusはpublished 1,591件、review_statusはnot_submitted 1,591件のまま。既存Factのcontext_role非NULLは0件のまま。ブラウザ用データはメモリ上の未保存モデルで生成した。

## O. UI Verification

V5 PC / スマホ資料を参照し、現行求職者UIの構成・色・余白・カード・CTAを再利用。

- Edge headless PC：画面幅1,440px、実測viewport 1,416px、content幅1,401px。横overflowなし。
- Mobile：iframeのviewportを390pxに固定。content幅390px以下。長文・Evidence展開時も横overflowなし。
- 通常v2、欠損情報、長文（全details展開）、既存fallback、一覧比較を確認。
- Tool / Typical Day / EvidenceをPC・mobileで個別に展開・目視確認。
- 比較の自動選択、2件で比較可能、3件上限、解除をPC・mobileで検証。
- Browser JavaScript error / unhandled rejectionは0件。
- `npm run build`成功。Pint PASS。`git diff --check` PASS。既存詳細・比較・応募方法のrouteを維持。

HTTPの認可・公開状態・データ境界はFeature Testで検証。ブラウザ確認は未保存fixtureから生成したBladeであり、実DBログインを伴うE2Eではない。

Laravel logの新規実行時エラーは0件。追加ERRORは全テスト2回で各1件ずつ発生した、既存`CompanyStructuredJobTest`の意図的なrollback例外（`testing.ERROR: simulated row failure`）のみ。

確認用一時ファイル：`/tmp/jobdd-batch6-ui/`（画像・ブラウザ検証結果）、`/tmp/jobdd-batch6-related-final.log`、`/tmp/jobdd-batch6-full-final.log`、`/tmp/jobdd-batch6-before.json`、`/tmp/jobdd-batch6-after.json`。リポジトリへ一時成果物を追加していない。

## P. Remaining Risks / OPEN

- 企業申告ToolをFitのMATCHへ昇格させるルールは未変更。現在はUNKNOWNを維持する。
- 比較選択は従来どおりページ内のみで保存しない。詳細から戻ったページに対象求人が存在する場合に自動選択する。ページ横断の保持・Level 2比較列はBatch 8の対象。
- Progressive Input・詳細希望条件の追加入力はBatch 7へ持ち越し。今回実装していない。
- Snapshot schema v1が対象。将来のschema改定時はPresenterと公開投影を併せて更新する。未対応版は404で公開内容の混在を防ぐ。
- 既存Factに保存された案件例JSONが上流の文字数制限等で読めない場合は、そのFactの詳細を未確認として表示する。Snapshot内の案件例は表示でき、Snapshot / Fact生成処理は今回変更していない。
- 公開版の表示・原子的読取は現行MySQL構成を前提とする。別DBや分離レベル変更時は再検証が必要。
- Company Preview、一覧、Compare本体、Route Comparisonの表示構成は今回の詳細v2への置換対象外。各画面の既存ロジック・導線を維持する。

## Q. Verdict

**PASS**。Batch 6のみで終了。Batch 7 / 8へ進まず、commitしていない。
