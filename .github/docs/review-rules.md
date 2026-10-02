# Laravel Copilot Code Review — Project Instructions

## Project

This repository is a Laravel application.

IMPORTANT:
Inspect the repository before assuming optional technologies.

Determine the actual versions and stack from:
- composer.json
- composer.lock
- package.json
- lockfiles
- Docker files
- CI configuration
- application source

Possible technologies include:
- PHP
- Laravel
- Eloquent
- MySQL
- Redis
- Horizon
- Queues
- Pest
- PHPUnit
- PHPStan
- Psalm
- Pint
- Blade
- Livewire
- Vue
- Inertia
- Docker
- Vite

Never assume a technology exists just because this review pack supports it.

---

# General Review Rules

Prefer:

- simple solutions
- explicit code
- Laravel conventions
- maintainability
- testability
- low coupling
- clear business rules

Do NOT recommend abstractions merely because a pattern exists.

Do not introduce:

- repositories
- interfaces
- DTOs
- factories
- service layers
- domain layers
- microservices
- CQRS
- event sourcing
- Clean Architecture

unless there is a concrete problem that the abstraction solves.

Distinguish clearly between:

1. confirmed bug
2. confirmed security issue
3. probable risk
4. performance issue
5. architecture problem
6. maintainability problem
7. technical debt
8. optional improvement
9. personal preference

Never present personal preference as a defect.

---

# Evidence

Never invent:

- files
- line numbers
- APIs
- vulnerabilities
- test results
- benchmark results
- database behavior
- tool output

CRITICAL and HIGH findings require concrete evidence.

If something cannot be verified, say so.

---

# Review Mode

Reviews are READ-ONLY.

During a review:

DO NOT:

- modify application code
- modify tests
- modify dependencies
- modify composer.lock
- modify package-lock files
- modify database data
- modify configuration
- perform refactors

unless the user explicitly switches to FIX MODE.

---

# Finding Format

Every accepted finding must contain:

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

Severity levels:

CRITICAL
HIGH
MEDIUM
LOW
INFO

Confidence:

HIGH
MEDIUM
LOW

---

# Laravel

Use current Laravel conventions.

Prefer framework functionality over reinventing equivalent functionality.

Review:

- routing
- controllers
- middleware
- Form Requests
- validation
- policies
- gates
- authorization
- dependency injection
- service container
- Eloquent
- API Resources
- jobs
- queues
- events
- listeners
- notifications
- mail
- scheduling
- commands
- cache
- sessions
- storage
- configuration
- logging
- transactions

Before claiming an API is deprecated, obsolete or unsupported, verify it against current Laravel documentation when web access is available.

---

# Security

Pay special attention to:

- authentication
- authorization
- IDOR
- BOLA
- mass assignment
- SQL injection
- XSS
- CSRF
- SSRF
- command injection
- path traversal
- unsafe file uploads
- session security
- cookie security
- rate limiting
- secrets
- sensitive logging
- excessive API exposure
- insecure Docker configuration

Do not claim a vulnerability without evidence.

---

# Database

Review:

- schema
- relationships
- foreign keys
- indexes
- constraints
- migrations
- Eloquent queries
- eager loading
- lazy loading
- N+1
- transactions
- locks
- race conditions
- data integrity
- destructive migrations

---

# Testing

Use the project's existing testing style.

Do not rewrite tests simply for stylistic reasons.

Prefer tests that verify:

- behavior
- business rules
- authorization
- validation
- edge cases
- failure cases
- important integrations

---

# Performance

Do not optimize speculatively.

A performance finding should explain:

1. evidence
2. likely bottleneck
3. expected impact
4. proposed solution
5. complexity/cost

---

# Fix Mode

When the user explicitly requests fixes:

1. Re-check the finding against current code.
2. Confirm that it still exists.
3. Make the smallest safe change.
4. Add/update tests.
5. Run relevant tests/checks.
6. Do not fix unrelated findings.
7. Do not perform broad refactors.
8. Report exactly what was changed.
9. Report exactly which commands were executed.
10. Never claim a test passed unless it actually ran.

---

# Architecture

Architecture must be proportional to the application.

Prefer:

simple > clever

explicit > implicit

boring > fashionable

locality > unnecessary abstraction

Do not introduce architecture patterns just to satisfy theoretical purity.

---

# Long-Term Maintainability

Review the application as if another developer must maintain it for two years.

Ask:

- Can a new developer understand this?
- Is business logic easy to find?
- Are dependencies understandable?
- Are important invariants tested?
- Are dangerous areas documented?
- Are there hidden assumptions?
- Are changes likely to cause regressions?
- Is technical debt explicit?

---

# Architecture Context

If present, read:

.github/docs/architecture/ARCHITECTURE.md

Do not contradict documented architectural decisions without explaining why the current decision creates a concrete problem.

