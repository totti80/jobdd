# JobDD Phase A Production Readiness

更新: 2026-09-23 / 対象: v5.3 Batch 1〜9。これは実行前の手順書。本番操作・実メール・権限変更・commit/tag/pushは未実行。

## 判定と実行条件

ローカルのcode hardening結果は [Batch 9報告](JobDD_Batch9_Implementation.md) を参照。本番GOはまだ出していない。とおる承認、対象ホスト・release構成の確定、環境チェック、移行前バックアップの別DB復元リハーサル、運営アカウント本人確認を実施してから進む。

現時点の既知の復帰点は `graduation-submit-ready-v1` / `0a852d3`。Batch 9開始HEADは `ea2260d`。実デプロイ対象は、今回の差分をレビュー後に作るcheckpointの完全SHAで固定する。

## 環境チェック

秘密値はチケット・ログ・報告・Gitへ掲載しない。以下は必要項目であり、本番設定の確認済み一覧ではない。

| 項目 | 必要条件 |
|---|---|
| APP | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://jobdd.jp`。既存APP_KEYを維持。key:generateは禁止 |
| runtime | composer.lockに合うPHP/拡張、MySQL JSON/subquery対応。PHP要件は^8.3、Laravel 13。実機で `composer check-platform-reqs --no-dev`。ローカルPHP8.5/MySQL8.4の成功だけで本番を判断しない |
| DB | 接続先DB/ユーザー/権限/TLS、DB_URLが個別設定を上書きしないこと。backup/restore権限と通常アプリ権限を分離 |
| MAIL | 実配送可能なmailer。SMTPの場合MAIL_HOST/PORT/SCHEME/USERNAME/PASSWORD、MAIL_FROM_ADDRESS/NAME、必要ならEHLO_DOMAIN。MAIL_URLとの優先関係を確認。log/arrayは本番通知に使わない |
| 宛先 | `JOBDD_REVIEW_NOTIFICATION_EMAIL=postmaster@jobdd.jp`。config/jobdd.php経由。送信元ドメイン認証・配送制限・迷惑メール判定は承認後に確認 |
| 通知動作 | DB commit後に同期送信。queue化されていない。失敗してもpending_reviewは保持し管理画面で確認可能。自動再送なし。SMTP timeoutはPHP/transport設定依存なので応答時間と監視を運用確認 |
| queue/cron | Phase A通知だけならworker追加不要。既存worker/cronの有無を確認し移行中は停止。既存MHI日次crawlerがあるため、Phase Aのために新しくschedule:runを開始しない |
| Session | SESSION_DRIVERと保存先を維持。databaseならsessionsテーブル、複数台なら共有保存先。SESSION_SECURE_COOKIE=true、HTTP_ONLY=true、SAME_SITE=lax、domain/pathが本番URLと一致。検索tokenを含むセッションをログに出さない |
| CSRF | web middlewareを維持。HTTPS同一originで登録/更新/申請/審査/preferences/interactionを確認。419時は再読込・再ログイン。除外ルートを増やさない |
| TLS/proxy | 直接TLSかreverse proxyかを確定。proxy構成なら実際の信頼CIDRとforwarded headersを設定・検証。無条件の全proxy信頼はしない。生成URLがhttpsになること |
| storage | Webユーザーがstorageとbootstrap/cacheへ必要範囲で書込可能。777不可。release間で必要なstorageを共有。public rootをpublic/に限定 |
| assets | lockfile固定のCI build。public/build/manifest.jsonとハッシュ付きCSS/JSを同一releaseに含める。public/hotを本番へ持ち込まない。Flux/Livewire assetsの配信も確認 |
| cache | 本番env設置後にconfig:cache, route:cache, view:cache。cacheファイルにはsecretが含まれ得るのでアクセス制限。古いreleaseのcacheを持ち込まない |
| 運用 | PHP-FPM/workerのreload方法、maintenanceの共通化、エラーログ閲覧、通知失敗の監視、申請queue巡回担当を確定 |

現在UserはMustVerifyEmailを実装していない。verified middlewareを通過してもメール所有確認済みとは限らない。Self-service仕様は今回変更していない。運営権限は下記の本人確認を必須とする。productionのパスワード既定にはuncompromised検証があり、外部照会の通信可否も確認する。

## Platform Owner bootstrap（計画のみ）

新しい汎用Seederや公開昇格APIは作らない。最小案は承認済みの既存User 1名を、メンテナンス中にTinkerのtransactionで昇格すること。対象がなければ、本人が通常の登録フローでアカウントを作成してから、運営が本人確認する。パスワードをSQLやシェル履歴へ置かない。

