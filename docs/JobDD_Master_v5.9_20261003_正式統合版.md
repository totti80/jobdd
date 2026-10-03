# JobDD Master Context

**Version:** 5.9  
**初版:** 2026-08-13  
**更新:** 2026-10-03  
**Project:** JobDD  
**Owner:** 池田 徹

---

# 0. この文書の位置づけ

この文書は、JobDDに関する **Single Source of Truth（正本）** とする。

JobDDについて企画・検証・開発・資料作成・議論を行う際は、本書の最新版を最優先の参照元とする。

情報の優先順位は以下。

1. `JobDD_Master.md` 最新版
2. ユーザーがその場で明示した最新情報
3. 最新のインタビュー・検証結果
4. `JobDD_Interview_Log.md`
5. `JobDD_Decision_Log.md`
6. その他のJobDD関連資料
7. 過去チャット
8. 推測・一般論

新しい事実が本書と矛盾する場合は、勝手に統合せず、矛盾を明示した上で本書を更新する。

---

# 1. 情報分類

JobDDでは以下を明確に区別する。

## FACT

インタビュー、実装、実データ、実際の利用、公開情報等によって確認できた事実。

## DECISION

現時点で採用している意思決定。

## HYPOTHESIS

合理的ではあるが、まだ十分に検証されていない仮説。

## OPEN

未確認・未決定であり、今後検証または意思決定が必要な事項。

HYPOTHESISをFACTとして扱わない。

---

# 2. v5.0での重要な変更

## DECISION

JobDD v5.0では、これまでの

**「JobDD側が外部求人情報を収集して比較可能にする」**

というデータ供給モデルを見直し、

**企業・人材紹介会社自身からの情報提供を中心に据えたSupply Sideを追加する。**

これはJobDDの目的そのものを変更するピボットではない。

JobDDの中心価値は引き続き、

**求人や応募経路を、根拠付きで理解・比較し、求職者自身が判断できる状態を作ること**

である。

変更するのは主に、

**「情報をどこから、どう入手するか」**

である。

---


# 2.1 v5.1で確定した追加仕様

## DECISION

v5.1では、企業側Level 2 Structured Job Profileについて、
単なる項目候補ではなく、初期実装仕様 `Level 2 Structured Job Profile v0.1` を定義する。

v5.1で新たに確定する主な内容：

- 企業入力フォームを5ステップ構成とする
- Level 2 Core必須項目を定義する
- Toolは「実際の使用」と「応募時経験要件」を分離する
- Typical DayをLevel 2 Coreへ含める
- 企業入力用Authoring ModelとDecision Support Modelを分離する
- Publish時にJob Fact / Context Role / Evidence / Provenanceへ変換する
- Level 2情報のうち、求職者条件として取得していない項目はFit判定へ利用しない
- Previewは求職者向けDecision Viewそのものとして表示する
- Phase AではLevel 1必須＋Level 2 Core完了をPublish条件とする


# 2.2 v5.2で確定した追加仕様

## DECISION

v5.2では、Phase A実装前の設計を以下まで具体化した。

- 企業登録は承認待ちを前提としないSelf-service方式とする
- 登録時は「会社名またはユーザーID」「メールアドレス」「パスワード」を基本入力とする
- 登録時にUser / Company / company_userを生成し、登録者をcompany_ownerとして紐付ける
- JobDD運営側のPlatform Owner / Adminと、企業内のCompany Roleを別レイヤーで管理する
- Platform Owner / Adminは全企業・全求人・全Structured Profile・Evidence / Provenance・Application Routeを閲覧・操作できる
- Phase A DBは新規5テーブル＋既存3テーブル変更を基本とする
- Previewは求人statusではなく、Draftを求職者向けDecision Viewで確認する表示Actionとする
- 公開済み求人の編集中は、求職者側へ最後にPublishした内容を維持する
- 公開Snapshotとして `job_published_profiles` を保持する
- Job Fact Dictionaryを単一正本として管理する
- 求職者入力は簡易入力から必要な人だけ詳細入力へ進むProgressive Disclosure方式とする
- 詳細希望条件は当初は比較材料として利用し、直ちにMATCH / MISMATCH判定へ昇格させない
- Phase AをBatch 0〜9へ分割し、Batch 5のPublish Coreを重点レビュー箇所とする

---

# 2.3 v5.3で確定した追加仕様

## DECISION

v5.3では、企業Self-service登録は維持しつつ、求職者へ公開される求人情報の品質とProvenanceを守るため、**Controlled Publish** をPhase Aへ追加する。

基本フロー：

```text
企業Self-service登録
↓
Company Dashboard
↓
求人Draft作成 / Level 1 / Level 2入力
↓
Preview
↓
公開申請
↓
postmaster@jobdd.jp へ通知
↓
Platform Owner / AdminがJobDD管理画面で確認
↓
承認 または 差戻し
↓
承認時のみPublish
```

主な追加決定：

- 企業アカウント登録自体は承認待ちにしない
- 企業はDraft作成・編集・PreviewまでSelf-serviceで行える
- 求職者向け公開だけPlatform Owner / Adminの承認を必須とする
- 公開状態 `status` と審査状態 `review_status` を分離する
- 公開済み求人を再編集・再申請している間も、求職者には最後に承認・PublishされたSnapshotを維持する
- 公開申請時は `postmaster@jobdd.jp` へ通知メールを送る
- メールは通知のみとし、承認 / 差戻し操作は必ずJobDD管理画面上で行う
- 求人のLevel 1に `application_requirements`（最低限の応募条件）を正式追加する
- Platform Ownerの公開確認は企業申告内容の真偽保証ではなく、公開可否・必須入力・明らかな矛盾等を確認する運用とする

---

# 2.4 v5.5で確定した本番配置・リリース方針

## FACT

2026-09-23時点で、Phase A Batch 1〜9のローカル実装・Hardeningは完了している。

- Batch 9最終：**832 tests PASS / 5,561 assertions**
- PC / 390px確認、Pint、`npm run build`、`git diff --check` PASS
- ローカル実DB主要19テーブルの件数・SHA-256不変
- Batch 9 checkpoint：`83a5734 Harden Phase A public boundaries and document production readiness`
- Production deploy / migration / 実メール送信 / Platform Owner昇格 / 第2Submit Ready Tagは未実施

## FACT｜現行本番構成

現行の提出保険版V4は、さくらレンタルサーバ上で以下の構成で稼働している。

```text
Laravel本体：/home/ikeda-dev/jobdd-v4-deploy
公開フォルダ：/home/ikeda-dev/www/jobdd
公開URL：https://jobdd.jp/
```

本番はGit checkoutで直接運用する方式ではなく、**ローカル等でvendor / assetsを用意した成果物を配置する方式**である。

サーバー側にはPHP / MySQL / mysqldumpは存在するが、Composer / Node / npmはPATH上に存在しない。

## DECISION｜V5.3の本番配置方式

現行V4を削除・上書きせず、非公開のRollback用保険として保持する。

最新Phase Aは新しい本体ディレクトリへ別配置する。

候補：

`/home/ikeda-dev/jobdd-v5-deploy`

その後、承認済み手順に従い、公開側 `/home/ikeda-dev/www/jobdd` のLaravel参照先・assets・storage参照をV5.3へ切り替える。

切替後は、

- `https://jobdd.jp/`
- `https://jobdd.jp/jobs/start`
- `https://jobdd.jp/company/register`

を含む公開URL全体をV5.3で動かす。

旧V4を `/jobs/start` だけで恒久公開する構成にはしない。

## DECISION｜APP_URL / HTTPS

本番の正規URLは、

`https://jobdd.jp`

とする。

現行 `.env` の旧値 `https://ikeda-dev.sakura.ne.jp/jobdd` は、V5.3 deploy承認後に `https://jobdd.jp` へ変更する。

さくら管理画面では、

- Web公開フォルダ：`~/www/jobdd`
- SSL：有効
- HTTPS転送：有効
- `www.jobdd.jp` → `jobdd.jp` 転送：有効

を確認済み。

現時点では `URL::forceScheme('https')` やTrusted Proxy全許可を追加する根拠はない。最終確認はProduction Smokeで行う。

## DECISION｜本番メール通知

公開申請通知先は引き続き、

`postmaster@jobdd.jp`

とする。

さくら側でSPF / DKIM/ARC / DMARC、および `postmaster@jobdd.jp` メールボックスの存在を確認済み。

PHPのsendmail経路は、

```text
PHP
→ /usr/sbin/sendmail -t -i
→ mailwrapper
→ /usr/libexec/sendmail/sendmail
```

まで確認済み。

本番通知方式は**ローカルsendmail transportを第一候補**とする。ただし、実配送・受信確認が完了するまではPublic Release GOとしない。

## DECISION｜Backup / Restore / Rollback

V5.3 migration前に本番DB backupを取得し、**隔離環境でrestore rehearsalを成功させてからdeployへ進む**。

BackupはWeb公開領域外へ保存する。

正式保存先：

```text
/home/ikeda-dev/jobdd-backups/
  pre-v53-YYYYMMDD-HHMMSS/
```

2026-09-23にGate 1を実行し、本番DB backup取得はPASSした。

### FACT｜Gate 1 Backup結果

保存先：

```text
/home/ikeda-dev/jobdd-backups/pre-v53-20260923-174046/
```

結果：

- dump正常終了
- dumpサイズ：5,141,507 bytes
- dump SHA-256：`2fb364c09ba8f01363c1b11312f0ffc302b2e57c0689b9bc1dc86cdd7791f9c9`
- baseline：24 tables / 27 migrations
- backup前後で件数・全列hash・schema・migration履歴が一致
- dump checksum不変
- 一時認証ファイルは削除済み
- Web maintenance解除・UP確認済み
- migration / deploy / 本番DB更新 / 実メール送信は未実施

### DECISION｜Restore Rehearsal

さくら管理画面では、別DBユーザーを作成し、特定DBだけへSELECT権限を限定する安全な分離方法を確認できなかった。

そのため、Restore rehearsalは**隔離したローカルMySQL 8.0 Dockerコンテナ + 専用volume**を第一候補として正式採用する。

