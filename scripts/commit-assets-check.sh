#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

echo "== Git state =="
git status --short --branch
echo

echo "== Staged assets =="
git diff --cached --name-status
echo

echo "== Unstaged assets =="
git diff --name-status
echo

echo "== Untracked assets =="
git ls-files --others --exclude-standard
echo

echo "コミット前に、対象資産と各資産を含める根拠を依頼者へ提示し、承認を得てください。"