1. とおるが対象メールとID、本人、現在のroleを確認。ロール変更の承認と記録を残す。
2. 信頼された管理端末から対象releaseの `php artisan tinker` を開く。以下は置換必須の例。

```php
DB::transaction(function () {
    $user = App\Models\User::query()
        ->whereKey(CONFIRMED_USER_ID)
        ->where('email', 'CONFIRMED_EMAIL')
        ->lockForUpdate()->sole();
    if ($user->system_role !== 'user') {
        throw new RuntimeException('Unexpected current role');
    }
    $user->system_role = 'platform_owner';
    $user->saveOrFail();
});
```

3. 再ログインし `/admin/job-reviews` を開く。通常User/Company Userの別セッションでは403を確認。メールリンクは審査画面を開くだけで承認しない。
4. 結果のID/role/実施者/時刻のみ記録。Company membershipのないPlatform OwnerではDashboardが空で新規求人作成が403になる既存仕様。審査の開始URLを使う。

## Migration plan

v1との差分は `database/migrations/2026_09_23_000001`〜`000010` の10本。実機のmigrate:statusと照合し、想定外のpendingがあれば停止。

| 番号 | 変更・互換性 |
|---|---|
| 1 | company_user、company/userのFKと組合せunique |
| 2 | job_structured_profiles、jobごとunique、入力途中を許すnullable項目 |
| 3 | job_tool_usages、jobへのFK、行順序 |
| 4 | job_typical_day_items、jobへのFK、行順序 |
| 5 | job_published_profiles、jobごとunique、JSON snapshotと公開日時 |
| 6 | users.system_role、既存userはuser |
| 7 | job_postings.status、既存求人はpublished |
| 8 | job_facts.context_role、nullable、既存Fact維持 |
| 9 | review_status既定not_submitted、申請/審査日時、reviewer FK、note |
| 10 | application_requirements nullable TEXT |

既存求人はpublished + not_submittedでも公開継続。LegacyにはSnapshot不要。新規Company求人はcontroller側でdraftを明示する。新migration/schema変更はBatch 9ではない。

MySQL DDLは一括transaction rollbackを期待できない。DDL lockとコード/DB差の時間を避けるため短いmaintenance枠を確保する。ローカル1591求人は全件維持済み。本番実件数は実行前に取得し1591と異なる場合は増減理由を確認する。移行前後は既存列の件数/値のhashを同じ射影で比較する（追加列込みhashは移行で変わる）。users/companies/jobs/routes/sources/facts/queries/logs等も確認し、新テーブルは初回空、既存status/review_status/roleの分布を確認する。

## Backupと復元リハーサル

1. メンテナンス前にrelease SHA、v1 tag SHA、web/runtime/cron構成、env名のチェック結果を記録。secretを含むenvは暗号化・アクセス制限したbackupへ退避。
2. maintenanceでHTTP更新を止め、worker/cron/importを停止。残存処理の完了を待つ。
3. MySQLのDB全体をbackup。認証は権限0600のdefaults-extra-fileを使用し、パスワードを引数へ書かない。以下の大文字は実環境確定後に置換する。

```sh
umask 077
mysqldump --defaults-extra-file=PROTECTED_MYSQL_CONFIG --single-transaction --quick --routines --triggers --events --hex-blob --no-tablespaces PROD_DATABASE > PROTECTED_BACKUP.sql
sha256sum PROTECTED_BACKUP.sql
```

4. storageの必要ファイル、旧code artifact/vendor/build、設定・サービス定義も保全。別ホスト等へ暗号化転送し保存期限と責任者を記録。
5. 別の隔離DBへ復元して、テーブル数/主要件数/既存列hash、v1によるLegacy一覧→詳細→比較→応募方法を検証。メール/cron/外部更新は無効。dump成功だけを復元成功としない。
6. 移行後も別backupを採取し、移行前と区別する。

## Deploy手順（未実行）

ホスト/path/サービス名は未確定。以下は新releaseディレクトリで行うアプリ側コマンド。現行稼働ディレクトリを直接git resetしない。install/buildはCIまたは承認されたrelease領域で行う。

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
npm ci
npm run build
```

秘密設定とstorageを接続し権限を確認。現行releaseでmaintenance、cron/worker停止、backup/復元確認後、新releaseで次を実行する。

```sh
php artisan migrate:status
php artisan migrate --pretend --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

