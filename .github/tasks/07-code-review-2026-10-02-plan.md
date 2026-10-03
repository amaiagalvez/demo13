# TASK — Code review 2026-10-02: plan de corrección

Derivado de la revisión completa en `.github/reviews/2026-10-02/CODE-REVIEW.md`
(HEAD `781ccbb` + working tree en curso del 2026-10-02 22:19 CEST).

> **Estado del repo al escribir esto:** había cambios sin commitear de otra sesión (endpoint JSON
> `epics.comments.index`). Los puntos 1-3 de P0 ya estaban siendo resueltos mientras escribía el informe.
> **Vuelve a ejecutar `composer ci:check` antes de tocar nada.**

---

## P0 — CI está en rojo. Nada más se puede verificar hasta que esto pase.

### [x] 1. Los `index()` de listados devuelven un string, no una `View`

`BUG-001` · app/Http/Controllers/*Controller.php

Los nueve `index()` hacen `->fragmentIf(...)`, y `View::fragmentIf()` **siempre devuelve string**
(`vendor/laravel/framework/src/Illuminate/View/View.php:114-121`). El tipo `View|string` miente: la rama `View`
es inalcanzable.

Fallos reales medidos en HEAD:

```
FAILED Tests\Feature\Epics\EpicCommentTest ... The response is not a view.
  at tests/Feature/Epics/EpicCommentTest.php:77
FAILED Tests\Feature\ResourceActivationTest ... (x3)  at :372

Tests: 4 failed, 233 passed
```

`ResourceActivationTest.php:372-378` es el **único** sitio donde se comprueba la paginación de la lista de
inactivos: si se pierde ese test, se pierde cobertura real.

**Arreglo:**

```php
$view = view('customers.list', $data);

return $request->hasHeader('X-List-Fragment')
    ? $view->fragment('list-results')
    : $view;
```

El working tree ya lo hace con `Controller::listView()`. **Consérvalo** y combínalo con el punto 2.

**No** cambies los tests para asertar sobre el body: las aserciones sobre view data son el contrato correcto.

### [x] 2. PHPStan nivel 9 falla y detiene el pipeline antes del suite

`BUG-002` · app/Http/Controllers/Controller.php:20

```
20  Parameter #1 $view of function view expects view-string|null, string given.  🪪 argument.type
[ERROR] Found 1 error
```

`composer ci:check` → `test:prepare` (incluye `types:check`) → `@test`. Como PHPStan corre primero, **los 4 tests
rojos de arriba ni siquiera se reportan en CI**.

**Arreglo (solo PHPDoc, sin efecto en runtime):**

```php
/**
 * @param  view-string  $view
 * @param  array<string, mixed>  $data
 */
```

### [x] 3. `EpicCommentController::index` rompe la propia regla de arquitectura

`BUG-003` · app/Http/Controllers/EpicCommentController.php:14

`tests/Unit/ArchitectureTest.php:27` exige que todo `index()` declare un `FormRequest`. El endpoint nuevo **no lee
input de usuario** (ni query ni body), así que la regla —no el código— es demasiado amplia.

```
FAILED Tests\Unit\ArchitectureTest > input endpoints declare a form request
Tests: 1 failed, 238 passed
```

**Arreglo:** renombrar la acción a `show` y dejar el nombre de ruta igual, para no tocar plantillas ni tests:

```php
Route::get('epics/{epic}/comments', [EpicCommentController::class, 'show'])
    ->whereNumber('epic')
    ->name('epics.comments.index');
