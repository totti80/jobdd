# JobDD v5.3 Phase A Batch 9 — Hardening / Production readiness

検証日: 2026-09-23。正本: JobDD_Master.md v5.3 / JobDD_Decision_Log.md / JobDD_AI_Coding_Rules.md。

## A. Verdict

**PASS — code hardening / deploy準備の範囲。** 本番GOは承認・実環境確認待ち。Production deploy / DB write / 実メール / commit / tag / pushは未実施。

Scope: Batch 1〜8の縦断検証、公開境界の重大bug最小修正、回帰テスト、UI検証、本番手順書。Do Not Change: Fit軸・Score・Ranking・Evidenceの意味・DB schema・依存package・新機能。これらは変更していない。

## B. Changed Files

| ファイル | 修正内容・理由 |
|---|---|
| app/Services/PublishedJobQuery.php | 未対応schemaのSnapshotがある場合は公開対象から除外。従来のjoin条件ではSnapshotなしと同じ扱いになり未承認Authoringへfallbackできた。公開会社名もSnapshotから同じqueryで投影 |
| app/Services/JobDecisionUseCaseService.php | 会社名に公開投影を利用、Legacy fallback維持 |
| app/Services/JobSelectionUseCaseService.php | 同上、一覧・比較の公開会社名を固定 |
| app/Services/RouteSummaryService.php | 応募経路の会社表示と既存ダミー会社除外を公開値へ統一。Pintによる既存indent整形を含む |
| app/Http/Controllers/UserQueryController.php | 旧resultsで空の保存tokenと空session tokenが一致するケースを拒否。既存会社除外を公開値へ統一。Pint整形を含む |
| app/Http/Controllers/RouteComparisonController.php | show固有のSnapshot追加queryを共通投影へ統合。action側にも同じ境界が適用される |
| app/Http/Controllers/JobReviewController.php | public Provenanceも対応schemaを必須にする |
| app/Http/Requests/ApplicationRouteInteractionRequest.php | 新規。公開求人かつ有効Direct/Agent/Platformだけをログ対象として受理。HTMLだけでなく直接POSTにも非公開境界を適用 |
| routes/web.php | contact-clicked / route-selectedで上記Requestを利用。route-selectedもログ失敗で利用者の操作を阻害しない既存contact方式に揃える |
| resources/views/query/results.blade.php | 公開会社名、HTTP(S)安全URL表示 |
| resources/views/routes/action.blade.php | 公開会社名、危険URLをリンク化しない |
| resources/views/routes/show.blade.php | 共通の公開会社名を表示 |
| tests/Feature/PhaseAHardeningTest.php | 新規19ケース。縦断・role matrix・非公開ログ対象・Snapshot会社名・不正schema・CSRF・query数 |
| tests/Feature/JobDiscoverySliceTest.php | 公開投影の追加属性を明示して検証。Legacyの全保存属性、Fact順序、relation、DB不変検証は維持 |
| docs/JobDD_PhaseA_Production_Readiness.md | 設定・bootstrap・migration・backup・deploy・smoke・承認済みrollback・tag計画 |
| docs/JobDD_Batch9_Implementation.md | 本報告 |

修正の中心は公開境界。未知の情報をMISMATCHへ変換せず、未対応Snapshotも編集値を公開するfallbackには使わない。

## C. Vertical Slice

新規HTTP縦断テストでCompany登録からUser/Company/pivot作成、Dashboard、Level1、STEP1〜5、100% completion、Preview、申請、Mail fake、管理queue、差戻し、理由付き修正、再申請、承認、求職者一覧/詳細/詳細希望/比較/Evidence/RoutesまでPASS。さらに再編集→旧Snapshot確認→再申請→再承認を通した。

DB更新はtestingのみ。ブラウザは未保存モデルをBladeへ渡したfixtureとbuild済みassetsを使用。HTTPの保存・認可・transactionはFeature testで確認し、実DBへのブラウザ書込E2Eとは区別している。

## D. Authorization

