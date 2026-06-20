---
name: pr-git-workflow
description: warikan_app のコミット準備、PR前チェック、Pull Request作成を行う。ユーザーがコミット、PR準備、PR作成、PRまでのGit操作を依頼したときに使う。
disable-model-invocation: true
---

# PR Git ワークフロー

## 手順

1. 作業ツリーを確認する:
   - `git status --short --branch`
   - `git diff`
   - `git log --oneline -5`
2. コミット前に `scripts/commit-assets-check.sh` を使い、依頼者へコミット対象資産と各資産を含める根拠を提示して承認を得る。
3. ユーザーの依頼に関係するファイルだけをステージする。
4. コミットはユーザーが明示し、コミット対象資産の承認が取れたときだけ行う。
5. PR作成前に以下を実行する:
   - `scripts/pr-ready-check.sh`
6. push/PR作成前に、依頼者へ以下を提示して承認を得る:
   - 反映資産: push対象のコミット・ファイル
   - PR内容: タイトル、概要、変更点、テスト、レビュー観点、特記事項
7. 承認後、PRは以下で作成する:
   - `APPROVED_ASSETS=1 APPROVED_PR=1 scripts/create-pr.sh "PR title"`
8. PR本文を調整する場合は `.github/pull_request_template.md` に沿って、概要・変更点・レビュー観点・テスト・特記事項を埋める。

## コミット対象承認の提示形式

- 対象資産: `path/to/file`
- 根拠: そのファイルを含める理由
- 除外資産: 未追跡ファイルや無関係な差分がある場合、除外理由も明記する

## 安全ルール

- 秘密情報や環境ファイルはコミットしない。
- ユーザーが明示しない限り force push しない。
- コミット対象資産と各資産を含める根拠の承認なしにコミットしない。
- 反映資産とPR内容の承認なしに push / PR作成をしない。
- `main` / `master` へ直接 push しない。
- 無関係な未追跡ファイルをコミットに含めない。