pretendと実行結果を保存し10本と照合。失敗時は継続・再実行を機械的に行わず適用済みDDLを確認する。既存列hash/status分布/新テーブルを確認し、bootstrap承認済みなら上記手順を実施する。

環境固有のatomic release切替、PHP-FPM reloadを行う。共有maintenanceを保ったまま許可された検証経路でsmoke、問題なければ `php artisan up`。既存worker/cronだけを元の状態に戻す。既存queueがある場合のみ旧code workerをreloadする。migrate:fresh、migrate:reset、migrate:rollback、APP_KEY変更は行わない。

## Production smoke checklist（承認後に実施）

- [ ] 既存: トップ→簡易入力→一覧→Legacy詳細→2/3件比較→Evidence→応募方法。既存代表IDと件数を記録。
- [ ] 390px/PCで主要画面、assets、フォームラベル、エラー、flash、比較表内スクロール、keyboard focus。
- [ ] とおる管理下のCompanyアカウント→Dashboard→Draft Level1/Level2各STEP→Preview。Draft URLが別sessionから見えない。
- [ ] 申請→pending_review。ここで初めて承認された実配送を確認。postmaster受信、会社/求人/時刻/https管理リンク、ログにsecretがない。
- [ ] Platform Owner→queue→差戻し理由→企業側修正/再申請→確認済み内容を承認。公開情報として適切な実在の求人のみ承認し、検証用の架空求人は公開しない。
- [ ] 新しい公開求人→詳細希望保存/解除→Compare(Legacy混在)→企業提供/JobDD公開確認のProvenance→Direct。Fit/表示順が変わらない。
- [ ] 再編集/申請中は前Snapshot、再承認後だけ更新。Source/Fact/Snapshot/Directの重複なし、外部Fact維持。
- [ ] Company別アカウント/通常Userは他社・admin403、Guestはlogin、CSRF不正419、非公開/不正query404、競合409、入力不正422。
- [ ] 外部HTTP(S)先・新規tab・CSRF/keepalive・クリック1回を確認。外部応募フォームの送信までは行わない。
- [ ] Laravel/PHP/Webログに未解決500なし、応答時間/通知待ち時間/DB query増加に異常なし。

smokeで発生したアカウント/申請/ログは記録し、削除を自動実行しない。運営承認が必要な公開・配送・cleanupはこの計画のレビューで対象を確定する。

## Rollback（ユーザー承認済み方式）

**v1コードだけをv5 DBへ接続して戻すことは禁止。** v1はdraft除外を持たず、追加テーブルを無視できても非公開情報の漏洩につながる。ユーザー承認: maintenance中に移行前backupを別DBへ復元し、移行後DBを保全して切替する。今回は手順のみ。

1. 不具合検知でmaintenance、worker/cron停止。事象/時刻/デプロイSHAを記録。
2. 現在の移行後DBをbackupし保全。新しい登録・求人編集・申請・検索などを失わないため、移行前backup以降の差分を後で照合する。自動マージしない。
3. 移行前backupを**新しい隔離DB**へ復元。元のv5 DBをdrop/上書きしない。件数/hashと復元の成功を確認。
4. v1 `0a852d3` artifactを別releaseに用意し、復元DBへ接続する設定を配置。APP_KEY維持。既存セッションの扱い・再ログインを確認。環境キャッシュを再生成。
5. v1 read-only smoke後、code/DB接続を一緒に切替。PHP-FPM等をreloadしmaintenance解除。v1に必要な既存ジョブだけ再開。
6. v5側に残った新規データは一般公開から隔離し、復旧後の再反映を人手で判断する。移行前状態へ戻るため、その間の更新は一時的にサービスから見えなくなることを運営へ伝える。

不可逆migration downを復帰手段にしない。問題がcodeのみでも、v1を採るなら同じ分離復元手順が必要。

## Checkpoint / 第2 Submit Ready Point（未実行）

まずBatch 9差分をレビューし、対象ファイルだけstageしてcheckpoint commitする。推奨message: `Harden Phase A public boundaries and document production readiness`。テスト結果と完全SHAを記録する。

本番smoke PASS、とおる確認後だけ、**実際にデプロイして確認したSHA**へ以下のtagを作る。

```sh
git tag -a graduation-submit-ready-v2 VERIFIED_DEPLOYED_SHA -m 'JobDD Phase A production smoke verified'
```

commit/tag/pushの実行は別途承認後。v1 tagを変更しない。現時点はproduction NO-GO（準備完了後の承認・環境確認待ち）であり、コード検証PASSと本番検証PASSを区別する。
