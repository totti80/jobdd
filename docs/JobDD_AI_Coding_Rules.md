# JobDD_AI_Coding_Rules.md
## JobDD｜AI Coding Agent / Codex 運用ルール

最終更新: 2026-09-18

---

## 1. 目的

この文書は、JobDD開発において Codex、Claude Code、その他のAI Coding Agentへ実装・調査・レビューを依頼する際の標準運用ルールを定義する。

JobDDでは、AI Coding Agentを単なるコード生成器として扱わない。

目的は、

- 実装速度を上げる
- 既存仕様を壊さない
- 変更範囲を明確にする
- 過剰な実装や不要なリファクタリングを防ぐ
- テスト範囲を変更規模に合わせる
- 人間がコードと設計意図を理解できる状態を保つ
- 将来のマルチエージェント開発へ接続できる運用基盤を作る

ことである。

---

## 2. 基本方針

JobDDでは、AI Coding Agentへの依頼を以下の構造で行う。

**Goal / Context / Scope / Constraints / Definition of Done / Verification**

を最低限明示し、必要に応じて

**Testing Scope / Parallelization / Ambiguity Rule / Report Back**

を追加する。

単に、

> 「この機能を作って」

と依頼するのではなく、

> 「何を完成させるか」  
> 「なぜ必要か」  
> 「どこまで触ってよいか」  
> 「何を変更してはいけないか」  
> 「どの状態なら完了か」  
> 「どう確認するか」

まで定義してから実装させる。

---

## 3. AI Coding Agentへの標準依頼フォーマット

### 3.1 Task

何を完成させたいかを、1〜2文で明確に記述する。

例：

> 検索結果ページのEvidence表示を、情報量を減らさずに見つけやすく改善する。

### 3.2 Context

変更の背景、既存仕様、設計意図を記述する。

必要な情報だけを渡し、無関係な情報を大量に含めない。

例：

- JobDDはDecision Supportサービスである
- AIが最終判断を行うのではなく、ユーザー本人が判断する
- Evidence / Source / Scoreの透明性を重視する
- 現在の検索ロジックは変更対象外

### 3.3 Scope

今回変更してよい範囲を明示する。

例：

- `resources/views/results.blade.php`
- 関連するBlade Component
- Tailwind CSS class
- 対象画面の表示ロジック

### 3.4 Do Not Change

変更してはいけない範囲を明示する。

例：

- DB schema
- migration
- ScoreService
- route
- API仕様
- user_queries保存ロジック
- application_routes生成ロジック

AI Coding Agentが「ついでに改善」しないよう、明確に境界を設定する。

### 3.5 Constraints

技術・設計・UI・依存関係などの制約を記述する。

例：

- Laravel 11を維持する
- Blade + Tailwindを維持する
- 不要な新規ライブラリを追加しない
- 既存データ構造を変更しない
- PC / mobile両方で成立させる
- Evidenceの情報量を削らない
- 既存の命名規則に合わせる

---

## 4. Definition of Done

実装前に「何をもって完了とするか」を定義する。

例：

- 対象画面が正常表示される
- PC / mobileでレイアウト崩れがない
- Evidenceが現在より見つけやすい
- Score / Source / 応募経路比較が従来どおり利用できる
- console error / Laravel errorがない
- 既存仕様に影響がない

「それっぽく動く」ではなく、完了条件を観測可能な形で定義する。

---

## 5. Testing Scope

AI Coding Agentには、変更規模に応じたテスト範囲を明示する。

### 小規模変更

例：

- Blade表示
- CSS
- 文言
- 小さなUI改善

の場合、

> 今回の変更に直接関係する確認だけ実施する。  
> 無関係な全体テスト、大規模リファクタリング、広範な調査は行わない。  
> 新しいエラーや依存問題を発見した場合のみ調査範囲を拡張する。

### 中〜大規模変更

例：

- Service変更
- DB変更
- 認証
- API
- scoring
- application route生成

の場合、

> 関連するFeature Test / Unit Test / integration pointまで確認する。  
> 影響範囲を明示したうえで、必要な回帰確認を実施する。

重要なのは、

**常に最大テストを要求するのではなく、変更のリスクに合わせて検証範囲を指定すること。**

---

## 6. Parallelization

独立した作業のみ、並列化を許可する。

例：

- Result Card
- Evidence Block
- Mobile Layout

が互いに独立している場合は、並列subagentへ分割してよい。

一方、

- migration → model → service
- API → controller → view

のように依存関係がある作業は、無理に並列化しない。

原則：

> Parallelize independent work only.

目的はAgent数を増やすことではなく、不要な待ち時間を減らすことである。

---

## 7. Ambiguity Rule

AI Coding Agentが確認質問を行う条件を明示する。

### 質問する

以下の場合のみ質問する。

- 結果を大きく変える仕様上の曖昧さ
- DBやAPI仕様を変える可能性がある
- 既存Decisionと矛盾する
- 複数案でUXや事業要件が大きく変わる
- 不可逆な操作が必要

### 質問せず進める

以下は既存コードのパターンに合わせて判断してよい。

- 変数名
- class名
- 軽微なレイアウト判断
- 既存パターンに沿ったcomponent分割
- 小さなリファクタリング判断

原則：

> 重大な曖昧さだけ確認し、軽微な判断で開発を止めない。

---

## 8. Verification

実装後、最低限以下を確認する。

