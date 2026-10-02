---
applyTo: "tests/**"
---
# Test rules
- Prefer Feature tests; Dusk (`tests/Browser`) only for JS behavior.
- Duplicate-insert races are tested per resource for store, update and restore by simulating a duplicate-key `QueryException`.
- Dusk runs against `laravel_test` with a non-English default locale, so assert on translated text.
