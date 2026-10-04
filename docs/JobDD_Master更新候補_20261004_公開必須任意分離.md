# JobDD_Master更新候補
## 公開必須項目と任意項目の分離 / 必須・任意表示

**作成日:** 2026-10-04  
**対象:** JobDD_Master v5.9 / JobDD_Decision_Log v4.6  
**ステータス:** JobDD_Master更新候補 / Decision Log更新候補

---

# 1. 背景

## FACT

JobDDの企業向け求人入力では、Level 1 Basic と Level 2 Structured Job Profile を入力し、Preview から公開申請を行う。

現行実装では、公開申請前のValidatorによって複数のLevel 2項目が必須判定されている。

実際の本番確認では、以下の未入力項目がある場合、公開申請できないことを確認した。

- 最初に担当する仕事
- 仕事の進め方
- 仕事の難しさ
- 製造との関わり方
- 現場との関わり方
- 製造との接点の頻度
- 現場との接点の頻度

また、現行のJobDD_Master v5.9では、Level 2の一部項目について明示的に「必須」と定義している。

例：

- `design_target`
- `design_phases`
- `initial_assignment`

---

# 2. 今回の問題認識

## HYPOTHESIS

企業担当者にLevel 2の全項目入力を公開申請前に要求すると、入力負荷が高くなり、求人登録途中での離脱や公開申請までのハードル上昇につながる可能性がある。

特に、

- 製造との関わり
- 現場との関わり
- 製造との接点頻度
- 現場との接点頻度
- Typical Day等の補足情報

は、企業担当者がその場で把握していないケースも想定される。

---

# 3. 基本思想

## DECISION

JobDDでは、

> すべての項目を入力させること

を公開条件にするのではなく、

> 求職者が仕事を理解・比較し、意思決定するために最低限必要な情報が揃っていること

を公開条件とする。

JobDDはRecommendではなくDecision Supportである。

そのため、公開可否に必要な「コア情報」と、入力すると求人情報の解像度が上がる「追加情報」を分離する。

---

# 4. 公開必須と任意の分離

## DECISION

企業求人入力項目を、以下の2区分へ整理する。

### Required

公開申請に必要な項目。

未入力の場合は公開申請できない。

### Optional

公開申請には必須ではない項目。

未入力でも公開申請できるが、入力されている場合は求職者向けDecision View、比較、Job Fact等で活用する。

---

# 5. 公開必須候補

## OPEN

具体的なRequired項目の最終確定は、現行Validator調査後に行う。

現時点の候補は以下。

### Level 1

現行の公開必須項目を基本的に維持する。

候補：

- 求人タイトル
- 職種
- 勤務地
- 年収
- 雇用形態
- 仕事内容
- 応募URL
- その他、現在のLevel 1 Validatorで必須としている項目

### Level 2

現時点の必須候補：

- `design_target`
  - この求人では何を設計するか

- `design_phases`
  - 主に担当する設計工程

- Tool情報
  - 少なくとも1件
  - `tool_usage`
  - `tool_expectation`
  - 「未定」の扱いは現行Validator調査後に確定する

- `initial_assignment`
  - 入社直後に担当する仕事

- `work_style`
  - 仕事の進め方

- 仕事の難しさ
  - 保存キーは現行実装確認後に確定する

---

# 6. 任意候補

## OPEN

以下は公開必須から外し、Optionalへ変更する候補とする。

- `manufacturing_relation`
  - 製造との関わり

- `site_relation`
  - 現場との関わり

- 製造との接点頻度

- 現場との接点頻度

また、現行Validatorで必須となっているその他の項目についても、求職者の意思決定に必要な最低限情報かどうかを確認し、Required / Optionalを再分類する。

---

# 7. 必須・任意表示

## DECISION

企業向け入力フォームでは、各入力項目のラベル付近に以下を明示する。

- `必須`
- `任意`

企業担当者が入力前に公開必須項目を理解できるようにする。

例：

```text
この求人では、何を設計しますか？　必須

主な製品・設備・システムを教えてください　任意

主に担当する設計工程は？　必須

経験を積んだ後、担当範囲はどう広がりますか？　任意
```

---

# 8. Validatorとの整合

## DECISION

フォーム上の「必須 / 任意」表示と、公開申請Validatorの必須判定が食い違わないようにする。

可能であれば、

- 公開申請Validator
- フォームの必須 / 任意表示
- Previewの不足項目表示

が同じ必須定義を参照できる構成とする。

ただし、卒業制作MVPでは大規模リファクタリングを行わず、既存構造を大きく崩さない最小変更を優先する。

---

# 9. 入力充足率との関係

## OPEN

現行の「入力充足率」と公開可否の関係を調査する。

今後は概念として、

```text
公開可能条件
=
Required項目がすべて入力済み

情報充実度
=
Required + Optionalを含めた情報の充実度
```

へ分離する方向を検討する。

現時点では、情報充実度の表示仕様・計算式・求職者側への表示有無は未決定。

