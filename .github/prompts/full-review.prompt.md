---
description: Run a complete read-only Laravel technical audit and produce a dated CODE-REVIEW.md report
---

# FULL LARAVEL CODE REVIEW

Perform a complete READ-ONLY technical audit of this repository.

DO NOT modify application code.

---

## PHASE 1 — DISCOVERY

Inspect the repository.

Determine the actual:

- PHP version
- Laravel version
- database
- frontend stack
- cache
- Redis
- queues
- Horizon
- scheduler
- Docker
- Pest
- PHPUnit
- PHPStan
- Psalm
- Pint
- CI/CD
- external services

Inspect when relevant:

- composer.json
- composer.lock
- package.json
- lockfiles
- routes/
- app/
- bootstrap/
- config/
- database/
- resources/
- tests/
- Docker files
- CI/CD files

Never assume optional technologies.

---

# PHASE 2 — VALIDATION

Run safe checks that already exist in the project.

Examples:

- php artisan test
- vendor/bin/pest
- vendor/bin/phpunit
- vendor/bin/phpstan analyse
- vendor/bin/pint --test
- npm test
- npm run lint
- npm run typecheck
- composer audit

Only execute commands that are appropriate for the detected project.

DO NOT modify:

- source code
- tests
- dependencies
- lockfiles
- database data
- configuration

Record only commands that were actually executed and their actual results.

---

# PHASE 3 — SPECIALIST REVIEWS

Read and apply the relevant specialist instructions under:

.github/agents/

Review using:

1. Laravel
2. Clean Code
3. Security
4. Architecture
5. Database
6. Performance
7. Testing
8. API
9. Frontend
10. Concurrency
11. Dependencies
12. DevOps
13. Observability
14. Business Logic
15. Maintainability

Skip reviewers that clearly do not apply.

For every finding provide:

- ID
- severity
- category
- file
- line
- problem
- evidence
- impact
- recommendation
- confidence

---

# PHASE 4 — DEVIL'S ADVOCATE

Read:

.github/agents/devil-advocate.agent.md

Challenge every important finding.

Ask:

- Is the evidence real?
- Is this actually a bug?
- Is the severity justified?
- Is it duplicated?
- Is it only style?
- Is the recommendation necessary?
- Could the recommendation create more complexity?
- Is there an existing architecture decision explaining it?
- Could fixing it introduce regression risk?

Remove unsupported findings.

---

# PHASE 5 — REPORT

Create or update only this path, relative to the repository root:

.github/reviews/YYYY-MM-DD/CODE-REVIEW.md

Never create or update `CODE-REVIEW.md` in the repository root. The report
must not be saved in the workspace home directory or any other location.

Include:

## Executive Summary

## Detected Stack

## Checks Executed

## CRITICAL

## HIGH

## MEDIUM

## LOW

## INFO

## Security

## Bugs / Correctness

## Database

## Performance

## Architecture

## Testing

## Production

## Maintainability

## Rejected Findings

## Recommended Action Plan

Use stable IDs.

Examples:

SEC-001
BUG-001
DB-001
PERF-001
ARCH-001
TEST-001
API-001
FE-001
CONC-001
DEP-001
DEVOPS-001
OBS-001
BIZ-001
MAINT-001

Do not modify application code.