```

**No** añadas un FormRequest vacío paracontentar el test.

> Orden: el punto 2 tiene que entrar junto al 1, o el CI sigue rojo por otro motivo.

---

## P1 — Fallos que un usuario puede tocar hoy

### 4. Los padres inactivos salen en los desplegables de crear/editar

`BUS-001` · app/Http/Controllers/ProjectController.php:28 · app/Http/Controllers/EpicController.php:28

```php
'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),   // sin where('active', true)
```

La vista promete lo contrario (`projects/list.blade.php:92`: *"No active customers are available"*). Comprobado
en la BD viva: el cliente `Bezero 2` (`active=0`) es seleccionable ahora mismo, y `projects.id=1` depende de él.

Los listados **sí** filtran (`ProjectListQuery.php:29`), y la validación tampoco ayuda:
`Rule::exists(...)->whereNull('deleted_at')` excluye los borrados pero no mira `active`.

**Arreglo:**

1. `->where('active', true)` en las dos consultas. Dos líneas.
2. Para el modo edición, volver a inyectar el padre ya seleccionado aunque esté inactivo → es el paso 2 del
   ítem que ya está anotado en `todo.md`.

No pagines estos desplegables: con 5 filas es coste puro.

### 5. `phpunit.xml` no fuerza sus variables de entorno

`OPS-002` · phpunit.xml:17-27

Ningún `<env>` lleva `force="true"`, y PHPUnit solo asigna si la variable no existe ya. Ejecuté la lógica del
propio framework (`vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:134-149`):

```
# con DB_DATABASE=laravel en el entorno (como hace el job `ci`)
getenv("DB_DATABASE")  =>  laravel      (NO laravel_test)
```

Quien exporte `DB_DATABASE` y lance `php artisan test` **migra ese sitio** con `RefreshDatabase`.

**Arreglo:** añadir `force="true"` a las entradas `DB_*`. En CI no cambia nada (los valores son los mismos).

### 6. El mensaje de conflicto al restaurar dice "active" pero el guard cubre también inactivos

`BUS-005` · CustomerTrashController.php:73-74 · ProjectTrashController.php:75 · EpicTrashController.php:74

El guard de arriba (`CustomerTrashController.php:35`) usa `Customer::query()`, que excluye los borrados pero
**incluye los inactivos**. `ARCHITECTURE.md:61` confirma que el código es correcto y el texto no.

**Arreglo:** cambiar el string en los tres controladores y añadir la clave en los cuatro `lang/*.json`
(`tests/Unit/Translations/TranslationFilesTest.php` falla si se olvida una).

### 7. El job `ci` corre el suite contra la base de datos de la aplicación

`OPS-001` · .github/workflows/tests.yml:118

`composer setup` ejecuta `php artisan migrate --force` contra `DB_DATABASE: laravel`. El servicio MariaDB solo
crea `laravel`; `laravel_test` solo la crea el job `dusk` (línea 209).

**Arreglo:** crear `laravel_test` en el job `ci` (un paso `mysql -e`, igual que `dusk` en las líneas 206-209) y
poner `DB_DATABASE: laravel_test`.

---

## P2 — Mantenibilidad

| # | Qué | Dónde | Nota |
|---|-----|-------|------|
| 8 | Los servicios `composer`/`npm` de compose **borran los lockfiles** | docker-compose.yml:70-76, 107-108, 129-130 | Quita los `rm -f *.lock`. Contradice `AGENTS.md` |
| 9 | Extraer el bloque Alpine `x-data` duplicado en las 3 vistas de listado | resources/views/{customers,projects,epics}/list.blade.php | Solo lo idéntico. **Nada** de componente genérico de listado (las columnas son 3/5/7) |
| 10 | Convertir el invariante de unicidad en aserción en vez de prosa | ARCHITECTURE.md:60-63 | Ya hay 4 meta-tests; añadir uno más |
| 11 | Corregir la frase de la BD de test en `ARCHITECTURE.md` | ARCHITECTURE.md:50-51 | Dice SQLite; `phpunit.xml:22` fija MySQL. Es lo que esconde DB-001 |
| 12 | `DB-001`: `COLLATE NOCASE` en el índice parcial SQLite | 3 migraciones `2026_*` | MariaDB `utf8mb4_unicode_ci` no distingue mayúsculas; SQLite sí. La rama SQLite **nunca** la ejecuta el suite |
| 13 | `ARCH-002`: una frase en `ARCHITECTURE.md` sobre `active` | ARCHITECTURE.md | Ortogonal a `deleted_at`, nunca asignable en masa |
| 14 | `SEC-004`: comentario en las 3 policies | app/Policies/*.php | "No aplican nada por diseño". **No** añadir roles |
| 15 | `OBS-001`: mostrar el fallo del refresco de listado | resources/js/app.js:478-481 | Hoy es un rejection no manejado; el usuario ve la lista congelada sin aviso |
| 16 | `SEC-003`: enrutar el login por `retrieveByCredentials()` | app/Providers/FortifyServiceProvider.php:44 | Sin cambio de comportamiento. El rate limiting **no** está saltado |
| 17 | `BUS-006`: redirect de comentario sin `back()` | app/Http/Controllers/EpicCommentController.php:17 | Quita una rama sin test. Coordinar con el refactor en curso |

---

## P3 — Cuando haya hueco

- `OPS-004` — publicar el puerto en `127.0.0.1:80:80` (docker-compose.yml:12). Hoy es `0.0.0.0` con `APP_DEBUG=true`.
- `OPS-003` — quitar `--sql-mode=""` del servicio `db` (línea 201); local debería coincidir con producción.
- `DEP-001` — quitar los 3 binarios linux-x64 de `optionalDependencies` (package.json:21-25).
- `CONC-001` — `deactivate`/`reactivate` como update atómico de una query (6 métodos).
- `CONC-002` — transaction en el guard de borrado de padres (4 controladores). Requiere **ensanchar**
  `ArchitectureTest.php:67` para permitir `DB::transaction(` a propósito.
- `CLEAN-005` — extraer la cadena de `orderBy` duplicada en `EpicListQuery`.
- `FE-001` — distinguir fallo de transporte vs. de validación en el alta inline.
- `CLEAN-002` — documentar que `$createClick` es una expresión Alpine, no un dato.
- `MAINT-005` — opcionalmente sembrar una cadena cliente → proyecto → épica con comentario.

---

## Explícitamente NO recomendado

Cada punto se propuso, se discutió y se descartó. El porqué está en la sección *Rejected Findings* del informe.

- **No** añadir Sentry/Bugsnag/OpenTelemetry: no hay target de despliegue declarado en el repo.
- **No** añadir logging estructurado: no hay consumidor de logs.
- **No** añadir monitorización/alerting: `failed_jobs` y `cache` están **vacías** (0 jobs, 0 `Cache::`) — una tabla
  vacía no es una señal de salud.
- **No** añadir Redis/cache: cero llamadas a `Cache` en `app/`.
- **No** añadir locking optimista: estorbaría al usuario sin resolver un problema real.
- **No** añadir trait `HasActive` ni modelo base: 3 modelos, 1 booleano. `ARCHITECTURE.md:122-123` lo prohíbe.
- **No** crear un componente genérico de listado: las columnas son 3/5/7.
- **No** unificar los 9 call sites de `UniqueConstraintViolation`: los dos estilos responden a requisitos distintos.
- **No** índices trigram/full-text para la búsqueda: 5 filas.

---

## Antes de tocar código

```bash
DX php artisan test --compact                     # estado actual
DX ./vendor/bin/phpstan analyse                  # debe quedar en 0 errores
DX ./vendor/bin/pint --dirty --format agent       # DESPUÉS de editar, nunca --test
```

Los tres meta-tests (`ModelSchemaParityTest`, `ArchitectureTest`, `ValidationCoverageTest`) son la red de seguridad
de este proyecto: si añades una columna, un endpoint o una vista, ellos son los que te avisan.