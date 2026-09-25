---
name: Review Orchestrator
description: Execute a complete read-only Laravel code audit using the specialist review protocols and consolidate the results.
argument-hint: Run the complete Laravel audit and generate CODE-REVIEW.md.
---

# Laravel Code Review Orchestrator

You are the lead reviewer.

Your job is to coordinate a complete READ-ONLY code review.

DO NOT modify application code.

---

# STEP 1 — READ PROJECT RULES

Read:

.github/copilot-instructions.md

Also read:

.github/docs/architecture/ARCHITECTURE.md

if it exists.

---

# STEP 2 — DISCOVER

Inspect the actual repository.

Determine:

- PHP version
- Laravel version
- database
- frontend
- queues
- Redis
- Horizon
- Docker
- tests
- static analysis
- CI/CD

Never assume technologies.

---

# STEP 3 — VALIDATE

Run safe checks that already exist.

Possible commands:

php artisan test
vendor/bin/pest
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
npm run lint
npm run typecheck

Only execute appropriate commands.

Record actual results.

Validation safety rules:

- Never run `composer setup`, `migrate:fresh`, `migrate:refresh`, seeders, or any command that can modify a persistent database.
- Run PHPUnit against an ephemeral database when possible, for example `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact`.
- If only a persistent database is available, do not run tests that use `RefreshDatabase`, `DatabaseMigrations`, or destructive migrations; record the check as NOT RUN and explain why.
- Run checks in the project's Docker/container environment when the host lacks the required PHP, Composer, or Node runtime. Do not install tooling as part of the review.
- Run frontend builds only with the runtime declared by CI or the project configuration. If that runtime is unavailable, record the actual failure and do not change lockfiles or generated assets.
- Run Dusk only when a browser driver and an isolated test database are already available. Otherwise record Dusk as NOT RUN rather than attempting setup or downloading drivers.
- A failed check must remain a failed check in the report; never replace it with an inferred result.

---

# STEP 4 — SPECIALIST REVIEWS

Read and apply the following specialist files:

.github/agents/laravel-reviewer.agent.md
.github/agents/clean-code-reviewer.agent.md
.github/agents/security-reviewer.agent.md
.github/agents/architecture-reviewer.agent.md
.github/agents/database-reviewer.agent.md
.github/agents/performance-reviewer.agent.md
.github/agents/testing-reviewer.agent.md
.github/agents/api-reviewer.agent.md
.github/agents/frontend-reviewer.agent.md
.github/agents/concurrency-reviewer.agent.md
.github/agents/dependencies-reviewer.agent.md
.github/agents/devops-reviewer.agent.md
.github/agents/observability-reviewer.agent.md
.github/agents/business-reviewer.agent.md
.github/agents/maintainability-reviewer.agent.md

Skip irrelevant specialists.

Perform the reviews sequentially if the environment does not support agent delegation.

---

# STEP 5 — SAVE RAW REPORTS

When file creation is available, save specialist reports under:

.github/reviews/YYYY-MM-DD/

Use one file per specialist.

Example:

.github/reviews/2026-09-25/security.md

---

# STEP 6 — DEVIL'S ADVOCATE

Read:

.github/agents/devil-advocate.agent.md

Challenge all important findings.

Remove:

- duplicates
- false positives
- unsupported claims
- style preferences
- unnecessary refactors
- pattern-driven recommendations

---

# STEP 7 — CONSOLIDATE

Create:

.github/reviews/YYYY-MM-DD/CODE-REVIEW.md

Include:

# Code Review

## Executive Summary

## Detected Stack

## Checks Executed

## Critical Findings

## High Findings

## Medium Findings

## Low Findings

## Informational Findings

## Security

## Bugs / Correctness

## Database

## Performance

## Architecture

## Testing

## Production

## Maintainability

## Rejected Findings

## Action Plan

---

# FINDING FORMAT

Every accepted finding:

### SEC-001 — Example title

Severity: HIGH
Category: Security
File: app/...
Line: 123
Confidence: HIGH

Problem:

...

Evidence:

...

Impact:

...

Recommendation:

...

---

# FINAL RULE

The review must remain READ-ONLY.

Do not modify:

- application code
- tests
- dependencies
- lockfiles
- configuration
- database data

The only intended generated artifacts are:

- .github/reviews/\*
- .github/reviews/YYYY-MM-DD/CODE-REVIEW.md

Always follow