Gate 2で使用する予定：

```text
ローカル保存先：
/home/aatik/jobdd-restore-rehearsal/pre-v53-20260923-174046/

Restore専用DB：
jobdd_restore_v1
```

既存開発DB `jobdd_v4` / `testing` にはrestoreしない。

本番 `.env` や認証ファイルはローカルへ転送せず、転送対象はdump・checksum・baselineのみとする。

重要：**v1コードだけをV5 migration後DBへ接続してRollbackしない。**

v1へ戻す場合は、移行前backupを隔離DBへ復元し、v1コードとその復元DBをセットで切り替える。移行後DBは破棄せず保全する。

Gate 2（ローカル転送・restore・v1表示検証）は承認済みだが、2026-09-23時点では未実施。

## DECISION｜Production Scheduler / CRON

旧本体 `/home/ikeda-dev/jobdd` のLaravel scheduler起動CRONについて、さくら管理画面では毎分実行が「実行間隔が短すぎます」と拒否された。

元のCRONは毎分 `* * * * *` だったが、Laravel側では `crawler.run-mhi` が `dailyAt('05:00')` / `withoutOverlapping()` で定義されている。

そのため、本番CRONは以下へ意図的に変更した。

```text
0 * * * *
```

つまり、**毎時00分に `php artisan schedule:run` を起動する。**

実行コマンド自体は変更していない。

これにより、

- さくら側の実行頻度制限へ対応
- 05:00にLaravel schedulerが起動
- Laravel内の `dailyAt('05:00')` の意味を維持

する。

Gate 1終了時点で、CRON有効行は1件、毎時00分設定で復帰済み。

最終cron SHA-256：

`2ed3e34b05df6ae8843dbf32f550a89e27d0fd0c588635fcbccc045a2ba787da`

元の毎分CRON原本もbackup内に保持する。

## DECISION｜Platform Owner

Phase A本番のPlatform Ownerは、Owner本人のアカウントを対象とする。

本番deploy後、Owner本人が通常登録フローでUserを作成し、本人確認後に承認された手順で `system_role=platform_owner` へ昇格する。

事前に汎用Seederや公開昇格APIは作らない。

## DECISION｜卒業制作審査期間中のDemo Company / Demo求人

卒業制作の審査・本番動作確認を目的として、**審査期間中に限り、Demoであることを明示した架空のCompany / 求人を公開可能とする。**

これは通常運用における恒常的な架空求人公開を認めるものではなく、**卒業制作デモ用途に限定した例外ルール**とする。

最低条件：

- 求人タイトル等に「卒業制作デモ」「応募不可」等を明示する
- 求人詳細冒頭に「実際の募集ではない」ことを明記する
- 実在企業と誤認しにくいDemo Company名を用いる
- 実在企業の社名・ロゴをDemo用途へ流用しない
- 実応募へ接続しない
- 審査期間終了後は `published → paused` として公開停止する

詳細ルールは **2.7 v5.8で確定した卒業制作Demo求人公開仕様** を正本とする。

将来の実サービス運用でProduction Smokeを行う場合は、

- 実在Company
- 公開して問題のない実在求人
- Ownerが内容を確認し明示承認したもの

を利用する方針を維持する。

## FACT｜Preflight判定

2026-09-23 Production Preflightの判定は、**Public Release NO-GO**。

これはコード品質のNO-GOではなく、本番公開前の運用・環境条件が未完了であるため。

主な未完了事項：

- APP_URL変更の実施と実環境確認
- sendmailによる実配送 / 受信確認
- migration前backup取得：**完了 / PASS**
- 隔離ローカルMySQLでのrestore rehearsal：未実施
- cron停止 / 再開：Gate 1で実施・復帰済み。毎時00分へ意図的変更
- release切替手順の最終確認
- Platform Owner作成 / 本人確認 / 昇格
- 卒業制作審査用Demo Company / Demo求人の確定（通常運用のProduction Smokeは将来、実在Company / 実在求人で実施）
- Production Smoke PASS
- `graduation-submit-ready-v2` tag作成

---

# 2.5 v5.6で確定した企業Dashboard UI / Review State表示仕様

## DECISION

企業向けUIは、既存の `JobDD_画面設計案_V5_PC版` を親デザインとして維持しつつ、現行v5.5のControlled Publish仕様へ合わせて更新する。

企業側Phase Aの画面導線は以下を基本とする。

```text
企業ログイン / 新規企業登録
↓
企業Dashboard
↓
Level 1 Basic
↓
Level 2 Structured Job Profile
↓
Preview
↓
公開申請
↓
審査状況
↓
Platform Owner / Admin審査
↓
承認時のみPublish
```

企業Dashboardの役割は、高度な採用Analyticsではなく、

**「自社求人が現在どの状態にあり、次に何をすれば公開・更新まで進められるかを一目で理解できること」**

とする。

### 企業向け表示文言

内部値を企業画面へそのまま表示せず、以下の日本語表示へ変換する。

| 内部値 | 企業向け表示 |
|---|---|
| `draft` | 未公開 |
| `published` | 公開中 |
| `paused` | 公開停止中 |
| `not_submitted` | 未申請 |
| `pending_review` | 審査中 |
| `changes_requested` | 修正をお願いします |
| `approved` | 承認済み |

Admin側では `changes_requested` を「差戻し」と表現してよいが、企業側では「修正をお願いします」を基本とする。

### Dashboard KPI

Phase AのDashboard上部は、原則として以下4状態を表示する。

- 公開中
- 作成中
- 審査中
- 修正依頼

Structured Profile平均完成度は主要KPIとせず、Completion %は求人単位で表示する。

### Dashboard求人一覧

基本列は以下を候補とする。

- 求人タイトル / 職種
- 入力状況
- 公開状況
- 審査状況
- 最終更新
- 操作

公開状態 `status` と審査状態 `review_status` は意味が異なるため、企業UIでも分離して表示する。

### 状態別Primary CTA

| 状態 | 公開状況 | 審査状況 | Primary CTA |
|---|---|---|---|
| 新規・入力途中 | 未公開 | 未申請 | 入力を再開 |
| 入力完了・未申請 | 未公開 | 未申請 | Preview |
| 初回申請・審査中 | 未公開 | 審査中 | 審査状況を見る |
| 初回申請・修正依頼 | 未公開 | 修正をお願いします | 修正する |
| 初回承認・公開済み | 公開中 | 承認済み | 編集 |
| 公開済み・編集中 | 公開中 | 承認済み + 更新作業中 | 編集を続ける |
| 更新申請・審査中 | 公開中 | 更新内容を審査中 | 審査状況を見る |
| 更新申請・修正依頼 | 公開中 | 更新内容の修正をお願いします | 修正する |
| 更新承認後 | 公開中 | 承認済み | 編集 |
| 公開停止・変更なし | 公開停止中 | 承認済み | 公開を再開 |
| 公開停止・編集中 | 公開停止中 | 更新作業中 | 編集を続ける |
| 公開停止・再公開審査中 | 公開停止中 | 再公開審査中 | 審査状況を見る |
| 公開停止・再公開修正依頼 | 公開停止中 | 修正をお願いします | 修正する |

公開済み求人の編集中を示すために、新しいDB status / review_statusは追加しない。

**Authoring ModelとPublished Snapshotに差分がある場合に限り、UI上で「更新作業中」を導出表示する。**

これにより、公開状態・審査状態の既存モデルを増やさずに、企業へ編集中であることを伝える。

### 審査中の編集

Phase Aでは `pending_review` 中の申請対象Authoring内容を企業側から編集不可とする。

理由：

- Adminが確認している申請内容と企業側の編集内容が途中でずれることを防ぐ
- 審査対象を固定し、承認 / 差戻し判断を再現可能にする
- 卒業制作Phase Aで複雑な申請version管理を追加しない

審査中はPreviewと審査状況確認を可能とし、差戻し後に編集を再開する。

### 公開済み求人の再審査

公開済み求人の更新申請中は、既存仕様どおり、

```text
status = published
review_status = pending_review
```

を許容する。

この間、求職者側には最後に承認・PublishされたPublished Snapshotを維持する。

企業側には、

> 現在公開中の内容はそのまま表示されています。

等の補足を表示する。

### 無効 / 異常状態

以下は通常フローでは成立させない。

- `draft + approved`
- Publish Validatorを満たしていない `draft + pending_review`

発生した場合は通常状態として表示せず、状態不整合として管理者確認・ログ対象とする。

`published + not_submitted` は公開済み求人の編集開始状態として正式利用しない。
公開済み・編集中は、DB状態を増やさずAuthoring / Published Snapshot差分からUI表示を導出する。

## 判断理由

- 企業担当者が内部statusを知らなくても現在地と次アクションを理解できるようにするため
- 公開状態と審査状態を混同しないため
- Published Snapshotによる安全な公開境界をUIでも明確にするため
- Controlled Publishの審査対象を途中編集で変化させないため
- 新しい状態カラムやversion管理をPhase Aへ安易に追加しないため

## HYPOTHESIS

このDashboard UIによって、企業担当者が「求人を書く」ではなく、

**仕事の中身を構造化し、Previewし、公開確認を経て求職者へ届ける**

というJobDD独自の流れを迷わず理解できる可能性がある。

これは企業実利用で検証する。


---

# 2.6 v5.7で確定した求人公開停止（paused）仕様

## DECISION

企業が一度Publishした求人について、採用充足・一時停止・社内事情等により求職者向け表示を止められるよう、求人Lifecycleへ `paused` を正式追加する。

`paused` は、

**一度承認・Publishされた求人を、企業の判断で求職者向け表示から一時的に外した状態**

とする。

公開停止は物理削除ではない。

- `job_postings`
- Published Snapshot
- Job Fact
- Evidence / Provenance
- Application Route
- 過去の公開・審査情報

は原則保持し、求職者向けの一覧・詳細・比較・応募導線から非表示にする。

`closed` と物理DELETEは今回の必須実装に含めず、将来拡張とする。

### 基本状態遷移

```text
draft
  ↓ 公開申請 / Admin承認
published
  ↓ 企業が「公開を停止」
paused
```

