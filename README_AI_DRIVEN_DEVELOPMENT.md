# AI駆動開発ガイド（warikan_app）

このドキュメントは、`warikan_app` で Cursor / Codex を使って安全に開発するための運用ガイドです。

## 目的

- 仕様漏れを減らす
- TDD と仕様駆動開発を安定運用する
- 改修時の回帰リスクを抑える
- Cursor と Codex で同じ運用を再利用する
- AI駆動開発を観測、仮説、最小変更、検証、学習のループとして回す
- Rules / Agents / Skills / Docs / Scripts の責務を分離し、開発ハーネスを保守しやすくする
- コミット・PR作成時の対象資産とレビュー観点を明確にする

## 前提

- 常時ルール: `.cursor/rules/`
- Cursor用 Agent / Skill: `.cursor/agents/`, `.cursor/skills/`
- Codex用 Agent / Skill: `.codex/agents/`, `.codex/skills/`
- PRテンプレート: `.github/pull_request_template.md`
- PR補助スクリプト: `.cursor/skills/pr-coordinator/scripts/`

`SKILL.md` は `disable-model-invocation: true` のため、基本的に `/skill-name` で明示呼び出しします。

## ディレクトリ構成設計

AI駆動開発の運用情報は、責務ごとに以下へ分離します。

```text
warikan_app/
├── .cursor/
│   ├── rules/                           # 常時守る規約（短く最小）
│   │   ├── ai-driven-harness.mdc
│   ├── agents/                          # Agent定義（Skill管理・業務進行管理）
│   │   ├── spec-delivery-manager/
│   │   ├── loop-engineering-manager/
│   │   └── platform-engineering-manager/
│   └── skills/                          # Skill定義（実行能力 + ランチャー）
│       ├── loop-engineering-lead/        # agents/loop-engineering-manager を起動
│       ├── spec-delivery-lead/          # agents/spec-delivery-manager を起動
│       ├── spec-architect/
│       ├── tdd-implementer/
│       ├── api-contract-keeper/
│       ├── platform-dependency-maintainer/
│       ├── qa-spec-guard/
│       └── pr-coordinator/
│           └── scripts/                  # Skill用補助スクリプト
├── .codex/
│   ├── agents/                          # Agent定義（Skill管理・業務進行管理）
│   │   ├── spec-delivery-manager/
│   │   ├── loop-engineering-manager/
│   │   └── platform-engineering-manager/
│   └── skills/                          # Skill定義（実行能力 + ランチャー）
│       ├── loop-engineering-lead/
│       ├── spec-delivery-lead/
│       ├── spec-architect/
│       ├── tdd-implementer/
│       ├── api-contract-keeper/
│       ├── platform-dependency-maintainer/
│       ├── qa-spec-guard/
│       └── pr-coordinator/
├── docs/
│   ├── ai-driven/
│   │   ├── loop-engineering.md          # 反復改善ループの正本
│   │   └── harness-engineering.md       # Rules/Agents/Skillsの責務分離の正本
│   └── domain/
│       └── warikan/
│           └── common-invariants.md     # ドメイン不変条件の正本
├── .github/
│   └── pull_request_template.md
└── README_AI_DRIVEN_DEVELOPMENT.md
```

### 設計方針

- Rulesは「常時必要な最小規約」だけを置く
- Agentは「Skill選択・承認ゲート・業務進行管理」、Skillは「各工程の実行能力」に分離する
- `loop-engineering-manager` は既存の `spec-delivery-manager` / `platform-engineering-manager` の上位で、小さな既存改修、反復改善、学習記録を管理する
- ハーネス構造の正本は `docs/ai-driven/harness-engineering.md` に置き、Rulesには要約だけ置く
- `/spec-delivery-lead` などを明示呼び出しするため、skills側にランチャーを置く
- Skillsは「手順・進め方」を置き、必要時に呼び出す
- Cursor/Codexで同じ運用を維持するため、対応する Skill / Agent は同じ意味に揃える
- PR関連スクリプトはCursor公式のSkill構成に合わせ、`.cursor/skills/pr-coordinator/scripts/` に集約する
- 割り勘アプリ固有の詳細仕様は `docs/domain/` に集約し、Rules/Skillsから参照する
- `project_member_id` / `del_flg` などの共通知識は `docs/domain/warikan/common-invariants.md` を正本にする

## Agent と Skill の役割分離

### ハーネス構造

