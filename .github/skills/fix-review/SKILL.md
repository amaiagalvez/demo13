---
name: fix-review
description: Safely implement selected findings from a dated CODE-REVIEW.md report
disable-model-invocation: true
---
# FIX SELECTED CODE REVIEW FINDINGS

Read:

.github/docs/review-rules.md

and:

.github/reviews/YYYY-MM-DD/CODE-REVIEW.md

The user will provide one or more finding IDs.

Examples:

Fix SEC-002.

Fix SEC-002 and DB-004.

Fix all HIGH findings.

---

# FOR EACH REQUESTED FINDING

## 1. Locate

Find the reported code.

## 2. Re-verify

Determine whether the problem still exists.

The code may have changed since the review.

## 3. Understand

Inspect:

- callers
- dependencies
- related models
- related migrations
- tests
- configuration
- business rules

## 4. Decide

If the finding is no longer valid:

DO NOT change code.

Explain why.

If the finding is valid:

continue.

## 5. Fix

Implement the smallest safe change.

Avoid unrelated refactoring.

## 6. Tests

Add or update tests that demonstrate the intended behavior.

Follow the project's existing PHPUnit conventions.

## 7. Validation

Run relevant checks. `DX` is defined in `AGENTS.md`.

Examples:

DX php artisan test --compact <test-path>

DX ./vendor/bin/phpstan analyse

DX ./vendor/bin/pint --dirty --format agent

DX composer ci:check --no-interaction

docker compose run --rm --no-deps --entrypoint npm laravel13-npm run build

Only run commands appropriate to the project.
Prefer the narrowest relevant tests; use `composer ci:check` for the full gate. It runs `pint --test` inside its `test:prepare` step, which is fine here because it checks without rewriting.

## 8. Final report

For each finding report:

- ID
- original severity
- status
- explanation
- files changed
- tests added/changed
- commands executed
- results
- remaining risk

Never claim a test passed unless it actually ran.

Do not fix unrelated findings.