`paused` からは、以下の2経路を持つ。

```text
A. 承認済み公開内容に変更なし
paused + approved
Authoring == Published Snapshot
  ↓
公開を再開
  ↓
published + approved
```

```text
B. 停止中にAuthoringを変更
paused + approved
Authoring != Published Snapshot
  ↓
Preview
  ↓
再公開申請
  ↓
paused + pending_review
  ↓
Admin承認
  ↓
published + approved
```

### 停止中の表示 / CTA

| 状態 | 公開状況 | 審査状況 | Primary CTA | Secondary |
|---|---|---|---|---|
| `published + approved` | 公開中 | 承認済み | 編集 | 公開ページを見る / 公開を停止 |
| `paused + approved`・差分なし | 公開停止中 | 承認済み | 公開を再開 | 編集 / Preview |
| `paused + approved`・差分あり | 公開停止中 | 更新作業中 | 編集を続ける | Preview |
| `paused + pending_review` | 公開停止中 | 再公開審査中 | 審査状況を見る | Preview |
| `paused + changes_requested` | 公開停止中 | 修正をお願いします | 修正する | 審査内容を見る / Preview |
| 再公開承認後 | 公開中 | 承認済み | 編集 | 公開ページを見る / 公開を停止 |

### 公開停止操作

公開中求人では、

- 編集
- 公開ページを見る
- 公開を停止

を利用可能とする。

「公開を停止」は破壊的操作として強調しすぎず、Primary CTAにはしない。

確認文言候補：

> この求人の公開を停止しますか？  
> 求職者向けの求人一覧・比較・応募導線から非表示になります。  
> 求人データと過去に承認された公開内容は保持されます。

公開停止成功時は、原則として、

```text
status = paused
```

へ変更し、Published Snapshotを削除しない。

### 再公開

`paused + approved` かつ AuthoringとPublished Snapshotに差分がない場合は、前回承認済み内容をそのまま再表示するだけなので、再審査なしで `published` へ戻せる。

一方、停止中にAuthoringを変更した場合は、未承認内容を直接公開しない。

その場合は、

```text
paused
↓
Preview
↓
再公開申請
↓
pending_review
↓
Admin承認
↓
published
```

のControlled Publishを必須とする。

### 審査中の緊急公開停止

公開済み求人の更新申請中でも、採用充足等により「今すぐ求職者向け表示を止めたい」ケースを想定する。

そのため、公開停止は審査中でも実行可能とする。

```text
published + pending_review
↓ 公開停止
paused + not_submitted
```

この場合、

- 現在公開中の求人を即時非表示
- Published Snapshotは保持
- 最新Authoringも保持
- 進行中の公開申請は取り下げ扱い
- `review_requested_at` 等の審査履歴情報は消去しない
- 再公開する場合は、Previewから改めて公開申請する

とする。

### 削除の扱い

Phase A / v5.7では、公開済み求人の物理DELETEを通常運用にしない。

一度もPublishしていないDraftの削除は将来検討可能だが、一度Publishした求人は原則として、

**公開停止 / 将来の募集終了**

で扱い、履歴・Evidence・Provenanceを保持する。

## 判断理由

- 採用充足等で求人を即時非表示にできる必要がある
- 公開済み求人を物理削除するとEvidence / Provenance / Snapshotの追跡性を損なう
- 前回承認済み内容をそのまま再開する場合まで毎回審査すると企業負担が大きい
- 未承認の編集内容を再公開する場合はControlled Publishを維持する必要がある
- Phase Aで `closed`、version管理、アーカイブ等まで一度に広げないため

## 影響範囲

- `job_postings.status`
- Company Dashboard
- Dashboard CTA / Badge
- 求職者側公開フィルタ
- Preview
- Review Lifecycle
- Admin Review
- Application Route公開境界
- Feature Test / E2E


---

# 2.7 v5.8で確定した卒業制作Demo求人公開仕様

## DECISION

卒業制作の審査・本番動作確認を目的として、**審査期間中に限り、Demoであることを明示した架空のCompany / 求人を本番環境で公開可能とする。**

このDecisionは、

**通常運用で架空求人を恒常公開してよい**

という方針変更ではない。

卒業制作審査中に、

- Controlled Publish
- 求職者向け求人表示
- Decision View
- 公開停止（paused）

までを本番URL上で確認可能にするための、**期間限定Demo運用ルール**である。

## Demo求人の表示ルール

実在する求人と誤認されないことを最優先とする。

必須条件：

- 求人タイトルの先頭等、求職者が最初に認識できる位置へ **「卒業制作デモ」** または **「応募不可」** を明示する
- 求人詳細の冒頭にも、**「この求人はJobDD卒業制作の動作確認用デモであり、実際の募集ではありません」** と明記する
- Demo Company名は、実在企業と誤認しにくい名称とする
  - 例：`JobDDデモ企業`
  - 例：`JobDDテスト株式会社`
- 実在企業の社名・ロゴをDemo用途へ流用しない
- 実在求人の原稿を、実求人であるかのようにそのまま転載しない

## 応募導線

Demo求人では、**実際の応募が発生しない状態を必須**とする。

v5.9では、卒業制作専用の `is_demo` フラグ、Demo専用Account Type、Demo専用Publish分岐等は追加しない。

Demo求人も通常求人と同じControlled Publish / Publish Validator / Application Routeの仕組みを通す。

既存Publish仕様上、応募URLが必要な場合は、

**JobDD内の安全な非応募URL**

を設定してよい。

例：

```text
https://jobdd.jp/
```

重要なのは、

- 実在企業の応募ページへ接続しない
- 求人媒体・人材紹介会社等の実応募先へ接続しない
- Demo求人のタイトル・本文で「卒業制作デモ」「応募不可」「実際の募集ではない」ことを明示する

ことである。

したがって、Demo求人では技術的にApplication Route / CTAが生成される場合があっても、**その遷移先は実応募先ではなくJobDD内の非応募URLとする。**

Demo求人の目的は、

**JobDDの表示・比較・公開Lifecycleを審査可能にすること**

であり、応募獲得ではない。

将来、Demo専用の案内ページやCTA非表示が必要になった場合は別途検討するが、卒業制作提出前の必須実装には含めない。

## 公開期間 / paused

Demo求人の公開は、卒業制作の審査・確認期間に限定する。

審査期間終了後は、

```text
published
↓
paused
```

として求職者向け公開を停止する。

物理削除は行わず、v5.7で確定した `paused` Lifecycleルールに従い、

- `job_postings`
- Published Snapshot
- Job Fact
- Evidence / Provenance
- Application Route
- 過去の公開・審査情報

を原則保持する。

## SEO / 外部露出

Demo求人は卒業制作審査の確認用途であり、恒常的なSEO流入を目的とするコンテンツではない。

## OPEN

- Demo求人へ `noindex` 等のSEO制御を今回の提出前に追加するか
- Demo求人の具体的な公開終了日
- 審査終了後の `paused` 実行タイミング / 運用担当
- 将来、実在Company / 実在求人を用いた正式なProduction Smokeを行う時期

これらは未決定であり、勝手にFACTまたはDECISIONとして扱わない。

## Production Smokeとの関係

卒業制作審査用Demo求人は、

- UI
- Controlled Publish
- Admin Review
- Decision View
- paused

の本番動作確認には利用できる。

ただし、**実在求人としての本番運用確認とは区別する。**

将来の実サービス運用でProduction Smokeを行う場合は、

- 実在Company
- 公開して問題のない実在求人
- Ownerが内容を確認し明示承認したもの

を利用する。

## 実装・運用前チェック

卒業制作審査用Demo求人を本番公開する前に、最低限以下を確認する。

- [ ] 求人タイトルにDemo / 応募不可表示がある
- [ ] 求人詳細冒頭に実募集ではない旨を表示している
- [ ] Demo Company名になっている
- [ ] 応募URL / Application RouteがJobDD内の安全な非応募URLを指し、実応募先へ接続しない
- [ ] 実在企業の社名・ロゴをDemo用途へ使用していない
- [ ] Admin承認後の公開表示を確認する
- [ ] 審査終了後に `paused` へ変更する運用を確認する

## 情報分類

### FACT

- v5.7までのMasterでは「架空求人は一般公開しない」としていた
- JobDDには `published / paused` のLifecycleが実装されている
- 卒業制作では本番URL上での審査を予定している

### DECISION

- 卒業制作審査期間中に限り、Demoであることを明示した架空求人を公開可能とする
- Demo求人から実応募へ接続しない
- 審査期間終了後は `paused` とする
- 通常運用の架空求人恒常公開は認めない

### HYPOTHESIS

なし。

本変更は事業仮説ではなく、卒業制作審査時の運用ルールである。

### OPEN

- `noindex` 等のSEO制御
- 具体的な公開終了日
- 審査終了後の停止運用
- 将来の実在求人Production Smoke実施時期


---

# 2.8 v5.9で確定した卒業制作Demoデータ運用仕様

## DECISION

卒業制作Demoのために、JobDD本体へDemo専用の状態・権限・Account Type・Publish分岐を追加しない。

Demoであることは、**入力データの内容によって明示する。**

卒業制作審査では、通常の企業アカウント / 通常の求人Lifecycleを利用し、

```text
企業登録 / Login
↓
Company Dashboard
↓
Level 1 / Level 2
↓
Preview
↓
公開申請
↓
Admin Review
↓
Publish
↓
Seeker Decision View
↓
paused
```

までを、本番と同じシステム挙動で確認できる状態を維持する。

## Demo Company

卒業制作審査用Companyとして、

```text
JobDDテスト株式会社
```

を利用する。

このCompanyはシステム上の特殊Accountではない。

通常の `Company` / `company_user` / Ownership / Company Dashboard / Controlled Publishを利用する。

Demoであることは、

- Company名
- 求人タイトル
- 求人本文

等の入力内容から人間が明確に認識できるようにする。

## Demo求人

求人タイトルには、求職者が最初に認識できる位置へ、

```text
【卒業制作デモ・応募不可】
```

等を明示する。

求人詳細の冒頭には、

> この求人はJobDD卒業制作の動作確認用デモです。実際の募集ではありません。応募はできません。

