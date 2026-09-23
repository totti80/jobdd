# JobDD v5.3 Phase A｜Batch 8 実装報告

実施日：2026-09-23。対象branch：`new-jobdd-v4`。開始時clean、開始HEAD：`9992a1c`。
正本：`JobDD_Master.md` v5.3、`JobDD_Decision_Log.md`、`JobDD_AI_Coding_Rules.md`。Batch 7の完了状態を基に実装。

Scope：既存Compareの表示拡張、Evidence / Provenance / 応募方法への接続、ページをまたぐ比較選択保持、応募方法画面の公開会社名・外部リンク処理の最小修正。

Do Not Change：DB schema / migration、Seeker入力schema、Fit判定、ランキング・スコア・推薦、Company Authoring、Review / Publish / Snapshot / Fact生成、Crawler / Importer / Payment / 本番環境。依存追加なし。commitなし。

## A. 変更ファイル

| 区分 | ファイル | 役割 |
|---|---|---|
| 追加 | `app/Services/JobComparisonUseCaseService.php` | 既存選択処理を再利用し、Snapshotと利用可能な応募経路を一括取得 |
| 追加 | `app/Support/JobComparisonPresenter.php` | 比較材料・短縮文・日本語ラベル・Provenance・経路summary |
| 変更 | `app/Http/Controllers/JobDecisionController.php` | Compareのみ新UseCaseへ接続 |
| 変更 | `app/Http/Controllers/RouteComparisonController.php` | 応募方法画面の会社名をSnapshotに合わせる |
| 変更 | `resources/views/query/job-compare.blade.php` | Compare v2、希望表示、Evidence / 応募方法リンク |
| 変更 | `resources/views/query/jobs.blade.php` | Queryごとの選択保持情報・説明 |
| 変更 | `resources/views/query/job-show-v2.blade.php` | Snapshotの会社名で比較選択を追加 |
| 変更 | `resources/views/query/job-show.blade.php` | Legacy詳細からの比較追加 |
| 変更 | `resources/js/jobdd-ui.js` | 選択順・追加・解除・上限・ページをまたぐ保持 |
| 変更 | `resources/css/app.css` | Mobileの表内スクロール・固定ヘッダー・横はみ出し修正 |
| 変更 | `resources/views/routes/show.blade.php` | HTTP(S)リンク確認、クリック時の二重遷移解消、ログ送信のCSRF付与 |
| 追加 | `tests/Feature/JobComparisonV2Test.php` | Batch 8の14テストケース |
| 変更 | `tests/Feature/JobDetailCompareTest.php` | Snapshot / Routeの一括読み取り追加に伴うSQL数の更新 |
| 変更 | `tests/Feature/JobUiV5Test.php` | Compare v2の行数・セクション数の更新 |
| 追加 | `docs/JobDD_Batch8_Implementation.md` | この報告書 |

## B. Compare Data Source

- 既存 `JobSelectionUseCaseService` の認可対象・公開候補境界・Fit結果・選択順を維持。
- Self-service：`forPublic()` による承認済みLevel 1と、`job_published_profiles.profile_data` のLevel 2 / 会社名 / Provenanceを利用。再審査中も最後の公開内容を表示。
- Legacy：既存JobPosting / JobFacts / Fit Evidenceを利用。CAD / ToolのFactは「記載：…（用途は詳細で確認）」とし、担当業務での使用を推定しない。
- Legacyに対応するLevel 2がない項目は「未確認」。AuthoringのStructured Profile / Tool Usage / Typical Dayテーブルを読まない。
- 未対応Snapshot versionは404。編集可能なデータへfallbackしない。
- 取得全体は読み取りtransaction内。Snapshot・Routeは最大3求人分を一括取得し、N+1を追加しない。
- Compare HTTPは6 SELECT、DB session利用時は7 SELECT。既存のsession読み取り専用境界を維持。

## C. Compare Rows

正式な比較材料は、職種、勤務地、掲載年収、設計対象、主な工程、入社直後、将来的な担当可能性、CAD / Tool、顧客、製造、現場、仕事の進め方、仕事の難しさ、情報源 / Provenance、応募方法。

- 工程・頻度・Tool用途 / 経験条件は既存辞書の日本語ラベルを使用。
- 長文は80文字相当で短縮し、主要文章は2行表示。全文確認は求人詳細の対応箇所へ戻す。
- Tool usage_notesを比較表へ追加しない。
- Typical Day / Representative Projectは全文を表示せず、詳細への案内のみ。
- 既存Fitの確認件数と条件別Evidenceは残す。AutoCADを選択したテストでは、案内行・既存Fitを含め21行、4セクション。

## D. Detailed Preferences

`SeekerPreferences::read()` / `comparison()` を再利用。「あなたの希望」として比較表の上に一度だけ表示する。未入力時は欄を出さない。

