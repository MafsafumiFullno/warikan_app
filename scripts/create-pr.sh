#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

if ! command -v gh >/dev/null 2>&1; then
  echo "gh command is required." >&2
  exit 1
fi

branch="$(git branch --show-current)"
if [[ -z "$branch" || "$branch" == "main" || "$branch" == "master" ]]; then
  echo "Create a feature/refactor/fix branch before opening a PR." >&2
  exit 1
fi

if [[ -n "$(git status --short)" && "${ALLOW_DIRTY:-0}" != "1" ]]; then
  echo "Working tree has uncommitted changes. Commit first, or set ALLOW_DIRTY=1." >&2
  git status --short
  exit 1
fi

base_branch="${BASE_BRANCH:-main}"
if ! git show-ref --verify --quiet "refs/remotes/origin/${base_branch}"; then
  base_branch="master"
fi

title="${1:-$(git log -1 --pretty=%s)}"
template=".github/pull_request_template.md"

git fetch origin "$base_branch" --prune
git push -u origin HEAD

if [[ -f "$template" ]]; then
  gh pr create --base "$base_branch" --head "$branch" --title "$title" --body-file "$template"
else
  gh pr create --base "$base_branch" --head "$branch" --title "$title" --fill
fi