等の説明を表示内容として入力する。

Demo求人の業務内容自体は、JobDDのLevel 1 / Level 2 / Decision Viewを確認できる具体性を持たせる。

ただし、

- 実在企業の求人であるように見せない
- 実在企業の社名・ロゴを流用しない
- 実在企業の求人原稿を実求人として転載しない

ことを維持する。

## 応募URL / Application Route

通常求人のPublish仕様を変更しない。

つまり、

- Publish Validatorの応募URL必須ルールをDemoのために緩和しない
- Demo専用のPublish分岐を追加しない
- Demo専用の `is_demo` カラムを追加しない

既存仕様上URLが必要なため、Demo求人には、

**JobDD内の安全な非応募URL**

を設定する。

例：

```text
https://jobdd.jp/
```

このURLはPublish Validatorを満たすためのDemo用入力値であり、実応募先ではない。

Application Route / CTAが表示される場合も、実在企業・求人媒体・人材紹介会社等の応募先へ接続してはならない。

求人タイトル・本文のDemo / 応募不可表示と組み合わせて、実際の募集ではないことを明確にする。

## 判断理由

- 卒業制作専用の状態や分岐を本番コードへ残さないため
- 通常のControlled Publishをそのまま審査員に体験してもらうため
- Demo専用例外によって通常求人のPublish Validator / Application Routeを壊さないため
- migrationや追加UIを増やさず、提出前の変更リスクを抑えるため
- 「データはDemo、システム挙動は通常」とすることで、卒業制作の完成度を分かりやすく示せるため

## 審査期間終了後

審査終了後、Demo求人は既存のLifecycleに従って、

```text
published
↓
paused
```

とする。

Demo Company / Demo求人を物理削除することを必須としない。

Published Snapshot / Job Fact / Evidence / Provenance / Review履歴等は既存仕様どおり保持する。

## HYPOTHESIS

なし。

これは事業仮説ではなく、卒業制作審査用の運用Decisionである。

## OPEN

- Demo求人の具体的な公開終了日時
- 審査終了後に `paused` を実行する担当 / タイミング
- Demo専用 `noindex` の採否
- 将来、Demo専用案内ページ `/demo-info` 等を作るか
- 将来の実在Company / 実在求人による正式なProduction Smoke実施時期


---

# 3. JobDDとは

## DECISION

JobDDは、

**求人を探すだけでは分からない「仕事の中身」を構造化し、根拠とともに比較して、自分で選べるようにするDecision Supportサービス**

である。

JobDDはAIが転職先や応募先を決定するサービスではない。

JobDD / AIが担当するのは、

- 情報整理
- 情報構造化
- 求人間比較
- 希望条件との照合
- 適合理由の説明
- 相違点の説明
- 未確認事項の明示
- Evidenceの提示
- 応募経路の整理

までとする。

最終判断は求職者本人が行う。

## 基本思想

**RecommendではなくDecision Support**

---

# 4. JobDDが目指すポジション

## DECISION

JobDDは、

**求人を大量に保有すること自体**

を競争力の中心にしない。

JobDDが目指すのは、

**求人を「理解できる状態」「比較できる状態」に変換すること**

である。

概念的には、

- ATS / 採用管理ツール：Publish / Manage
- 求人媒体 / 求人検索：Find
- JobDD：Understand / Compare / Decide

という役割の違いを持つ。

内部的な表現として、

> 求人を探すサービスではなく、求人を選ぶためのサービス

を現在の方向性とする。

企業側に対しては、

> 求人を掲載するだけの場所ではなく、仕事を正しく理解してもらう場所

を目指す。

---

# 5. 現時点で確認できているPain

## FACT｜人材紹介会社側

人材紹介会社へのインタビューから、少なくとも以下のPainが確認されている。

- 求職者が集まらない
- 集まった求職者と求人のマッチ度が低い

一方、

**求人と求職者を効率的に機械照合する作業そのものが主要Painである**

という旧HM仮説は、主要Painではないと判断した。

## DECISION

明確な新しいEvidenceがない限り、JobDDを旧HM型の人材紹介業務効率化SaaSへ戻さない。

---

# 6. 求職者側のPain

## HYPOTHESIS

求職者には、

- 求人票だけでは実際の仕事内容が分かりにくい
- 同じ職種名でも仕事内容が大きく異なる
- 担当工程、使用ツール、顧客との関係、現場との関係等が分かりにくい
- 複数求人を同じ軸で比較しにくい
- 情報が書かれていないことと、自分に合わないことを区別しにくい
- どの応募経路を利用するか判断しにくい
- 自分に合う人材紹介会社を登録前に比較しにくい

というPainが存在する可能性がある。

ただし、

**「求職者は、自分に合った人材紹介会社を登録前に比較して選びたい」**

というPainは、現時点ではまだHYPOTHESISでありFACTではない。

---

# 7. 初期ターゲット

## DECISION

卒業制作MVPの中心対象は、

**近畿6府県 × 機械設計・電気設計**

とする。

対象地域：

- 大阪府
- 兵庫県
- 京都府
- 滋賀県
- 奈良県
- 和歌山県

対象職種：

- 機械設計
- 電気設計

将来的には、

- 生産技術
- プラントエンジニア
- 設備設計
- 施工管理
- 品質保証
- その他機電系技術職

等への拡張を検討する。

## DECISION

卒業制作段階で全国・全職種へ拡張することを目的にしない。

---

# 8. 現在の実装状態

## FACT｜2026-09-21

JobDD提出保険版を `jobdd.jp` に本番デプロイ済み。

本番環境で少なくとも、

希望条件入力
↓
求人一覧
↓
求人詳細
↓
Evidence確認
↓
求人比較
↓
応募方法確認
↓
企業公式HPへの遷移

までの主要導線を実機確認した。

## FACT

提出保険版をGit tag

`graduation-submit-ready-v1`

として固定した。

対象commit：

`0a852d3`

この版は、今後の開発に問題が発生した場合でも卒業制作提出版へ戻れる復帰点とする。

## FACT｜2026-09-23 Phase A Batch 1〜3

ローカル `~/jobdd-v4` でPhase A実装を開始し、Batch 1〜3まで検証済み。

### Batch 1｜DB Foundation

- 新規5テーブル：`company_user`、`job_structured_profiles`、`job_tool_usages`、`job_typical_day_items`、`job_published_profiles`
- 既存変更：`users.system_role`、`job_postings.status`、`job_facts.context_role`
- 622 tests PASS / 3,628 assertions
- 既存 `job_postings` 1,591件を `published` のまま維持
- 主要既存データ件数・ハッシュ不変を確認
- Git checkpoint：`a626345 Add Phase A company supply DB foundation`

### Batch 2｜Company Account / Ownership

- Company Self-service Registration実装
- User / Company / `company_user(company_owner)` をTransactionで自動生成
- CompanyPolicy / JobPostingPolicy実装
- Platform Owner / Company Owner / Company Editor / Guestの認可境界を実装
- `/admin/*` をPlatform Owner専用に保護
- 650 tests PASS / 3,870 assertions
- 主要既存データ不変を確認

### Batch 3｜Company Dashboard + Level 1

- Company Dashboard実装
- 企業求人Draft作成・Level 1 CRUD実装
- `job_postings.review_status`、`review_requested_at`、`reviewed_at`、`reviewed_by_user_id`、`review_note` を追加
- `job_postings.application_requirements` を追加
- 企業求人作成時に `status=draft`、`review_status=not_submitted` を明示
- Draftを求職者側の一覧・詳細・比較・応募経路から除外
- 680 tests PASS / 4,183 assertions
- 既存1,591求人・主要既存データ件数・ハッシュ不変を確認

## FACT｜2026-09-23 Phase A Batch 4〜9

### Batch 4｜Level 2 Authoring

- STEP 1〜5、Tool、Typical Day、Representative Project、途中保存・再開を実装
- Level 2 Core completion判定を実装
- 702 tests PASS / 4,402 assertions

### Batch 5｜Controlled Publish Core

- Preview → 公開申請 → 通知 → Platform Owner審査 → 承認 / 差戻し → Publishを接続
- Publish時にSource / Job Fact / Published Snapshot / Direct RouteをTransactionで更新
- 二重承認、rollback、既存Fact保護、再審査中Snapshot維持を検証
- 751 tests PASS / 4,675 assertions

### Batch 6｜Seeker Decision View v2

- Published Snapshotを正本とするSeeker Decision View v2を実装
- SnapshotなしLegacy求人は既存表示へfallback
- 未承認Authoringを求職者側へ漏らさない境界を実装
- 766 tests PASS / 4,861 assertions
- Git checkpoint：`2a6b74f Add Seeker Decision View v2`

### Batch 7｜Seeker Progressive Input

- 任意の詳細希望入力・保存・再編集・解除を実装
- 詳細希望はFit / Rankingへ利用せず、比較材料としてのみ表示
- 799 tests PASS / 5,108 assertions
- Git checkpoint：`9992a1c`（Batch 7完了時HEAD）

### Batch 8｜Compare v2 / Evidence / Application Route

- 最大3求人のCompare v2を実装
- Evidence / Provenance / Application Routeへの導線を接続
- ページをまたぐ比較選択保持を実装
- 813 tests PASS / 5,275 assertions
- Self-service / Legacy混在比較を検証

### Batch 9｜Hardening / Production Readiness

- Draft / Published Snapshot / interaction log targetの公開境界を総点検・修正
- 空token、危険URL、未知Snapshot schemaからの未承認Authoring漏洩を防止
- Company → Review → Publish → Seeker → Compare → RouteのVertical Sliceを全回帰
- Authorization matrix、Republish、Data Integrity、UI、PerformanceをHardening
- **832 tests PASS / 5,561 assertions**
- PC / 390px、Pint、build、diff check PASS
- ローカル実DB主要19テーブルの件数・SHA-256不変
- Git checkpoint：`83a5734 Harden Phase A public boundaries and document production readiness`

## FACT｜Production Preflight

Phase AコードHardeningはPASSしているが、本番公開はまだ実施していない。