工程、顧客・製造・現場との関わり、仕事の進め方を表示材料として扱う。求人ごとの評価・適合バッジは追加しない。保存形式を変更していない。

## E. Fit Separation

既存職種・地域・年収・選択ToolのFit結果をそのまま使用。比較材料の表示層からFitを呼び出さない。

詳細希望の保存前後でFit配列と求人順が同一であることをテスト。新しいMATCH / MISMATCH / UNKNOWN判定軸、スコア、ランキング、推薦は追加していない。

## F. Provenance

- Self-service：「企業提供情報」「JobDD公開確認済み」、publisher、公開確認・更新日時（日本時間）。公開可能な状態の確認であり、事実の保証ではない旨を表示。
- Legacy：「外部情報」と既存provider情報。公式性を推定してラベルを格上げしない。
- 信頼度・Evidence Scoreを追加しない。

## G. Evidence

比較表から既存の `jobs.provenance` と求人詳細のEvidence節へ遷移できる。Self-serviceの項目別evidence_text / source_type / publisher / 公開日時はBatch 6の既存UIで確認する。Legacyも既存の本文・出典・Fit Evidenceを維持。

求人用の新Analytics基盤は作らない。既存 `evidence_opened` はAgency用の契約であり、変更していない。

## H. Application Route

- `availability_status=available` かつ `unavailable_at IS NULL` のDirect / Agent / Platformを短く表示。
- 各求人の「応募方法を見る」から既存 `routes.show` へ接続。経路の優劣は付けない。
- Self-service Directは既存公開処理が生成したRouteを利用。Route生成処理は変更していない。
- 応募方法画面で、未承認編集後の会社名が表示され得たため、Snapshotの会社名に合わせた。
- 外部リンクは既存のHTTP(S)判定を再利用。カード・比較表の不正なURLをリンクにしない。
- 既存クリック処理の通常リンク遷移と `window.location.href` による二重遷移を解消。通常の別タブリンクを維持し、ログはCSRFヘッダー付きkeepalive fetchで送る。
- `route_opened` / `route_selected` / `contact_clicked` を維持。検索IDなしの場合はnull。ログ送信のために外部遷移を待たせない。

## I. Compare State

- 2件で比較可能、最大3件。選択した順に送信し、サーバー側でも件数・重複・公開境界・Query sessionを検証する。
- `sessionStorage` の `jobdd:compare:{public_id}` に求人IDと表示ラベルだけ保存。認証token・希望入力は保存しない。
- 同じタブ・同じ検索で、一覧ページ、詳細、比較、応募方法を移動しても選択を保持。
- 別ページの求人も一覧の選択欄から解除できる。詳細からはその求人を追加して一覧へ戻る。
- hidden inputで選択順を保持し、画面内checkboxとの二重送信を防ぐ。
- 戻る操作のBFCache復帰時に再読込。壊れた保存値を無視し、保存不可の場合も現在ページの比較は利用できる。
- DB・Laravel sessionの保存構造、route、上限を変更していない。

## J. Mobile

390pxでは表内の横・縦スクロールと、固定の求人ヘッダー・項目列を利用。表の上に比較中の2 / 3求人を列挙し、追加・解除へのリンクを置いた。

比較表内の読み上げ用絶対配置要素がページ全体へはみ出す問題を、表コンテナを配置基準にして修正した。

Edgeで2件、3件、3列目 / 下段、一覧、応募方法をPC / 390px確認。PCのviewport 1416pxに対してcontent 1401px、Mobile viewport 390pxに対してcontent 375px（スクロールバー分）。ページ全体の横overflowなし、JS error / unhandled rejectionなし。

## K. UI Reuse

既存selection-header、company-name、Fit / axis / Evidence partial、`jobdd-card`、`jobdd-link`、`jobdd-button`、`jobdd-scope-badge`、比較表CSSを再利用。新しいUIライブラリ・大規模共通部品化なし。

## L. Tests

| 確認 | 結果 |
|---|---|
| 関連Feature（比較・既存UI・詳細・詳細希望） | **110 PASS / 998 assertions** |
| Full suite（testing DB） | **813 PASS / 5,275 assertions** |
| Batch 7からの増加 | 14 tests / 167 assertions |
| Pint（変更PHP 7ファイル） | PASS |
| `npm run build` | PASS |
| `git diff --check` | PASS |
| Compare route一覧確認 | 既存route維持 |
| 実DB read-only smoke | 求人7 / 12 / 13の3件比較、8 SELECT / 0 non-SELECT |
| Edge PC / 390px | 2 / 3件、下段・3列目、一覧、応募方法でJS errorなし・横overflowなし |
| 応募クリック模擬通信 | 7項目PASS（各イベント1回、null Query、CSRF、keepalive、別タブ属性、同一タブ二重遷移なし） |
| 選択保持ブラウザテスト | 15項目PASS（追加、上限、解除、ページ移動、詳細追加、BFCache、破損保存値、検索分離、保存不可等） |