| Role | 自社Draft/Published Authoring | 他社求人 | admin queue/review操作 |
|---|---|---|---|
| Guest | login redirect | login redirect | login redirect |
| Normal User / Companyなし | 403 | 403 | 403 |
| Company Owner A | OK | 403 | 403 |
| Company Editor A | OK | 403 | 403 |
| Company Owner B | 自社のみOK | Company Aは403 | 403 |
| Platform Owner | 全件OK | 全件OK | OK |

新規role matrixはGET/PATCH/Preview/審査GET/承認POST/差戻しPOSTを検証。既存CompanyOwnership/Registration/Level1/ControlledPublishテストも全PASS。Company属性・User roleのmass assignment昇格不可、登録者company_owner固定、通常system_role=user固定を維持。

## E. Draft / Published Boundary

Draft/paused/closedは一覧、詳細、比較、Provenance、Routes、直接URL、旧resultsとログ対象から除外。review_statusだけで公開を決めない。

published + pending_review / changes_requestedは最後の承認Snapshotを維持。Authoringの基本値/構造化値だけでなく会社名も公開投影へ統一した。schema_versionが未対応のSnapshotは公開不可、SnapshotのないLegacyは従来値。新規テストは未対応schemaによるAuthoring漏洩と非公開route IDへの直接ログPOSTを再現し防いでいる。

## F. Controlled Publish

not_submitted→pending_review→changes_requested→pending_review→approved、再申請時の旧note/reviewer/dateクリア、差戻しnote必須、status/Snapshot維持をPASS。承認のみが公開する。審査中編集不可・stale review409・二重承認・transaction rollbackは既存ControlledPublishテストを含めPASS。

通知先はconfig/jobdd.phpと.env.exampleの `JOBDD_REVIEW_NOTIFICATION_EMAIL`、既定postmaster@jobdd.jp。件名/会社名/求人名/申請時刻/admin review linkをMail fakeで確認。メールは画面への通知リンクのみ。実送信なし。commit後の同期送信失敗時はpendingを保持し、例外classとjob IDを記録する。queueによる再送はない。

## G. Data Integrity

再承認後もSnapshot1件、Direct ID維持、company_self_reported Factの件数維持、外部Fact保持、Sourceの安定性を確認。Factはself_reportedと明示context_roleを持ち、ContextRoleClassifierによる再推定はしない。既存rollback testと組み合わせ、Source/Fact/context/Snapshot/Route/status/reviewer/dateの一括公開を検証。

登録のduplicate email・同名会社分離・password hash・transaction rollback、途中Draft保存、Core必須、Tool/Typical Day行置換とsort_order、不正key拒否も既存suiteでPASS。

## H. Fit / Ranking

職種・地域・年収・CAD/Tool以外へFitを拡張していない。詳細希望の保存/解除と比較追加の前後でFit/順序不変。self-report Toolの現行UNKNOWNも維持。Score/Rankingの計算コード変更なし。Discoveryの保存属性/Fact順序/ページング/query数回帰もPASS。

## I. Compare / Evidence / Route

2/3件、最大3、追加/解除、ページ・詳細間移動、検索別sessionStorage、破損storage、storage不可、BFCache pageshow相当の再表示をブラウザで15項目PASS。BFCacheはイベント再現であり、全実機の実際のcache採用までは保証しない。

Self-service/Legacy混在、企業提供/JobDD公開確認・非保証文言・source/publisher/timestampを維持。Direct/Agent/Platformの公開available境界を検証。安全なHTTP(S)だけリンク化。

route_opened / route_selected / contact_clicked契約、nullable query ID、metadataの最小化を維持。ブラウザmock fetchで送信1回ずつ、CSRF、keepalive、新規tab/noopener、同tab遷移なしの7項目PASS。実際のログ保存はFeature test。外部応募の送信はしていない。

## J. UI

Headless Edge、PC window1440px（実viewport1416px）/Mobile iframe390pxで27画面状態×2の54capture。対象: register、dashboard、Level1、STEP1〜5、Preview、未完了Preview、review queue/detail、簡易入力、一覧、Decision View、Legacy、長文、詳細希望/入力エラー、Compare2/3、Provenance、Routes/action。長い日本語求人名・差戻し理由・Typical Day、validation/flashも含む。

