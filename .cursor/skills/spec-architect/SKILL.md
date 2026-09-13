---
name: spec-architect
description: 仕様をユースケース、受け入れ基準、ドメインモデルへ分解する。要件整理、仕様駆動開発、DDDの観点整理、実装前設計が必要なときに使う。
disable-model-invocation: true
---

# Spec Architect

## 目的

曖昧な要求を「実装可能でテスト可能な仕様」に変換する。
PDM / Domain Expert / System Architect の観点で、ユーザー価値、優先度、スコープ、業務ルール、技術構造を実装前に固定する。

## 使うタイミング

- 要件が文章だけで、受け入れ条件が未定義
- 実装前に DDD の境界・責務を明確化したい
- TDD の最初の失敗テストを決めたい

## 実行手順

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
6. ドメインを整理する。
   - エンティティ、値オブジェクト、集約
   - 集約ルートで守る不変条件
   - アプリケーションサービスの責務
7. 仕様を検証可能な粒度に落とす。
   - API 契約（入力、出力、エラー）
   - DB 変更の有無
   - フロント表示/操作仕様
8. テスト観点に変換する。
   - Unit: ビジネスルール
   - Integration/Feature: ユースケース成立
   - E2E 相当: 主要ユーザーフロー

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
- 境界づけられたコンテキスト:
- 集約:
- 不変条件:
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

- API でメンバー識別子を扱う場合は `project_member_id` と `id` を混同しない
- 論理削除対象は `del_flg = false` 条件を明示する
