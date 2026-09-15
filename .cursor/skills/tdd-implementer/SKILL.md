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
   - Responsibility Classification を行い、Primary / Reviewer を決める
   - 変更対象、対象外、関連する不変条件を確認する
   - 既存テスト、型、API契約、DB変更有無を確認する
   - DB変更がある場合はマイグレーション方針とロールバック方針を先に決める
   - Application Engineering変更では `docs/architecture/ddd.md` を確認する
   - DDD設計判断が未確定の場合は、実装中に独自判断せず `spec-architect` へ戻す
   - DDD進化が必要な兆候を見つけても、大規模リファクタを勝手に実施せず提案として残す
1. **Red**
   - 1つの期待動作だけを表すテストを書く
   - まず失敗を確認する
2. **Test Review**
   - 実装前に、追加テストの観点漏れをレビューする
   - 必要に応じて複数のレビュー役で、仕様、境界値、契約、データ整合、セキュリティ、回帰を確認する
   - レビューで見つかった不足をテストに反映してから Green へ進む
3. **Green**
   - Primary Engineering Function が最小実装を担当する
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
- 実装開始前に Engineering Function の責務分類を行う
- 複数領域にまたがる場合は Primary と Reviewer を明示する
- テストコードも設計対象として扱う
- 変更ごとに「なぜこのテストが必要か」を1行で説明できる状態にする
- エラーを握りつぶさず、既存の例外処理・レスポンス形式に合わせる
- 重複したバリデーションやID変換を増やす場合は、既存Serviceの責務へ寄せられないか確認する
- Application Engineering変更でEntity / Value Object / Aggregate / Repository / Domain ServiceなどのDDD判断が必要になった場合は、`docs/architecture/ddd.md` と `docs/domain/warikan/common-invariants.md` を確認し、未確定なら `spec-architect` に戻す
- ドメインルールの重複、散在、不変条件の壊れやすさ、テストしづらさを見つけた場合は、DDD進化候補として記録し、実装中に独自判断で構造を増やさない

## Responsibility Classification

実装開始前に変更内容を次の Engineering Function へ分類する。Functionの責務境界は `docs/ai-driven/engineering-responsibilities.md` を正本とする。

- Application Engineering: Backend, Frontend, API, Database, Domain model, Application architecture
- Platform Engineering: CI/CD, runtime, dependency, Docker, development environment, developer experience, automation
- Reliability Engineering: SLO/SLI, production reliability, incident, observability, capacity, recovery, resilience
- Security Engineering: Auth, Authorization, Secret, Vulnerability, Threat modeling, 権限境界, 入力検証
- Quality Engineering: Test, Regression, Acceptance criteria, E2E, Contract verification

分類ルール:

- 変更ごとに Primary を1つ決める
- 複数領域にまたがる場合は Reviewer を1つ以上決める
- Reviewer は実装責務を奪わず、観点漏れとハンドオフ要否を確認する
- Reliability Engineeringは現在独立Agentにせず、必要時のレビュー責務として扱う
- Security / Quality は、該当リスクがある場合に必ずReviewerへ含める

分類例:

- API実装: Primary: Application Engineering / Reviewer: Security Engineering, Quality Engineering
- CI変更: Primary: Platform Engineering / Reviewer: Quality Engineering
- 依存更新: Primary: Platform Engineering / Reviewer: Security Engineering, Quality Engineering
- パフォーマンス改善: Primary: Application Engineering または Reliability Engineering / Reviewer: Platform Engineering
- SLO変更: Primary: Reliability Engineering / Reviewer: Platform Engineering, Quality Engineering
- 認可変更: Primary: Application Engineering / Reviewer: Security Engineering, Quality Engineering
- E2E追加: Primary: Quality Engineering / Reviewer: Application Engineering

Primary / Reviewer を決めたら、細かい実装者ロールを増やさず、影響領域だけを確認する。

## 実装責務の扱い

実装はPrimary Engineering Functionが担当する。Backend、Frontend、Database、Infra、UI/UX、Testなどは独立ロールとして増やさず、影響領域として扱う。

影響領域の確認:

- Backend / API: Controller、Request、Service、Model、API response、例外処理
- Frontend / UI: 画面、hooks、型、API呼び出し、表示状態、E2E影響
- Database: マイグレーション、リレーション、インデックス、既存データ影響
- Platform: Docker、CI、環境変数、runtime、依存関係
- Security: 認証、認可、入力検証、秘密情報、権限境界、データ露出
- Reliability: 可用性、性能、ログ、復旧、デプロイ安全性
- Quality: 受け入れ基準、Red、境界値、回帰テスト、未実行検証

扱い方:

- 影響領域は実装者ロールではなく、PreflightとReviewのチェック項目として使う
- 複数領域にまたがる場合も、Primary / Reviewer で責務を表現する
- 領域間の受け渡しが必要な場合は、API契約、DB変更、設定変更、検証結果などの具体的な成果物で渡す
- 受け渡し後は必要に応じて `api-contract-keeper` と `qa-spec-guard` で契約と品質を確認する

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
## Responsibility Classification
- Primary:
- Reviewer:
- 理由:
- spec-architectへ戻す判断:

## Preflight
- 対象:
- 対象外:
- 不変条件:
- DB変更:
- DDD方針確認:
- DDD進化候補:

## 今回のRed
- 追加テスト:
- 失敗理由:

## Greenで入れる最小実装
- 変更対象:
- 変更理由:

## 影響領域
- Backend / API:
- Frontend / UI:
- Database:
- Platform:
- Security:
- Reliability:
- Quality:
- 受け渡し成果物:

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