各DOMのページ全体横overflow、JS error/unhandled rejection、フォームの名前を検査。登録画面は初回fixtureがLivewire未挿入/Flux配信ファイル違いだったため、実際のAssetManagerと同じflux-lite + Livewireへ補正して再検証。アプリ修正は不要だった。比較表は内部スクロールを維持する。

代表スクリーンショットでheader、button、badge、textarea、エラー表示を目視確認。全画面の全スクロール位置・全実機OSを網羅する検証ではない。成果物は `/tmp/jobdd-batch9-ui/`、repoには追加していない。

## K. Accessibility / Error / Performance

native labelまたはaria-labelledby/aria-labelによる関連付けをDOM検査。入力エラーのrole=alert、保存flash、状態の文字表示、button typeと既存focus stylingを確認。重大なアクセシビリティ問題は検出していない。screen readerによる完全監査は未実施。

403/404/409/422はFeature test、419は本物のPreventRequestForgeryをunit-test bypass無効の派生middlewareで適用して検証。正しいCSRF headerは204。500系は意図的transaction failureからのrollbackを確認。

開始以後のLaravel errorは全suite各1回の `testing.ERROR: simulated row failure` の2件のみ（CompanyStructuredJobTest.php:137）。未解決の想定外500なし。ログは削除していない。

Dashboard/Review Queueは1→25求人でSELECT数不変、上限8/4をassert。Decision View既存5/6 SELECT制約もPASS。実DBのLegacy3件比較は8 SELECT / 0 non-SELECTでBatch8と同数。今回の会社名projectionによるN+1追加なし。

## L. Tests

| 確認 | 結果 |
|---|---|
| 関連: Hardening / DecisionPage / DetailCompare / P0RouteSummary | 84 PASS / 755 assertions |
| 関連: JobDiscoverySlice | 92 PASS / 842 assertions / 23.06秒 |
| Full suite（最終） | **832 PASS / 5,561 assertions / 123.81秒** |
| Batch8 baseline | 813 PASS / 5,275 assertions |
| Pint | PASS、変更PHP 11ファイルを最終チェック |
| npm run build | PASS、既存lockfile・依存変更なし |
| config / route / view cache | PASS、testing設定 + /tmpの専用cacheパスで隔離 |
| composer check-platform-reqs --no-dev | ローカルPASS |
| git diff --check | PASS |

初回fullは831 PASS/1 FAIL。失敗は公開投影へ追加した `published_company_name=null` を既存属性厳密比較が未考慮だったため。期待値へ追加属性を明示し、保存値・relation/Factの確認を弱めず最終全PASSを確認した。

証跡: `/tmp/jobdd-batch9-full-final.log`, `jobdd-batch9-hardening-tests.log`, `jobdd-batch9-discovery.log`, `jobdd-batch9-build.log`, `jobdd-batch9-pint-final.log`, `jobdd-batch9-cache.log`, `jobdd-batch9-platform.log`。

## M. DB Safety

ローカル実DB jobdd_v4はread-only。主要19テーブルの全行・全列をID順に読み、件数とSHA-256全64桁を開始/終了で比較して完全一致。状態分布も一致。新schema/packageなし。testing DB以外への書込なし。

| Table | 前後件数 | 前後同一SHA-256先頭12桁 |
|---|---:|---|
| job_structured_profiles | 0 | e3b0c44298fc |
| job_tool_usages | 0 | e3b0c44298fc |
| job_typical_day_items | 0 | e3b0c44298fc |
| job_published_profiles | 0 | e3b0c44298fc |
| company_user | 0 | e3b0c44298fc |
| users | 0 | e3b0c44298fc |
| companies | 499 | 3a6a169948e2 |
| job_postings | 1591 | 87b0914dfe2d |
| application_routes | 1594 | c8a69d39a2b1 |
| sources | 465 | 874728d3ddac |
| job_facts | 1732 | a8f04e78c6e1 |
| agencies | 9 | c3eda0fd942f |
| agency_facts | 27 | 44e5f1963b0e |
| platforms | 3 | f2610e63b6ef |
| user_queries | 42 | 054e87bc5359 |
| score_results | 151 | 8cdfb9c19376 |
| interaction_logs | 196 | c48406ef07ad |
| crawl_runs | 18 | 80bc955ab0a0 |
| direct_reverse_lookup_candidates | 362 | 55c0d066d726 |

