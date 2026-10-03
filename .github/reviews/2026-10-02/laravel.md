# Laravel Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb`, working tree contains uncommitted WIP (see note at the end).
Stack verified: Laravel 13.17, PHP 8.4 (Docker) / 8.3 (CI), Livewire 4.1, Flux 2.13, Fortify 1.37,
MariaDB 11.7, Blade + Alpine + Tailwind 4 + Vite 8. No queues, no API routes, no Redis/Horizon.

## LAR-001 — `View::fragmentIf()` destroys the View instance, breaking view-data assertions

Severity: HIGH
Category: Laravel / Correctness
File: app/Http/Controllers/CustomerController.php
Line: 29
Confidence: HIGH

Problem:

Every list `index()` controller returns `view(...)->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results')`.
`Illuminate\View\View::fragmentIf()` **always returns a rendered string**, never a `View` instance
(vendor/laravel/framework/src/Illuminate/View/View.php:114-121 — the `false` branch calls `$this->render()`).
The declared return type `View|string` is therefore a lie: the `View` arm is unreachable.

Evidence:

```php
// vendor/laravel/framework/src/Illuminate/View/View.php:114
public function fragmentIf($boolean, $fragment)
{
    if (value($boolean)) {
        return $this->fragment($fragment);   // string
    }
    return $this->render();                  // string
}
```

Committed HEAD (781ccbb) uses `fragmentIf` in all six list controllers:

- `git show HEAD:app/Http/Controllers/CustomerController.php` → line 29 `->fragmentIf(...)`
- same pattern in `CustomerInactiveController.php`, `CustomerTrashController.php`,
  `ProjectController.php`, `ProjectInactiveController.php`, `ProjectTrashController.php`,
  `EpicController.php`, `EpicInactiveController.php`, `EpicTrashController.php`.

Reproduced failure at HEAD (`docker compose exec laravel13 php artisan test --compact`):

```
Tests: 4 failed, 233 passed (1168 assertions)
FAILED Tests\Feature\Epics\EpicCommentTest > ... : AssertionFailedError The response is not a view.
  at tests/Feature/Epics/EpicCommentTest.php:77   ($response->viewData('list'))
FAILED Tests\Feature\ResourceActivationTest > inac... : AssertionFailedError The response is not a view.
  at tests/Feature/ResourceActivationTest.php:372 (assertViewHas)
```

Both assertions are standard Laravel test helpers that require a `View` in the response
(`TestResponse::assertViewHas()` → `assertView()`, which throws when the original content is not a view).

Impact:

- CI is RED: `composer ci:check` runs `@test:prepare` (which includes `types:check`) then `@test`, and 4 tests fail.
- Any future test that inspects view data on a list route will fail for the same reason; the failure message
  ("The response is not a view") does not point at the controller.
- The controllers render the view inside the controller action, so an exception in the view is thrown from the
  controller rather than during response preparation. This also bypasses the framework's view-exception handling path.

Recommendation:

Return the `View` when the request is not a fragment request; only render the fragment when the header is present.
The WIP already in the working tree does exactly this via a `Controller::listView()` helper — see the
"Working tree note" below. Keep that shape and make sure it is type-safe (see LAR-002).

## LAR-002 — `Controller::listView()` breaks the `view-string` contract (WIP, PHPStan level 9 error)

Severity: MEDIUM
Category: Laravel / Static analysis
File: app/Http/Controllers/Controller.php
Line: 20
Confidence: HIGH

Problem:

The in-progress fix introduces a shared helper typed `listView(Request $request, string $view, array $data): View|string`.
The `$view` parameter is a plain `string`, but `view()` expects `view-string|null`. Larastan level 9 rejects it.

Evidence:

`./vendor/bin/phpstan analyse --no-progress` (Docker, HEAD 781ccbb + working tree):

```
 ------ ----------------------------------------------------------------------
  Line   app/Http/Controllers/Controller.php
 ------ ----------------------------------------------------------------------
  20     Parameter #1 $view of function view expects view-string|null, string
         given.
         🪪  argument.type
 ------ ----------------------------------------------------------------------
 [ERROR] Found 1 error
```

Impact:

CI is still red: `composer test:prepare` runs `@types:check` before `@test`, so PHPStan fails first and the
suite never runs.

Recommendation:

Type the parameter as `view-string` in the PHPDoc (and keep `list<string, mixed>` for `$data`), which is what
Larastan needs. Alternatively narrow with `assert(is_string($view))`-style helpers — but the PHPDoc
`@param view-string $view` is the minimal, idiomatic fix and does not change runtime behaviour.

## LAR-003 — `EpicCommentController::index()` violates the project's own architecture test

