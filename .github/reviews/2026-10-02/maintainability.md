# Maintainability Review — 2026-10-02

Judged as if a different developer has to maintain this for two years.

## Findings

### MAINT-001 — Orphan translation key in all four locales

Severity: LOW
Category: Maintainability / i18n
File: lang/en.json, lang/es.json, lang/eu.json, lang/fr.json
Line: 47 in each file
Confidence: HIGH

Problem: `"No active customers available."` exists in every locale but is used nowhere; the string
that *is* used is `"No active customers are available. Create one from the project form."` (line 74).

Evidence: `grep -rn "No active customers available\." resources/ app/ tests/` → no matches outside
`lang/*.json`.

Impact: minor, but it is exactly the drift the i18n parity test was written to prevent — and that
test (`tests/Unit/Translations/TranslationFilesTest.php`) only compares keys *across* locales, so it
structurally cannot detect orphans.
Recommendation: delete the four lines. Then add a unit test asserting every key in `lang/en.json` is
referenced by `resources/` or `app/`, which is the only mechanism that closes the class of bug.

### MAINT-002 — The test database name is documented in two places and they disagree

Severity: MEDIUM
Category: Maintainability
File: phpunit.xml, .github/workflows/tests.yml, AGENTS.md
Line: 19 / 39-51 / "Tests (PHPUnit)"
Confidence: HIGH

Problem: `phpunit.xml` says `laravel_test`, the CI `ci` job says `laravel`, and `AGENTS.md` says
"Everything runs in Docker" without mentioning that a second database has to exist. See
`testing.md` TEST-002 for the mechanism (PHPUnit does not override an already-set `<env>`).

Impact: this is the single most likely source of "works locally, fails in CI" for the next person.
Recommendation: single source of truth + a documented bootstrap step.

### MAINT-003 — `todo.md` mixes shipped work, ideas and review assignments with no state

Severity: INFO
Category: Maintainability
File: todo.md
Line: 1-16
Confidence: HIGH

Observation: the file contains unchecked boxes (`[ ]`, and one open `[ ]` for a review request that
this session is answering), product ideas, and now three line items that are genuinely actionable
product requirements — most notably the select/`active` rule that `business.md` BIZ-001 also
reports. Two entries are already implemented ("crear el CRUD de usuarios" — Epic exists;
"installar debugbar" — not started).

Impact: a requirement living only in `todo.md` is invisible to the next contributor and to every
agent, since `AGENTS.md` and the architecture document do not reference it.
Recommendation: when BIZ-001 is scheduled, move that requirement into
`.github/docs/architecture/ARCHITECTURE.md` next to the other business rules, and leave `todo.md`
for unvalidated ideas only. No code change.

## Two-year maintainability assessment

**Can a new developer understand this?** Yes, unusually well. `AGENTS.md` documents the stack, the
exact commands and the project map; `.github/docs/architecture/ARCHITECTURE.md` states the business
invariants; `.github/instructions/*.instructions.md` inject the per-zone rules automatically. This
is above average for a project of this size.

**Is business logic easy to find?** Yes. Deletion guards, uniqueness scopes and the trash-name
conflict flow are all in Form Requests or controllers, and `http.instructions.md` names them
explicitly.

**Are important invariants tested?** Mostly yes — and, unusually, the *structural* ones are enforced
by the suite (`ArchitectureTest`, `ValidationCoverageTest`, `ModelSchemaParityTest`,
`TranslationFilesTest`) rather than by convention. The gaps are TEST-002 (database divergence),
TEST-003 (SQLite) and TEST-004 (project/epic access coverage).

**Are dangerous areas documented?** Yes: the generated-column uniqueness trick, the
`UniqueConstraintViolation` race path and the `DatabaseMigrations`-per-Dusk-test choice all have
explanatory comments or docblocks.

**Hidden assumptions?**
1. MySQL/MariaDB is the only supported engine (`LIKE` escape, generated columns) — stated nowhere
   outside the migrations' branching.
2. `PER_PAGE = 5` is assumed small enough that loading all option rows is acceptable
   (see PERF-001).
3. `resolve_name_conflict` and `reuse_deleted_name` only change *messages* / *branching*, never
   behaviour, despite the names implying otherwise. Worth a one-line comment in each
   `*RestoreRequest`.

**Are changes likely to cause regressions?** Adding a fourth resource is the highest-risk change,
because the three existing ones are independent copies rather than a shared base. The mitigation
already in place is `ArchitectureTest::test_every_resource_ships_the_complete_set` plus the
`http.instructions.md` rule to mirror the canonical resource. That is the right trade-off at this
size; FE-001 is the cleanup to schedule if a fourth resource ever arrives.

**Is technical debt explicit?** Mostly — `todo.md` and the "Known Technical Debt" section of
`ARCHITECTURE.md:137-140` both exist and are honest. The exception is MAINT-003.