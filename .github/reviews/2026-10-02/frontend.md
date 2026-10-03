# Frontend Specialist Review — 2026-10-02

Snapshot: HEAD `781ccbb` + working tree. Stack: Blade + Alpine (bundled by Laravel) + Tailwind 4 + Vite 8 /
`vite-plus` 0.3, plus jQuery 3.7 + Select2 4 for the project form's customer dropdown. No Vue, no Inertia, no React.

## FE-001 — The inline customer creation in the project form trusts a server error message it does not validate

Severity: LOW
Category: Frontend / Error handling
File: resources/js/app.js
Line: 330-344
Confidence: MEDIUM

Problem:

The Select2 `createTag` flow lets the user type a customer name that does not exist and creates it on selection:

```js
const response = await fetch(element.dataset.customerStoreUrl, { method: 'POST', ... });
const result = await response.json();

if (!response.ok) {
    throw new Error(result.errors?.name?.[0] ?? result.message ?? element.dataset.createError);
}
```

Two assumptions are made without checking: that the response body is JSON, and that the failure message has a usable
shape. Both hold against the current controller (`CustomerController::store` returns
`response()->json(['message' => ..., 'errors' => ['name' => [...]]], 409)` at lines 41-48, and
`response()->json($customer->only(['id','name']), 201)` at line 65), and a Laravel validation failure under
`Accept: application/json` also returns `{message, errors}`. The Dusk test
`tests/Browser/Projects/ProjectCustomerSelectTest.php` exercises the happy path.

Evidence:

- `resources/js/app.js:318-344` — the fetch and its `catch`.
- `app/Http/Controllers/CustomerController.php:41-48` — the 409 JSON shape the code depends on.
- `app/Http/Controllers/CustomerController.php:60-62` — the `catch (QueryException)` path, which throws
  `ValidationException`; `bootstrap/app.php:24-26` renders that as JSON because the request `expectsJson()`.
- Note the asymmetry: only `CustomerController::store()` has the JSON branch. `ProjectController::store()` and
  `EpicController::store()` do not (see LAR-004), so the identical Select2 pattern would break if reused there.

Impact:

If the endpoint ever returned HTML (a 500 page, a redirect), `await response.json()` rejects with a
`SyntaxError` whose message is not user-facing, and the user sees `element.dataset.createError` =
"Unable to create customer." — which is a reasonable fallback, so the failure mode is graceful.
Not a security issue: the message is rendered with `x-text` (`projects/form.blade.php:49-50`), never `innerHTML`.

Recommendation:

Keep the fallback; optionally distinguish a transport failure from a validation failure so the UI can say which.
Low priority — the current behaviour is acceptable.

## FE-002 — The list-fragment refresh replaces the whole results subtree via `Alpine.morph`

Severity: LOW
Category: Frontend / Correctness
File: resources/js/app.js
Line: 462-477
Confidence: MEDIUM

Problem:

```js
const template = document.createElement('template');
template.innerHTML = await response.text();
const results = template.content.querySelector('[data-list-results]');
...
window.Alpine.morph(this.$root, results.outerHTML);
```

The response body (server-rendered HTML for the authenticated user) is parsed via `innerHTML` into an inert
`<template>` and then morphed into the live DOM. `template.content` is a document fragment with scripting disabled,
so parsing does not execute anything, and the content is same-origin, first-party, escaped Blade output — there is
no injection vector here.

Evidence:

- `resources/js/app.js:453-457` — the fetch sends only `headers: { 'X-List-Fragment': 'true' }`, no user-controlled URL;
  the URL is built from the form action and `searchParams` (lines 433-443).
- `resources/views/**` — verified no `v-html`, no `{!! !!}`, no `innerHTML`; all user data uses `x-text` or `{{ }}`.
- `tests/Feature/ListSearchFragmentTest.php:13-33` asserts every fragment route returns only the
  `[data-list-results]` subtree for a fragment request.

Impact:

