---
applyTo: "resources/views/**,lang/**,resources/js/**"
---
# View / i18n rules
- List views reuse `resources/views/components/list/*`; do not copy their markup back into a view. Keep existing `data-test` names; row actions pass the edit payload via `data-payload`.
- Use Flux components where one exists.
- Any new user-facing string goes in `lang/*.json` for all 4 locales (same key and `:placeholders`); a unit test checks parity. Default locale is `eu`.
