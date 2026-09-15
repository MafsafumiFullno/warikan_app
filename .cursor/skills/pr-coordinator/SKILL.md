---
name: pr-coordinator
description: warikan_app のコミット準備、PR前チェック、Pull Request作成を調整する。ユーザーがコミット、PR準備、PR作成、PRまでのGit操作を依頼したときに使う。
disable-model-invocation: true
---

# PR Coordinator

## 目的

コミット対象資産、PR内容、push/PR作成の承認を明確にし、不要な差分や秘密情報の混入を防ぐ。
PR Coordinator はPR作成実務のSkillであり、複数PR候補の最終交通整理は `asset-release-gate-manager` に任せる。

## 使うタイミング

- コミット前の対象資産を整理するとき
- PR作成前の差分・テスト確認をするとき
- push / PR作成を依頼されたとき
- 複数の変更、失敗、未追跡ファイル、後続課題が混ざっているとき
- どの修正を先にPR化するか、別PRに分けるかを判断するとき
- 複数のAgent/ManagerからPR候補が集まる場合は、先に `asset-release-gate-manager` で反映順を整理する

## Utility scripts

このSkillの補助スクリプトは、CursorのSkill構成に合わせて `scripts/` に置く。

- `scripts/commit-assets-check.sh`: コミット前のステージ済み、未ステージ、未追跡資産を確認する
- `scripts/create-branch.sh`: ブランチ作成前の安全確認、命名チェック、ベースブランチからの作成を行う
- `scripts/pr-ready-check.sh`: PR前に差分と必要なテストを確認する
- `scripts/prepare-pr-summary.sh`: PR作成前に反映資産、コミット、差分、PR本文テンプレートをまとめる
- `scripts/create-pr.sh`: ユーザー承認後にpushとPR作成を行う

## 手順

1. 作業ツリーを確認する:
   - `git status --short --branch`
   - `git diff`
   - `git log --oneline -5`
2. `asset-release-gate-manager` が必要な状況か確認する:
   - 複数PR候補がある
   - CI赤、依存関係、仕様未確定が混ざっている
   - 優先度や反映順の判断が必要
3. 新しい作業ブランチが必要な場合は `bash .cursor/skills/pr-coordinator/scripts/create-branch.sh "type/name"` を使う。
   - 原則として `main` から切る
   - `main` 以外をベースにする場合は依頼者に確認し、`APPROVED_NON_MAIN_BASE=1 BASE_BRANCH="base/name"` を付けて実行する
4. ブロッカーを分類する:
   - 今すぐ直す
   - 別PRに分ける
   - issue化する
   - ユーザー判断待ち
5. コミット前に `bash .cursor/skills/pr-coordinator/scripts/commit-assets-check.sh` を使い、依頼者へコミット対象資産と各資産を含める根拠を提示して承認を得る。
6. ユーザーの依頼に関係するファイルだけをステージする。
7. コミットはユーザーが明示し、コミット対象資産の承認が取れたときだけ行う。
8. PR作成前に `bash .cursor/skills/pr-coordinator/scripts/pr-ready-check.sh` を実行する。
9. `bash .cursor/skills/pr-coordinator/scripts/prepare-pr-summary.sh` を実行し、PR説明の材料をまとめる。
10. push/PR作成前に、依頼者へ以下を提示して承認を得る:
   - 反映資産: push対象のコミット・ファイル
   - PR内容: タイトル、概要、変更点、テスト、レビュー観点、特記事項
11. Issue対応PRは、PRタイトルとPR本文を日本語で作成する。
12. PR本文は `.github/pull_request_template.md` の項目を省略せず、対象外の場合も `なし` または `対象外` と明記する。
13. 承認後、PRは以下で作成する:
   - `APPROVED_ASSETS=1 APPROVED_PR=1 bash .cursor/skills/pr-coordinator/scripts/create-pr.sh "PR title"`

## コミット対象承認の提示形式

- 対象資産: `path/to/file`
- 根拠: そのファイルを含める理由
- 除外資産: 未追跡ファイルや無関係な差分がある場合、除外理由も明記する

## PR作成前承認の提示形式

- 反映資産:
- 優先度:
- ブロッカー:
- PR分割判断:
- PRタイトル:
- 概要:
- 変更点:
- テスト:
- レビュー観点:
- 特記事項・懸念点:
- 除外資産:

## 安全ルール

- 秘密情報や環境ファイルはコミットしない
- ユーザーが明示しない限り force push しない
- コミット対象資産と各資産を含める根拠の承認なしにコミットしない
- 反映資産とPR内容の承認なしに push / PR作成をしない
- `main` / `master` へ直接 push しない
- 無関係な未追跡ファイルをコミットに含めない
- CIが赤いPRを完成扱いにしない