- Rules: 常時守る短い規約
- Agents: 業務進行、Skill選択、承認ゲート
- Skills: 各工程の実行手順、チェックリスト、出力テンプレート
- Docs: 正本情報、設計判断、運用背景
- Scripts: 人間承認後に使う機械的チェックとPR補助

詳細は `docs/ai-driven/harness-engineering.md` を参照します。

### Agent（業務管理層）

- `spec-delivery-manager`: `spec-architect` / `tdd-implementer` / `api-contract-keeper` / `qa-spec-guard` を束ねる業務進行管理
- `loop-engineering-manager`: Observe / Frame / Act / Verify / Learn の反復、ゲート判定、AI Systems Engineer としてのハーネス改善を管理
- `platform-engineering-manager`: 依存更新、ランタイム警告、CI/E2E基盤の検知、原因分類、最小修正、検証、PR準備への受け渡しを管理
- Agent は Project Manager として、状況に応じて必要な Skill を選択し、工程、ブロッカー、承認待ち、役割間の受け渡しを管理する
- ループでは低リスク作業を自動判定で進め、納品フローでは必要な承認ゲートを管理する

### Skill（実行能力層）

- `loop-engineering-lead`: 観測、仮説、最小変更、検証、学習のループを開始する入口
- `spec-architect`: PDM / Domain Expert / System Architect / Spec Architect による価値、優先度、ドメイン不変条件、技術構造、受け入れ基準、要件確認（Assumptions / Unknowns / Open Questions）の整理
- `tdd-implementer`: AI Implementation Lead / Infra / SRE / Security / Database / Backend / Frontend / UI/UX / Test の役割分担による Preflight, Red, Test Review, Green, Refactor, Regression で実装
- `api-contract-keeper`: backend / frontend / tests の契約同期
- `platform-dependency-maintainer`: Platform Engineering観点で依存更新、ランタイム警告、CI/E2E基盤、Dependabot PRを検知、切り分け、最小修正、検証、PR準備まで進める。SREは本番信頼性や復旧性への影響がある場合のレビュー観点として扱う
- `qa-spec-guard`: Spec / Contract / Data / Security / Regression Reviewer による仕様適合、契約同期、データ整合、セキュリティ、回帰リスクの判定
- `pr-coordinator`: コミット対象資産、PR内容、push/PR作成承認の管理

## 推奨フロー

### 1) ループエンジニアリング

```text
/loop-engineering-lead
対象: [観測・改善したい対象]
目的: [今回のループで明らかにしたいこと]
モード: inspect | fix | workflow
```

`Observe -> Frame -> Act -> Verify -> Learn` の順に進めます。詳細は `docs/ai-driven/loop-engineering.md` を参照します。

ループでは承認を最小化します。観測、整理、小さな整合修正、非破壊な検証は自動で進めます。外部設定や破壊的操作も、確認、dry-run、計画作成、下書き作成までは自動で進め、commit、push、PR作成、本番反映、履歴改変、実データ削除、プロダクト判断だけユーザー承認で止めます。

### 2) 新機能開発

```text
/spec-delivery-lead
要件: [実現したいこと]
モード: full
```

進行中は Phase ごとに承認します（`OK` / `承認` / `進めてください`）。

### 3) バグ修正

```text
/spec-delivery-lead
バグ: [再現条件]
モード: bugfix
```

### 4) API変更を含む改修

```text
/spec-delivery-lead
要件: [変更内容]
モード: api-change
```

### 5) 既存機能の改善・改修

```text
/loop-engineering-lead
対象: [改修対象]
目的: [改善したい点]
モード: fix
```

受け入れ基準や人間承認フェーズが必要な大きな改修は `/spec-delivery-lead` を使います。

### 6) PR準備

```text
/pr-coordinator
目的: [PRにしたい変更]
```

## ゲート運用（重要）

`loop-engineering-lead` では、次の4ゲートを確認します。

- 不変条件ゲート: `project_member_id` / `del_flg` / `apiFetch` の規約違反がない
- 契約ゲート: backend / frontend / tests で API の意味が同期している
- テストゲート: 変更対象に対して必要な検証が実行または計画されている
- 学習ゲート: 発見事項、判断待ち、次ループの材料が記録されている

これらのゲートは、毎回ユーザー承認を取る場所ではなく、エージェントが自動で次へ進めるかを判断する基準です。

`spec-delivery-lead` では、仕様や実装内容を人間が確認すべき Phase 完了後にユーザー承認を取ります。

