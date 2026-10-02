# Laravel Review — 2026-10-02

READ-ONLY. Stack: Laravel 13.17, PHP ^8.3 (Docker 8.4), Fortify 1.37, Livewire 4.1, Blade, MariaDB 11.7.

Areas reviewed: routing, middleware, Form Requests, policies, controllers, Eloquent, config, providers.

## Findings

### LAR-001 — List index actions never return a View, breaking view-data assertions

Severity: HIGH
Category: Laravel / Correctness
File: app/Http/Controllers/EpicController.php
Line: 30 (and the 8 sibling `index()` methods in `*Controller`, `*InactiveController`, `*TrashController`)
Confidence: HIGH

Problem: every `index()` declares `: View|string` but the returned expression
`view(...)->fragmentIf(...)` evaluates to a **string in both branches**, so the `View` half of the
union type is unreachable.

Evidence:

```php
// app/Http/Controllers/EpicController.php:26-30
return view('epics.list', [
    'epics' => $epics,
    'availableProjects' => Project::query()->with('customer')->orderBy('name')->get([...]),
    'list' => $transformer->active($epics, $search),
])->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results');
```

```php
// vendor/laravel/framework/src/Illuminate/View/View.php:114-121
public function fragmentIf($boolean, $fragment)
{
    if (value($boolean)) {
        return $this->fragment($fragment);   // string
    }

    return $this->render();                   // string
}
```

`TestResponse::viewData()` / `assertViewHas()` call `ensureResponseHasView()`, which fails with
"The response is not a view." because `$this->original` is a string.

Impact: one test is red (see TEST-001 in `testing.md`) and any future view-data assertion on a list
route fails. The declared type also misleads readers into thinking the two branches differ.

Recommendation (minimal, keeps the fragment feature):

```php
$view = view('epics.list', [...]);

return $request->hasHeader('X-List-Fragment')
    ? $view->fragment('list-results')
    : $view;
```

Apply the same shape to the nine `index()` methods; the declared type then stays `View|string`.

## Verified clean (no findings)

- **Middleware order.** `bootstrap/app.php:18-21` prepends `EnsureUserIsActive` before
  `AuthenticatesRequests` in the priority list (`Kernel::$middlewarePriority`,
  `vendor/.../Foundation/Http/Kernel.php:103-115`), so it runs before `auth`. `routes/web.php:17`
  adds `verified`. Covered by `tests/Feature/Auth/InactiveUserTest.php:122-130`.
- **Authorization coverage.** Every input endpoint has a Form Request whose `authorize()` delegates
  to the resource policy (`CustomerRequest:20-27`, `ProjectRequest:21-28`, `EpicRequest:21-28`,
  `*ListRequest:10-13`, `*RestoreRequest:12-15`, `EpicCommentRequest:25-28`). `deactivate`,
  `reactivate`, `destroy` and `forceDelete` call `$this->authorize()` explicitly.
  `tests/Unit/ArchitectureTest.php:88-121` enforces this by reflection and passes.
- **Route model binding scoping.** `{customer}`/`{project}`/`{epic}` bind non-trashed rows; trash
  routes take `int` and use `onlyTrashed()->findOrFail()`. `whereNumber()` guards all of them.
- **Validation completeness.** `tests/Unit/ValidationCoverageTest.php` fails the build if a fillable
  attribute has no rule or a controller reads an unvalidated key. It passes.
- **Config.** `config/database.php` `'strict' => true` for mysql/mariadb forces strict mode per
  connection via `PDO::MYSQL_ATTR_INIT_COMMAND`, so the server's `--sql-mode=""` in
  `docker-compose.yml` does not weaken validation.
- **`AppServiceProvider`.** `Date::use(CarbonImmutable::class)`, `DB::prohibitDestructiveCommands`,
  and production-only `Password::defaults()` are all conventional.
- **No jobs, events, listeners, notifications, mail or scheduled tasks** exist outside Fortify.
  `config/queue.php` defaults to `database`; `routes/console.php` only registers `inspire`.
- **No caches, no API routes, no broadcasting** in application code.

## Rejected hypotheses

- `EnsureUserIsActive` breaking `POST /login`: no session user exists before authentication, so
  the guard short-circuits. `InactiveUserTest` covers login, 2FA and passkeys.
- `Fortify::authenticateUsing()` bypassing rate limiting: Fortify's `EnsureLoginIsNotThrottled`
  still applies the `login` limiter defined in `FortifyServiceProvider::configureRateLimiting()`.
- `EpicRequest`'s `Rule::unique(...)->ignore($this->route('epic'))->where('project_id', ...)` being
  broken on update: `ignore()` accepts a model and resolves its key; the composite DB index
  `(project_id, active_name)` is re-evaluated on every update.