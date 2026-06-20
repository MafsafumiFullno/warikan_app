---
name: pr-git-workflow
description: Prepare commits, run PR readiness checks, and create pull requests for warikan_app. Use when the user asks to commit, prepare a PR, open a PR, or perform git operations up to PR creation.
disable-model-invocation: true
---

# PR Git Workflow

## Instructions

1. Inspect the working tree:
   - `git status --short --branch`
   - `git diff`
   - `git log --oneline -5`
2. Stage only files related to the user's requested work.
3. Commit only when the user explicitly asks.
4. Before opening a PR, run:
   - `scripts/pr-ready-check.sh`
5. Open the PR with:
   - `scripts/create-pr.sh "PR title"`
6. Fill `.github/pull_request_template.md` with the actual summary and test plan when a custom PR body is needed.

## Safety

- Never commit secrets or environment files.
- Never force push unless the user explicitly requests it.
- Never push directly to `main` or `master`.
- Do not include unrelated untracked files in the commit.
