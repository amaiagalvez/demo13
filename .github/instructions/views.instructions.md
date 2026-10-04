---
applyTo: "resources/views/**,lang/**,resources/js/**"
---
# View / i18n rules
- Canonical form: `resources/views/customers/form.blade.php`. Every resource form MUST wrap its content in `<x-forms.tracked-resource>` (`resources/views/components/forms/tracked-resource.blade.php`): header, CSRF, `_method`, dirty tracking, double-submit guard and the cancel/submit footer with `{prefix}-cancel`/`{prefix}-submit` data-tests live only there. Field markup stays local per resource but always follows the same pattern: `flux:field` → `flux:label` (red `*` when required) → control with `data-test` → `flux:error`.
- Canonical list: `resources/views/customers/list.blade.php` + `resources/views/components/list/*`; table and pagination go through `<x-list.table>`. Do not copy shared markup back into a view. Keep existing `data-test` names; row actions pass the edit payload via `data-payload`.
- Use Flux components where one exists.
- Any new user-facing string goes in `lang/*.json` for all 4 locales (same key and `:placeholders`); a unit test checks parity. Default locale is `eu`.
- A `maxlength` attribute never carries a literal number either: it renders `config('validation.max_length.string')` or `config('validation.max_length.longtext')`, the same value the FormRequest validates, so the browser cannot stop the input the server would accept.
