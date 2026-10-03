# Business Logic Specialist Review — 2026-10-02

Specification of record: `.github/docs/architecture/ARCHITECTURE.md`.
Snapshot: HEAD `781ccbb` + working tree.

## Rule-by-rule coverage

| # | Documented rule | Satisfied | Evidence | Test covered |
|---|---|---|---|---|
| 1 | Customers/projects/epics/users have `active` boolean, default true | yes | `2026_10_02_180040_add_active...php:22-28`; `Customer.php:28-30`, `Project.php:29-31`, `Epic.php:28-30`; `users.active tinyint(1) NOT NULL DEFAULT 1` (live) | yes — `ModelSchemaParityTest:88` asserts casts exist in schema |
| 2 | Active lists show only active non-deleted; inactive lists only inactive non-deleted + last-modification date; trash shows every deleted record regardless of `active`; restore preserves the flag | yes | `CustomerListQuery.php:22,36,48`; `ProjectListQuery.php:29,47,64`; `EpicListQuery.php:33,56,72`; `extraDate` = `updated_at` (`CustomerListTransformer.php:93`); `restore()` sets only `deleted_at` (SoftDeletes) | yes — `ResourceActivationTest.php`, `CustomerListQueryTest.php` |
| 3 | Deactivation does not cascade to children | yes | `CustomerInactiveController.php:31-32` etc. write one row only | yes — `ResourceActivationTest::test_deactivating_a_parent_does_not_cascade_to_children:396` |
| 4 | Index rows offer deactivation instead of deletion when a child exists, **including deleted children** | yes | `CustomerListTransformer.php:43` `$customer->projects_exists`; `ProjectListTransformer.php:49` `$project->epics_exists`; both populated by `withExists([... withoutGlobalScope(SoftDeletingScope::class)])` (`CustomerListQuery.php:23`, `ProjectListQuery.php:30`) | yes — `ResourceActivationTest::test_childless_parents_keep_the_delete_action:381` |
| 5 | Names unique among non-deleted incl. inactive; soft-deleted names reusable; restore rejected on conflict | yes | DB: `customers_active_name_unique` on stored generated col (live `SHOW CREATE TABLE`); `CustomerTrashController.php:35-47` | yes — `CustomerTrashTest.php`, race tests at `:108` |
| 6 | Projects require one customer; nullable end date cannot precede required start date | yes | `ProjectRequest.php:45-51` (`required`, `after_or_equal:start_date`, `Rule::exists(...)->whereNull('deleted_at')`); `projects.customer_id bigint unsigned NOT NULL` + FK (live) | yes — `ProjectInputValidationTest.php` |
| 7 | Customers with projects cannot be trashed or force-deleted; projects with epics likewise | yes | `CustomerController.php:86`, `CustomerTrashController.php:61`, `ProjectController.php:74`, `ProjectTrashController.php:62` — all use `withTrashed()` | yes — `CustomerTrashTest.php:147`, `ProjectTrashTest.php:73` |
| 8 | Epic names unique per project among non-deleted; dates optional but end requires start and must be later | yes | DB: `epics_project_id_active_name_unique (project_id, active_name)`; `EpicRequest.php:41-47` (`required_with:end_date`, `after:start_date`) | yes — `EpicInputValidationTest.php`, `EpicCrudTest.php:175` |
| 9 | Epic comments removed when the epic is permanently deleted; keep a null author when the user is deleted | yes | `epic_comments.epic_id ... ON DELETE CASCADE`, `user_id ... ON DELETE SET NULL` (live `SHOW CREATE TABLE`); `EpicListTransformer.php:58` `?? __('Deleted user')` | yes — `EpicTrashTest::test_permanently_deleting_an_epic_removes_its_comments:102` |
| 10 | On create, if the name is in the trash, offer create-new or restore | yes | `CustomerController.php:35-56`, `ProjectController.php:36-48`, `EpicController.php:35-48`; modal `resources/views/components/name-conflict-modal.blade.php` | yes — `*TrashTest`, `CustomerCrudTest` |
| 11 | Epic comments carry author + creation time, added from the edit drawer | yes | `epic_comments.user_id`, `created_at` (live); `resources/views/epics/form.blade.php:57-84` | yes — `EpicCommentTest.php` |
| 12 | Inactive users cannot authenticate via password, 2FA, passkey or remember-me; a web middleware logs out existing sessions | yes | `FortifyServiceProvider.php:44-47` (authenticateUsing), `:62-81` (Login listener), `EnsureUserIsActive.php:23-30`, `bootstrap/app.php:17-21` | yes — `InactiveUserTest.php` covers all five paths |

