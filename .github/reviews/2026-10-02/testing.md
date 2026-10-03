# Testing Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree. PHPUnit 12.5.23 (not Pest), Paratest 7.20 available, Dusk 8.7.

Suite as measured:

```
$ docker compose exec laravel13 php artisan test --compact
Tests: 4 failed, 233 passed (1168 assertions)     # at HEAD 781ccbb
Tests: 1 failed, 238 passed (1208 assertions)     # working tree at 22:19
```

## TEST-001 — CI is red: PHPStan fails before the test suite even runs

Severity: HIGH
Category: Testing / CI
File: phpstan.neon
Line: 15
Confidence: HIGH

Problem:

`composer ci:check` runs `@test:prepare` (which includes `@types:check` → `phpstan analyse`) and only then `@test`.
PHPStan currently reports an error, so the suite never executes in CI and the red state is reported as a static
analysis failure rather than as the test failures it is masking.

Evidence — `.github/workflows/tests.yml:120` runs `composer ci:check`; `composer.json:63-66` defines
`ci:check: ["Composer\\Config::disableProcessTimeout", "@test"]` and `test: ["@test:prepare", "@php artisan test"]`
where `test:prepare` includes `@types:check`.

```
$ docker compose exec laravel13 ./vendor/bin/phpstan analyse --no-progress
  Line   app/Http/Controllers/Controller.php
  20     Parameter #1 $view of function view expects view-string|null, string given.
         🪪  argument.type
 [ERROR] Found 1 error
```

The level is 9 (`phpstan.neon:15`), matching the documented command in `AGENTS.md:43`.

Impact:

CI is red on `master`/`PR`. Because PHPStan runs first, the 4 test failures reported at HEAD are invisible in CI —
the pipeline stops before them. Fixing only the tests would still leave CI red.

Recommendation:

Fix `app/Http/Controllers/Controller.php:20` by typing the parameter:

```php
/**
 * @param  view-string  $view
 * @param  array<string, mixed>  $data
 */
protected function listView(Request $request, string $view, array $data): View|string
```

This is a PHPDoc-only change with no runtime effect. Tracked as LAR-002 in laravel.md.

## TEST-002 — Four list tests fail at HEAD because the list controllers return a string, not a View

Severity: HIGH
Category: Testing / Correctness
File: app/Http/Controllers/CustomerController.php
Line: 29
Confidence: HIGH

Problem:

Every list `index()` ends with `->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results')`.
`Illuminate\View\View::fragmentIf()` always returns a rendered **string** (vendor/.../View/View.php:114-121), so the
response never carries view data. `TestResponse::assertViewHas()` and `viewData()` both require a view and throw
`AssertionFailedError: The response is not a view.`

Evidence — the exact failures at HEAD:

```
FAILED Tests\Feature\Epics\EpicCommentTest > list_embeds_only_the_most_recent_comments_of_each_epic_but_counts_all
  AssertionFailedError The response is not a view.
  at tests/Feature/Epics/EpicCommentTest.php:77      ($response->viewData('list'))

FAILED Tests\Feature\ResourceActivationTest > inactive_list_paginates_only_inactive_records  (x3 data sets)
  AssertionFailedError The response is not a view.
  at tests/Feature/ResourceActivationTest.php:372   (assertViewHas)
```

```
Tests: 4 failed, 233 passed (1168 assertions)
```

Affected controllers: all six list index actions (`CustomerController`, `CustomerInactiveController`,
`CustomerTrashController`, `ProjectController`, `ProjectInactiveController`, `ProjectTrashController`,
`EpicController`, `EpicInactiveController`, `EpicTrashController`).

Impact:

- CI red (TEST-001 is the first failure, this is the second).
- The tests are correct; the controllers are wrong. They assert on view data that genuinely exists.
- `tests/Feature/ResourceActivationTest.php:372-378` is the only place pagination invariants for the inactive lists
  are asserted, so losing it removes real coverage until it is restored.