- Phase 1: PDM / Domain Expert / System Architect / Spec Architect の観点で、要件漏れ・未確定要件（Unknowns）なしを確認
- Phase 2: 受け入れ基準に対応するテストが Green であることを確認
- Phase 2では、変更範囲に応じて AI Implementation Lead / Infra / SRE / Security / Database / Backend / Frontend / UI/UX / Test の役割を割り当てる
- Phase 2では、変更リスクに応じて Spec / Contract / Data / Security / Regression の観点でテストレビューしてから Green へ進む
- Phase 3: API 契約の未反映リスクがないことを確認
- Phase 4: `qa-spec-guard` で必要な Reviewer を割り当て、最終判定（Pass / Pass with Notes）を確認
- 主要ユーザーフロー影響時は E2E（`cd frontend && npm run e2e`）実行結果を確認

承認が必要な Phase では、承認がなければ次工程に進みません。

## PR / Git 運用

- コミット・push・PR作成はユーザー明示時のみ行う
- アプリ機能修正とAI駆動開発の体制整備は、原則として別ブランチに分離する
- 体制整備は `chore/loop-engineering-*` 系ブランチを使う
- 別件の不具合を見つけた場合は、その場で混ぜずに issue、stash、別ブランチへ分離する
- コミット前に `bash .cursor/skills/pr-coordinator/scripts/commit-assets-check.sh` で対象資産を確認する
- PR前に `bash .cursor/skills/pr-coordinator/scripts/pr-ready-check.sh` を実行し、差分とテスト結果を確認する
- PR内容の提示前に `bash .cursor/skills/pr-coordinator/scripts/prepare-pr-summary.sh` を実行し、反映資産・コミット・差分・PR本文テンプレートを確認する
- push/PR作成前に、反映資産とPR内容をユーザーへ提示して承認を得る
- 承認後は `APPROVED_ASSETS=1 APPROVED_PR=1 bash .cursor/skills/pr-coordinator/scripts/create-pr.sh "PR title"` を使う
- PR本文は `.github/pull_request_template.md` に沿って、概要、変更種別、変更点、仕様・契約、確認ゲート、テスト、レビュー観点、除外変更、特記事項、関連Issueを埋める

## 依存更新 / 警告の運用

- 依存更新は `.github/dependabot.yml` で Composer、frontend npm、GitHub Actions を週次PR化する
- Dependabot PR は CI の結果を見てマージ判断し、アプリコード修正と依存更新を原則として分ける
- `.github/workflows/dependabot-automerge.yml` で低リスクな Dependabot PR は CI 通過後に自動マージ候補にする
- 自動マージ対象は semver patch と、開発依存の semver minor に限定する
- runtime 依存の semver minor と semver major は人間レビューを必須にする
- Backend CI は PHP 8.2 / 8.4 / 8.5 で `composer test` を必須実行する
- PHP 8.5 など新しい実行環境で依存パッケージ由来の非推奨警告が先行して出る場合、テストコマンド側で一時的に隔離する
- アプリコード由来の警告は抑制対象にせず、最小PRで修正してからテストを通す
- Laravel / PHP のバージョン引き上げは、Dependabot PR と CI 結果を確認し、互換性修正をまとめた専用PRで行う
- 一時的な警告隔離を入れた場合は、PR本文の「特記事項・懸念点」か docs に解除条件を残す

## 要件漏れを防ぐコツ

- Phase 1 で Unknowns を空にする
- スコープ内/外を明示する
- 受け入れ基準は「正常系・異常系・境界値」を必ず含める
- API 変更時は request/response/error を先に合意する

## warikan_app 固有の注意

- API のメンバー識別子は `project_member_id` を使う（`id` と混同しない）
- 論理削除対象は `del_flg = false` を前提に確認する
- Frontend の API 呼び出しは `@/lib/api` の `apiFetch` を利用する

## 最小運用ルール

- コミット・push はユーザー明示時のみ
- 依頼範囲外のリファクタはしない
- 作業開始時に Observe と Frame を短く置く
- 作業終了時に Verify と Learn を短く残す
- 迷ったら `spec-architect` で設計を先に固める

## クイックリファレンス

```text
# ループエンジニアリング
/loop-engineering-lead
対象: ...
目的: ...
モード: inspect | fix | workflow

# 新機能
/spec-delivery-lead
要件: ...
モード: full

# バグ修正
/spec-delivery-lead
バグ: ...
モード: bugfix

# 改修
/loop-engineering-lead
対象: ...
目的: ...
モード: fix

# PR準備
/pr-coordinator
目的: ...
```