**All 12 documented rules are implemented and tested.** The findings below are inconsistencies *between the three
resources*, not violations of the specification.

## BUS-001 — Inactive parents are offered in create/edit selects, contradicting the on-screen copy

Severity: MEDIUM
Category: Business Logic
File: app/Http/Controllers/ProjectController.php
Line: 28
Confidence: HIGH

Problem:

`ProjectController::index()` offers **every** non-deleted customer as a project parent, including inactive ones:

```php
'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),
```

`EpicController::index()` does the same for projects (line 28). Neither applies `where('active', true)`. Meanwhile the
list views display a callout that promises the opposite:

```blade
{{-- resources/views/projects/list.blade.php:90-94 --}}
@if ($list['create'] && $availableCustomers->isEmpty())
    <flux:callout icon="exclamation-triangle" variant="warning">
        {{ __('No active customers are available. Create one from the project form.') }}
    </flux:callout>
@endif
```

So the UI says "no **active** customers are available" while the query happily returns inactive ones.

Evidence:

- `ProjectController.php:28`, `EpicController.php:28` — no `active` filter.
- Live data proves inactive parents are offered: `SELECT id, name, active FROM customers` returns
  `id=2, name='Bezero 2', active=0`, and `projects.id=1` has `customer_id=2`. That inactive customer is currently
  selectable when creating a project.
- `EpicListQuery.php` / `ProjectListQuery.php` *do* filter `active` for their own list queries
  (`ProjectListQuery.php:29` `where('projects.active', true)`) — so the lists and the selects disagree.
- `ARCHITECTURE.md:53-54` establishes `active` as a first-class flag distinguishing active from inactive records,
  and `:57` "Deactivation does not cascade to children" — which means an inactive customer keeps its projects, so it
  is a realistic state, not a theoretical one.

Impact:

A user can create a project attached to a customer they have just deactivated, or attach an epic to an inactive
project. Nothing rejects it server-side either — `ProjectRequest.php:47-51` and `EpicRequest.php:48-52` validate
`Rule::exists(...)->whereNull('deleted_at')`, which excludes trashed parents but says nothing about `active`.

The requirement itself is already written down by the author in `todo.md` (last item): *"si el formulario se abre en
modo create, en el select solo se mostrarán los que tengan active=1; si se abre en modo edición, se mostrará el
elemento seleccionado (tenga o no active=1) y el resto solo los active=1"*. So this is **known, accepted, planned
work** — which is why it is MEDIUM and not HIGH. Reporting it so it is tracked rather than rediscovered.

Recommendation:

Two-step, matching `todo.md`:

1. Filter the select query: `Customer::query()->where('active', true)->orderBy('name')->get(...)` (and the same for
   projects). This alone fixes the mismatch between the callout copy and the data.
2. For the edit case, merge the currently-selected parent back into the collection so an inactive parent already
   attached to a record stays visible and editable.

Step 1 is a two-line change; step 2 belongs with the rest of the `todo.md` item.

## BUS-002 — Customer and Project name validation messages/rules differ from Epic in `min` ordering only

Severity: INFO (not a defect — recorded after checking, to prevent a false positive)
Category: Business Logic / Consistency
File: app/Http/Requests/CustomerRequest.php
Line: 35-43
Confidence: HIGH

