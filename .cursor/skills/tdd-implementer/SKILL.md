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
   - 必要に応じて複数のレビュー役で、仕様、境界値、契約、データ整合、セキュリティ、回帰を確認する
   - レビューで見つかった不足をテストに反映してから Green へ進む
3. **Green**
   - 実装対象に応じて AI Implementation Lead / Infra / SRE / Security / Database / Backend / Frontend / UI/UX / Test の役割を分ける
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

## 実装役割分担

実装は1人の担当にまとめず、変更対象に応じて役割を分ける。各役割は担当範囲の最小変更に集中し、他領域へ影響する場合は契約と検証結果を明示してから次へ渡す。

- AI Implementation Lead: AI が実装しやすい作業分解、指示の明確さ、担当範囲、受け渡し、検証可能性を監督する
- Infra Implementer: Docker、CI、環境変数、デプロイ設定、マイグレーション運用、外部サービス設定案を構築する
- SRE Engineer: 可用性、運用性、ログ、ヘルスチェック、デプロイ安全性、障害時の切り分けを確認する
- Security Engineer: 認証、認可、入力検証、秘密情報、権限境界、データ露出、依存関係リスクを確認する
- Database Engineer: テーブル設計、リレーション、インデックス、マイグレーション、クエリ効率、既存データ影響を扱う
- Backend Implementer: Laravel の Controller、Request、Service、Model、Feature/Unit Test を扱う
- Frontend Implementer: Next.js の画面、hooks、型、API呼び出し、UI状態、E2E観点を実装する
- UI/UX Designer: 画面導線、情報設計、表示状態、エラー/空状態、アクセシビリティ、操作負荷を設計する
- Test Implementer: 受け入れ基準に対応する Red、境界値、回帰テスト、テストデータを扱う

### Implementer 適用基準

- 変更が複数領域にまたがる、またはAIへ作業委譲する: AI Implementation Lead
- 環境構築、Render、Docker、CI、`.env.example`: Infra Implementer
- Render、Docker、CI、環境変数、起動順序、ログ、ヘルスチェック、デプロイ手順に運用リスクがある: SRE Engineer
- 認証、認可、入力検証、権限境界、共有リンク、秘密情報、依存関係にリスクがある: Security Engineer
- テーブル設計、リレーション、インデックス、マイグレーション、クエリ効率、既存データ影響: Database Engineer
- API、業務ロジック、権限、論理削除: Backend Implementer
- 画面、フォーム、表示、状態管理、API連携: Frontend Implementer
- ユーザー導線、入力体験、表示優先度、エラー/空/ロード状態、アクセシビリティ: UI/UX Designer
- 受け入れ基準、バグ再現、回帰防止のテスト追加: Test Implementer
- UIを伴う変更: Frontend Implementer と UI/UX Designer を分けて扱う
- DB変更や `project_member_id` / `del_flg` に関わる変更: Database, Security, Backend, Frontend, UI/UX, Test を基本セットにする
- デプロイ設定とアプリ挙動が同時に変わる場合: Infra, SRE, Security, Database, Backend, Frontend, UI/UX, Test を分けて扱う

### 役割間の受け渡し

- AI Implementation Lead から各役割へ: 目的、担当範囲、入力、期待出力、完了条件、検証方法を渡す
- Infra から SRE / Backend / Frontend へ: 必要な環境変数、起動条件、外部設定、承認が必要な操作を渡す
- SRE から Infra / Backend / Frontend へ: ヘルスチェック、ログ確認方法、デプロイ順序、障害時の切り分け観点を渡す
- Security から Backend / Frontend / Infra へ: 認証認可要件、入力検証、秘密情報の扱い、権限境界、データ露出リスクを渡す
- Database から Backend / Test へ: テーブル、リレーション、インデックス、マイグレーション影響、必要なデータ検証を渡す
- Backend から Frontend へ: API path, method, request, response, error, ID意味を渡す
- UI/UX から Frontend へ: 画面導線、状態別表示、文言、操作優先度、アクセシビリティ要件を渡す
- Frontend から Backend へ: UIが必要とする状態、エラー表示、追加で必要なAPI情報を戻す
- Test から各実装役へ: 失敗している期待動作、未カバーの境界値、回帰リスクを戻す
- 受け渡し後は `api-contract-keeper` と `qa-spec-guard` で契約と品質を確認する

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
- Security Reviewer: 認証、認可、入力検証、秘密情報、権限境界、データ露出を確認する
- Regression Reviewer: 既存機能、周辺テスト、E2E要否を確認する

### レビュー適用基準

- 小さな表示変更や文言修正: Spec Reviewer のみ
- Backend Service / DB / 権限変更: Spec, Data, Regression Reviewer
- API変更やFrontend連携変更: Spec, Contract, Regression Reviewer
- 認証、認可、共有リンク、入力検証、秘密情報、依存関係に関わる変更: Security Reviewer
- `project_member_id` / `del_flg` に関わる変更: Spec, Contract, Data, Security, Regression Reviewer
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

## Implementer 分担
- AI Implementation Lead:
- Infra Implementer:
- SRE Engineer:
- Security Engineer:
- Database Engineer:
- Backend Implementer:
- Frontend Implementer:
- UI/UX Designer:
- Test Implementer:
- 役割間の受け渡し:

## Test Review
- Spec Reviewer:
- Contract Reviewer:
- Data Reviewer:
- Security Reviewer:
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
