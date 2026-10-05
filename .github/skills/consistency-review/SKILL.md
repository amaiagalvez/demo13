---
name: consistency-review
description: Audit forms, lists and resource sets for divergence from the canonical ones
disable-model-invocation: true
---
# CONSISTENCY REVIEW

READ-ONLY audit of pattern consistency across resources. DO NOT modify any file.

1. Read `AGENTS.md` and `.github/instructions/{views,http,models,tests}.instructions.md`.
2. Canonical patterns:
   - Form: `resources/views/customers/form.blade.php` + `<x-forms.tracked-resource>`; field pattern `flux:field` → `flux:label` (`*` if required) → control with `data-test` → `flux:error`.
   - List: `resources/views/customers/list.blade.php` + `<x-list.table>` + `components/list/*`.
   - Resource set: controller, trash controller, Form Requests, policy, `XListQuery`, `XListTransformer`, list + form views, `lang` keys in 4 locales, feature tests (CRUD, input validation, trash, access).
3. Compare every resource (`customers`, `projects`, `epics`, plus any new one) against them: wrapper usage, label/required/error structure, action buttons and `data-test` prefixes, pagination `data-test`, i18n key parity, list-component usage, controller/query/transformer layering, validation coverage (every user input has a FormRequest rule).
4. Report each divergence as a finding:

   ### PAT-001 — Title
   Severity: HIGH|MEDIUM|LOW · File: `path:line` · Confidence: HIGH|MEDIUM|LOW

   Problem / Evidence / Recommendation.

5. Finish with `### RESUMEN` (counts by severity) and `### TOP 5` of the changes that most improve consistency. No purely stylistic findings; never invent evidence.