Problem:

None. A previous review flagged this; on re-reading it is consistent. All three name rules are identical in content:

```php
// CustomerRequest.php:35-43   'required','string','max:255','min:4', + unique
// ProjectRequest.php:36-44    'required','string','min:4','max:255', + unique
// EpicRequest.php:36-45       'required','string','min:4','max:255', + unique(+project_id)
```

Only the *order* of `min:4` and `max:255` differs, which has no behavioural effect.

Evidence: quoted above; all three validated by `tests/Feature/{Customers,Projects,Epics}/*InputValidationTest.php`.

Impact:

None.

Recommendation:

None. **Rejected as a finding.**

## BUS-003 — Date rule for `end_date` is intentionally different between Project and Epic, and correctly so

Severity: INFO (not a defect — recorded after checking)
Category: Business Logic / Consistency
File: app/Http/Requests/ProjectRequest.php
Line: 46
Confidence: HIGH

Problem:

None, once the specification is applied.

- `ProjectRequest.php:46` → `'after_or_equal:start_date'` (end date **may equal** start date)
- `EpicRequest.php:47` → `'after:start_date'` (end date must be **strictly later**)

Evidence: `ARCHITECTURE.md:64` — "Projects require one customer and have a nullable end date that cannot precede the
required start date" (`>=`, so `after_or_equal`), versus `:66-67` — "Epic ... an end date requires a start date and
must be later than it" (`>`, so `after`). The code matches the specification exactly. The client-side mirrors agree:
`projects/form.blade.php:27` `x-bind:min="form.start_date"` vs `epics/form.blade.php:46`
`x-bind:min="window.addDays(form.start_date, 1)"`.

Impact:

None. The divergence is intentional and is documented.

Recommendation:

None. **Rejected as a finding.**

## BUS-004 — Epic comment visibility is not scoped to the epic's own list state

Severity: LOW
Category: Business Logic
File: app/Transformers/EpicListTransformer.php
Line: 45-60 (committed HEAD)
Confidence: MEDIUM

Problem:

Comments are embedded only in the **active** epic list payload. The inactive and trash list payloads include
`commentsCount` (via `withCount('comments')`) but no comment bodies and no `commentAction`, and the epic edit drawer
is only rendered when `$list['create']` is true (`resources/views/epics/list.blade.php:172`), which is only for the
active list. So this is coherent.

Evidence:

```php
// EpicListTransformer::active() only
'commentAction' => route('epics.comments.store', $epic),
'commentsCount' => (int) $epic->comments_count,
'comments' => $epic->comments->map(...)->all(),
```

`EpicListTransformer::inactive()` (line ~90) and `trash()` (line ~135) have no `commentAction`.

Impact:

None today. Recorded because `ARCHITECTURE.md:26-27` describes comments as a feature of the epic edit drawer, and a
future reader could reasonably expect them everywhere `commentsCount` is shown.

Recommendation:

None now. If the drawer is ever extended to the inactive list, `commentAction` must be added alongside it, and the
`EpicPolicy::comment` ability (`app/Policies/EpicPolicy.php:45`) already authorises it.

## BUS-005 — `restore()` conflict message says "active" but the check covers inactive records too

Severity: LOW
Category: Business Logic / Copy
File: app/Http/Controllers/CustomerTrashController.php
Line: 73-74
Confidence: HIGH

Problem:

The restore-conflict copy describes a narrower condition than the code checks:

```php
return to_route('customers.trash.index')
    ->with('error', __('Customer cannot be restored while another active customer uses this name.'));
```

The guard above it (`CustomerTrashController.php:35`) is:

```php
if (Customer::query()->where('name', $customer->name)->exists()) { ... }
```

`Customer::query()` excludes soft-deleted rows but includes **inactive** ones. So restoring a customer is blocked by
an *inactive* customer holding the name, while the message tells the user the blocker is an "active" one.