- Blast radius is larger than the four tests: any future `assertViewHas`/`assertViewHasErrors` on a list route will
  fail with a message that does not point at the controller.

Recommendation:

Return the `View` and only render the fragment when the header is present:

```php
$view = view('customers.list', [...]);

return $request->hasHeader('X-List-Fragment')
    ? $view->fragment('list-results')
    : $view;
```

The working tree already introduces `Controller::listView()` implementing exactly this — keep it, add the
`@param view-string` from TEST-001, and the four tests go back to green. Do **not** "fix" the tests by asserting on
the response body instead; the view-data assertions are the correct contract.

## TEST-003 — `tests/Browser` are excluded from `phpunit.xml` and only run in a separate CI job

Severity: LOW
Category: Testing / Coverage
File: phpunit.xml
Line: 13-22
Confidence: HIGH

Problem:

`phpunit.xml` declares only two suites — `Unit` and `Feature`. The seven Dusk tests in `tests/Browser/` are not part
of any suite and are executed only by `php artisan dusk` in a dedicated CI job (`.github/workflows/tests.yml:228`).

Evidence:

```xml
<testsuites>
    <testsuite name="Unit"><directory>tests/Unit</directory></testsuite>
    <testsuite name="Feature"><directory>tests/Feature</directory></testsuite>
</testsuites>
```

This is deliberate and documented: `ARCHITECTURE.md:113-114` — "Keep browser tests in a separate CI job because they
require a browser and a test database" — and `AGENTS.md` documents `DX php artisan dusk tests/Browser/...`.

Impact:

None functionally. It does mean `php artisan test` locally reports a green suite while JS behaviour (dirty-form
guards, modal open/close, select2 inline create, list fragment refresh) is unverified.

Recommendation:

None. Documented and intentional. I did **not** run Dusk: no ChromeDriver/chromedriver was verified as available in
this environment, so per the orchestrator's safety rules Dusk is recorded as **NOT RUN**.

## TEST-004 — `EpicCommentController::index()` breaks the architecture test's Form Request rule

Severity: MEDIUM
Category: Testing / Test design
File: tests/Unit/ArchitectureTest.php
Line: 27
Confidence: HIGH

Problem:

`FORM_REQUEST_ENDPOINTS = ['index', 'store', 'update', 'restore', 'comment']` requires every controller `index()` to
declare a Form Request. The new `EpicCommentController::index(Epic $epic): JsonResponse` reads no user input, so it
declares none, and the architecture test fails.

Evidence:

```
FAILED Tests\Unit\ArchitectureTest > input endpoints declare a form request
  App\Http\Controllers\EpicCommentController::index must declare a FormRequest (user input must be validated)
  at tests/Unit/ArchitectureTest.php:115
Tests: 1 failed, 238 passed (1208 assertions)
```

The rule is implemented at `ArchitectureTest.php:100-120`: it reflects every public method declared on the class,
filters by name, and asserts at least one parameter is a `FormRequest` subclass.

Impact:

CI red. More interestingly, the test is now encoding an **incorrect** invariant: `index` is in the list because list
indexes take a `*ListRequest` for the `search` input. A read-only JSON sub-resource has no input and needs no
Form Request, so the rule is over-broad.

Recommendation:

Rename the action from `index` to `show` and keep the route name `epics.comments.index` (route names are what
templates and tests reference):

```php
Route::get('epics/{epic}/comments', [EpicCommentController::class, 'show'])
    ->whereNumber('epic')
    ->name('epics.comments.index');
```

This keeps the rule intact for the endpoints it was written for. Do **not** add an empty Form Request to satisfy the
test — that would be cargo-culting and would add a class with no purpose.

## TEST-005 — The duplicate-name race is tested for all three resources on all three write paths

Severity: INFO (positive finding)
Category: Testing
File: tests/Feature/Projects/ProjectCrudTest.php
Line: 114
Confidence: HIGH

