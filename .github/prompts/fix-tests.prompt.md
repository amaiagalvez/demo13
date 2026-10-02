---
description: Run failing tests and get them green with the smallest safe change
argument-hint: <path or --filter=name>
---

# FIX FAILING TESTS

1. Run the narrowest failing suite: `DX php artisan test --compact <path|--filter=name>` (use the argument, or ask the user for it; run the full suite only if asked).
2. For each failure, reproduce it with the narrowest command and read the test plus the production code it covers.
3. Decide: bug in production code, bug in the test, or environment issue. Fix production code unless the test itself is wrong; if you change a test, say why.
4. Make the smallest safe change. Keep sibling-file conventions, do not refactor unrelated code, do not touch unrelated findings.
5. After PHP edits: `DX ./vendor/bin/pint --dirty --format agent`.
6. Rerun the narrowest test after each fix, then the affected suite.
7. Report every command executed and its real result. Never claim a test passed unless it ran.