Full suiteは135.34秒。Self-service同士 / Legacy同士 / 混在、未承認編集、非公開・未対応Snapshot、欠損・null・短縮、希望とFitの分離、経路・Provenance、危険URLとログ契約を確認。

Laravel log：作業開始以後の全体テスト2回で、既存rollbackテスト由来の `testing.ERROR: simulated row failure` が各1件。検証用未保存モデルにFact relationを付け忘れた初回HTML生成で `local.ERROR: Undefined array key "jobFacts"` が1件あり、検証fixtureを補正した。最終の画面生成・実DBsmokeでは再発せず、未解決のアプリ実行エラーなし。ログは削除していない。

検証記録（ローカル一時ファイル）：

- `/tmp/jobdd-batch8-related-final.log`
- `/tmp/jobdd-batch8-full-final.log`
- `/tmp/jobdd-batch8-build.log`
- `/tmp/jobdd-batch8-pint.log`
- `/tmp/jobdd-batch8-smoke.json`
- `/tmp/jobdd-batch8-ui/results.json`
- `/tmp/jobdd-batch8-ui/state-dom.html`
- `/tmp/jobdd-batch8-final-browser.log`
- `/tmp/jobdd-batch8-ui/route-click-dom.html`
- `/tmp/jobdd-batch8-click-final.log`
- `/tmp/jobdd-batch8-ui/compare3-pc.png`
- `/tmp/jobdd-batch8-ui/compare3-mobile.png`
- `/tmp/jobdd-batch8-ui/compare3-bottom-mobile.png`

## M. DB Safety

Schema / migrationの追加・変更なし。自動テストは `APP_ENV=testing DB_DATABASE=testing DB_URL=` で実行。実DBへ検証データ・Query・ログを書き込んでいない。

主要19テーブルについて、全列をID順に読み取ったSHA-256と件数を前後比較し、すべて一致。求人状態・審査状態の分布も一致。以下は同一ハッシュの先頭12桁（比較自体は64桁すべて）。

| テーブル | 前後件数 | SHA-256先頭（前後同一） |
|---|---:|---|
| `job_structured_profiles` | 0 | `e3b0c44298fc` |
| `job_tool_usages` | 0 | `e3b0c44298fc` |
| `job_typical_day_items` | 0 | `e3b0c44298fc` |
| `job_published_profiles` | 0 | `e3b0c44298fc` |
| `company_user` | 0 | `e3b0c44298fc` |
| `users` | 0 | `e3b0c44298fc` |
| `companies` | 499 | `3a6a169948e2` |
| `job_postings` | 1,591 | `87b0914dfe2d` |
| `application_routes` | 1,594 | `c8a69d39a2b1` |
| `sources` | 465 | `874728d3ddac` |
| `job_facts` | 1,732 | `a8f04e78c6e1` |
| `agencies` | 9 | `c3eda0fd942f` |
| `agency_facts` | 27 | `44e5f1963b0e` |
| `platforms` | 3 | `f2610e63b6ef` |
| `user_queries` | 42 | `054e87bc5359` |
| `score_results` | 151 | `8cdfb9c19376` |
| `interaction_logs` | 196 | `c48406ef07ad` |
| `crawl_runs` | 18 | `80bc955ab0a0` |
| `direct_reverse_lookup_candidates` | 362 | `55c0d066d726` |

完全な照合記録：`/tmp/jobdd-batch8-before.json`、`/tmp/jobdd-batch8-after.json`。

## N. Remaining Risks / OPEN

- 選択保持はブラウザのタブ内。別端末共有・ログインによる同期・永久保存は対象外。JS無効時は既存のページ内GETフォームを利用する。
- 選択後に求人が非公開・取得対象外になった場合、既存公開境界で404になる。一覧へ戻ってその選択を解除できる。自動削除・復元基盤は追加していない。
- LegacyのLevel 2欠損は未確認のまま。記載されたToolを本人の担当業務と推定しない。
- 外部サイトの現在の募集状況や到達性までは保証しない。検証はURL・リンク属性・経路表示・ログ契約までで、外部応募や本番送信は行っていない。
- 実DBにSelf-service Snapshotは0件のため、Self-service検証はtesting DBと未保存ブラウザfixtureで実施した。
- 詳細希望のFit昇格・ランキング利用、Batch 9の仕事は実装していない。

## O. Verdict

**PASS**。Batch 8のCompare / Evidence / Provenance / Application Routeと必要最小限の比較選択保持を実装し、回帰・全体テストとDB不変確認を完了。

とおる確認用：3求人比較のPC / 390px表示、比較からEvidence・応募方法への導線、別ページからの追加・解除。commit・本番操作なし。Batch 9には進んでいない。