- 変更差分
- 変更対象の動作
- 既存仕様との一致
- console error
- Laravel log
- PC表示
- mobile表示
- 必要なテスト結果

コードを「書いた」ことではなく、

**期待する結果になったことをEvidence付きで確認する。**

---

## 9. Report Back

AI Coding Agentは作業終了後、必ず以下を報告する。

1. 変更したファイル
2. 何を変更したか
3. なぜその方法を選んだか
4. 実施した確認・テスト
5. 未解決事項
6. 残っているリスク
7. 次に人間が確認すべき箇所

大量の説明は不要だが、人間が変更内容を追跡できることを優先する。

---

## 10. 学習方針

JobDDでは、AI Coding Agentへ実装を丸投げし、ブラックボックス化しない。

実装後は、とおるが以下を理解できる状態を維持する。

- 何を変更したか
- なぜその変更が必要だったか
- 各ファイルがどの役割を持つか
- データがどの経路で流れるか
- 変更が他の機能へどう影響するか

AI Coding Agentの目的は、

**「自分がコードを書かなくて済むこと」ではなく、  
「理解を保ちながら開発速度を上げること」**

である。

---

## 11. リファクタリングルール

ユーザーから明示されていない大規模リファクタリングは原則禁止する。

AI Coding Agentが改善余地を見つけた場合は、

> 現在のTaskとは分離して、改善候補として報告する。

今回のTaskに必要な範囲だけ変更する。

例外：

- 現状のままではTask実装が不可能
- 明確な不具合原因になっている
- セキュリティ上重大
- テスト不能

この場合は、理由と影響範囲を説明してから進める。

---

## 12. 新規依存関係ルール

Composer / npm package / MCP / 外部サービス等を新規追加する場合、以下を事前確認する。

- 本当に必要か
- 既存機能で代替できないか
- maintenance状況
- license
- security
- bundle size / dependency増加
- 本番環境への影響

AI Coding Agentが勝手に新規ライブラリを追加しない。

---

## 13. 不可逆操作

以下は人間承認なしで実行しない。

- 本番DB変更
- 本番データ削除
- destructive migration
- production deploy
- Git history rewrite
- force push
- secret / credential変更
- 外部公開
- 課金処理
- 本番設定変更

AI Coding Agentは不可逆操作の直前まで準備し、

**実行前で停止して人間へ確認する。**

---

## 14. Agent分業を行う場合

将来的に複数Agentを使う場合、役割とhandoff contractを明示する。

### Architect

入力：
- Task
- Context
- Constraints

出力：
- 実装計画
- 変更ファイル候補
- Interface
- Definition of Done

### Builder

入力：
- Architect仕様

出力：
- diff
- 実装
- テスト結果
- 不明点

### Critic / Reviewer

入力：
- Task
- Definition of Done
- diff
- test result

出力：
- PASS / REVISE / FAIL
- 具体的な問題点
- 根拠

### Manager

入力：
- Reviewer verdict

出力：
- 完了
- Builderへ修正指示
- 人間へエスカレーション

重要：

**Agent間では会話全文を渡すのではなく、必要なartifactとhandoff packetを渡す。**

---

## 15. Retry / Stop Condition

自己修正Loopを使う場合、無限ループを禁止する。

例：

- 最大修正回数：3回
- 同じ失敗が2回続いたら人間へ報告
- 想定時間を超えたら停止
- コスト上限を超えたら停止
- 仕様解釈の問題なら再実装せず人間へ戻す

AI Coding Agent自身に、無制限な実行権限を与えない。

---

## 16. JobDDでの優先順位

AI Coding Agentは以下を優先する。

1. 既存仕様を壊さない
2. Decision Support思想を守る
3. Evidence / Source / Scoreの透明性を維持する
4. ユーザー本人の最終判断を尊重する
5. 小さい変更で目的を達成する
6. テスト可能な状態を保つ
7. 人間が理解可能な変更にする
8. その後に速度を追求する

---

## 17. Codex向け標準テンプレート

```text
# Task
[今回完成させたいこと]

# Context
[背景 / 既存仕様 / 設計意図]

# Scope
[変更してよい範囲]

# Do Not Change
[変更禁止範囲]

# Constraints
[技術 / UI / DB / 依存関係等の条件]

# Definition of Done
[完了条件]

# Testing Scope
[今回必要なテスト範囲]

# Parallelization
[独立作業がある場合のみ記述]

# Ambiguity Rule
結果を大きく変える重大な曖昧さがある場合のみ質問してください。
軽微な実装判断は既存コード・既存設計パターンに合わせて進めてください。

# Verification
実装後、変更差分・対象機能・必要なテスト・エラー有無を確認してください。

# Report Back
最後に以下を簡潔に報告してください。
1. 変更したファイル
2. 変更内容
3. 変更理由
4. 実施した確認 / テスト
5. 残っている懸念点

実装だけで終わらず、とおるがコードを理解できるよう、変更意図を短く説明してください。
```

---

## 18. このルールの扱い

この文書は、JobDDにおけるAI Coding Agent運用の正本とする。

`JobDD_Master_v4.0.md` には、以下のような参照を追加する。

> Codex / Claude Code / その他AI Coding Agentの運用ルールについては `JobDD_AI_Coding_Rules.md` を正本とする。

今後、AI Agent運用方針を変更した場合は、この文書を更新する。
