---
name: spec-architect
description: 仕様をユースケース、受け入れ基準、ドメインモデルへ分解する。要件整理、仕様駆動開発、DDDの観点整理、実装前設計が必要なときに使う。
disable-model-invocation: true
---

# Spec Architect

## 目的

曖昧な要求を「実装可能でテスト可能な仕様」に変換する。
PDM / Domain Expert / System Architect の観点で、ユーザー価値、優先度、スコープ、業務ルール、技術構造を実装前に固定する。
DDD の検討は `docs/architecture/ddd.md` に従い、軽量DDDとして必要な場合だけ行う。
DDDは完成形を固定せず、変更ごとの複雑性に応じて段階的に進化させる。
ドメイン不変条件は `docs/domain/warikan/common-invariants.md` を正本とする。

## 使うタイミング

- 要件が文章だけで、受け入れ条件が未定義
- 実装前に DDD の境界・責務を明確化したい
- TDD の最初の失敗テストを決めたい

## 実行手順

0. 共通知識を確認する。
   - DDD設計方針: `docs/architecture/ddd.md`
   - ドメイン不変条件: `docs/domain/warikan/common-invariants.md`
   - 単純CRUDや表示整形でドメイン複雑性がない場合は、DDD構造追加不要と判断してよい
   - DDD進化が必要な場合も、大規模リファクタを勝手に前提化せず、提案、issue化、小さなPRに分ける
1. PDM 観点で目的とスコープを整理する。
   - ユーザー価値
   - 成功指標
   - 優先度
   - リリース判断
   - 今回やること / やらないこと
2. Domain Expert 観点で業務ルールを整理する。
   - 用語の意味
   - ドメイン不変条件
   - 例外ケース
   - 既存仕様との整合
3. System Architect 観点で技術構造を整理する。
   - Backend / Frontend / Database / Infra の責務境界
   - 依存関係
   - 変更容易性
   - 拡張性
   - 今回直す構造問題 / 後でよい構造問題
4. 要求を次の形式に分解する。
   - ユーザーストーリー
   - スコープ内/スコープ外
   - 受け入れ基準（正常系、異常系、境界値）
5. 要件の不確実性を整理する。
   - 仮定（Assumptions）
   - 未確定要件（Unknowns）
   - ユーザー確認が必要な質問（Open Questions）
   - 未確定要件が残る場合は実装フェーズへ進まない
6. ドメイン複雑性を判定する。
   - 論理削除、権限、割り勘計算、識別子、状態遷移、複数テーブル整合性が絡むか
   - 既存の `project_member_id` / `del_flg` の不変条件へ影響するか
   - 単純CRUD、表示整形、既存Serviceへの小さな追記で十分か
   - ドメイン複雑性が低い場合は「DDD構造追加不要」と明記し、Entity / Value Object / Repository を機械的に新設しない
   - ドメインルールの重複、散在、不変条件の壊れやすさ、テストしづらさがあればDDD進化候補として記録する
7. ドメイン複雑性がある場合のみ DDD Design Check を行う。
   - Bounded Context
   - Domain terminology / Ubiquitous Language
   - Entity
   - Value Object
   - Aggregate
   - Aggregate Root
   - Domain Invariant
   - Repositoryの必要性
   - Domain Serviceの必要性
   - Application Serviceとの責務境界
   - 新設が不要なDDD要素と、その理由
8. 仕様を検証可能な粒度に落とす。
   - API 契約（入力、出力、エラー）
   - DB 変更の有無
   - フロント表示/操作仕様
9. テスト観点に変換する。
   - Unit: ビジネスルール
   - Integration/Feature: ユースケース成立
   - E2E 相当: 主要ユーザーフロー

## DDD Design Check の適用基準

DDD Design Check は、設計対象にドメイン複雑性がある場合だけ使う。

適用する例:

- `project_member_id` と内部IDの変換や公開範囲に影響する
- `del_flg`、削除済みメンバー、削除済み会計の扱いに影響する
- 割り勘計算、対象メンバー配分、支払いフローに影響する
- 権限、共有リンク、認証済み/未認証の境界に影響する
- 複数のEntityやテーブルを同時に更新し、不変条件を守る必要がある
- Serviceがドメイン判断、DB操作、手順制御を抱え込みすぎる

適用しない例:

- 単純CRUD
- 表示文言や軽微なUI変更
- 既存APIレスポンスの小さな表示用整形
- 既存Serviceの読みやすい範囲に収まる単純な入力検証
- DDD要素を増やしても仕様理解や安全性が上がらない変更

DDD要素は必要になった時だけ導入する。Entity、Value Object、Aggregate、Repository、Domain Serviceを機械的に新設しない。
DDD進化候補は、Product Owner と Product / Specification の判断へ戻せる形で整理する。

## 仕様設計役割分担

- PDM: ユーザー価値、成功指標、優先度、スコープ内外、リリース判断を扱う
- Domain Expert: 割り勘、会計、メンバー識別、論理削除、権限などの業務ルールと用語を扱う
- System Architect: 技術構成、境界、依存関係、責務分離、拡張性、変更容易性を扱う
- Spec Architect: PDM / Domain Expert / System Architect の判断を、受け入れ基準、API契約、DB/画面仕様、テスト計画へ落とす

### 役割間の受け渡し

- PDM から Spec Architect へ: 目的、優先度、今回含める/含めない範囲、リリース上の制約を渡す
- Domain Expert から Spec Architect へ: 用語、例外ケース、不変条件、既存仕様との整合リスクを渡す
- System Architect から Spec Architect へ: 技術境界、依存関係、責務分離、今回直す/後でよい構造問題を渡す
- Spec Architect から TDD / QA へ: 受け入れ基準、Unknowns、API契約、DB変更有無、テスト計画を渡す

## 出力テンプレート

```markdown
## ユーザーストーリー
As a ...
I want ...
So that ...

## 受け入れ基準
- [ ] 正常系:
- [ ] 異常系:
- [ ] 境界値:

## PDM 判定
- ユーザー価値:
- 成功指標:
- 優先度:
- スコープ内:
- スコープ外:
- リリース判断:

## 要件確認
- 仮定（Assumptions）:
- 未確定要件（Unknowns）:
- 要確認事項（Open Questions）:
- 実装フェーズ進行条件: 未確定要件が空であること

## ドメイン設計
- Domain Expert 判定:
- ドメイン複雑性: あり | なし
- DDD構造追加: 必要 | 不要（単純CRUD / 既存構造で十分）
- Bounded Context:
- Domain terminology / Ubiquitous Language:
- Entity:
- Value Object:
- Aggregate:
- Aggregate Root:
- Domain Invariant:
- Repositoryの必要性:
- Domain Serviceの必要性:
- Application Serviceとの責務境界:
- 新設しないDDD要素と理由:
- DDD進化候補:
- 提案 / issue化 / 小さなPRへの分解:
- 例外ケース:
- 既存仕様との整合:

## 実装方針
- Architecture:
- Backend:
- Frontend:
- Database:
- Infra:
- API契約:

## テスト計画
- Unit:
- Integration/Feature:
- 回帰観点:
```

## warikan_app 向け注意

- DDD設計方針は `docs/architecture/ddd.md` を参照する
- ドメイン不変条件は `docs/domain/warikan/common-invariants.md` を正本とする
- API でメンバー識別子を扱う場合は `project_member_id` と `id` を混同しない
- 論理削除対象は `del_flg = false` 条件を明示する
- 単純CRUDでは「DDD構造追加不要」と判断してよい
- DDD進化が必要な兆候を見つけたら、勝手に大規模リファクタを進めず提案として残す
