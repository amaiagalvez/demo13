# Clean Code / Maintainability Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree. Files changed concurrently during the review; findings below are against the
code as read, and file:line references are pinned to what was on disk.

## CLEAN-001 — The three list views are ~90% identical (carried over from 2026-09-30, still open)

Severity: MEDIUM
Category: Clean Code / Duplication
File: resources/views/epics/list.blade.php
Line: 1 (whole file, ~197 lines)
Confidence: HIGH

Problem:

`customers/list.blade.php`, `projects/list.blade.php` and `epics/list.blade.php` are near-copies differing only by the
prefix (`customer-`/`project-`/`epic-`), the column set, and the parent-payload shape. The Alpine `x-data` block alone
is duplicated three times: `form`, `confirmation`, `storeUrl`, `updateUrl`, `createX()`, `editX()`, `confirmAction()`.

Evidence — the duplicated Alpine state, verbatim in three files:

```js
// customers/list.blade.php:23-67, projects/list.blade.php:28-79, epics/list.blade.php:40-94
x-data="{
    form: @js($initialForm),
    confirmation: { action: '', method: 'DELETE', title: '', text: '', label: '', danger: false },
    storeUrl: @js(route('customers.store')),
    updateUrl: @js(route('customers.update', '__CUSTOMER__')),
    createCustomer() { ... },
    editCustomer(customer) { ... },
    confirmAction(action) { ... },
}"
```

Shared markup that is **already** componentised (`x-list.header`, `x-list.flash`, `x-list.search`,
`x-list.searchable-results`, `x-list.table`, `x-list.row-actions`, `x-list.confirm-modal`) sits *beside* ~80 lines
that are still copy-pasted per resource: the `@php $initialForm` block (lines 1-20 of each file), the whole
`x-data` block, and the modal wiring (lines 125-148 of each file).

Impact:

Every UX change to the drawer, the confirm modal or the dirty-tracking wiring must be made three times, and it has
already drifted — see CLEAN-002. This is the single largest maintainability risk in the codebase.

Recommendation:

Follow the precedent the project already established in `.github/instructions/views.instructions.md`
("Do not copy shared markup back into a view") and extend it:

- Extract the `x-data` block into one Blade component that owns the Alpine state and takes `prefix`, `form`,
  `storeUrl`, `updateUrl` plus create/edit handler names.
- Do **not** build a generic "list page" component. The column sets genuinely differ (3 / 5 / 7 columns) and the
  2026-09-30 review already made this call. Scope the extraction to the parts that are byte-identical.

P2 work. Not urgent, but it is what makes the next feature risky.

## CLEAN-002 — `x-list.header` renders `$createClick` unescaped into an `x-on:click` attribute

Severity: LOW
Category: Clean Code / Injection surface
File: resources/views/components/list/header.blade.php
Line: 33
Confidence: HIGH

Problem:

```blade
<flux:button size="sm" square variant="primary" icon="plus"
    :aria-label="$createLabel" x-on:click="{{ $createClick }}"
```

`$createClick` is a raw HTML attribute containing an Alpine expression. Blade's `{{ }}` escapes it for HTML, so it
cannot break out of the attribute — but the value is an **executable JavaScript expression assembled by the caller**,
and the component provides no validation of it. Compare with the same file's other props, which are plain data:

```blade
<flux:button :href="$list['navigation']['url']" ... wire:navigate :data-test="$list['navigation']['test']" />
```

Evidence:

- `resources/views/components/list/header.blade.php:33` — `x-on:click="{{ $createClick }}"`.
- The three call sites pass a hard-coded literal each: `create-customer-create-button` via
  `create-click="createCustomer()"` (`customers/list.blade.php:73-74`), `"createProject()"` (`projects/list.blade.php:87-88`),
  `"createEpic()"` (`epics/list.blade.php:101-102`).
- The compiled Blade output preserves the escape: `xOn:click' => e($createClick)`
  (confirmed by compiling the template in-container).
- Same pattern at `resources/views/components/list/row-actions.blade.php:15`
  — `x-on:click="{{ $editHandler }}(JSON.parse($el.dataset.payload))"`.

Impact:

No vulnerability today: all three call sites are literal PHP strings in the repository, and there is no path by which
a user can influence `$createClick`. The finding is that a component prop is a code-execution surface, and the next
person to pass a variable to it (e.g. a create-handler name built from a prefix) could introduce an injection bug
without noticing that this prop is different in kind from the others.

Recommendation:

Make the contract explicit with a PHPDoc `@param string $createClick Alpine expression` plus a one-line comment
stating it must be a literal, or rename the prop to `$createClickExpression` so the nature is obvious at the call site.
No behaviour change, no risk.

## CLEAN-003 — `UniqueConstraintViolation` is applied at 8 call sites with two different styles