Preflightでは現行本番構成、PHP / MySQL、公開フォルダ、SSL / HTTPS、sendmail経路、DB容量、backup / restore可能性等を確認した。

現時点のPublic Release判定は**NO-GO（運用・環境条件待ち）**。

---

# 9. JobDD v5.0の全体構造

## DECISION

JobDD v5.0は3層で考える。

```text
Supply Side
    ↓
Decision Support Core
    ↓
Demand Side
```

## Supply Side

情報をJobDDへ供給する側。

主に、

- 企業
- 人材紹介会社
- 許諾済み外部データ
- 公式データ
- JobDD独自取材

を想定する。

## Decision Support Core

JobDD固有の情報構造化・比較層。

- Job
- Fact
- Context Role
- Evidence
- Source
- Job Fit
- Application Route
- Provenance

を扱う。

## Demand Side

求職者が、

- 求人を理解する
- 希望条件と照合する
- 求人同士を比較する
- Evidenceを確認する
- 応募方法を選ぶ

ためのUI。

---

# 10. 企業登録型JobDD

## DECISION

JobDD v5.0では、企業自身が求人情報を登録・更新できる仕組みを追加する。

基本フロー：

```text
企業ログイン
↓
企業Dashboard
↓
求人新規作成
↓
Level 1 Basic
↓
Level 2 Structured Job Profile
↓
Preview
↓
公開申請
↓
Platform Owner / Admin審査
↓
承認時のみPublish
↓
JobDD Decision Support Core
↓
求職者側求人詳細・比較画面
```

重要なのは、

**企業向け求人入力CMSを作ること自体ではない。**

企業が入力した情報を、

**求職者の意思決定に役立つ構造へ変換すること**

を目的とする。

---

# 11. Structured Job Profile v0.1

## DECISION｜情報の厚さを3段階に分ける

### Level 1｜Basic

一般的な求人として必要な基本情報。

候補：

- 求人タイトル
- 職種
- 勤務地
- 年収
- 雇用形態
- 仕事内容
- 応募URL
- その他最低限の採用条件

### Level 2｜Structured Job Profile

JobDDの差別化となる構造化情報。

Level 2は、企業へ長文求人原稿を書かせるのではなく、

> **質問に答えていくと、技術職の「実際の仕事の中身」が構造化される**

ことを目的とする。

企業側フォームは以下の5ステップとする。

---

## STEP 1｜何を設計する仕事か

1. この求人では、何を設計しますか？
   - 必須
   - 保存キー：`design_target`
   - 求職者側：何を設計する仕事か

2. 主な製品・設備・システムを教えてください
   - 任意
   - 保存キー：`product_context`
   - 求職者側：設計対象

3. 主に担当する設計工程は？
   - 必須
   - 複数選択
   - 保存キー：`design_phases`

選択肢：

- 構想
- 基本設計
- 詳細設計
- 製図
- 解析
- 試験・評価
- 製造対応
- 現地対応
- その他

4. 入社直後は、どの仕事から担当しますか？
   - 必須
   - 保存キー：`initial_assignment`

5. 経験を積んだ後、担当範囲はどう広がりますか？
   - 任意
   - 保存キー：`future_scope`

---

## STEP 2｜CAD・ツール・必要経験

### DECISION

Toolについては、

**「この仕事で実際にどう使うか」**
と
**「応募時点で経験を求めるか」**

を別軸で保存する。

初期Tool：

- AutoCAD
- Inventor
- SolidWorks
- CATIA
- Creo
- NX
- 電気CAD
- その他自由入力

使用文脈：

- 主に使う
- 時々使う
- 他部署・協力会社が使う
- 使用しない
- 未定

応募時経験要件：

- 必須経験
- 歓迎経験
- 経験不問
- 未定

保存：

- `tools`
- `tool_usage`
- `tool_expectation`
- `required_experience`
- `preferred_experience`

Context Role変換例：

- 主に使う → `responsibility`
- 他部署が使う → `other_department`
- 必須経験 → `required_experience`
- 歓迎経験 → `preferred_experience`

---

## STEP 3｜誰と、どう仕事をするか

企業へ確認する内容：

- 普段、誰と一緒に仕事をするか
- 顧客との打合せ頻度
- 製造部門との関わり
- 工場・現地・建設現場との関わり
- 仕事の進め方

保存キー候補：

- `collaborators`
- `customer_contact`
- `manufacturing_relation`
- `site_relation`
- `work_style`

Collaborator候補：

- 同じ設計チーム
- 他分野設計者
- 製造
- 品質
- 営業
- 顧客
- 協力会社
- 現場担当
- その他

頻度候補：

- ほぼ毎日
- 週に数回
- 月に数回
- ほとんどない
- 案件による

---

## STEP 4｜仕事のリアル

確認項目：

- 1案件の期間
- 同時担当案件数
- この仕事で難しいところ
- 入社後につまずきやすいポイント
- 合いやすい働き方・志向
- 合いにくい可能性がある働き方・志向

保存キー候補：

- `project_duration`
- `concurrent_projects`
- `difficult_points`
- `onboarding_challenges`
- `fit_work_style`
- `misfit_work_style`

### DECISION

「向いている人 / 向いていない人」という人物評価ではなく、

**仕事内容・働き方・経験・志向との相性**

として入力・表示する。

年齢、性別、国籍、家庭状況などを回答対象にしない。

---

## STEP 5｜1日の仕事と具体例

確認項目：

- 代表的な1日の流れ
- 代表的なプロジェクト
- 通常の求人票では伝えにくい、この仕事の特徴

保存キー候補：

- `typical_day`
- `representative_project`
- `hard_to_convey`

Typical Dayは、

**時刻 + 活動内容**

の行追加式を基本とする。

Representative Projectは最低限、

- 何を作ったか
- どの工程を担当したか
- 期間
- チーム
- 難しかったこと

を入力できる構造とする。

---

## Level 2 Core｜卒業制作Phase Aの必須入力

Level 2の全項目を必須にはしない。

Phase Aでは以下をCore必須とする。

- 設計対象
- 主な設計工程
- 入社直後の担当
- CAD / Tool
- Toolの使用文脈
- Tool経験要件
- 一緒に働く相手
- 顧客との関係
- 製造との関係
- 現場との関係
- 仕事の進め方
- 仕事の難しさ
- Typical Day

その他は、

**入力すると求人の解像度がさらに上がる情報**

として任意入力とする。

---

### Level 3｜Editorial

JobDD独自取材による高解像度情報。

候補：

- 現地取材
- 写真
- 技術者インタビュー
- 社員インタビュー
- 実際の仕事風景
- 職場環境
- 製品 / 設備
- 仕事の面白さ
- 難しさ
- キャリア
- 企業文化

Level 3は卒業制作MVP必須範囲には含めない。


# 12. Typical Day

## DECISION

「代表的な1日の仕事の流れ」をLevel 2 Coreに含める。

企業側では、

**時刻 + 活動内容**

を複数行入力できるTimeline Builderを基本とする。

例：

```text
08:30 朝会
09:00 CAD設計
11:00 製造部門レビュー
12:00 昼休み
13:00 詳細設計
15:00 顧客打合せ
16:00 図面修正
17:30 退勤
```

求職者側ではTimelineとして表示する。

## 注意

Typical Dayはあくまで代表例であり、

**毎日必ず同じ勤務内容になることを保証する情報として表示しない。**

企業入力画面・求職者画面の双方で、代表例であることを明示する。


# 13. 情報入力方法

## DECISION

「情報の深さ」と「JobDDへの入力方法」を別の軸として扱う。

将来候補：

1. 手入力
2. CSV / Excel
3. API / ATS連携
4. 企業公式採用ページとの許諾同期
5. JobDDによる取材入力

## DECISION｜卒業制作MVP

卒業制作では、

**手入力を実動させる。**

CSV / API / ATS / Crawl Sync等は将来拡張可能な設計にはするが、卒業制作の必須実装にはしない。

---

# 14. クローリング方針の変更

## FACT

外部求人サイト・求人媒体・人材紹介会社等には、

- robots.txt
- 利用規約
- 情報再利用条件
- 永続保存条件
- 商用利用条件
- URL / IDの不安定性

等の制約が存在し、JobDDが大量データ供給を第三者クローリングだけに依存することは難しいことが確認・示唆された。

## DECISION

JobDD v5.0では、

**無許諾の大量クローリングを主要データ供給モデルにしない。**

Crawlerは廃止するのではなく、

**企業自身が許諾した自社採用ページ等との同期手段**

として将来的に活用する。

想定：

```text
企業登録
↓
同期対象URL登録
↓
同期への明示的同意
↓
JobDDが定期取得
↓
変更検知
↓
企業確認 / 更新
```

---

# 15. 既存求人データの扱い

## DECISION

これまで収集・構築した求人・企業・Fact等のデータ資産は捨てない。

既存データは、

- 開発
- UI検証
- デモ
- データモデル検証
- Decision Support検証

等で継続利用する。

ただし、

**技術的に保有していることと、本番公開・商用再利用してよいことは別問題**

として扱う。

公開・商用利用については各Sourceの利用条件・権利・許諾を別途判断する。

---

# 15.1 Authoring ModelとDecision Support Model

## DECISION

企業が編集するためのデータ構造と、
求職者向けDecision Supportで使用するFact構造を分離する。

概念：

```text
Company Input
↓
Authoring Model
↓
Preview / Publish
↓
Decision Support Model
```

Authoring側の候補：

- `job_structured_profiles`
- `job_tool_usages`
- `job_typical_day_items`

Publish時に既存の、

- `job_facts`
- `sources`
- Context Role
- Evidence
- Provenance

へ変換する。

## 判断理由

企業フォームを`job_facts`へ直結しすぎると、

- 編集UX
- Draft保存
- CSV
- ATS
- API
- Authorized Crawl Sync
- Editorial

等の将来拡張が難しくなるため。

入力経路が異なっても、
最終的には同じDecision Support Coreへ接続できる構造を目指す。

---

# 15.2 Phase A Authoring / Publish DB

## DECISION

Phase Aでは、企業が編集するAuthoring Modelと、求職者へ公開するPublished Modelを分離する。

