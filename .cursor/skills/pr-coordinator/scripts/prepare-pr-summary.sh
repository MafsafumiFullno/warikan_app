#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

base_branch="${BASE_BRANCH:-main}"
if ! git show-ref --verify --quiet "refs/remotes/origin/${base_branch}"; then
  base_branch="master"
fi

branch="$(git branch --show-current)"

echo "== PR summary draft =="
echo
echo "Branch: ${branch}"
echo "Base: origin/${base_branch}"
echo

echo "## 反映資産"
git diff --name-status "origin/${base_branch}...HEAD" || true
echo

echo "## 未コミット差分"
git status --short
echo

echo "## 直近コミット"
git log --oneline "origin/${base_branch}..HEAD" || true
echo

echo "## 差分サマリ"
git diff --stat "origin/${base_branch}...HEAD" || true
echo

echo "## PR本文テンプレート"
if [[ -f .github/pull_request_template.md ]]; then
  sed -n '1,220p' .github/pull_request_template.md
else
  echo "- .github/pull_request_template.md が見つかりません"
fi
echo

echo "依頼者へ反映資産、PRタイトル、概要、変更点、テスト、レビュー観点、懸念点を提示して承認を得てください。"
