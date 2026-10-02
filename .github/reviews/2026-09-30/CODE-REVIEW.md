# Code Review — Epics vs Customers/Projects (coherencia y duplicación)

## Executive Summary

El módulo Epics sigue la misma arquitectura que Customers y Projects (Controller + TrashController,
FormRequest, Policy, `ListQueryBase`, Transformer, vista `list`/`form`, papelera, conflicto de nombre
borrado). No se han encontrado bugs de negocio. Los problemas principales son de **duplicación**
(vistas de listado casi idénticas, manejo de colisión UNIQUE repetido 8 veces) y de **incoherencia
interna**: Epics introdujo pequeñas refactorizaciones (helpers privados) que los módulos anteriores
no tienen, así que hoy hay dos estilos para lo mismo. Faltan además tests de carrera concurrente que
sí existen para Projects.

## Detected Stack

PHP 8.4, Laravel 13, Livewire 4 + Flux, Blade + Alpine, Vite, MariaDB 11.7 (Docker), PHPUnit, Dusk, Pint.

## Checks Executed

Ejecutados en el paso anterior (implementación), no repetidos en esta revisión:

- `php artisan test --compact` → 150 passed (555 assertions)
- `php artisan dusk tests/Browser/Epics` → 1 passed
- `vendor/bin/pint --dirty --format agent` → passed

Comparación estructural: `diff` normalizado (`project→X`, `epic→X`) entre `projects/list.blade.php`
y `epics/list.blade.php` → sólo 60 líneas distintas de ~290.

## Critical Findings

Ninguno.

## High Findings

Ninguno.

## Medium Findings

### MAINT-001 — Las tres vistas de listado están duplicadas casi por completo

Severity: MEDIUM
Category: Maintainability / Duplication
File: resources/views/epics/list.blade.php, resources/views/projects/list.blade.php, resources/views/customers/list.blade.php
Line: epics 139 (búsqueda), 262-290 (modal de confirmación); projects 128, 242-270; customers 108, 213-241
Confidence: HIGH

Problem: cabecera, callouts de status/error, formulario de búsqueda, botones de acción de la tabla,
paginación y modal de confirmación se repiten en los tres ficheros cambiando sólo el prefijo
(`customer-`/`project-`/`epic-`).

Evidence: diff normalizado projects↔epics = 60 líneas diferentes de 272/292. Las tres vistas
contienen el mismo bloque `x-on:submit="if (isSubmitting) ..."` y los dos `<template x-if="confirmation.danger">`.

Impact: cualquier cambio de UX (p. ej. accesibilidad del modal de confirmación) debe hacerse tres veces;
ya existen pequeñas divergencias (ver LOW-002).

Recommendation: extraer componentes Blade anónimos, siguiendo el precedente de
`x-name-conflict-modal`: `x-list.search`, `x-list.flash`, `x-list.row-actions` y `x-list.confirm-modal`
con prop `prefix` para los `data-test`. No se recomienda un componente "listado genérico" completo:
las columnas y el formulario sí difieren.

### MAINT-002 — Conversión de colisión UNIQUE a error de validación repetida 8 veces, con dos estilos

Severity: MEDIUM
Category: Maintainability / Consistency
File: app/Http/Controllers/EpicController.php (82), ProjectController.php (53, 70), CustomerController.php (61, 82), *TrashController.php (Customer 42, Project 43, Epic 48)
Line: ver arriba
Confidence: HIGH

Problem: el bloque `catch (QueryException) { if (! UniqueConstraintViolation::causedBy(...)) throw; throw ValidationException::withMessages(['name' => ...]); }`
aparece inline en Customer y Project; Epic lo encapsula en un helper privado `throwIfNotDuplicateName()`.
Mismo comportamiento, dos estilos.

Impact: incoherencia y riesgo de que un cambio (mensaje, atributo) se aplique sólo en algunos sitios.

Recommendation: mover el helper a la clase ya existente `App\Support\Database\UniqueConstraintViolation`
(p. ej. `rethrowAsValidationError(QueryException $e, string $field = 'name'): never`) y usarlo en los
6 puntos de store/update. Sin nuevas capas.

### TEST-001 — Sin tests de carrera concurrente para Epics