One behavioural consequence worth naming: morphing the subtree discards any scroll position and any transient UI
state inside it (e.g. an open modal trigger's tooltip). Because the create/edit modals live *outside* the
`@fragment('list-results')` block (`resources/views/projects/list.blade.php:98-146` — fragment closes at 146, modals
at 149+), the drawer is preserved across a search. That is correct by construction and is why the fragment boundary
is placed where it is.

Recommendation:

None. The design is deliberate and correctly bounded. Recorded so the constraint (modals must stay outside the
fragment) is known to whoever edits these views next.

## FE-003 — Search is debounced at 400 ms and gated at 4 characters, but the server accepts 1 character

Severity: INFO
Category: Frontend / UX consistency
File: resources/js/app.js
Line: 391-401
Confidence: HIGH

Problem:

The client only fires a search when `query.length > 3` or when clearing:

```js
if (query.length > 3
    || (query.length === 0 && (this.currentSearch !== '' || this.requestController))) {
    this.search(query);
    return;
}
```

and `submitSearch` (lines 403-413) explicitly refuses queries of 1-3 characters. Meanwhile the Form Request accepts
any non-empty string up to 255 (`CustomerListRequest.php:21` → `['nullable','string','max:255']`), so a direct
`?search=ab` request works but the UI cannot produce one.

Evidence: `resources/js/app.js:391-396` and `:407-410`; `app/Http/Requests/CustomerListRequest.php:21`.

Impact:

None functional. It is a deliberate minimum-query-length policy, and it prevents a full table scan per keystroke.
But it is invisible: a user who types "ac" and waits sees nothing happen and gets no explanation.

Recommendation:

Optional. If desired, add a hint in the search placeholder or leave as-is. Not worth a placeholder string that would
need to be added to four locales.

## FE-004 — Dirty-form tracking is implemented once in JS and used by all three forms via a shared Blade component

Severity: INFO (positive finding)
Category: Frontend / Design
File: resources/views/components/forms/tracked-resource.blade.php
Line: 14-18
Confidence: HIGH

Problem:

None. This is the correct shape for the duplication described in CLEAN-001: the shared logic lives in one component
and one JS module, and the three forms only supply the fields.

Evidence:

- The component owns CSRF, `_method`, the context/id hidden fields, the double-submit guard and the footer
  (`tracked-resource.blade.php:14-38`), exactly as `.github/instructions/views.instructions.md` prescribes.
- `resources/js/app.js:9-104` implements `trackedFormValues`, `trackForm`, `resetTrackedForms`, `dirtyTrackedForms`
  generically via a `WeakMap` and a `data-track-changes` attribute lookup — no per-resource code.
- `resources/js/app.js:126-140` intercepts `[data-flux-modal-close]` globally, and `:142-146` does the same for
  `livewire:navigate`, so navigation guards work for the whole app rather than per view.
- `tests/Browser/Settings/ProfileDirtyFormTest.php` and `tests/Browser/Customers/CustomerCrudTest.php` cover it.

Impact:

None.

Recommendation:

None. This is the pattern the list views in CLEAN-001 should be brought in line with.

## FE-005 — `select2` and `jQuery` are loaded globally for a single dropdown

Severity: LOW
Category: Frontend / Payload
File: resources/js/app.js
Line: 1-5
Confidence: MEDIUM

Problem:

```js
import $ from 'jquery';
import select2 from 'select2';
window.$ = window.jQuery = $;
select2(window, $);
```

jQuery (~90 KB min+gzip) and Select2 are pulled into the single main bundle and attached to `window`, used only by
`initializeProjectCustomerSelect` for the project form's customer field. Every page — dashboard, customers list,
epics list, auth pages — pays for them.

Evidence:

- `resources/js/app.js:1-5` — unconditional imports and `window` assignment.
- `resources/views/partials/head.blade.php:14` — `@vite(['resources/css/app.css', 'resources/js/app.js'])`, i.e. one
  bundle for the whole app.
- The only consumer is `window.initializeProjectCustomerSelect` (line 266), invoked from
  `resources/views/projects/form.blade.php:34-42` via the `data-project-customer-select` attribute.
- The build output exists locally (`public/build/manifest.json`), so the bundle is real.

Impact:

Real but small: this is a single-tenant internal tool where the users are already authenticated and on a LAN or
localhost. The cost is a larger main bundle and two global library attachments on pages that never use them. No
measured bundle sizes were taken, so I am not quantifying the bytes.

Recommendation:

If bundle size ever matters, lazy-load Select2 only where the attribute is present (dynamic `import()` inside
`initializeProjectCustomerSelect`). **Do not** do this now — there is no measurement showing a user-visible problem,
and the project's rules forbid speculative optimisation.

## Notes / not findings (rejected after devil's-advocate challenge)

- **`x-on:click="{{ $createClick }}"`** in `list/header.blade.php:33` — raw expression prop. Recorded as CLEAN-002
  (LOW), not as an XSS finding: all three call sites are literal repository strings and the value is HTML-escaped.
- **Comment bodies rendered with `x-text`** (`epics/form.blade.php:111`) — correct, no XSS. Recorded in security.md
  as SEC-006 so it is not re-litigated.
- **`@js()` for Alpine payloads** — `Illuminate\Support\Js::from()` escapes for both HTML attribute and JS-string
  contexts. Used correctly throughout (`epics/list.blade.php:41`, `form.blade.php:99`). **Rejected.**
- **A11y** — the tables use `<flux:table>` semantics, icon buttons carry `:aria-label`, pagination is wrapped in
  `<nav aria-label>` (`list/table.blade.php:11`), and the confirm modal has a heading. Two icon buttons in
  `list/header.blade.php:38-42` rely on `<flux:tooltip>` plus `:aria-label`. Adequate. **Rejected** — reporting
  speculative a11y improvements would be exactly the "personal preference presented as a defect" the rules forbid.
- **Flux/Vite fragments** (`@fragment('list-results')`, `View::fragment()`) — a Laravel 13.17 feature used
  consistently across all three resources and covered by `ListSearchFragmentTest`. **Rejected.**
- **No `npm run lint` / `npm run typecheck`.** `package.json:6-9` defines only `build` and `dev`. No linter or
  typechecker is configured for the frontend, so those checks are **NOT APPLICABLE** rather than failing.
  `npm run build` was **NOT RUN** in this review (it would regenerate `public/build`, i.e. modify generated assets).
- **Missing `devDependencies` split** — everything is in `dependencies`. For a private app where all JS ships to the
  browser, this is harmless. **Rejected.**
- **Pint formatting of Blade** — irrelevant, Pint is PHP-only.