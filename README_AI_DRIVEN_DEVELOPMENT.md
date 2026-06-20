# AI駆動開発ガイド（warikan_app）

このドキュメントは、`warikan_app` で Cursor / Codex を使って安全に開発するための運用ガイドです。

## 目的

- 仕様漏れを減らす
- TDD と仕様駆動開発を安定運用する
- 改修時の回帰リスクを抑える
- Cursor と Codex で同じ運用を再利用する
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
│   ├── agents/                          # Agent定義（Skill管理・業務進行管理）
│   │   ├── spec-delivery-manager/
│   │   └── refactor-manager/
│   └── skills/                          # Skill定義（実行能力 + ランチャー）
│       ├── spec-delivery-lead/          # agents/spec-delivery-manager を起動
│       ├── refactor-lead/               # agents/refactor-manager を起動
│       ├── spec-architect/
│       ├── tdd-implementer/
│       ├── api-contract-keeper/
│       ├── qa-spec-guard/
│       └── pr-coordinator/
│           └── scripts/                  # Skill用補助スクリプト
├── .codex/
│   ├── agents/                          # Agent定義（Skill管理・業務進行管理）
│   │   ├── spec-delivery-manager/
│   │   └── refactor-manager/
│   └── skills/                          # Skill定義（実行能力 + ランチャー）
│       ├── spec-delivery-lead/
│       ├── refactor-lead/
│       ├── spec-architect/
│       ├── tdd-implementer/
│       ├── api-contract-keeper/
│       ├── qa-spec-guard/
│       └── pr-coordinator/
├── docs/
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
- `/spec-delivery-lead` などを明示呼び出しするため、skills側にランチャーを置く
- Skillsは「手順・進め方」を置き、必要時に呼び出す
- Cursor/Codexで同じ運用を維持するため、対応する Skill / Agent は同じ意味に揃える
- PR関連スクリプトはCursor公式のSkill構成に合わせ、`.cursor/skills/pr-coordinator/scripts/` に集約する
- 割り勘アプリ固有の詳細仕様は `docs/domain/` に集約し、Rules/Skillsから参照する
- `project_member_id` / `del_flg` などの共通知識は `docs/domain/warikan/common-invariants.md` を正本にする

## Agent と Skill の役割分離

### Agent（業務管理層）

- `spec-delivery-manager`: `spec-architect` / `tdd-implementer` / `api-contract-keeper` / `qa-spec-guard` を束ねる業務進行管理
- `refactor-manager`: 改善・改修に必要なSkill選択と回帰確認の業務進行管理
- Agent は状況に応じて必要な Skill を選択し、業務の進行状況を管理する
- 承認ゲートを通過するまで次の Skill を呼び出さない

### Skill（実行能力層）

- `spec-architect`: 仕様分解、受け入れ基準、要件確認（Assumptions / Unknowns / Open Questions）
- `tdd-implementer`: Red-Green-Refactor で実装
- `api-contract-keeper`: backend / frontend / tests の契約同期
- `qa-spec-guard`: 仕様適合とテスト十分性の判定
- `pr-coordinator`: コミット対象資産、PR内容、push/PR作成承認の管理

## 推奨フロー

### 1) 新機能開発

```text
/spec-delivery-lead
要件: [実現したいこと]
モード: full
```

進行中は Phase ごとに承認します（`OK` / `承認` / `進めてください`）。

### 2) バグ修正

```text
/spec-delivery-lead
バグ: [再現条件]
モード: bugfix
```

### 3) API変更を含む改修

```text
/spec-delivery-lead
要件: [変更内容]
モード: api-change
```

### 4) 既存機能の改善・改修

```text
/refactor-lead
対象: [改修対象]
目的: [改善したい点]
```

### 5) PR準備

```text
/pr-coordinator
目的: [PRにしたい変更]
```

## 承認ゲート運用（重要）

`spec-delivery-lead` では各 Phase 完了後にユーザー承認を取ります。

- Phase 1: 要件漏れ・未確定要件（Unknowns）なしを確認
- Phase 2: 受け入れ基準に対応するテストが Green であることを確認
- Phase 3: API 契約の未反映リスクがないことを確認
- Phase 4: 最終判定（Pass / Pass with Notes）を確認
- 主要ユーザーフロー影響時は E2E（`cd frontend && npm run e2e`）実行結果を確認

承認がなければ次工程に進みません。

## PR / Git 運用

- コミット・push・PR作成はユーザー明示時のみ行う
- コミット前に `bash .cursor/skills/pr-coordinator/scripts/commit-assets-check.sh` で対象資産を確認する
- PR前に `bash .cursor/skills/pr-coordinator/scripts/pr-ready-check.sh` を実行し、差分とテスト結果を確認する
- push/PR作成前に、反映資産とPR内容をユーザーへ提示して承認を得る
- 承認後は `APPROVED_ASSETS=1 APPROVED_PR=1 bash .cursor/skills/pr-coordinator/scripts/create-pr.sh "PR title"` を使う
- PR本文は `.github/pull_request_template.md` に沿って、概要・変更点・レビュー観点・テスト・特記事項を埋める

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
- 迷ったら `spec-architect` で設計を先に固める

## クイックリファレンス

```text
# 新機能
/spec-delivery-lead
要件: ...
モード: full

# バグ修正
/spec-delivery-lead
バグ: ...
モード: bugfix

# 改修
/refactor-lead
対象: ...
目的: ...

# PR準備
/pr-coordinator
目的: ...
```