求人1591件すべてpublished / not_submittedのまま。証跡: `/tmp/jobdd-batch9-before.json`, `/tmp/jobdd-batch9-after.json`, `/tmp/jobdd-batch9-smoke.json`。

## N. Production Config Checklist

[Production Readiness](JobDD_PhaseA_Production_Readiness.md)にAPP/DB/MAIL/通知先/queue/session/CSRF/proxy/storage/assets/cache/runtime/監視を記載。secretの読出し・表示・commitなし。本番host/settingsは未検証。

## O. Migration Plan

同手順書の10migration一覧、maintenance、pretend、既存列hash/status/role確認、Legacy Snapshot不要の互換条件を参照。testingでmigration既存回帰PASS。本番実行なし。

## P. Backup Plan

DB全体、code artifact、保護されたenv、storage、サービス設定を保全。defaults-extra-fileによる秘密値非表示、checksum、別DBへの復元リハーサル、移行後backupまで定義。

## Q. Deploy Plan

lockfile固定install/build、env/storage配置、maintenance、backup、migration、config/route/view cache、atomic release切替、PHP reload、smoke、up。実環境path/サービス名は承認時に確定。今回は未実行。

## R. Production Smoke Plan

既存Seeker、Company登録/入力/申請、運営通知/差戻し/再申請/承認、新規公開Seeker/詳細希望/比較/出所/Direct、再編集境界、PC/mobile、権限/CSRF/ログをチェックリスト化。実メール・公開する実在求人・運営対象Userは承認後に確定する。

## S. Rollback Plan

v1はDraftを除外しないため**v1コードだけをv5 DBへ戻すことは不可**。ユーザーへ報告し、「maintenance中に移行前backupを別DBへ復元し、移行後DBを保全してv1へ切替」の承認を得た。破壊的migration rollbackなし。移行以後の更新は保全DBから後で照合する。実復元/切替は未実行。

## T. Known Risks

- 本番runtime/proxy/SMTP/backup復元/運営本人確認/実配送は未検証。ローカルPASSから本番PASSを推測しない。
- メールは同期、失敗時自動再送なし。申請queue巡回と通知失敗監視が必要。
- UserはMustVerifyEmailを実装しておらず、verified middlewareをメール所有確認の保証に使えない。運営bootstrapは人手で本人確認する。
- 実DBのSelf-service Snapshotは0件。Self-service検証はtesting DBと未保存fixture。公開後smokeを別途実施する。
- Company/通知日時はAPP timezone依存、Seekerには個別の表示変換もある。既存UTC設定を勝手に変更していない。運用時の時刻基準を確認する。
- v1復帰は移行前データへ切替となり、移行後の更新は一時的に利用者から見えなくなる。移行後DBを破棄せず復旧後の扱いを人手で決める。

## U. Recommended Next Step

**Production deployは現時点NO-GO（とおる承認・環境確認待ち）。** コード差分/手順書をレビューしcheckpointを作る。その後、環境/本人/backup復元を確認してから承認されたdeployとsmokeを行う。ユーザーのBatch9 Scope29/31に従い、この準備段階で停止する。

## V. Git

開始時clean、branch `new-jobdd-v4`、HEAD `ea2260d`。今回の変更は未commit。tag/push/history rewriteなし、v1復帰点は変更していない。

推奨commit message: `Harden Phase A public boundaries and document production readiness`。
推奨tag: `graduation-submit-ready-v2`。Production smoke PASS・とおる確認後、実際にdeployした完全SHAへannotated tagを作る。今回の作業では作成しない。
