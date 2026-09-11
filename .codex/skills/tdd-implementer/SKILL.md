---
name: tdd-implementer
description: Red-Green-Refactor で小さく実装を進める。テスト先行実装、最小実装、リファクタリング、回帰防止を重視するときに使う。
disable-model-invocation: true
---

# TDD Implementer

## 目的

仕様に対して最小単位で失敗テストを追加し、最短で Green にして安全に改善する。

## 使うタイミング

- 実装に着手するとき
- 変更の影響範囲が読みにくいとき
- 回帰を避けながら段階的に開発したいとき

## サイクル

0. **Preflight**
   - 変更対象、対象外、関連する不変条件を確認する
   - 既存テスト、型、API契約、DB変更有無を確認する
   - DB変更がある場合はマイグレーション方針とロールバック方針を先に決める
1. **Red**
   - 1つの期待動作だけを表すテストを書く
   - まず失敗を確認する
2. **Test Review**
   - 実装前に、追加テストの観点漏れをレビューする
   - 必要に応じて複数のレビュー役で、仕様、境界値、契約、回帰、データ整合を確認する
   - レビューで見つかった不足をテストに反映してから Green へ進む
3. **Green**
   - テストを通す最小実装だけ追加する
   - 追加実装は次のテストまで持ち越す
4. **Refactor**
   - 重複排除と命名改善を行う
   - テストが全件 Green のままか確認する
5. **Regression**
   - 変更対象の周辺テスト、lint、typecheck を実行する
   - API変更やUI変更がある場合は契約同期とE2E要否を確認する

## 進め方ルール

- 1サイクル1目的（複数仕様を同時に実装しない）
- 失敗理由が仕様か実装かを先に分類する
- テストコードも設計対象として扱う
- 変更ごとに「なぜこのテストが必要か」を1行で説明できる状態にする
- エラーを握りつぶさず、既存の例外処理・レスポンス形式に合わせる
- 重複したバリデーションやID変換を増やす場合は、既存Serviceの責務へ寄せられないか確認する

## 品質ゲート

- 仕様: 受け入れ基準に対応するテストがある
- 契約: request / response / error / ID意味が backend, frontend, tests で揃っている
- 型: TypeScript型とLaravel validationが矛盾していない
- データ: 保存値、論理削除、権限境界をDB実データで確認している
- 回帰: 変更対象の周辺テストを実行し、未実行なら理由を残している
- 運用: マイグレーション、設定、外部連携がある場合は承認要否を分類している

## テストレビュー体制

実装者が書いたテストだけで品質判定しない。Greenへ進む前に、必要なレビュー役を分けてテスト観点を確認する。

- Spec Reviewer: 受け入れ基準、正常系、異常系、境界値の抜けを確認する
- Contract Reviewer: API request / response / error / ID意味の同期を確認する
- Data Reviewer: DB保存値、論理削除、権限境界、マイグレーション影響を確認する
- Regression Reviewer: 既存機能、周辺テスト、E2E要否を確認する

### レビュー適用基準

- 小さな表示変更や文言修正: Spec Reviewer のみ
- Backend Service / DB / 権限変更: Spec, Data, Regression Reviewer
- API変更やFrontend連携変更: Spec, Contract, Regression Reviewer
- `project_member_id` / `del_flg` に関わる変更: Spec, Contract, Data, Regression Reviewer
- マイグレーションを伴う変更: Data Reviewer を必須にする

### Greenへ進む条件

- 追加テストが受け入れ基準に対応している
- レビューで出た不足がテストへ反映済み、または未対応理由が明記されている
- 実装で満たすべき最小仕様が1サイクル1目的に収まっている

## マイグレーション方針

- DB変更がある場合は、先に目的、対象テーブル、既存データ影響、ロールバック可否を整理する
- カラム追加は nullable / default / backfill の要否を確認する
- カラム削除、型変更、制約追加は破壊的変更として扱い、実行前に承認を取る
- テストDBやローカル環境での migrate はローカル限定実行として進めてよい
- 本番適用、実データ変更、戻せないマイグレーションは承認必須

## warikan_app 向けチェック

- Backend Service 変更時は戻り値だけでなく DB 実データも検証する
- `project_member_id` を使うべき箇所で `id` を使っていないか確認する
- `del_flg` を考慮した検索条件がテストで再現されているか確認する
- Frontend 変更時は `apiFetch`、型定義、UI表示の3点を確認する
- 主要導線に影響するUI変更は E2E 要否を `qa-spec-guard` で確認する

## 出力テンプレート

```markdown
## Preflight
- 対象:
- 対象外:
- 不変条件:
- DB変更:

## 今回のRed
- 追加テスト:
- 失敗理由:

## Greenで入れる最小実装
- 変更対象:
- 変更理由:

## Test Review
- Spec Reviewer:
- Contract Reviewer:
- Data Reviewer:
- Regression Reviewer:
- テストへ反映した不足:
- 未対応理由:

## Refactor
- 改善内容:
- 振る舞い不変の根拠:

## Regression
- 実行した検証:
- 未実行の検証と理由:

## 次サイクル
- 次の失敗テスト:
```