Severity: LOW (downgraded from MEDIUM — see the devil's-advocate note in the consolidated report)
Category: Clean Code / Consistency
File: app/Http/Controllers/EpicController.php
Line: 52-54, 63-65
Confidence: HIGH

Problem:

Six store/update sites use the helper directly:

```php
try {
    Epic::create($request->validated());
} catch (QueryException $exception) {
    UniqueConstraintViolation::rethrowAsValidationError($exception);
}
```

(`CustomerController.php:60-62,74-77`, `ProjectController.php:51-54,61-65`, `EpicController.php:51-54,62-65`.)

Three restore sites use the lower-level predicate plus a manual branch, because they need a *different* response:

```php
try {
    $customer->restore();
} catch (QueryException $exception) {
    if (! UniqueConstraintViolation::causedBy($exception)) {
        throw $exception;
    }
    return $this->restoreConflictResponse();
}
```

(`CustomerTrashController.php:41-47`, `ProjectTrashController.php:42-48`, `EpicTrashController.php:47-53`.)

The 2026-09-30 review reported that Epics had a private `throwIfNotDuplicateName()` helper while the others did not;
that has since been resolved (all six are now identical). The remaining duplication is the three
`restoreConflictResponse()` private methods, which are byte-identical apart from the resource noun.

Evidence: quoted above, three files.

Impact:

Six copies of a four-line try/catch and three copies of a five-line private method. A future change to the
duplicate-name behaviour (different field, different message) touches up to 9 sites.

Recommendation:

**Accept it.** `.github/docs/architecture/ARCHITECTURE.md:122-123` explicitly rejects adding abstractions
("No repository, service, DTO, domain-layer, CQRS, or event-sourcing abstraction is justified by the current
application size"), and a trait or abstract controller for three resources would couple them against
`tests/Unit/ArchitectureTest.php`. Revisit only when a fourth resource appears. Recorded so the duplication is known,
not so it is refactored.

## CLEAN-004 — `EpicController::store` re-reads `project_id` from the request outside the validated payload

Severity: INFO (rejected as a finding — recorded so it is not re-raised)
Category: Clean Code
File: app/Http/Controllers/EpicController.php
Line: 36
Confidence: HIGH

Problem:

None.

```php
$deletedEpic = Epic::onlyTrashed()
    ->where('project_id', $request->integer('project_id'))
    ->where('name', $request->string('name')->toString())
```

Customer and Project use the same pattern (`CustomerController.php:35`, `ProjectController.php:35`), so this is
consistent. `$request->integer()`/`$request->string()` are safe accessors, and `EpicRequest.php:48-52` validates
`project_id` as `required|integer|exists` before the controller body runs.

Evidence: quoted above. `tests/Unit/ValidationCoverageTest.php:66-86` enforces that every `$request->string()`/
`$request->boolean()` key read in a controller has a matching rule in that method's Form Request — so this pattern is
actively guarded by the test suite and is intentional.

Impact:

None.

Recommendation:

None. **Rejected as a finding.**

## CLEAN-005 — `EpicListQuery` ordering logic is duplicated verbatim across `active()` and `inactive()`

Severity: LOW
Category: Clean Code / Duplication
File: app/Queries/Epics/EpicListQuery.php
Line: 36-43, 55-62
Confidence: HIGH

Problem:

The nine-line ordering chain is repeated identically in `active()` (lines 36-43) and `inactive()` (lines 55-62):

```php
->orderByRaw('epics.start_date IS NULL')
->orderBy('epics.start_date')
->orderByRaw('epics.end_date IS NULL')
->orderBy('epics.end_date')
->orderBy('epic_projects.name')
->orderBy('epic_customers.name')
->orderBy('epics.name')
->orderBy('epics.id')
```

`ProjectListQuery` has the same shape (`active()` lines 31-35, `inactive()` lines 48-52) minus the null-last handling.

Evidence: quoted above; `ProjectListQuery.php:31-35,48-52` is the same pattern with `project_customers.name`.

Impact:

Low. Ordering is a UI decision that will rarely change, and the two copies are adjacent in the same class.

Recommendation:

Optional (P3). If extracted, do it as a single `private function orderByDatesThenNames(Builder $query): Builder` on
`EpicListQuery` — no new class, no interface.

## Notes / not findings (rejected after devil's-advocate challenge)

- **`x-list.header` multi-line PHP concatenation is CORRECT, not a bug.** My initial hypothesis was that
  `:name="$prefix.\n            '-form'"` embeds a literal newline in the modal name and would break the Flux trigger.
  **I verified this and it is false.** PHP's `.` operator ignores whitespace — including newlines — between operands,
  so the compiled value is exactly `"customer-form"`. Proof, executed in-container:

  ```
  $ php -r '$prefix = "customer";
  $name = $prefix.
              "-form";
  var_dump($name); var_dump($name === "customer-form");'
  string(13) "customer-form"
  bool(true)
  ```

  The formatting is merely ugly (very likely a formatter artifact). **Rejected as a finding.** If it is touched at
  all while doing CLEAN-001, collapse it to one line for readability — but it is not broken and is not worth an
  action-plan entry.
- **Form Requests are near-identical across resources** (`CustomerListRequest` / `ProjectListRequest` /
  `EpicListRequest` are 100% identical apart from the model in `authorize()`). Looks like duplication but is the
  **correct** design: each request must reference its own policy, and a shared base class would couple them.
  `tests/Unit/ArchitectureTest.php:142` requires each request to define public `rules()` and `authorize()`, which a
  base class would undermine. **Rejected.**
- **Models are appropriately thin** — `Customer.php`, `Project.php`, `Epic.php` declare only `#[Fillable]`,
  `#[UsePolicy]`, `casts()` and relationships, exactly as `.github/instructions/models.instructions.md` requires.
  No fat models. **Rejected.**
- **`Customer::$attributes = ['active' => true]` repeated in three models.** Could be a trait or a base model, but
  three lines of duplication is cheaper than an inheritance hierarchy for a 5-model app. **Rejected.**
- **Test-file regex-based architecture enforcement** (`ArchitectureTest.php:69,75,80` match source text). Brittle in
  principle, but it enforces real invariants and it is the project's chosen mechanism. **Rejected.**
- **Inline `@php` blocks at the top of the three list views.** Residual duplication from CLEAN-001; subsumed by it,
  not reported separately.