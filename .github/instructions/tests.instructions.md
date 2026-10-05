---
applyTo: "tests/**"
---
# Test rules
- Prefer Feature tests; Dusk (`tests/Browser`) only for JS behavior.
- Duplicate-insert races are tested per resource for store, update and restore by simulating a duplicate-key `QueryException`.
- Dusk runs against `laravel_test` with a non-English default locale, so assert on translated text.
- Run it as `docker compose exec -e XDEBUG_MODE=off laravel13-dusk php artisan dusk [tests/Browser/...php]`, never in the default service: only that one gives the runner and the browser server the same `laravel_test` database and its own config cache.
