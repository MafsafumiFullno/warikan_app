#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

base_branch="${BASE_BRANCH:-main}"
if ! git show-ref --verify --quiet "refs/remotes/origin/${base_branch}"; then
  base_branch="master"
fi

echo "== Git state =="
git fetch origin "$base_branch" --prune
git status --short --branch
echo

echo "== Branch diff summary: origin/${base_branch}...HEAD =="
git diff --stat "origin/${base_branch}...HEAD"
echo

if [[ -f backend/artisan ]]; then
  echo "== Backend tests =="
  (cd backend && php artisan test)
  echo
fi

if [[ -f frontend/package.json ]]; then
  echo "== Frontend lint =="
  (cd frontend && npm run lint)
  echo

  echo "== Frontend typecheck =="
  (cd frontend && npx tsc --noEmit)
  echo
fi

echo "PR ready checks completed."
