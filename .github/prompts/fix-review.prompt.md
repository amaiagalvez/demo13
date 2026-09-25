---
description: Safely implement selected findings from CODE-REVIEW.md
---

# FIX SELECTED CODE REVIEW FINDINGS

Read:

.github/copilot-instructions.md

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

Follow the project's existing Pest/PHPUnit conventions.

## 7. Validation

Run relevant checks.

Examples:

php artisan test

vendor/bin/pest

vendor/bin/phpunit

vendor/bin/phpstan analyse

vendor/bin/pint --test

npm run lint

Only run commands appropriate to the project.

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