Severity: MEDIUM
Category: Testing
File: tests/Feature/Epics/EpicCrudTest.php, tests/Feature/Epics/EpicTrashTest.php
Line: —
Confidence: HIGH

Problem: Projects prueba el camino `catch (QueryException)` en store, update y restore
(`test_store_converts_a_concurrent_duplicate_insert_to_validation_error`, etc. con `DB::listen`).
Epics no tiene equivalente; las ramas `catch` de EpicController (53, 64) y EpicTrashController (48) no están cubiertas.

Impact: si se rompe el índice único compuesto `(project_id, active_name)` o el helper, ningún test lo detecta.

Recommendation: añadir los tres tests equivalentes a los de Projects.

## Low Findings

### CONS-001 — Query y Transformer de Epics refactorizados; los de Project no

Severity: LOW
Category: Consistency
File: app/Queries/Epics/EpicListQuery.php (16, 64); app/Transformers/EpicListTransformer.php (126); app/Queries/Projects/ProjectListQuery.php (22, 47)
Confidence: HIGH

Problem: `EpicListQuery` centraliza joins y columnas de búsqueda (`SEARCH_COLUMNS`, `withProjectAndCustomer()`)
y `EpicListTransformer::columns()` comparte las columnas active/trash. `ProjectListQuery` y
`ProjectListTransformer` duplican joins, columnas de búsqueda y columnas de fila entre `active()` y `trashed()`.

Recommendation: aplicar la misma pequeña extracción a Project (y Customer si aplica), o aceptar la
divergencia conscientemente. No requiere nuevas abstracciones.

### CONS-002 — Divergencias menores en formularios

Severity: LOW
Category: Consistency / UX
File: resources/views/epics/form.blade.php (17, 25, 43); resources/views/projects/form.blade.php
Confidence: HIGH

- Epic tiene `minlength="4"` en el nombre; Project (misma regla `min:4`) no.
- Project usa select2 con creación inline de cliente; Epic usa `flux:select` sin creación inline de proyecto.
  Diferencia aceptable (crear un proyecto exige fechas), pero conviene que sea una decisión explícita.
- En Project, "End date" usa `flux:input :label` fuera de `flux:field`; en Epic va dentro de `flux:field`.
- `x-bind:min="form.start_date"` en Epic permite en el navegador fin = inicio, mientras que el servidor
  exige `after:start_date`. El servidor valida correctamente; sólo es feedback de cliente inexacto.

### CONS-003 — Claves de traducción del conflicto de nombre incoherentes (preexistente en Project)