Evidence:

- `CustomerTrashController.php:35` vs `:74`.
- Same wording at `ProjectTrashController.php:75` and `EpicTrashController.php:74` ("another active project/epic").
- `ARCHITECTURE.md:61` — "customer and project names are unique among non-deleted records, **including inactive
  records**" — so the code is right and the copy is wrong.

Impact:

Confusing error message in a real scenario: an inactive customer (which the app can create — see BUS-001) will block a
restore and the user is told to look for an active one. All three resources share the wording, so all three are wrong
in the same way.

Recommendation:

Change the string to "another existing customer" (or drop "active") in all three trash controllers, and add the new
key to all four `lang/*.json` files — the project has a parity unit test (`tests/Unit/Translations/TranslationFilesTest.php`)
that will fail if a locale is missed.

## BUS-006 — `EpicCommentController::store` redirects to `back()`, which after a comment lands on whatever page the drawer was opened from

Severity: LOW
Category: Business Logic / UX
File: app/Http/Controllers/EpicCommentController.php
Line: 17
Confidence: MEDIUM

Problem:

```php
return back(fallback: route('epics.index'))
    ->with('status', __('Comment added successfully.'))
    ->with('commented_epic_id', $epic->id);
```

The comment form is rendered inside a modal on the epics index, so `back()` is normally correct. But the `fallback`
means that if the form is submitted without a referrer (or from a stale tab), the user lands on `epics.index`
without the drawer being re-opened. The `commented_epic_id` session key is then unused.

Evidence:

- `EpicCommentController.php:17-19`.
- The consuming logic is `resources/views/epics/list.blade.php:8-12`:
  `$commentedEpic = $list['create'] ? $findEpicPayload(session('commented_epic_id') ?? ...) : null;`
  which then re-opens the drawer at line 95. This only works if the redirect target is the epics index.
- `tests/Feature/Epics/EpicCommentTest.php` `test_comment_body_is_required` uses `->from(route('epics.index'))`, i.e.
  the tests always supply the referrer, so the fallback path is untested.

Impact:

Low. A comment submitted without a referrer still saves correctly; only the UX (drawer not re-opened) degrades.
No data is lost and no error occurs.

Recommendation:

Make the redirect unconditional — the comment form only exists on the epics index, so `back()`'s flexibility buys
nothing:

```php
return to_route('epics.index')->with('status', ...)->with('commented_epic_id', $epic->id);
```

This also removes an untested branch. Note the working tree is already refactoring this area, so coordinate rather
than collide.

## Notes / not findings (rejected after challenge)

- **Soft-deleted names reusable** (`ARCHITECTURE.md:63`). Verified correct: the generated column returns `NULL` when
  `deleted_at IS NOT NULL`, and MySQL/MariaDB unique indexes permit multiple `NULL`s. Live schema confirms
  `Null: YES` on `customers_active_name_unique`. Rejected as a finding — this is a subtle design that works.
- **"Index rows offer deactivation instead of deletion"** (`ARCHITECTURE.md:58`). Verified: `withExists` with
  `withoutGlobalScope(SoftDeletingScope::class)` counts trashed children, so a customer with only a trashed project
  still shows "Deactivate", never "Delete". Correct and tested. Rejected.
- **Epic has no "deactivate instead of delete" branch.** Correct — epics have no children that block deletion, and
  their comments cascade on force delete. Rejected.
- **`reuse_deleted_name` flag semantics.** The modal submits `reuse_deleted_name=1` to the *store* route and
  `resolve_name_conflict=1` to the *restore* route (`name-conflict-modal.blade.php:27,35`). Both are declared in the
  respective Form Requests (`CustomerRequest.php:44`, `CustomerRestoreRequest.php:23`). Coherent. Rejected.
- **No cascade on delete of an inactive parent.** `ARCHITECTURE.md:57` forbids it; the code does not do it. Rejected.