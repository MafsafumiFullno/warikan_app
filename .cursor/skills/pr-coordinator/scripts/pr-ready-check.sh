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

echo "== Working tree pending assets =="
git status --short
echo

changed_files="$(
  {
    git diff --name-only "origin/${base_branch}...HEAD"
    git diff --name-only
    git diff --cached --name-only
  } | sort -u
)"

if echo "$changed_files" | grep -E '^(backend/|docker-compose.yml)' >/dev/null && [[ -f backend/artisan ]]; then
  echo "== Backend tests =="
  (cd backend && php artisan test)
  echo
else
  echo "== Backend tests =="
  echo "Skipped: no backend app changes detected."
  echo
fi

if echo "$changed_files" | grep -E '^(frontend/|docker-compose.yml)' >/dev/null && [[ -f frontend/package.json ]]; then
  echo "== Frontend lint =="
  (cd frontend && npm run lint)
  echo

  echo "== Frontend typecheck =="
  (cd frontend && npx tsc --noEmit)
  echo
else
  echo "== Frontend checks =="
  echo "Skipped: no frontend app changes detected."
  echo
fi

echo "PR ready checks completed."
