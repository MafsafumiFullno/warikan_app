# ハーネスエンジニアリング運用

## 目的

AI駆動開発を安定して回すための足場を整理する。ここでいうハーネスは、Rules、Agents、Skills、Docs、Scripts、PRテンプレートをまとめた開発支援構造を指す。

## レイヤー

### Rules

常時またはファイル種別ごとに守る短い規約を置く。

- 置き場所: `.cursor/rules/`
- 内容: 禁止事項、識別子、不変条件、Git安全ルール、技術スタック別の最小規約
- 書かないもの: 長い手順、Phase管理、詳細な仕様分解

### Agents

業務進行、Skill選択、承認ゲートを管理する。

- 置き場所: `.cursor/agents/`, `.codex/agents/`
- 内容: Project Manager 観点での進行フロー、ブロッカー、承認待ち、役割間の受け渡し、ゲート、Skill選択基準、完了条件
- 書かないもの: 実装の細かい手順、長いコード例

### Skills

特定工程を実行するための手順を置く。

- 置き場所: `.cursor/skills/`, `.codex/skills/`
- 内容: 使うタイミング、チェックリスト、出力テンプレート
- 書かないもの: プロジェクト横断の正本情報

### Docs

判断の正本と、運用の背景を置く。

- 置き場所: `docs/ai-driven/`, `docs/domain/`
- 内容: ループ運用、ハーネス構造、ドメイン不変条件、issue化した設計判断
- 書かないもの: ツール固有の呼び出し手順だけで完結するもの

### Scripts

機械的チェックやPR補助を置く。非破壊な確認は自動実行でき、pushやPR作成など外部へ影響する操作は承認後に実行する。

- 置き場所: `.cursor/skills/pr-coordinator/scripts/`
- 内容: commit対象確認、PR準備確認、PR説明材料の生成、PR作成補助
- Codexからも同じスクリプトを呼び出す

## 正本ルール

- ドメイン不変条件: `docs/domain/warikan/common-invariants.md`
- ループエンジニアリング: `docs/ai-driven/loop-engineering.md`
- ハーネス構造: `docs/ai-driven/harness-engineering.md`
- PR/Git補助: `.cursor/skills/pr-coordinator/SKILL.md` と `.cursor/skills/pr-coordinator/scripts/`

## 現在の責務マップ

| 対象 | 責務 | 入口 |
|------|------|------|
| ループ改善 | 観測、仮説、最小変更、検証、学習、AI Systems Engineer によるハーネス改善 | `loop-engineering-lead` |
| Platform Engineering | 依存更新、ランタイム警告、CI/E2E基盤の検知、原因分類、最小修正、検証、PR準備への受け渡し | `platform-engineering-manager` |
| 新機能/API変更 | 仕様設計からQA判定までの納品フロー | `spec-delivery-lead` |
| 仕様設計 | PDM / Domain Expert / System Architect / Spec Architect による価値、優先度、ドメイン不変条件、技術構造、受け入れ基準、Unknowns、テスト計画 | `spec-architect` |
| 実装 | AI Implementation Lead / Infra / SRE / Security / Database / Backend / Frontend / UI/UX / Test の役割分担による Preflight, Red, Test Review, Green, Refactor, Regression | `tdd-implementer` |
| API契約 | backend / frontend / tests の同期 | `api-contract-keeper` |
| Platform実務 | Platform Engineering の実務手順。Platform Engineering Manager が必要時に呼び出す | `platform-dependency-maintainer` |
| QA判定 | Spec / Contract / Data / Security / Regression Reviewer による仕様適合、契約同期、データ整合、セキュリティ、回帰リスク | `qa-spec-guard` |
| PR/Git | 対象資産、PR内容、push/PR作成承認 | `pr-coordinator` |

## 重複を見つけた時の寄せ先

- 規約が長い: Docsへ移し、Rulesには参照だけ残す
- 進行順が重複する: Agentへ寄せる
- 工程内チェックが重複する: Skillへ寄せる
- ドメイン判断が重複する: `docs/domain/warikan/common-invariants.md` へ寄せる
- PR/Git手順が重複する: `pr-coordinator` Skillへ寄せる

## PRまでの責務分離

- Agent: いつPR準備へ進むか、承認が取れているかを管理する
- Skill: PRまでの手順、提示形式、安全ルールを管理する
- Script: Git状態、差分、テスト、PR説明材料、PR作成を機械的に処理する
- User: コミット対象、push対象、PR内容を承認する

## 承認最小化の責務分離

- Agent: 自動で進めてよい範囲と止める範囲を判定する
- Skill: 承認が必要な条件と不要な条件を明記する
- Script: 非破壊な確認はそのまま実行でき、破壊的操作は環境変数や明示承認で止める
- User: プロダクト判断、履歴操作、外部公開、データ破壊だけを承認する

## 自動化レベル

- A 自動実行: 読み取り、検証、小さな整合修正
- B 自動準備: PR本文案、設定案、削除計画、マイグレーション計画
- C ローカル限定実行: テストDB、ローカルDocker、作業ブランチ、stash
- D 承認必須: push、PR作成、本番反映、履歴改変、実データ削除

## 整理原則

- Rulesは短く保ち、詳しい説明はDocsへ逃がす
- Agentは「いつ、どのSkillを使うか」に集中する
- Skillは「その工程で何を確認し、何を出力するか」に集中する
- Cursor版とCodex版は、パス以外の意味を揃える
- 新しい不変条件は先にDocsへ追加し、必要ならRules/Skillsへ要約だけ反映する

## 変更時チェック

- [ ] 新しいルールがRulesに長く入りすぎていない
- [ ] Agentが実装手順を抱え込みすぎていない
- [ ] Skillが正本情報を重複管理していない
- [ ] Cursor/Codexで意味がずれていない
- [ ] READMEから入口が辿れる