Severity: LOW
Category: Consistency / i18n
File: lang/*.json (49); resources/views/projects/list.blade.php (223)
Confidence: HIGH

Problem: la clave `"A deleted project already uses this name."` tiene como valor `"...uses the name :name."`
(la clave no describe el texto). Customer usa clave y valor sin `:name`. Epic usa clave = valor con `:name`.
Tres convenciones distintas para el mismo mensaje.

Recommendation: unificar (clave igual al texto inglés, con `:name` en los tres).

## Informational Findings

### PERF-INFO-001 — Todos los comentarios de cada épica de la página se incrustan en el HTML

Severity: INFO
Category: Performance
File: app/Queries/Epics/EpicListQuery.php (33); app/Transformers/EpicListTransformer.php (46)
Confidence: MEDIUM

Con 5 épicas por página el coste es bajo, pero no hay límite de comentarios por épica: el atributo
`data-epic` crece sin tope. Sólo relevante si los comentarios crecen mucho; entonces cargarlos bajo demanda
o limitar a los N últimos.

### INFO-002 — Diferencias intencionadas (no son incoherencias)

- Unicidad: Project/Customer global; Epic por proyecto (requisito).
- Fechas: Project `start_date` obligatoria y `after_or_equal`; Epic ambas opcionales, `required_with` y `after` (requisito "fin > inicio").
- Orden: Epic coloca fechas nulas al final (`orderByRaw ... IS NULL`); Project no lo necesita porque `start_date` es obligatoria.
- Bloqueo de borrado padre→hijos (`withTrashed()->exists()`) replicado de forma idéntica en Customer→Project y Project→Epic, tanto en destroy como en force-delete. Coherente.
- Policies: `EpicPolicy` copia `ProjectPolicy` (todo `true`) + `comment`. Coherente con la decisión documentada en ARCHITECTURE.md.

## Security

Sin hallazgos. Rutas bajo `auth`+`verified`; FormRequests autorizan vía policy; comentarios asignan
`user_id` desde el usuario autenticado (no es mass-assignable); salida Alpine con `x-text` (sin XSS).

## Bugs / Correctness

Sin hallazgos confirmados.

## Database

Índice único compuesto `(project_id, active_name)` coherente con el patrón de columna generada de Projects.
FKs: `epics.project_id` restrict (coherente con `projects.customer_id`), `epic_comments.epic_id` cascade,
`user_id` null on delete.

## Performance

PERF-INFO-001.

## Architecture

Epics respeta ARCHITECTURE.md y el patrón existente. Sin hallazgos.

## Testing

TEST-001.

## Production

No aplica a este cambio.

## Maintainability

MAINT-001, MAINT-002, CONS-001, CONS-002, CONS-003.

## Rejected Findings

- "Crear un listado/controlador genérico para los tres recursos": rechazado; las diferencias de dominio
  (JSON en Customer, select2 en Project, comentarios en Epic) harían la abstracción más compleja que la duplicación.
- "EpicPolicy duplica ProjectPolicy": rechazado; es la convención documentada y cada recurso puede divergir.
- "Epic debería usar select2 como Project": preferencia, no defecto.

## Action Plan

1. MAINT-002: mover el helper de colisión UNIQUE a `UniqueConstraintViolation` y usarlo en los 3 controladores (bajo riesgo, cubierto por tests).
2. TEST-001: añadir tests de carrera concurrente para Epics (store, update, restore).
3. MAINT-001: extraer `x-list.confirm-modal`, `x-list.search`, `x-list.flash`, `x-list.row-actions`.
4. CONS-001 / CONS-002 / CONS-003: alinear Query/Transformer de Project, atributos de formulario y claves de traducción.

## Resolution (FIX MODE, 2026-09-30)

- MAINT-002: fixed. Added `UniqueConstraintViolation::rethrowAsValidationError()`; used in store/update of
  `CustomerController`, `ProjectController` and `EpicController` (private helper removed). Restore flows keep
  their own conflict response.
- TEST-001: fixed. Added store/update concurrency tests in `tests/Feature/Epics/EpicCrudTest.php` and the
  restore concurrency test in `tests/Feature/Epics/EpicTrashTest.php`.
- Commands: `vendor/bin/pint --dirty --format agent` (fixed), `php artisan test --compact` → 153 passed (571 assertions).

### Second round (pending findings)

- CONS-003: fixed. Keys renamed to `A deleted customer|project already uses the name :name.` in the 4 locales;
  usages and tests updated. New `tests/Unit/Translations/TranslationFilesTest.php` checks key parity and
  `:placeholder` preservation across locales.
- CONS-002: fixed. Project form: `minlength="4"` on name, end date wrapped in `flux:field` with
  `x-bind:min="form.start_date"`. Epic form: end date `min` = start + 1 day via new `addDays()` in
  `resources/js/app.js` (covered by `tests/Browser/Epics/EpicFormTest.php`).
- CONS-001: fixed. `ProjectListQuery` uses `SEARCH_COLUMNS` + `withCustomer()`; `ProjectListTransformer`
  shares `columns()` between active and trash rows.
- PERF-INFO-001 (option B): `EpicListQuery::RECENT_COMMENTS_LIMIT = 20` limits embedded comments; payload adds
  `commentsCount` and the form shows "Showing the latest :shown of :total comments." when truncated.
- MAINT-001: fixed. Anonymous components in `resources/views/components/list/` (`header`, `flash`, `search`,
  `row-actions`, `confirm-modal`) replace the duplicated blocks in the 3 list views; `data-test` values unchanged.
  Row actions now pass the edit payload through `data-payload` instead of `data-customer|project|epic`.
- Commands: `npm run build`; `vendor/bin/pint --dirty --format agent` (passed); `php artisan test --compact` →
  156 passed (601 assertions); Dusk Epics 2 passed, Customers 7 passed, Projects 1 failed
  (`ProjectCustomerSelectTest` expects English "Create customer:" while `APP_LOCALE=eu`; pre-existing, untouched).
