---
description: Run a complete read-only Laravel technical audit and produce a dated CODE-REVIEW.md report
agent: review-orchestrator
subagent: true
---

# FULL LARAVEL CODE REVIEW

Perform the complete READ-ONLY audit defined in
`.github/agents/review-orchestrator.agent.md`, following it step by step:

1. Read `.github/docs/review-rules.md` and `.github/docs/architecture/ARCHITECTURE.md`.
2. Discover the real stack from `AGENTS.md` and the repository; never assume optional technologies.
3. Run only safe checks that already exist. DO NOT modify code, tests, dependencies, lockfiles, configuration or database data.
4. Run the specialist reviews in `.github/agents/` in risk order: Laravel, Security, Database, Performance, Concurrency, Business Logic; then Clean Code, Architecture, Testing, API, Frontend, Dependencies, Maintainability; then DevOps, Observability. Skip specialists that do not apply.

In Copilot, launch each specialist as a subagent by its `#agent-id` (the file name). In OpenCode, launch it with the `subagent` tool using the same ID (the `.opencode/agents/` symlinks point at these files).
5. Challenge every finding with `.github/agents/devil-advocate.agent.md` and drop unsupported, duplicated or stylistic ones.
6. Write only `.github/reviews/YYYY-MM-DD/CODE-REVIEW.md` (never at the repository root) with the sections and finding format of the orchestrator, stable IDs (SEC-001, BUG-001, DB-001...) and an action plan ordered by impact with no purely aesthetic suggestions.

Avoid overengineering: recommend the simplest solution that addresses a demonstrated problem, reuse existing patterns, and do not propose speculative abstractions, extra layers or dependencies without a concrete need.

DO NOT modify application code.