Severity: MEDIUM
Category: Laravel / Architecture conformance
File: app/Http/Controllers/EpicCommentController.php
Line: 14
Confidence: HIGH

Problem:

The new JSON endpoint `epics.comments.index` is named `index`, and `tests/Unit/ArchitectureTest.php:27` lists
`'index'` in `FORM_REQUEST_ENDPOINTS`, requiring every controller `index()` to declare a `FormRequest`.
The new method type-hints only `Epic $epic`, so the architecture test fails.

Evidence:

`php artisan test --compact` (working tree):

```
Tests: 1 failed, 238 passed (1208 assertions)
FAILED Tests\Unit\ArchitectureTest > input endpoints declare a form requ…
  App\Http\Controllers\EpicCommentController::index must declare a FormRequest (user input must be validated)
  at tests/Unit/ArchitectureTest.php:115
```

`tests/Unit/ArchitectureTest.php:105` — `if (! in_array($method->getName(), self::FORM_REQUEST_ENDPOINTS, true)) { continue; }`
and `FORM_REQUEST_ENDPOINTS = ['index', 'store', 'update', 'restore', 'comment']`.

Impact:

CI red again. More importantly the rule is being weakened for a real reason: this endpoint reads **no** user
input (no query string, no body), so there is nothing to validate — the rule's intent is satisfied.

Recommendation:

Rename the action to something outside the guarded list (e.g. `show`) and keep the route name
`epics.comments.index`, OR make the architecture test's intent explicit. Renaming the method is the smaller
change and keeps the route/URL stable. Do **not** add an empty FormRequest just to satisfy the test.

## LAR-004 — Inconsistent JSON handling in `store()` between the three resources

Severity: LOW
Category: Laravel / Consistency
File: app/Http/Controllers/CustomerController.php
Line: 41
Confidence: HIGH

Problem:

`CustomerController::store()` branches on `$request->expectsJson()` and returns `409` with a JSON error body
(lines 41-48) and `201` on success (lines 64-66). `ProjectController::store()` and `EpicController::store()`
have no JSON branch at all and always redirect.

Evidence:

```php
// CustomerController.php:41
if ($request->expectsJson()) {
    $message = __('A deleted customer already uses the name :name.', ['name' => $deletedCustomer->name]);
    return response()->json(['message' => $message, 'errors' => ['name' => [$message]]], 409);
}
```

`ProjectController.php:33` — `public function store(ProjectRequest $request): RedirectResponse` (no JsonResponse arm).
`EpicController.php:33` — `public function store(EpicRequest $request): RedirectResponse` (no JsonResponse arm).

Impact:

Only Customers are created inline from the project form's select2 (`resources/js/app.js:318` posts JSON to
`customers.store`), so the asymmetry is currently harmless. It is a latent trap: the first time the project form
gets an inline-create, `ProjectController::store()` will return an HTML redirect to an `fetch()` caller and the JS
will fail with a JSON parse error at `app.js:328`.

Recommendation:

Acceptable as-is given the current UI. Make it explicit in `.github/instructions/http.instructions.md` that the
JSON branch belongs to resources that support inline creation, so it is a decision and not an oversight. Do not
add the branch to Projects/Epics speculatively.

## Notes / not findings

- `bootstrap/app.php` uses `prependToPriorityList(before: AuthenticatesRequests::class, prepend: EnsureUserIsActive::class)`.
  Verified against `Illuminate\Foundation\Http\Kernel::$middlewarePriority` (Kernel.php:103-115): `AuthenticatesRequests`
  is a real entry, so `EnsureUserIsActive` is correctly ordered before it. Not a finding.
- `UniqueConstraintViolation::rethrowAsValidationError()` uses `never` return type and is called in a `catch`
  without `return`. Correct — it always throws. Not a finding.
- Route model binding on `{customer}/{project}/{epic}` correctly excludes soft-deleted rows; the trash controllers
  deliberately use `int` + `onlyTrashed()->findOrFail()` instead. Verified consistent across all three resources.
- Fortify 1.37 + `authenticateUsing` + `Login` event listener in `FortifyServiceProvider.php:42-82` is a valid
  Laravel 13 pattern and is covered by `tests/Feature/Auth/InactiveUserTest.php`. Not a finding.

## Working tree note

At the time of this review the repository had **uncommitted modifications** (14 files) implementing a lazy-loaded
epic-comments JSON endpoint. HEAD `781ccbb` is the last commit. All findings above are stated against HEAD plus the
working tree as observed, and the concrete command output was captured live at 22:12–22:19 CEST on 2026-10-02.
The working tree keeps changing; re-run the checks before acting on LAR-002/LAR-003.