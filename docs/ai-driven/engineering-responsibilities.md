# AI-Driven Engineering Responsibilities

## 目的

このドキュメントは、`warikan_app` のAI駆動開発におけるEngineering責務の正本である。

ここでいうAgentは「人」ではなく、責任を持つEngineering Function / 仮想組織として扱う。AgentやSkillは、このFunctionを実行するための入口、手順、レビュー観点である。

責務を分ける目的は、作業を細かく増やすことではない。仕様、実装、基盤、信頼性、セキュリティ、品質の判断を混ぜすぎず、必要な場面で必要な観点を呼び出せるようにするためである。

## Function Map

Product Owner はユーザー自身であり、価値、優先度、仕様、リリース判断の最終決定者である。Engineering Manager は Product Owner の直下で、技術側の交通整理、責務分配、優先度整理、handoff、過剰設計抑制を担う。Engineering Manager は実装者ではなく、Engineering Orchestrator 的な責務を持つ。

| Engineering Function | Mission | 現在の主担当Agent |
|---|---|---|
| Engineering Manager | 技術側の責務分配、優先度整理、handoff、過剰設計抑制、最終技術整理案を担う | `engineering-manager` |
| Product / Specification | 価値、仕様、スコープ、受け入れ基準、ドメイン判断を明確にする | `spec-delivery-manager` |
| Application Engineering | アプリケーション機能、API、DB、ドメインモデル、アプリケーション構造を実装する | `spec-delivery-manager`, `loop-engineering-manager` |
| Platform Engineering | CI/CD、Runtime、依存、Docker、開発環境、DX、自動化を整える | `platform-engineering-manager` |
| Reliability Engineering | 可用性、性能、可観測性、復旧性、耐障害性をレビューする | 独立Agentなし。必要時にPlatform / Implementation / QAから呼び出す |
| Security Engineering | 認証、認可、秘密情報、脆弱性、脅威モデリングをレビューする | 独立Agentなし。`tdd-implementer` / `qa-spec-guard` の観点として扱う |
| Quality Engineering | テスト、回帰、受け入れ基準、E2E、契約検証を担保する | `spec-delivery-manager`, `qa-spec-guard` |

## 1. Product Owner

### Mission

プロダクト価値、優先度、仕様、リリース判断の最終決定を行う。

### Owns

- プロダクト価値の最終判断
- 優先度の最終判断
- スコープの最終判断
- リリース判断
- 仕様選択で複数案がある場合の意思決定

### Does not own

- 実装手順の詳細設計
- CI/CDや開発基盤の具体設定
- テスト観点の網羅
- PR作成実務

### 他Functionとの境界

- Engineering Managerへ、目的、優先度、制約、判断待ちを渡す
- Product Owner が未判断のプロダクト判断を、Agent / Skill が勝手に確定しない

### 主なSkill

- なし。ユーザー自身の判断を受け取る

### 現在担当しているAgent

- なし。ユーザー自身

## 2. Engineering Manager

### Mission

Product Owner の直下で、Engineering Function の統合、優先度整理、責務分配、handoff、過剰設計抑制、最終技術整理案を担う。Engineering Manager は実装者ではなく、Engineering Orchestrator 的な責務を持つ。

### Owns

- Engineering Function間の責務分配
- 変更のPrimary / Reviewer整理
- Spec Delivery、Application Engineering、Platform Engineeringの受け渡し
- Quality / Security / Reliability Reviewの要否判断
- DDD進化が必要な兆候の検知と整理
- 過剰設計の抑制
- 複数作業や複数PR候補の技術的な優先度整理
- Product Ownerへ返す技術判断材料の整理

### Does not own

- Product Ownerの最終判断
- 実装作業そのもの
- 仕様詳細の確定
- Platform実務そのもの
- QA最終判定そのもの
- PR作成実務

### 他Functionとの境界

- Product Ownerから目的、優先度、制約を受け取る
- Product / Specificationへ、仕様化すべき要求や未確定事項を渡す
- DDD進化が必要な場合は Product / Specification と Spec Architect へ戻す
- Application Engineeringへ、実装Primaryとなる変更を渡す
- Platform Engineeringへ、CI/CD、runtime、dependency、development environment、automation、developer experienceの変更を渡す
- Quality / Security / Reliability Reviewを、必要時だけ横断レビュー機能として呼び出す
- PR反映順やリリース候補の最終交通整理は `asset-release-gate-manager` に渡す
- 大規模なDDDリファクタを勝手に実施せず、提案、issue化、小さなPRへ分解する

### 主なSkill

- `loop-engineering-lead`
- `spec-delivery-lead`
- `tdd-implementer`
- `qa-spec-guard`
- `pr-coordinator`

### 現在担当しているAgent

- `engineering-manager`
- 必要に応じて `loop-engineering-manager`

## 3. Product / Specification

### Mission

ユーザー価値、仕様、スコープ、ドメイン判断、受け入れ基準を明確にし、実装可能で検証可能な形へ落とす。

### Owns