### 新規テーブル

- `company_user`
- `job_structured_profiles`
- `job_tool_usages`
- `job_typical_day_items`
- `job_published_profiles`

### 既存テーブル変更

- `users.system_role`
- `job_postings.status`
- `job_postings.review_status`
- `job_postings.review_requested_at`
- `job_postings.reviewed_at`
- `job_postings.reviewed_by_user_id`
- `job_postings.review_note`
- `job_postings.application_requirements`
- `job_facts.context_role`

### 原則schema変更しない

- `companies`
- `sources`
- `application_routes`

既存の `job_postings.published_at` と `sources.source_type` は再利用する。

## Published Snapshot

`job_published_profiles` は、最後にPublishしたStructured Job Profileの求職者表示用Snapshotを保持する。

概念：

```text
Authoring Model
    ↓ Publish
Published Snapshot
    ↓
Seeker Decision View
```

企業が公開済み求人を編集中でも、求職者側は前回Publish時のSnapshotとJob Factを表示し続ける。再Publish成功時のみ公開内容を更新する。

---

# 15.3 Level 2 Authoring Model v0.2

## DECISION

`job_structured_profiles` はLevel 2本体を保持する。Draft途中保存を優先し、Phase Aでは多くの項目をnullableとし、必須判定はPublish時Validatorで行う。

主要項目：

- `design_target`
- `product_context`
- `design_phases` JSON
- `initial_assignment`
- `future_scope`
- `required_experience`
- `preferred_experience`
- `collaborators` JSON
- `customer_contact_frequency` / `customer_contact_note`
- `manufacturing_relation_frequency` / `manufacturing_relation_note`
- `site_relation_frequency` / `site_relation_note`
- `work_style`
- `project_duration`
- `concurrent_projects`
- `difficult_points`
- `onboarding_challenges`
- `fit_work_style`
- `misfit_work_style`
- `representative_project` JSON
- `hard_to_convey`

`job_tool_usages` はToolごとに、

- `tool_key`
- `tool_name`
- `usage_context`
- `experience_expectation`
- `usage_notes`
- `sort_order`

を保持する。

`job_typical_day_items` は、

- `time_label`
- `activity`
- `sort_order`

を保持する。

Typical Dayは順序を持つ集合として扱い、原則として1行1Job Factには変換しない。

---

# 16. Provenance / Evidence

## DECISION

JobDDでは、情報内容だけでなく、

**その情報がどこから来たか**

を重要な情報として扱う。

Source Type候補：

- `company_self_reported`
- `company_official_job`
- `api_sync`
- `crawl_sync`
- `jobdd_interview`
- `agency_self_reported`
- `public_registry`
- `external_import`

求職者側では必要に応じて、

- 企業本人が登録
- 企業公式情報から確認
- JobDD取材で確認
- 外部情報から取得

等を区別して表示する。

---

# 17. Job Fact / Context Role

## DECISION

Job Factは、

**求人本文・企業入力等に、その情報が存在していることを表すFact**

として扱う。

Factが存在することと、

**求職者本人がその業務を担当すること**

は同義ではない。

そのためContext Roleを利用する。

主要Role：

- responsibility
- required_experience
- preferred_experience
- collaboration
- other_department
- company_context
- product_context
- project_example
- unknown

## DECISION

求職者とのFit判定に使う場合は、単語が登場しただけではMATCHとしない。

Evidenceと文脈を確認する。

---

# 17.1 Job Fact Dictionary v5.1

## DECISION

Job Factの命名規約を以下とする。

- `fact_category`：意味の大分類
- `fact_key`：比較可能な最小概念
- `fact_value`：求人での具体状態・内容
- `normalized_value`：必要時の正規化値
- `context_role`：求人内での役割

英語 `snake_case` を使用し、UI文言変更でkeyを変えない。フォームSTEP名はcategoryに使用しない。

初期category：

- `job_content`
- `design_phase`
- `assignment`
- `tool_usage`
- `tool_expectation`
- `experience`
- `collaboration`
- `work_style`
- `work_reality`
- `project_example`

代表例：

```text
fact_category = tool_usage
fact_key      = inventor
fact_value    = primary
context_role  = responsibility
```

```text
fact_category = tool_expectation
fact_key      = inventor
fact_value    = required
context_role  = required_experience
```

プリセット外Toolは任意の新keyを乱立させず、`other_tool` 等の安定keyと `normalized_value` で保持する。

DictionaryはLaravelコード上の単一正本として管理し、Publish Transformer / JobFitService / Presenter / Validatorから参照する。

企業Self-service入力では明示されたContext Roleを保存し、Classifierで再推定しない。

---

# 18. Job Fit

## DECISION

Job Fitは3状態を基本とする。

- MATCH
- MISMATCH
- UNKNOWN

## DECISION

UNKNOWNをMISMATCHとして扱わない。

情報が確認できないことと、条件に合わないことを区別する。

## DECISION

総合点・総合ランキングをJobDDの中心UIにはしない。

各項目について、

- 何が一致しているか
- 何が異なるか
- 何が未確認か
- その根拠は何か

を示す。

最終判断は求職者本人に委ねる。

---

## DECISION｜Level 2情報のFit利用範囲

Level 2に存在する情報を、すべて自動的にFit判定へ利用しない。

卒業制作v5.1では、
求職者側で希望条件として取得している軸のみFit判定へ利用する。

現時点の利用候補：

- 職種
- 地域
- 年収
- CAD / Tool

現時点ではFit判定へ利用しないもの：

- 担当工程
- 必須経験
- 顧客対応
- 製造との関係
- Typical Day
- 仕事の難しさ
- 合いやすい働き方
- 合いにくい可能性がある働き方

これらは表示・比較には利用する。

## 判断理由

**求職者に聞いていない条件ではFitを判定しない。**

表示情報が存在することと、
求職者本人に適合することを混同しないため。

---

# 18.1 求職者入力のProgressive Disclosure

## DECISION

求職者入力は「簡易版」と「詳細版」を別サービスとして分断せず、段階的に深掘りする。

### 簡易入力

最初は主に以下で求人一覧へ進める。

- 希望職種
- 希望勤務地
- 希望年収
- CAD / Tool

### 詳細入力

必要な求職者だけ「もっと詳しく比較する」から以下を追加できる。

- 担当工程
- 顧客との関わり
- 製造との関わり
- 現場との関わり
- 仕事の進め方

詳細入力の目的は推薦精度を上げることではなく、**比較材料を増やすこと**である。

## HYPOTHESIS

詳細項目をMATCH / MISMATCHへ利用する価値がある可能性はあるが、Phase Aでは直ちにFit判定軸へ昇格させない。まずは比較表示へ利用し、実利用で検証する。

---

# 19. Direct / Agent / Platform

## DECISION

Direct / Agent / Platformは引き続きJobDDで扱う。

ただし、JobDDの主役は応募経路そのものではなく、

**求人を理解・比較した後に選択する応募方法**

とする。

つまり、

```text
求人理解
↓
求人比較
↓
候補決定
↓
利用可能な応募経路確認
↓
求職者本人が選択
```

を基本とする。

## DECISION

Direct / Agent / Platformを総合Scoreで順位付けし、JobDDが最適経路を決定する方式は採用しない。

---

# 20. 求職者向けDecision View v2

## DECISION

企業のLevel 2入力を、そのまま求人詳細へ羅列するのではなく、

**求職者が「仕事を理解する」ための画面へ再構成する。**

求人詳細の基本構成：

1. この求人の要点
2. あなたの希望との照合
   - MATCH
   - MISMATCH
   - UNKNOWN
3. 何を設計する仕事か
4. どの工程を担当するか
5. 入社直後 → 将来
6. CAD / Tool
   - 実際の使用文脈
   - 応募時経験要件
7. 誰と仕事をするか
   - 顧客
   - 製造
   - 現場
8. 仕事の進め方
9. 代表的な1日
10. この仕事の難しいところ
13. 合いやすい働き方
14. 合いにくい可能性がある働き方
15. Representative Project
16. Evidence / Source / Provenance
17. Application Route

## DECISION

企業側Level 2入力と求職者側Decision Viewは、

**同じVertical Sliceとして開発する。**

企業入力だけを完成させて終わらせない。


# 21. 求人比較 v2

## DECISION

複数求人を同じ軸で比較できる構造を維持・強化する。

比較画面は長文化させず、代表軸を横並びにする。

比較候補：

- 職種
- 勤務地
- 年収
- 設計対象
- 主な工程
- 入社直後
- 将来的な担当
- CAD / Tool
- 必要経験
- 顧客対応
- 製造・現場との関係
- 仕事の進め方
- 仕事の難しさ
- Typical Dayへの導線
- Evidence
- Application Route

長文項目は全文表示せず、求人詳細へ遷移して確認する。

求人の「優劣」をJobDDが決めるのではなく、

**違いを見える状態にする。**


# 22. Company Account / Ownership

## DECISION｜企業Self-service登録

Phase Aでは、Owner承認・Company Claim申請を前提とせず、企業自身がSelf-serviceで登録できる。

登録時の基本入力：

- 会社名またはユーザーID
- メールアドレス
- パスワード

登録成功時に同一Transactionで、

```text
User
↓
Company
↓
company_user（company_owner）
↓
企業Dashboard
```

を生成する。

正式会社名は求人Publish前までにCompany情報として確定させる。会社名の重複解消・Company Claim・法人本人確認はPhase B候補とする。

## DECISION｜権限レイヤー

System RoleとCompany Roleを分離する。

### System Role

- `platform_owner` / Admin：JobDD運営側。全企業・全求人・全Structured Profile・Evidence / Provenance・Application Route・管理画面を閲覧・操作可能
- `user`：通常ユーザー

### Company Role

- `company_owner`
- `company_editor`

Company Userは自社Company配下のみCRUD可能とする。他社求人への直接URLアクセスも認可で拒否する。

`/admin/*` はPlatform Owner / Admin専用、`/company/*` は企業ユーザー向けとする。

Phase AではCompany切替UI、複数担当者招待、role変更UI、Company Claimは必須としない。

---

# 23. 求人Lifecycle / Review Lifecycle