---

# 10. 既存仕様で維持するもの

## DECISION

今回の変更では、以下の既存仕様を維持する。

- Level 1 / Level 2の2段階入力構造
- Preview
- 公開申請
- Platform Owner / Admin審査
- 承認時のみPublish
- Published Snapshot
- Job Fact
- Evidence / Provenance
- Seeker Decision View
- Compare
- paused lifecycle
- Decision Support思想

任意項目に変更された情報も、入力されている場合は従来どおり求職者側Decision Supportへ還元する。

---

# 11. 次の実装前調査

## OPEN

Codexへ以下を調査依頼する。

1. 現在の公開申請Validatorで必須判定している全項目
2. 各項目の
   - 表示ラベル
   - 保存キー
   - Level 1 / Level 2
   - 現在の必須 / 任意
   - Validator実装箇所
   - フォーム表示箇所
   - Preview不足表示との関係
3. 現在の入力充足率計算
4. 入力充足率が公開可否に利用されているか
5. Tool入力の公開最低条件
6. Proposed Required Coreとの差分
7. RequiredからOptionalへ変更した場合の影響
8. Published Snapshot / Job Facts / Seeker Decision View / Compareへの影響
9. DB migrationの要否
10. Validator・必須表示・Preview不足表示の定義一元化の可否

調査結果を確認後、Required / Optionalの最終項目を確定してから実装する。

---

# 12. Decision Log追加候補

## D-110候補｜公開必須項目と任意項目を分離する

**日付:** 2026-10-04  
**Status:** 候補

### 背景

企業向け求人入力の本番確認で、Level 2の多くの項目をすべて入力しないと公開申請できないことを確認した。

公開品質の担保は必要だが、全項目を公開必須にすると企業担当者の入力負荷が高くなる可能性がある。

### 意思決定

JobDDでは、

- 公開申請に必要なRequired項目
- 公開には必須ではないOptional項目

を分離する。

フォームでは各入力項目に「必須 / 任意」を明示する。

Validatorと表示の必須定義は、可能な限り同一ルールを参照する。

### 判断理由

JobDDの目的は、企業へすべての求人情報を書かせることではなく、

**求職者が仕事の中身を理解・比較して意思決定できる情報を提供すること**

であるため。

### 未決定

- Required項目の最終一覧
- Toolの最低入力条件
- 情報充実度の計算方法
- 情報充実度の求職者側表示
- 必須定義の一元化方法

---

# 13. 情報分類まとめ

## FACT

- 現行実装では複数のLevel 2項目が公開申請時に必須。
- 本番画面で、製造・現場との関係や接点頻度を含む未入力項目があると公開申請できないことを確認。
- 現行MasterにはLevel 2の一部を必須とする定義がある。

## DECISION

- 公開必須と任意を分離する。
- 各入力項目に「必須 / 任意」を表示する。
- Validatorと画面表示の必須定義を整合させる。
- Optional項目も、入力されていればDecision Supportに利用する。

## HYPOTHESIS

- Level 2の全項目必須は企業担当者の入力負荷を高め、離脱や公開申請率低下につながる可能性がある。

## OPEN

- Required項目の最終一覧
- Tool入力条件
- 情報充実度の仕様
- Validator定義一元化の具体実装
- Codex調査結果を踏まえた最終Decision

---

# 14. 2026-10-04 追加検討｜Level 1のみ公開必須とする案

## 背景

Codexによる現行実装調査の結果、現在の公開申請条件は「全項目必須」ではなく、
**Level 1 Basic + Level 2 Core** を必須とする構成であることを確認した。

また、入力充足率は公開可否そのものには使用されておらず、公開可否は `CompanyJobPublishValidator` により判定されている。

このため、公開必須範囲をLevel 1のみに変更する場合でも、DB migrationなしで実現可能である。

---

## HYPOTHESIS

JobDDの入力設計思想を、以下のように明確化した方が企業側UXと将来拡張の両面で自然である可能性が高い。

```text
Level 1 Basic
= 簡単入力
= 求人を公開するための最低限情報

Level 2 Structured Job Profile
= JobDDらしさを尖らせる情報
= 求職者のDecision Supportを強化する情報
= 公開には必須ではない
```

---

## DECISION候補

公開申請のRequiredは、**Level 1 Basicのみ**とする方向を第一候補とする。

Level 2 Structured Job Profileは全項目Optionalとし、未入力でも公開申請可能とする。

ただし、Level 2に入力された情報は、従来どおり以下へ利用する。

- Published Snapshot
- Job Fact
- Evidence / Provenance
- Seeker Decision View
- Compare

Level 2をOptionalにすることは、Level 2の価値を下げることではない。

むしろ、

> 公開のための最低限情報

と

> 求職者の判断を助ける追加情報

を分離することで、JobDDの情報構造を明確にする。

---

# 15. 公開可否と情報充実度の分離

## DECISION候補

今後は、以下の2つを明確に分離する。

