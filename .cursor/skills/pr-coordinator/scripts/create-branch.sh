#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

branch="${1:-}"
base_branch="${BASE_BRANCH:-main}"

if [[ -z "$branch" ]]; then
  echo "Usage: $0 <type/name>" >&2
  echo "Example: $0 fix/member-id-contract" >&2
  exit 1
fi

if [[ "$branch" != */* ]]; then
  echo "Branch name should include a type prefix, such as fix/, feature/, chore/, test/, docs/, refactor/, or infra/." >&2
  exit 1
fi

case "$branch" in
  feature/*|fix/*|chore/*|test/*|docs/*|refactor/*|infra/*)
    ;;
  *)
    echo "Unsupported branch prefix. Use feature/, fix/, chore/, test/, docs/, refactor/, or infra/." >&2
    exit 1
    ;;
esac

if [[ -n "$(git status --short)" && "${ALLOW_DIRTY:-0}" != "1" ]]; then
  echo "Working tree has uncommitted changes. Commit/stash them first, or set ALLOW_DIRTY=1." >&2
  git status --short
  exit 1
fi

if [[ "$base_branch" != "main" && "${APPROVED_NON_MAIN_BASE:-0}" != "1" ]]; then
  echo "Branches should normally be created from main." >&2
  echo "Requested base: ${base_branch}" >&2
  echo "Ask the requester before using a non-main base, then rerun with APPROVED_NON_MAIN_BASE=1." >&2
  exit 1
fi

git fetch origin --prune

if ! git show-ref --verify --quiet "refs/remotes/origin/${base_branch}"; then
  if [[ "$base_branch" == "main" ]] && git show-ref --verify --quiet "refs/remotes/origin/master"; then
    base_branch="master"
  else
    echo "Base branch origin/${base_branch} was not found." >&2
    exit 1
  fi
fi

if git show-ref --verify --quiet "refs/heads/${branch}" || git show-ref --verify --quiet "refs/remotes/origin/${branch}"; then
  echo "Branch already exists: ${branch}" >&2
  exit 1
fi

git switch -c "$branch" "origin/${base_branch}"

echo "Created branch ${branch} from origin/${base_branch}."