## DECISION｜公開状態

Previewは永続statusではなく表示Actionとする。

Phase A / v5.7で実使用する求人statusは、

- `draft`
- `published`
- `paused`

を基本とする。

将来拡張候補：

- `closed`

既存求人はstatus導入時にpublished扱いとし、既存データを一括draft化しない。企業Self-service求人は作成時に明示的に `draft` を設定する。

## DECISION｜審査状態

公開状態と公開審査状態を分離する。Phase Aの `review_status` は以下を基本とする。

- `not_submitted`
- `pending_review`
- `changes_requested`
- `approved`

補助情報：

- `review_requested_at`
- `reviewed_at`
- `reviewed_by_user_id`
- `review_note`

公開済み求人のAuthoring内容を編集して再申請する場合、

```text
status = published
review_status = pending_review
```

を許容する。審査中も求職者には最後にPublishされたSnapshotを維持する。

---

# 23.1 Preview / Publish Request

## DECISION

Previewは単なる入力確認ではなく、**求職者から実際にどう見えるかを確認するSeeker Decision View** とする。

PreviewはDraftのAuthoring Modelを表示用Presenterへ渡すread-only Actionであり、Preview自体でstatusを書き換えない。

公開申請条件：

- Level 1必須項目 COMPLETE
- Level 2 Core COMPLETE
- 応募URL等の公開必須情報が有効

企業側の最終CTAは原則 **「公開する」ではなく「公開申請する」** とする。

公開申請時：

```text
review_status = pending_review
review_requested_at = now()
↓
postmaster@jobdd.jp へ通知メール
```

メールは通知専用とし、承認 / 差戻し操作はJobDD管理画面でPlatform Owner / Adminが行う。

Completion %はDB保存せず、現在の入力値から動的計算する。求職者向けScoreやランキングには使用しない。

## DECISION｜公開済み求人の編集

公開済み求人を企業が編集している途中でも、求職者側には最後にPublishした内容を維持する。

```text
Authoring Model = 最新編集中
Published Snapshot / Job Facts = 最後に承認・Publishした公開内容
```

再公開申請が承認された時にのみ公開Snapshot・企業入力由来Job Fact・Provenanceを更新する。

---

# 23.2 Platform Owner Review / Controlled Publish

## DECISION

Platform Owner / Adminは管理画面で公開申請を確認し、以下を行う。

- Preview確認
- 承認してPublish
- 差戻し

差戻し時：

```text
review_status = changes_requested
review_note = 差戻し理由
reviewed_at = now()
reviewed_by_user_id = reviewer
```

承認時にのみPublish処理を実行する。

公開確認は企業申告内容の事実保証を意味しない。JobDDが確認するのは、公開可能な体裁、必須入力、明らかな矛盾・不適切内容等である。

求職者向けProvenanceでは、必要に応じて、

> 企業提供情報 / JobDD公開確認済み

等の表現を候補とし、「内容の真偽をJobDDが保証した」と誤解される表現は避ける。

---

# 23.3 Publish変換

## DECISION

承認後のPublishはTransactionで行う。

```text
Platform Owner権限確認
↓
Publish Validator
↓
company_self_reported Source取得 / 作成
↓
既存の企業入力由来Factだけを置換
↓
Job Fact Dictionaryに従いFact生成
↓
明示Context Role保存
↓
Direct Application Routeを冪等upsert
↓
Published Snapshot生成
↓
status = published
review_status = approved
reviewed_at / reviewed_by_user_id 更新
↓
COMMIT
```

企業入力由来Factは、

- `extraction_method = company_self_reported`
- `verification_status = self_reported`

を基本とする。

外部Crawler / API / rule由来の既存FactをPublish時に削除しない。

---

# 23.4 Company Dashboard State / CTA

## DECISION

Company Dashboardでは、内部の `status` / `review_status` を直接操作させるのではなく、現在状態から「次に行うべき操作」をPrimary CTAとして提示する。

状態判定の優先順位は概念的に以下とする。

```text
1. review_status = pending_review
   → 審査状況を見る

2. review_status = changes_requested
   → 修正する

3. status = published かつ Authoring != Published Snapshot
   → 編集を続ける（更新作業中）

4. status = published
   → 編集

5. status = draft かつ Publish Validator未達
   → 入力を再開

6. status = draft かつ Publish Validator達成
   → Preview
```


7. status = paused かつ review_status = approved かつ Authoring == Published Snapshot
   → 公開を再開

8. status = paused かつ review_status = approved かつ Authoring != Published Snapshot
   → 編集を続ける（更新作業中）

9. status = paused かつ review_status = pending_review
   → 審査状況を見る（再公開審査中）

10. status = paused かつ review_status = changes_requested
    → 修正する

Secondary Action候補：

- Preview
- 現在の公開ページを見る
- 審査内容を見る
- 修正内容を見る

審査中は企業側から申請対象Authoringを編集不可とし、Preview / 審査状況確認のみ許可する。

公開済み求人の更新申請が審査中または差戻し中でも、求職者側の表示は最後に承認されたPublished Snapshotを正本とする。


---

# 24. 卒業制作MVP v5.3

## DECISION

卒業制作の次の必達ラインは、

**企業がSelf-service登録し、Level 2 Structured Job Profileを作成・Preview・公開申請し、Platform Ownerの承認後にPublishされた情報を求職者が段階的入力で理解・比較して応募経路へ進めること**

とする。

## Done Definition

最低1社について、

1. 企業UserがSelf-service登録できる
2. User / Company / company_userが正しく生成される
3. 自社企業・自社求人のみCRUDできる
4. Platform Owner / Adminは全企業・全求人を管理できる
5. 求人を新規作成しLevel 1を入力できる
6. Level 2 STEP 1〜5を途中保存・再開できる
7. PreviewでSeeker Decision Viewを確認できる
8. Level 1必須＋Level 2 Core完了後に公開申請できる
9. 公開申請時にpostmaster@jobdd.jpへ通知できる
10. Platform Owner / Adminが管理画面でPreviewし、承認 / 差戻しできる
11. 承認時のみPublishされ、Published求人が求職者側求人一覧へ出る
12. 公開済み求人を編集中・再審査中でも最後のPublish内容が維持される
13. 求職者は簡易入力だけでも求人一覧へ進める
14. 必要な求職者は詳細条件を追加して比較材料を増やせる
15. Seeker Decision View v2でLevel 2情報を理解できる
16. 現行Fit軸（職種・地域・年収・CAD / Tool）でMATCH / MISMATCH / UNKNOWNを確認できる
17. Evidence / Provenanceを確認できる
18. 他求人と比較できる
19. Application Routeへ進める
20. 企業公式HP等の外部応募先へ進める

ここまでをPhase A完成条件とする。

---

# 25. 卒業制作の開発戦略

## DECISION｜2段ロケット方式

### Phase A｜必達

```text
Company Account
↓
Ownership
↓
Level 1
↓
Level 2
↓
Preview
↓
公開申請
↓
Platform Owner承認 / 差戻し
↓
Publish
↓
Seeker Decision View v2
↓
Compare
↓
本番デプロイ
```

ここまで完成した時点で、

**第2のSubmit Ready Point**

としてtagを作成する。

### Phase B｜余力がある場合

Phase Aが早く完成した場合は、そのままフルサービス版へ開発を継続する。

候補：

- 求人複製
- Close
- 企業プロフィール
- Company Claim
- Typical Day高度化
- CSV
- API / ATS
- Authorized Crawl Sync
- Editorial Request
- 人材紹介会社セルフ登録
- Admin Review
- Analytics
- Entitlement
- Paid Plan

---

# 25.1 卒業制作デモの理想導線

Phase A完成時のデモでは、以下を1本で通せる状態を目指す。

```text
企業アカウントをSelf-service登録
↓
企業Dashboard
↓
求人を1件作る
↓
何を設計するかを入力
↓
CAD / Toolの使用文脈・経験要件を登録
↓
Typical Dayを入力
↓
Preview
↓
公開申請
↓
postmaster@jobdd.jpへ通知
↓
Platform Owner管理画面で承認
↓
Publish
↓
求職者モードへ
↓
簡易希望条件を入力
↓
その求人を検索
↓
必要なら「もっと詳しく比較する」で詳細希望を追加
↓
Level 2情報を確認
↓
別求人と比較
↓
Evidence / Provenanceを確認
↓
Application Route
↓
企業公式HP
```

この導線によって、

**企業入力 → 構造化 → Decision Support → 求職者判断**

までを1つのプロダクト体験として示す。

---

# 26. 卒業制作で必須としないもの

以下はフルサービス設計では考慮するが、Phase Aの必須条件にはしない。

- Stripe
- 本格決済
- 請求管理
- 本格ATS連携
- CSV完全対応
- production crawler scheduler
- 大規模求人DB拡張
- 全国全職種
- 高度なAI推薦
- 総合ランキング
- 大規模口コミ
- 成約管理
- 高度な企業Analytics
- 完全なEditorial CMS

---

# 27. 企業向け価値仮説

## HYPOTHESIS

企業には、

**通常の求人票では伝えにくい仕事の実態を、ガイド付きフォームによって整理して求職者へ伝えたい**

という需要がある可能性がある。

## HYPOTHESIS

採用担当者自身が機械設計・電気設計の仕事を十分に言語化できない場合でも、

JobDDの質問へ回答することで、

**技術職求職者が知りたい情報を構造化できる**

ことに価値がある可能性がある。

## HYPOTHESIS

情報の解像度が上がることで、

- 応募前理解
- 応募者との期待値調整
- ミスマッチ低減
- 応募品質

が改善する可能性がある。

これらは今後企業インタビュー・実利用で検証する。

---

# 28. 求職者向け価値仮説

## HYPOTHESIS

Level 2 Structured Job Profileによって、

求人票だけでは判断しにくかった、

- 具体的仕事内容
- 工程
- ツール
- 仕事の進め方
- チーム
- 顧客との関係
- 現場との関係
- 仕事の難しさ
- 1日の流れ

等が見えることで、

**求職者が応募前に求人をより深く理解・比較できる**

可能性がある。

---