- ユーザーストーリー
- スコープ内 / スコープ外
- 優先度
- 受け入れ基準
- Unknowns / Open Questions
- ドメイン用語
- ドメイン不変条件の確認
- API契約の初期整理
- DDD観点の設計整理

### Does not own

- 実装の詳細手順
- CI/CDや依存更新
- 本番運用、復旧、監視
- 脆弱性対応の実装修正
- PR作成実務

### 他Functionとの境界

- Application Engineeringへ、受け入れ基準、API契約、ドメイン判断、テスト計画を渡す
- Quality Engineeringへ、検証すべき仕様と受け入れ条件を渡す
- Security Engineeringへ、認証、認可、共有リンク、機微情報に関わる仕様を渡す
- Reliability Engineeringへ、本番可用性や復旧性に影響する仕様を渡す

### 主なSkill

- `spec-architect`
- `spec-delivery-lead`
- `api-contract-keeper`

### 現在担当しているAgent

- `spec-delivery-manager`
- 必要に応じて `loop-engineering-manager`

## 4. Application Engineering

### Mission

Backend、Frontend、API、Database、Domain model、Application architectureを、受け入れ基準とドメイン不変条件に沿って実装する。

### Owns

- Backend
- Frontend
- API
- Database
- Domain model
- Application architecture
- Use Case実装
- Application Service
- ControllerからApplicationへの接続
- API request / responseの整合
- DB変更、マイグレーション方針、データ整合
- `docs/domain/warikan/common-invariants.md` に沿った実装

### Does not own

- プロダクト価値や仕様優先度の最終判断
- CI/CD基盤そのものの設計
- DependabotやRuntime更新の運用
- SLO / SLIやIncident運用の最終責任
- セキュリティ方針の最終判断
- PR反映順の最終交通整理

### 他Functionとの境界

- Product / Specificationから、仕様、スコープ、受け入れ基準、API契約を受け取る
- Platform Engineeringへ、CI、Runtime、Docker、依存、開発環境に起因する問題を渡す
- Reliability Engineeringへ、性能、可用性、復旧性、デプロイ安全性に影響する変更をレビュー依頼する
- Security Engineeringへ、認証、認可、入力検証、共有リンク、秘密情報に関わる変更をレビュー依頼する
- Quality Engineeringへ、実装内容、テスト結果、未実行検証、回帰リスクを渡す

### 主なSkill

- `tdd-implementer`
- `api-contract-keeper`
- `spec-architect`
- `qa-spec-guard`

### 現在担当しているAgent

- `spec-delivery-manager`
- `loop-engineering-manager`

## 5. Platform Engineering

### Mission

CI/CD、Runtime、Dependency、Docker、Development environment、Developer Experience、Automationを安定させ、開発と検証が再現可能に回る状態を維持する。

### Owns

- CI/CD
- Runtime
- Dependency
- Docker
- Development environment
- Developer Experience
- Automation
- Composer / npm / GitHub Actions / Playwrightの基盤整備
- Dependabot PRの切り分け
- CI失敗やランタイム警告の原因分類
- 低リスク依存更新の自動化候補
- 開発者が同じ手順で検証できる入口

### Does not own

- アプリケーション仕様の最終判断
- ドメインモデル設計
- API契約変更のプロダクト判断
- 本番SLO / SLIの最終責任
- Incident Commanderとしての運用
- 脆弱性リスクの最終受容判断
- QAの最終合否判定

### 他Functionとの境界

- Application Engineeringから、CIやRuntimeで再現する失敗、依存更新に必要な最小修正を受け取る
- Reliability Engineeringから、可用性、性能、復旧性、Observabilityの観点をレビューとして受け取る
- Security Engineeringから、依存脆弱性、Secret、Runtime設定の懸念を受け取る
- Quality Engineeringへ、CI結果、E2E実行可否、テスト基盤の状態を渡す

### 主なSkill

- `platform-dependency-maintainer`
- `qa-spec-guard`
- `pr-coordinator`

### 現在担当しているAgent

- `platform-engineering-manager`
- 必要に応じて `loop-engineering-manager`

## 6. Reliability Engineering

### Mission

SLO / SLI、Availability、Performance、Observability、Incident、Recovery、Capacity、Resilienceの観点で、システムが安定して使える状態を守る。

### Owns

- SLO / SLI
- Availability
- Performance
- Observability
- Incident
- Recovery
- Capacity
- Resilience
- ヘルスチェック
- ログとメトリクスのレビュー
- デプロイ安全性
- 障害時の切り分け観点
- 本番復旧手順

### Does not own

- 通常の機能実装
- 全てのCI/CD設定
- 全てのテストケース設計
- プロダクト仕様の優先度判断
- Security Issueの最終判断

### 他Functionとの境界

- Platform Engineeringとは、Runtime、CI/CD、Docker、デプロイ基盤で接続する。Platformが基盤実装を担い、Reliabilityは可用性、復旧性、可観測性、耐障害性の観点を出す
- Application Engineeringとは、性能劣化、N+1、重いクエリ、例外処理、リトライ、タイムアウト、ユーザー影響で接続する
- Quality Engineeringとは、回帰テスト、E2E、性能確認、障害再現テストで接続する
- Security Engineeringとは、Incident時の機微情報漏えい、ログ、権限境界で接続する

