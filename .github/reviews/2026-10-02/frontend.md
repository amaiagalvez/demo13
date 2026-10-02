# Frontend Review — 2026-10-02

Blade + Alpine (via Flux) + Livewire 4, bundled by Vite 8 / vite-plus 0.3.0, Tailwind 4.
No Vue, no Inertia, no React.

## Findings

### FE-001 — The three list views are near-identical copies

Severity: LOW
Category: Frontend / Maintainability
File: resources/views/customers/list.blade.php,
      resources/views/projects/list.blade.php,
      resources/views/epics/list.blade.php
Line: whole files (~150 / ~180 / ~197 lines)
Confidence: HIGH

Problem: the Alpine `x-data` object (create/edit/confirm handlers, `storeUrl`, `updateUrl`),
the `@fragment('list-results')` wrapper, the `x-list.search` + `x-list.table` composition, the
error/conflict-modal bootstrap and the trailing `<x-list.confirm-modal>` are duplicated three times
with only the resource prefix and the column set changing. The two "no records available" callouts
and the `commented_epic_id` rehydration in the epics view are the only real divergences.

Impact: any UX or accessibility change to the search/confirm/row-action flow has to be applied and
re-verified three times. Already materialised as BIZ-001 (the two callouts drifted apart).

Recommendation: extract the shared Alpine component and the fragment wrapper, following the
precedent already set by `resources/views/components/list/*` and
`resources/views/components/name-conflict-modal.blade.php` — i.e. one more anonymous Blade
component with a `prefix` prop. Do **not** build a generic "list view" component: the columns and
the search form genuinely differ, and the project's own rules forbid that kind of speculative
abstraction. Treat it as cleanup, not as a prerequisite.

### FE-002 — Orphan translation key

Severity: LOW
Category: Frontend / i18n hygiene
File: lang/en.json, lang/es.json, lang/eu.json, lang/fr.json
Line: 47 in each file
Confidence: HIGH

Problem: `"No active customers available."` is present in all four locale files but referenced by
no view, controller or PHP string (`grep -rn "No active customers available\."` matches only the
`lang/*.json` files). The longer, actually-used key
`"No active customers are available. Create one from the project form."` lives at line 74.

Impact: four files carry a dead string; `TranslationFilesTest` only compares keys across locales,
so orphans are invisible.
Recommendation: delete the key from the four files. Optionally add a unit test that asserts every
key in `lang/en.json` is referenced somewhere in `resources/` or `app/` — that is the only way to
stop this class of drift returning.

## Verified clean (no findings)

- **No unescaped output of user data.** The single `{!! !!}` in the tree is Fortify's
  `$qrCodeSvg` (`resources/views/pages/settings/⚡two-factor-setup-modal.blade.php:225`).
- **`@js()` is used for every Alpine payload** carrying user data
  (`customers/list.blade.php:24,33,34`, `epics/list.blade.php:41,50,51,95`), so quotes and angle
  brackets are escaped for both HTML and JS contexts.
- **`data-payload="{{ json_encode(...) }}"` is safe**: Blade's `e()` escapes the quotes, the DOM
  decodes them back, and `JSON.parse` receives valid JSON
  (`resources/views/components/list/row-actions.blade.php:16,27`).
- **List search + pagination are progressive.** `resources/views/list.blade.php` renders a real
  `GET` form, and `resources/js/app.js:446-487` upgrades it to `fetch` + `Alpine.morph`. Without JS
  the form still works; `ListSearchFragmentTest` and `CustomerCrudTest:71` verify both paths.
- **The morph preserves Alpine state and focus.** `Alpine.morph(this.$root, results.outerHTML)`
  patches the `x-data` root in place; `CustomerCrudTest:87` asserts `window.listSearchPageState`
  survives the refresh. (The "search input loses the caret" hypothesis was tested and rejected.)
- **Dirty-form protection is layered**: per-form (`resources/views/components/forms/tracked-resource.blade.php:16-18`),
  modal close (`:127-128`), Flux close trigger interception (`app.js:126-140`), `livewire:navigate`
  (`app.js:142-146`) and `beforeunload` (`app.js:148-155`).
- **`RECENT_COMMENTS_LIMIT` truncation is communicated** —
  `resources/views/epics/form.blade.php:89-92` renders "Showing the latest :shown of :total
  comments." only when `commentsCount > comments.length`.
- **Accessibility basics are present**: `aria-label` / `sr-only` on action columns and icon
  buttons, `role="alert"` on error text, `aria-labelledby` on the comments section, `<time>` elements
  for timestamps, `aria-current` via Flux.
- **No `innerHTML`/`x-html` fed by user data** anywhere.