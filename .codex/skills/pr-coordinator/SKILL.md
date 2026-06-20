---
name: pr-coordinator
description: warikan_app のコミット準備、PR前チェック、Pull Request作成を調整する。ユーザーがコミット、PR準備、PR作成、PRまでのGit操作を依頼したときに使う。
disable-model-invocation: true
---

# PR Coordinator

## 目的

コミット対象資産、PR内容、push/PR作成の承認を明確にし、不要な差分や秘密情報の混入を防ぐ。

## Utility scripts

PR関連の補助スクリプトは、CursorのSkill構成に合わせて `.cursor/skills/pr-coordinator/scripts/` に集約する。Codexから実行する場合も同じスクリプトを使う。

- `.cursor/skills/pr-coordinator/scripts/commit-assets-check.sh`
- `.cursor/skills/pr-coordinator/scripts/pr-ready-check.sh`
- `.cursor/skills/pr-coordinator/scripts/create-pr.sh`

## 手順

1. 作業ツリーを確認する:
   - `git status --short --branch`
   - `git diff`
   - `git log --oneline -5`
2. コミット前に `bash .cursor/skills/pr-coordinator/scripts/commit-assets-check.sh` を使い、依頼者へコミット対象資産と各資産を含める根拠を提示して承認を得る。
3. ユーザーの依頼に関係するファイルだけをステージする。
4. コミットはユーザーが明示し、コミット対象資産の承認が取れたときだけ行う。
5. PR作成前に `bash .cursor/skills/pr-coordinator/scripts/pr-ready-check.sh` を実行する。
6. push/PR作成前に、反映資産とPR内容を依頼者へ提示して承認を得る。
7. 承認後、`APPROVED_ASSETS=1 APPROVED_PR=1 bash .cursor/skills/pr-coordinator/scripts/create-pr.sh "PR title"` でPRを作成する。

## 安全ルール

- 秘密情報や環境ファイルはコミットしない
- ユーザーが明示しない限り force push しない
- コミット対象資産と各資産を含める根拠の承認なしにコミットしない
- 反映資産とPR内容の承認なしに push / PR作成をしない
- `main` / `master` へ直接 push しない
- 無関係な未追跡ファイルをコミットに含めない
