# Dependency Maintenance Knowledge

## Purpose

This document records irregular dependency maintenance knowledge for `warikan_app`.

Repeatable procedure belongs in `platform-dependency-maintainer`.
Concrete incidents, compatibility findings, and future grouping lessons belong here.

## Responsibility Boundary

- Platform Engineering owns dependency update triage, CI/runtime compatibility, lockfile conflict handling, and developer experience around updates.
- Application Engineering is involved only when the dependency update changes application behavior or requires code changes in domain, API, UI, or database logic.
- Quality Engineering verifies regression risk, acceptance impact, E2E impact, and contract checks.
- Security Engineering reviews dependency vulnerabilities, auth/authorization impact, secrets, and threat-modeling concerns.
- Reliability Engineering is consulted when an update affects production reliability, capacity, resilience, recovery, or observability.

## Current Learnings

### Dependabot auto-merge failure is not always dependency failure

If the failed check is only the workflow that enables auto-merge, do not treat it as evidence that the dependency update is broken.
Check the normal CI jobs first, especially lint, build, backend tests, and E2E.

### Tailwind split PRs can conflict through package-lock changes

When related Tailwind packages are updated in separate PRs, merging one PR can make the remaining PR dirty through `package-lock.json`.
Resolve this by updating the remaining branch from latest `main` and regenerating the lockfile with npm.

Avoid hand-editing lockfile JSON except for very small, well-understood conflicts.

### Frontend tooling grouped major updates are high risk

Grouping TypeScript, ESLint, Next ESLint config, Playwright, and Node types into one major-update PR can hide the real failure source.
When such a PR fails, isolate the incompatible dependency before merging.

### TypeScript major updates can outrun ESLint support

If TypeScript is updated to a new major version before the ESLint TypeScript parser ecosystem supports it, lint can fail even when application code is unchanged.
Treat this as a Platform Engineering compatibility issue.

Recommended handling:

- hold the TypeScript major update
- split TypeScript from unrelated frontend tooling updates
- merge compatible tool updates separately
- revisit once the ESLint TypeScript ecosystem supports the target TypeScript major

## Decision Pattern

Use this sequence for dependency PRs:

1. Separate normal CI failures from auto-merge or repository-policy failures.
2. Identify whether the failure is application code, dependency compatibility, runtime, CI, or lockfile state.
3. For lockfile conflicts, update from latest `main` and regenerate with the package manager.
4. For grouped failures, split or hold the risky dependency instead of forcing the whole group through.
5. Record reusable lessons here when the finding is likely to recur.