### 主なSkill

- `tdd-implementer` の SRE Engineer 観点
- `platform-dependency-maintainer`
- `qa-spec-guard` の Regression Reviewer 観点

### 現在担当しているAgent

現在の `warikan_app` では、Reliability Engineeringは独立Agentにはしない。必要時に以下から呼び出すレビュー責務として扱う。

- Platform観点: `platform-engineering-manager`
- 実装観点: `tdd-implementer`
- QA観点: `qa-spec-guard`

独立Agent化を検討する条件:

- AWSなどの本番環境運用が本格化する
- SLO / SLIを明示して運用する
- Observability基盤、メトリクス、ログ、トレースの設計が必要になる
- Incident対応、オンコール、復旧手順、ポストモーテムが必要になる
- Capacity planningやPerformance tuningが継続的な課題になる
- デプロイ戦略、ロールバック、フェイルオーバー、DRを独立して管理する必要が出る

## 7. Security Engineering

### Mission

Auth、Authorization、Secret、Vulnerability、Threat modelingの観点で、ユーザー、プロジェクト、共有リンク、API、依存関係の安全性を守る。

### Owns

- Auth
- Authorization
- Secret
- Vulnerability
- Threat modeling
- 入力検証
- 権限境界
- 共有リンクの公開範囲
- 機微情報のログ出力防止
- 依存関係の脆弱性レビュー
- CSRF / CORS / Sanctum設定のレビュー

### Does not own

- 一般的な機能実装の進行管理
- 全てのQA判定
- CI/CD全体の運用
- プロダクト価値やリリース優先度の最終判断
- PR作成実務

### 他Functionとの境界

- Product / Specificationから、認証、認可、共有リンク、公開情報に関わる仕様を受け取る
- Application Engineeringへ、権限境界、入力検証、データ露出リスクの観点を渡す
- Platform Engineeringへ、Secret、依存脆弱性、Runtime設定の観点を渡す
- Reliability Engineeringへ、Incident時のログ、機微情報、復旧時の権限境界を渡す
- Quality Engineeringへ、Security Reviewerとして検証観点を渡す

### 主なSkill

- `tdd-implementer` の Security Engineer 観点
- `qa-spec-guard` の Security Reviewer 観点
- `platform-dependency-maintainer` の依存脆弱性確認

### 現在担当しているAgent

- 独立Agentなし
- `spec-delivery-manager`
- `loop-engineering-manager`
- `platform-engineering-manager`

必要時に `tdd-implementer` / `qa-spec-guard` のレビュー観点として呼び出す。

## 8. Quality Engineering

### Mission

Test、Regression、Acceptance criteria、E2E、Contract verificationを通じて、「実装した」ではなく「仕様を満たした」と言える状態を作る。

### Owns

- Test
- Regression
- Acceptance criteria
- E2E
- Contract verification
- Unit / Feature / Integration / E2Eの選択
- 受け入れ基準とテストの対応
- API request / response / error / ID意味の同期確認
- 回帰リスクの洗い出し
- 未実行テストと理由の明示
- QA最終判定

### Does not own

- 仕様の優先度決定
- 機能実装そのもの
- CI/CD基盤そのもの
- 本番Incident対応
- セキュリティリスクの最終受容判断
- PR反映順の交通整理

### 他Functionとの境界

- Product / Specificationから、受け入れ基準と仕様を受け取る
- Application Engineeringから、実装内容と検証結果を受け取る
- Platform Engineeringから、CI/E2E基盤の状態を受け取る
- Reliability Engineeringから、性能、可用性、復旧性に関わる検証観点を受け取る
- Security Engineeringから、認証、認可、入力検証、秘密情報に関わる検証観点を受け取る

### 主なSkill

- `qa-spec-guard`
- `tdd-implementer`
- `api-contract-keeper`

### 現在担当しているAgent

- `spec-delivery-manager`
- `loop-engineering-manager`

## PR / Release Coordinationとの境界

Engineering Functionは、作業やレビューの責務を持つ。PR反映順、複数PR候補、CI赤、依存関係、並行作業の交通整理は `asset-release-gate-manager` が担当する。

反映OKになった単一PR候補のコミット対象、PR内容、push / PR作成承認は `pr-coordinator` が担当する。

`asset-release-gate-manager` と `pr-coordinator` はEngineering Functionそのものではなく、複数Functionの成果物を安全に反映するためのRelease Coordination責務である。

## 運用原則

- Functionは必要な場面だけ呼び出す
- 小さなCRUD変更に全Functionレビューを要求しない
- ドメイン複雑性がある変更では Product / Application / Quality を必ず通す
- 認証、認可、共有リンク、Secret、依存脆弱性に関わる変更では Security観点を必ず通す
- CI/CD、Runtime、Dependency、Docker、開発環境に関わる変更では Platform観点を必ず通す
- 可用性、性能、復旧、Observability、本番運用に影響する変更では Reliability観点を必ず通す
- 主要ユーザーフローやAPI契約に影響する変更では Quality観点を必ず通す