# 29. ビジネスモデル

## HYPOTHESIS

将来的な企業向けプランは、

**情報の厚さ・運用支援**

に課金する方向を候補とする。

例：

### Basic

- 基本求人登録
- Manual
- 無料または低価格

### Structured

- Level 2 Structured Job Profile
- 詳細情報
- 比較表示強化

### Sync

- CSV
- API
- ATS
- Authorized Crawl Sync

### Editorial

- JobDD現地取材
- 写真
- インタビュー
- 編集記事

## DECISION

課金額によって、

- Fit判定
- Comparison結果
- MATCH / MISMATCH
- 求職者向けランキング

を有利にしない。

**Paid Information Depth ≠ Paid Ranking**

を原則とする。

---

# 30. AIの位置づけ

## DECISION

AIはJobDDの主役ではない。

AIは、

- 情報抽出
- 情報整理
- 正規化
- Fact候補生成
- Context Role補助
- 説明文生成
- 比較補助

等に使用できる。

最終判断は求職者本人が行う。

また、AIを使わなければ成立しないUXに限定しない。

可能な部分は、

- 構造化データ
- Rules
- 定型説明
- Deterministic Logic

によって実現する。

---

# 31. 技術資産

## FACT

現在のJobDDには少なくとも以下の資産が存在する。

- Laravel 13
- MySQL
- Blade
- Tailwind
- companies
- job_postings
- application_routes
- sources
- agencies
- agency_facts
- job_facts
- user_queries
- ContextRole
- JobFit
- Evidence
- Job Discovery
- 求人一覧
- 求人詳細
- 求人比較
- Map View
- Agent Decision View
- Interaction Log

## DECISION

企業登録型への変更では新しいLaravelプロジェクトを作らず、

**現在のJobDDコードベースを継続利用する。**

既存Decision Support資産を再利用する。

---

# 32. 開発環境 / Git方針

## FACT

提出保険版：

`graduation-submit-ready-v1`

として固定済み。

## DECISION

v5.0開発では既存Repositoryを継続利用し、企業登録型開発用branchを切る。

候補：

`company-structured-jobs-v5`

新しい `jobdd-v5` Laravelプロジェクトは作成しない。

---

# 33. 開発原則

## DECISION

新機能は、

**「作れるか」ではなく「どの仮説を検証するための機能か」**

を優先する。

機能追加時には可能な限り、

- 何を検証する機能か
- 誰のPainに対応するか
- Phase Aに必要か
- Phase Bへ後回しできるか

を明確にする。

## DECISION

卒業制作中も高速実装を利用する。

ただし、

- Safety Boundary
- Automated Test
- DB Safety
- Git Checkpoint
- Production Smoke Test

を維持する。

---

# 34. JobDDが目指さないもの

現時点でJobDDは以下を中心サービスにしない。

- 人材紹介会社向け基幹業務SaaS
- 求人と求職者を大量自動マッチングするHM型サービス
- AIが転職先を決定するサービス
- 課金企業を優遇するランキングサイト
- 求人件数の多さだけで競争する求人検索エンジン
- 全国全職種を最初から網羅する巨大求人DB
- 大規模口コミサイト
- 求職者の意思決定を代行するサービス

---

# 35. Why JobDD

JobDDが目指す価値は、

**求人情報を増やすことではなく、意思決定に必要な情報を増やすこと**

である。

求人票に情報が大量に書かれていても、

求職者が、

- 自分に関係する情報
- 関係しない情報
- 条件と一致する情報
- 条件と異なる情報
- まだ分からない情報

を判断できなければ、意思決定は難しい。

JobDDはそれらを整理し、

**「なぜそう判断できるのか」までEvidence付きで見える状態**

を作る。

---

# 36. North Star

## HYPOTHESIS

JobDDのNorth Star候補は、

> **求人を選ぶ前に、仕事の中身を理解して比較できる状態をつくる。**

である。

より短いプロダクト表現候補：

> **根拠とともに、仕事を選ぶ。**

このNorth Star自体も、今後ユーザー検証を通じて改善する。

---

# 37. 現在の最重要検証テーマ

## HYPOTHESIS｜求職者側

機械設計・電気設計の求職者は、

**通常の求人票だけでは分からない仕事の中身を、応募前に理解・比較したい**

というPainを持っているか。

## HYPOTHESIS｜企業側

機械設計・電気設計人材を採用する企業は、

**通常の求人票では仕事内容を十分に伝えにくい**

というPainを持っているか。

## HYPOTHESIS｜Structured Job Profile

JobDDのガイド付きLevel 2入力によって、

企業側の仕事内容の言語化と、求職者側の求人理解の両方を改善できるか。

---

# 38. 重要なOPEN

## OPEN｜求職者

- Level 2情報のどれが最も重要か
- Typical Dayは意思決定に有効か
- 「向いている人 / 合いにくい人」が有効か
- Evidence表示はどこまで必要か
- 比較画面で何項目まで扱うべきか
- Agent比較Painは実在するか

## OPEN｜企業

- Structured入力を実際に使いたいか
- 入力負担を許容できるか
- どこまで採用担当者だけで回答できるか
- 技術部門の協力が必要か
- Syncに対価を払うか
- Editorialに対価を払うか
- Structured情報が応募品質を改善するか

## OPEN｜Business

- Basic無料 / Structured有料が成立するか
- Sync料金
- Editorial料金
- 初期営業方法
- 採用単価との比較
- 企業契約までに必要な求職者Traffic

---

# 39. v5.3直近優先順位 / 実装Batch

## DECISION

Phase Aは以下のBatchで実装する。

### Batch 0｜設計凍結

DB・内部key・Snapshot・route命名などの根本仕様を固定する。

### Batch 1｜DB Foundation

新規5テーブル、既存3テーブル変更、Model relation / castsを実装する。

### Batch 2｜Company Account / Ownership

Self-service登録、Ownership、Policy、Platform Owner / Admin分離を実装する。

### Batch 3｜Company Dashboard + Level 1

企業Dashboard、求人Draft作成、Level 1 CRUDを実装する。

### Batch 4｜Level 2 Authoring

STEP 1〜5、Tool、Typical Day、Completion計算、途中保存・再開を実装する。

### Batch 5｜Preview / Review / Publish Core

Preview、公開申請、postmaster@jobdd.jpへの通知、Platform Owner向け公開審査画面、承認 / 差戻し、Publish Validator、Job Fact Dictionary、Transformer、Source / Provenance、Context Role、Direct Route、Published Snapshotを実装する。

**Batch 5は重点レビュー箇所とし、Transaction / rollback / 既存Fact保護 / 再審査中Snapshot維持を厚くテストする。**

### Batch 6｜Seeker Decision View v2

Published Snapshot / Job Factを使った仕事理解画面を実装する。

### Batch 7｜Seeker Progressive Input

簡易入力 → 求人一覧 → 必要時の詳細希望入力を実装する。詳細希望はまず比較材料として利用する。

### Batch 8｜Compare / Evidence / Application Route

求人理解 → 比較 → Evidence / Provenance → 応募方法 → 外部遷移を一本につなぐ。

### Batch 9｜Hardening / Production Readiness

全回帰、認可、Draft漏洩、再Publish、PC / mobile、Production Preflightまでを確認する。

実Production Smokeと第2Submit Ready Tagは、deploy後に別リリース工程として実施する。

## Git

設計反映後に既存Repositoryで `company-structured-jobs-v5` branchを作成し、Batch単位でGit checkpointを作る。

---

# 40. Master更新条件

以下が発生した場合は、JobDD_Master更新候補として扱う。

- HYPOTHESISがFACTになった
- HYPOTHESISが否定された
- Painが変わった
- Targetが変わった
- MVPが変わった
- Business Modelが変わった
- Data Supply Modelが変わった
- 重要な競合・代替手段が判明した
- 大きなPivotを行った
- User Interviewで重要な新事実を得た
- Productionで重要な実装状態が変わった

---

# 41. ChatGPT / Codex運用ルール

JobDDについて議論・実装するときは、本Masterを最優先する。

ChatGPTは、

- Product
- Business
- UX
- Architecture
- Hypothesis Design
- Validation Design

を中心に支援する。

Codexは、

- 実装
- Test
- Refactoring
- Mechanical Investigation

を中心に利用する。

ただしCodexへ、

- Pain定義
- Business判断
- UXの根本判断
- DB根本設計

を無条件に委任しない。

重要な設計変更はMaster / Decision Logへ反映してから実装する。

---

# 42. v5.9で絶対に戻らない原則

1. HYPOTHESISをFACTとして扱わない
2. HM型業務効率化SaaSへ根拠なく戻らない
3. AIに最終判断をさせない
4. UNKNOWNをMISMATCHとして扱わない
5. 課金でComparison結果を歪めない
6. 求人件数の多さだけを価値にしない
7. 無許諾クローリング依存へ戻らない
8. 企業入力機能そのものをJobDDの価値と勘違いしない
9. Level 2情報は必ず求職者側Decision Supportへ還元する
10. 卒業制作ではPhase Aを完成させてからPhase Bへ進む
11. 卒業制作Demoは入力内容で明示し、Demo専用システム分岐を追加せず、実応募へ接続せず、審査終了後に `paused` とする

---

# 43. 現在の一文説明

## Short

**JobDDは、求人を探すだけでは分からない仕事の中身を構造化し、根拠とともに比較して、自分で選べるようにするDecision Supportサービスです。**

## Company Side

**JobDDは、求人を掲載するだけの場所ではなく、仕事の中身を求職者に正しく理解してもらうためのサービスです。**

## Seeker Side

**求人を探すだけでは、わからない。仕事の中身まで比べて、選ぶ。**

---

# 44. 現在の最重要問い

JobDD v5.9で最も重要なのは、

> **機械設計・電気設計の仕事について、企業が仕事内容をより深く構造化して伝え、求職者が応募前にそれを比較できることに、企業・求職者双方が本当に価値を感じるのか？**

である。

卒業制作では、

**企業がLevel 2まで登録する → 求職者画面へ反映する → 実際に比較できる**

ところまで実装し、この仮説を検証可能な状態を作る。