Problem:

None. This is the strongest test coverage in the repository and it directly de-risks the concurrency design.

Evidence — all nine combinations exist:

| Resource | store | update | restore |
|---|---|---|---|
| Customer | `CustomerCrudTest.php:159` | `:176` | `CustomerTrashTest.php:108` |
| Project | `ProjectCrudTest.php:114` | `:136` | `ProjectTrashTest.php:145` |
| Epic | `EpicCrudTest.php:175` | `:195` | `EpicTrashTest.php:159` |

They inject a competing `INSERT` with `DB::listen` between validation and the model write, forcing the real
`QueryException`, and assert it surfaces as a validation error rather than a 500. `CustomerCrudTest.php:198` and
`ProjectCrudTest.php:94` / `EpicCrudTest.php:94` additionally assert that a **non**-unique `QueryException` is
rethrown untouched (`expectException(QueryException::class)`).

Impact:

None. `App\Support\Database\UniqueConstraintViolation` is fully exercised.

Recommendation:

None. Keep this pattern; it is the model to follow for any future race (see CONC-001, which has no test because there
is no meaningful invariant to assert).

## TEST-006 — Structural invariants are enforced by three meta-tests rather than by convention

Severity: INFO (positive finding)
Category: Testing
File: tests/Unit/ModelSchemaParityTest.php
Line: 52
Confidence: HIGH

Problem:

None. These three tests make several architecture rules mechanical.

Evidence:

- `ModelSchemaParityTest.php:52-65` — every `#[Fillable]` attribute must exist as a schema column.
- `ModelSchemaParityTest.php:67-86` — every non-`SYSTEM_COLUMNS` column must be in `#[Fillable]` (so a new column
  cannot be added without deciding how it is populated).
- `ModelSchemaParityTest.php:88-110` — every cast must exist in the schema; `SoftDeletes` implies a `deleted_at` column.
- `ArchitectureTest.php:62-86` — controllers stay thin, no raw input, no `->paginate(`.
- `ValidationCoverageTest.php:33-49` — every fillable attribute has a validation rule.
- `ValidationCoverageTest.php:51-87` — every input key read via `$request->string()`/`boolean()` in a controller has
  a rule in that method's Form Request.
- `ArchitectureTest.php:123-140` — list views use `<x-list.table>` and forms use `<x-forms.tracked-resource>`.

Impact:

None. This is why `AGENTS.md`'s "a new column needs three things together" rule holds in practice.

Recommendation:

None. Note in TEST-004 that this meta-test *style* is why an over-broad rule is worth fixing rather than working
around — the tests are the architecture here.

## Notes / not findings

- **`tests/Feature/Epics/EpicCommentTest.php` mid-refactor.** The working tree rewrites two tests to target the new
  JSON endpoint. That is legitimate work in progress, not a finding; but it means TEST-002's Epic failure is being
  resolved by a different route than the Customer/Project ones. Re-run the suite after the working tree settles.
- **Dusk: NOT RUN.** No ChromeDriver availability was verified in this environment, so Dusk was not executed per
  the orchestrator's safety rules. This is recorded as NOT RUN, not as a pass.
- **Paratest is available but unused in CI** (`.github/instructions/tests.instructions.md`: "never in CI — shared DB").
  Correct — the DB is shared, so parallel runs would collide. **Rejected as a finding.**
- **No coverage threshold configured.** `composer test:coverage` exists (`composer.json:68-70`) and
  `docker/run-phpunit-with-coverage.sh` writes a summary file, but there is no enforced minimum. For a small
  application with this much behavioural testing, a threshold would be arbitrary. **Rejected.**
- **Tests are locale-sensitive by design.** `.github/instructions/tests.instructions.md`: "Dusk runs against
  `laravel_test` with a non-English default locale, so assert on translated text." The app default locale is `eu`
  (`config/app.php` via `APP_LOCALE=eu`). Consistent. **Rejected.**