---
name: pr-coordinator
description: warikan_app のコミット準備、PR前チェック、Pull Request作成を調整する。ユーザーがコミット、PR準備、PR作成、PRまでのGit操作を依頼したときに使う。
disable-model-invocation: true
---

# PR Coordinator

## 目的

コミット対象資産、PR内容、push/PR作成の承認を明確にし、不要な差分や秘密情報の混入を防ぐ。

## 使うタイミング

- コミット前の対象資産を整理するとき
- PR作成前の差分・テスト確認をするとき
- push / PR作成を依頼されたとき

## Utility scripts

このSkillの補助スクリプトは、CursorのSkill構成に合わせて `scripts/` に置く。

- `scripts/commit-assets-check.sh`: コミット前のステージ済み、未ステージ、未追跡資産を確認する
- `scripts/pr-ready-check.sh`: PR前に差分と必要なテストを確認する
- `scripts/create-pr.sh`: ユーザー承認後にpushとPR作成を行う

## 手順

1. 作業ツリーを確認する:
   - `git status --short --branch`
   - `git diff`
   - `git log --oneline -5`
2. コミット前に `bash .cursor/skills/pr-coordinator/scripts/commit-assets-check.sh` を使い、依頼者へコミット対象資産と各資産を含める根拠を提示して承認を得る。
3. ユーザーの依頼に関係するファイルだけをステージする。
4. コミットはユーザーが明示し、コミット対象資産の承認が取れたときだけ行う。
5. PR作成前に `bash .cursor/skills/pr-coordinator/scripts/pr-ready-check.sh` を実行する。
6. push/PR作成前に、依頼者へ以下を提示して承認を得る:
   - 反映資産: push対象のコミット・ファイル
   - PR内容: タイトル、概要、変更点、テスト、レビュー観点、特記事項
7. 承認後、PRは以下で作成する:
   - `APPROVED_ASSETS=1 APPROVED_PR=1 bash .cursor/skills/pr-coordinator/scripts/create-pr.sh "PR title"`
8. PR本文を調整する場合は `.github/pull_request_template.md` に沿って、概要・変更点・レビュー観点・テスト・特記事項を埋める。

## コミット対象承認の提示形式

- 対象資産: `path/to/file`
- 根拠: そのファイルを含める理由
- 除外資産: 未追跡ファイルや無関係な差分がある場合、除外理由も明記する

## 安全ルール

- 秘密情報や環境ファイルはコミットしない
- ユーザーが明示しない限り force push しない
- コミット対象資産と各資産を含める根拠の承認なしにコミットしない
- 反映資産とPR内容の承認なしに push / PR作成をしない
- `main` / `master` へ直接 push しない
- 無関係な未追跡ファイルをコミットに含めない
