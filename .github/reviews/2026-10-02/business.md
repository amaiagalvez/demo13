# Business Rules Review — 2026-10-02

Authoritative spec: `.github/docs/architecture/ARCHITECTURE.md`. Every rule it states was
re-derived from the code.

## Documented rules verification

| # | Documented rule (`ARCHITECTURE.md`) | Code | Result |
|---|---|---|---|
| 1 | Customer/project names unique among non-deleted records, including inactive | `Rule::unique(...)->whereNull('deleted_at')` + `*_active_name_unique` generated-column index | VERIFIED |
| 2 | Epic names unique per project among non-deleted epics, including inactive | `EpicRequest:41-45` + `epics_project_id_active_name_unique` | VERIFIED |
| 3 | Soft-deleted names may be reused | Generated column is `NULL` when `deleted_at IS NOT NULL`, and MySQL/SQLite unique indexes allow multiple `NULL`s | VERIFIED |
| 4 | Restoring into a taken name is rejected with a conflict message | `*TrashController::restore` pre-check + `catch` fallback → `restoreConflictResponse()` | VERIFIED |
| 5 | Restore preserves the `active` flag | `restore()` only nulls `deleted_at`; transformers branch on `$model->active` for the confirm copy | VERIFIED |
| 6 | Project `end_date` cannot precede `start_date` | `ProjectRequest:46` `after_or_equal:start_date` | VERIFIED |
| 7 | Epic dates optional; `end_date` requires `start_date` and must be later | `EpicRequest:46-47` `required_with:end_date` + `after:start_date` | VERIFIED |
| 8 | Customers with projects cannot be trashed or permanently deleted (incl. trashed children) | `CustomerController:86`, `CustomerTrashController:61` — both `withTrashed()->exists()` | VERIFIED (race window noted separately) |
| 9 | Projects with epics (incl. trashed epics) cannot be trashed or permanently deleted | `ProjectController:74`, `ProjectTrashController:62` | VERIFIED (race window noted separately) |
| 10 | Epic comments removed when the epic is force-deleted | FK `epic_comments.epic_id ON DELETE CASCADE` | VERIFIED |
| 11 | Comments keep a null author when the user is deleted | FK `epic_comments.user_id ON DELETE SET NULL` + `EpicCommentFactory` | VERIFIED |
| 12 | Deactivation does not cascade to children | Inactive controllers only touch the path model; `ResourceActivationTest:409-424` asserts it | VERIFIED |
| 13 | Index rows offer deactivate instead of delete when children exist | `CustomerListTransformer:43`, `ProjectListTransformer:49` branch on `projects_exists` / `epics_exists` | VERIFIED |
| 14 | Active/inactive/trash lists include the right rows | `CustomerListQuery` / `ProjectListQuery` / `EpicListQuery` `active()`/`inactive()`/`trashed()` | VERIFIED |
| 15 | Inactive users cannot authenticate and existing sessions are revoked | `FortifyServiceProvider::configureActiveUsers()` + `EnsureUserIsActive` + 9 tests | VERIFIED |
| 16 | Every authenticated verified user may manage every resource | All three policies `return true` | VERIFIED (documented product decision) |

## Findings

### BIZ-001 — Inactive customers and projects are offered in the selects, contradicting the on-screen warning

Severity: MEDIUM
Category: Business logic / Correctness
File: app/Http/Controllers/ProjectController.php, app/Http/Controllers/EpicController.php,
      resources/views/projects/list.blade.php, resources/views/epics/list.blade.php
Line: 28 / 28 / 92 / 106
Confidence: HIGH

Problem: both list pages show a warning that says "No **active** … are available", but the option
lists that decide whether to show it are *not* filtered on `active`, so deactivated records are
selectable.

Evidence:

```php
// app/Http/Controllers/ProjectController.php:28  — no active filter
'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),

// app/Http/Controllers/EpicController.php:28     — no active filter
'availableProjects' => Project::query()->with('customer')->orderBy('name')->get([...]),
```

```blade
{{-- resources/views/projects/list.blade.php:90-93 --}}
@if ($list['create'] && $availableCustomers->isEmpty())
    <flux:callout icon="exclamation-triangle" variant="warning">
        {{ __('No active customers are available. Create one from the project form.') }}
```

The validation layer agrees with the query, not with the copy:

```php
// app/Http/Requests/ProjectRequest.php:47-51  and  EpicRequest.php:48-52
'customer_id' => ['required', 'integer', Rule::exists(Customer::class, 'id')->whereNull('deleted_at')],
'project_id'  => ['required', 'integer', Rule::exists(Project::class, 'id')->whereNull('deleted_at')],
```

Impact: a project can be created under a deactivated customer and an epic under a deactivated
project. Because deactivation does not cascade, the result is a child that the parent list hides.
The user sees a warning that talks about "active" records while the form happily offers inactive
ones, which is the confusing part.

Note: **this is already a known, tracked requirement.** `todo.md` contains

```
[] en los select hay dos opciones:
    1. si el formulario se abre en modo create, en el select o select2 solo se mostrarán los que tengan active=1
    2. si el formulario se abre en modo edición, … se mostraran el elemento seleccionado (tenga o no active=1) y el resto … solo los que tengan acitive=1
```

Recommendation: do not re-plan it here. Implement `todo.md`'s rule (create ⇒ only `active = 1`;
edit ⇒ keep the current selection even if inactive, plus the active ones) and align the two
callout strings with whatever that rule ends up being. The smallest consistent intermediate step
is to add `->where('active', true)` to the two option queries and drop the word "active" from the
copy — but that would hide an inactive parent from an edit form, which is exactly what `todo.md`
item 2 says not to do. Treat the two as one task.

## Verified clean (no findings)

- **`reuse_deleted_name` is not dead code.** `resources/views/components/name-conflict-modal.blade.php:27`
  posts `reuse_deleted_name=1` to `*.store`, which makes `CustomerController:40` /
  `ProjectController:41` / `EpicController:41` skip the trash-name prompt and create a **new**
  record; the trashed one is untouched. The test asserts both outcomes.
- **`resolve_name_conflict` is not dead code.** `name-conflict-modal.blade.php:35` posts it with the
  "restore instead" button, and `*TrashController::restore:49-53` uses it to pick the more explicit
  success message. Asserted in `ProjectTrashTest:87-103`.
- **Naming conflict modal round-trip.** `customers/list.blade.php:140` and `epics/list.blade.php:184-189`
  replay `old()` input (including `start_date`, `end_date`, `project_id`) so the user does not retype
  the form after choosing "create a new one".
- **Comment drawer rehydration after posting a comment is consistent.**
  `resources/views/epics/list.blade.php:4-13` looks the epic up inside `$list['rows']`, which is
  always the page the request came from, because `EpicCommentController::store:17` returns `back()`
  and Laravel restores the previous URL including `?page=`. No orphaned state.
- **Reachable inconsistent states** were enumerated and are limited to the CONC-001 race (a child
  whose parent is soft-deleted). Everything else is prevented by the soft-delete guards or the FKs.
- **i18n.** All 131 keys exist in `en/es/eu/fr` with identical placeholders; enforced by
  `tests/Unit/Translations/TranslationFilesTest.php`. One orphan key is reported under
  `maintainability.md` MAINT-001.