```text
公開可能条件
=
Level 1 Required項目がすべて有効

情報充実度
=
Level 1 + Level 2の入力状況
```

公開可能であることと、情報が十分に充実していることは同じ意味ではない。

企業向けDashboard / Previewでは、将来的に以下のような表示を検討する。

```text
公開準備
必須項目 100%
→ 公開申請できます

情報充実度
72%
→ 仕事の中身をもっと詳しく伝えられます
```

## OPEN

- 情報充実度の計算式
- 項目ごとの重み
- Dashboardでの表示方法
- 求職者側へ情報充実度を表示するか

卒業制作MVPでは、情報充実度の高度なスコア設計は後回しとしてよい。

---

# 16. Optional項目の入力時Validation

## DECISION候補

Optionalは、

> 未入力でも公開可能

という意味であり、

> 入力した不正な値も許容する

という意味ではない。

したがって、Level 2を全体としてOptionalにした場合でも、入力された項目については既存の型・選択肢・整合性Validationを維持する。

例：

### Typical Day

- 0行 → 公開可能
- 行を追加 → 時間帯と作業内容の整合を確認

### Tool

- 0行 → 公開可能候補
- 行を追加 → ツール名、使用場面、経験期待等の入力整合性を確認

この考え方により、入力負荷を下げながらデータ品質を維持する。

---

# 17. 将来の有料化を前提とした設計原則

## HYPOTHESIS

将来、JobDDでは「仕事の中身をより詳しく伝える機能」を有料化する可能性がある。

例：

- Level 2の一部高度機能
- Level 3 Editorial
- 独自取材
- 写真・インタビュー
- 強調表示
- 高度な比較情報
- 分析機能

ただし、卒業制作MVPでは課金機能そのものは実装しない。

---

## DECISION候補

将来の有料化を見据え、以下の3概念を分離する。

### 1. Publication Requirement

その求人を公開するために必要な条件。

```text
公開できるか？
```

### 2. Information Completeness

その求人情報がどれだけ充実しているか。

```text
どれだけ詳しく仕事を伝えられているか？
```

### 3. Feature Entitlement

契約プランによって、その機能・入力項目・表示機能を利用できるか。

```text
その企業プランで使えるか？
```

これらを同じ条件分岐へ混在させない。

---

## 重要原則

> 公開必須判定と将来のプラン別利用可否は別概念として扱い、Publication Requirementに課金ロジックを混在させない。

将来的に有料化する場合でも、

```text
required_for_publish
availability_by_plan
completion_weight
```

のような概念を独立して扱える構成とする。

現時点では `availability_by_plan` や課金処理を実装する必要はない。

重要なのは、Publication Requirementに課金条件を埋め込まないことである。

---

# 18. 将来プランの概念例

## HYPOTHESIS

将来的には、例えば以下のような整理が可能。

```text
Level 1
= Free
= Required
= Publish Gate

Level 2
= Optional
= Decision Support Enhancement
= Free / Premium混在の余地

Level 3
= Editorial
= Premium候補
```

この構造にすることで、

- 無料で求人掲載
- Level 2でJobDDの価値を体験
- 高度な情報表現や独自取材を有料化

という段階的な事業展開がしやすくなる。

---

# 19. 現時点の推奨方針

## DECISION候補

現時点の第一候補は以下。

### Required

Level 1 Basicのみ。

### Optional

Level 2 Structured Job Profileの全項目。

### UX

- 各入力項目に「必須 / 任意」を表示
- Level 1は公開必須
- Level 2は任意
- Level 2入力を強制ではなくUXで促す
- 「公開可能」と「情報充実度」を別表示

### Architecture

- Publication Requirement
- Information Completeness
- Feature Entitlement

を分離する。

### Scope

卒業制作MVPでは、

- Level 1のみで公開可能にする
- Level 2は任意化
- 必須 / 任意表示
- Validatorと表示の整合

までを優先する。

課金機能、契約プラン、Entitlement管理は実装しない。

---

# 20. 追加の情報分類

## FACT

- 現行実装はLevel 1 + Level 2 Coreを公開必須としている。
- 公開可否は入力充足率ではなく公開Validatorで判定される。
- DB migrationなしでRequired / Optionalの変更が可能。

## DECISION候補

- Level 1のみを公開必須とする方向を第一候補とする。
- Level 2は全項目Optionalとする。
- Optional項目でも入力済み値のValidationは維持する。
- 公開可否と情報充実度を分離する。
- Publication Requirementと将来の課金・Entitlementを分離する。

## HYPOTHESIS

- Level 1のみ必須の方が企業導入時の入力負荷を下げられる。
- Level 2を任意にすることで、将来の有料化や高度化へ拡張しやすい。

## OPEN

- Level 1のみ必須とする正式Decision
- 情報充実度の計算式
- Tool / Typical Day等の部分入力Validation詳細
- 将来のFree / Premium境界
- Entitlement管理の実装時期
