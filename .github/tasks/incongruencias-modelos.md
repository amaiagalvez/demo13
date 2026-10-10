# Incongruencias entre modelos y paquetes (demo13, basics13, customers13, testing13)

**Fecha:** 2026-10-10  
**Origen:** `@full-review analiza demo13 basics13 customer13 y testing13 busca incongruencias`

---

## Resumen ejecutivo

La extracción del paquete `customers13` está **a medias**:
- ✅ Modelos, controllers, requests, policies, queries, transformers, migraciones, tests → movidos al paquete
- ❌ **demo13 NO fue actualizado completamente** → referencias rotas, tablas inexistentes, tests rotos
- ❌ La tabla de customers en la BD principal sigue llamándose `customers` (no `CUM_customers`)
- ❌ La migración del paquete falla en MySQL (columna GENERATED)

---

## 1. Referencias rotas en demo13 (clases inexistentes)

| Archivo | Importa | Estado real |
| `routes/web.php:6,13-15` | `CustomerController`, `CustomerTrashController`, `CustomerArchivedController` | **NO EXISTEN** en demo13 (solo en paquete) |
| `tests/Unit/ResourceUniformityTest.php` | `App\Policies\CustomerPolicy`, `App\Http\Requests\CustomerRequest`, `App\Http\Requests\CustomerListRequest`, `App\Queries\Customers\CustomerListQuery`, `App\Transformers\CustomerListTransformer` | **NINGUNA EXISTE** en demo13 |

**Impacto:** Fatal error `Class not found` en cualquier request que toque proyectos, epics, planning o timeline.

---

## 2. Joins a tabla inexistente (`customers` vs `CUM_customers`)

La migración del paquete crea `CUM_customers` (ver `packages/customers13/src/Database/Migrations/2026_09_25_000000_create_cum_customers_table.php:12`), **PERO** las queries en demo13 siguen haciendo join a `customers`:

| Query | Línea | Join roto |
|-------|-------|-----------|
| `ProjectListQuery::withCustomer()` | 113 | `join('customers as project_customers', ...)` |
| `EpicListQuery::withProjectAndCustomer()` | 116 | `join('customers as epic_customers', ...)` |
| `PlanningQuery::projectIds()` | 121 | `join('customers as planning_customers', ...)` |
| `TimelineQuery::matchingProjects()` | 151 | `join('customers as timeline_customers', ...)` |

**En la BD principal (MySQL):** la tabla `customers` **SÍ EXISTE** (creada por migración antigua `2026_09_25_000000_create_customers_table`), con estructura diferente (tiene `active_name` GENERATED, FK a users). El paquete NO se aplica.

**En la BD de test (MySQL):** la migración del paquete **FALLA** al intentar crear `CUM_customers` con columna GENERATED → tests rotos (ver output phpunit arriba).

**Impacto:** 
- En producción: queries funcionan por accidente (tabla vieja existe) pero usan estructura incorrecta
- En tests: **fallan al migrar** → suite completa rota

---

## 3. Migración del paquete incompatible con MySQL

`packages/customers13/src/Database/Migrations/2026_09_25_000000_create_cum_customers_table.php:17`:
```php
addUniqueActiveNameIndex('CUM_customers');
```
En `basics13/src/Support/Database/Helpers.php:77` crea columna `active_name` como `GENERATED ALWAYS AS (IF(deleted_at IS NULL, name, NULL)) STORED`.

**MySQL 8.0+ SÍ soporta generated columns**, pero la sintaxis `IF()` en generated columns puede fallar según versión/configuración. El error del test:
```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'laravel_test.CUM_customers' doesn't exist
... Helpers.php:77 ... addUniqueActiveNameIndex ...
```

La tabla no se crea porque el statement falla silenciosamente o el error se propaga mal.

---

## 4. Tests rotos / no actualizados

| Test | Problema |
|------|----------|
| `tests/Unit/ResourceUniformityTest` | 12 errores `Class not found` (5 imports rotos) |
| `tests/Unit/ValidationCoverageTest` | Staged fix parcial (2 líneas), pero puede tener más |
| `tests/Unit/ModelSchemaParityTest` | Usa `Customer::class` del paquete ✓ (import correcto) |
| `tests/Unit/ArchitectureTest` | `RESOURCES` array **excluye Customer** (línea 25-28) → no valida Customer |
| **Package tests** (`packages/customers13/tests/`) | Migran OK pero BD test falla al crear `CUM_customers` |

---

## 5. Rutas duplicadas / conflicto

`routes/web.php` líneas 19-39 definen rutas de customers **idénticas** a las que registra `Customers13\ServiceProvider::registerRoutes()` (líneas 44-53). Doble registro → conflict potential.

El paquete usa nombres de ruta con prefijo `customers13.` (ej: `customers13.customers.index`), demo13 usa `customers.index`. **Inconsistencia de naming**.

---

## 6. CustomerListTransformer usa ruta inexistente

`packages/customers13/src/Transformers/CustomerListTransformer.php:89`:
```php
'projectsUrl' => $projectsCount > 0
    ? route('customers13.projects.index', ['search' => $customer->name])
```
La ruta `customers13.projects.index` **NO EXISTE** — el paquete solo registra rutas de *customers*, no de projects. Projects están en demo13 con nombre `projects.index`.

---

## 7. Inconsistencias de modelo (estructura diferente)

| Aspecto | `Customers13\Models\Customer` | `App\Models\Project` | `App\Models\Epic` | `App\Models\User` |
|---------|-------------------------------|----------------------|-------------------|-------------------|
| Tabla | `CUM_customers` (paquete) | `projects` | `epics` | `users` |
| `$fillable` | `['name', 'notes']` | `['name','notes','start_date','end_date','customer_id']` | `['name','notes','start_date','end_date','project_id']` | `['name','email','password','notes']` |
| Casts | `active: boolean` | `start_date:date, end_date:date, active:boolean` | `start_date:date, end_date:date, active:boolean` | `active:boolean, email_verified_at:datetime, ...` |
| Relaciones | `projects()`, `epics()` (HasManyThrough) | `customer()`, `epics()`, `comments()` | `project()`, `comments()` | Fortify traits |
| Policy | `CustomerPolicy` (en paquete) | `ProjectPolicy` | `EpicPolicy` | **NINGUNA** (excepción documentada) |
| Audit columns | `TracksAuditColumns` | `TracksAuditColumns` | `TracksAuditColumns` | `TracksAuditColumns` |
| SoftDeletes | ✅ | ✅ | ✅ | ✅ |
| Factory states | `trashed`, `archived` | `trashed`, `archived` | `trashed`, `archived`, `withoutDates` | `trashed` |

**Diferencias clave:**
- Customer **no tiene fechas** (start/end) → más simple
- Customer **no tiene parent_id** → raíz de la jerarquía
- Customer **sí tiene `active_name` GENERATED** (único índice) — los otros usan unique index compuesto normal
- User **no tiene Policy** — gestionado por Fortify
- Epic **tiene `withoutDates` factory state** — único

---

## 8. Código duplicado / sobreingeniería

### 8.1 ListQueryBase duplicado
- `basics13/src/Queries/ListQueryBase.php` — base compartida
- `ProjectListQuery`, `EpicListQuery`, `CustomerListQuery` (en paquete) — heredan OK
- `PlanningQuery`, `TimelineQuery` — **también heredan ListQueryBase** pero **no son resource queries** (son vistas agregadas). Usan `PER_PAGE` y `paginate()` pero devuelven estructuras custom, no `LengthAwarePaginator<Model>`. **Violan Liskov** — `ListQueryBase` asume paginación de modelos.

### 8.2 ListTransformer duplicado
- `basics13/src/Transformers/ListTransformer.php` — base
- `ProjectListTransformer`, `EpicListTransformer`, `CustomerListTransformer` — OK
- **Planning y Timeline NO tienen transformer** — devuelven arrays raw desde el query. Inconsistente.

### 8.3 Request bases duplicadas
- `basics13/src/Http/Requests/SearchableListRequest.php` — base para list requests
- `ProjectListRequest`, `EpicListRequest` — extienden OK
- `CustomerListRequest` (en paquete) — extiende OK
- `PlanningListRequest`, `TimelineListRequest` — **no extienden SearchableListRequest** (PlanningListRequest duplica reglas de search, TimelineListRequest extiende SearchableListRequest ✓)

### 8.4 Controller bases duplicadas
- `basics13/src/Http/Controllers/Controller.php` — `listView()`, `deletedNameConflict()`
- `basics13/src/Http/Controllers/ArchivedController.php` — `index()`, `activate()`, `archive()`
- `basics13/src/Http/Controllers/TrashController.php` — `index()`, `restore()`, `destroy()`
- **Todos los resource controllers extienden correctamente** (ProjectController, EpicController, CustomerController en paquete)
- **PlanningController, TimelineController** — extienden `App\Http\Controllers\Controller` (base app), no usan base de basics13. Duplican lógica de listado.

---

## 9. Qué está testeado en uno y no en otro

| Funcionalidad | Customer (paquete) | Project (demo13) | Epic (demo13) |
|---------------|-------------------|------------------|---------------|
| CRUD básico | ✅ `CustomerCrudTest` | ✅ `ProjectCrudTest` | ✅ `EpicCrudTest` |
| Trash (soft delete, restore, force delete) | ✅ `CustomerTrashTest` | ✅ `ProjectTrashTest` | ✅ `EpicTrashTest` |
| Archived (deactivate/reactivate) | ❌ **NO TEST** | ✅ `ResourceActivationTest` (shared) | ✅ `ResourceActivationTest` |
| Validación input | ✅ `CustomerRequestTest`, `CustomerRestoreRequestTest` | ✅ `ProjectInputValidationTest` | ✅ `EpicInputValidationTest` |
| Policy | ✅ `CustomerPolicyTest` | ❌ **NO TEST** (solo en ResourceUniformityTest) | ❌ **NO TEST** |
| Query structure | ✅ `CustomerListQueryTest` | ✅ `ProjectListQueryTest` | ✅ `EpicListQueryTest` |
| Select options | ❌ **NO TEST** | ✅ `CustomerSelectOptionsTest`, `ProjectSelectOptionsTest` | ✅ `ProjectSelectOptionsTest` |
| Dusk browser tests | ❌ **NINGUNO** | ✅ `ProjectCustomerSelectTest` | ❌ **NINGUNO** (solo CRUD, no trash/archived) |

**Customer es el MÁS TESTEADO en Unit, MENOS en Feature/Integration** (sin archived, sin Dusk).

---

## 10. Acciones recomendadas (ordenadas por impacto)

### CRÍTICO - Bloquean la aplicación

1. **[ ] Fix BD principal: renombrar tabla `customers` → `CUM_customers` y aplicar migración del paquete**
   - O bien: mantener tabla `customers` y actualizar paquete para usarla (cambiar `$table = 'customers'` en Customer model)
   - Recomendado: **mantener `customers`** (menos breaking, ya existe en prod, tiene datos). Eliminar migración del paquete o hacerla no-op.

2. **[ ] Fix migración paquete: quitar GENERATED column `active_name` o hacerla compatible**
   - Usar unique index compuesto normal: `UNIQUE INDEX (name) WHERE deleted_at IS NULL` (MySQL 8.0.13+)
   - O: index único normal en `(name, deleted_at)` + validación en request (ya existe)

3. **[ ] Actualizar TODAS las referencias `App\Models\Customer` → `Customers13\Models\Customer` en demo13**
   - 7 archivos listados en sección 1
   - `Project::customer()` relationship ya usa `Customers13\Models\Customer` ✓

4. **[ ] Eliminar rutas de customers de `routes/web.php` (líneas 22-39)**
   - Las registra el paquete → duplicado
   - O: quitar registro de rutas del ServiceProvider del paquete

5. **[ ] Fix `ResourceUniformityTest`: actualizar imports a namespace del paquete**
   - `App\Policies\CustomerPolicy` → `Customers13\Policies\CustomerPolicy`
   - `App\Http\Requests\CustomerRequest` → `Customers13\Http\Requests\CustomerRequest`
   - etc. (5 imports)

6. **[ ] Fix `CustomerListTransformer`: ruta `customers13.projects.index` → `projects.index`**
   - Projects están en demo13, no en paquete

### ALTO - Inconsistencias arquitectónicas

7. **[ ] PlanningQuery y TimelineQuery: ¿deberían heredar de ListQueryBase?**
   - No son resource list queries → crear base propia `AggregateQueryBase` o no heredar

8. **[ ] Unificar SelectOptions: ProjectSelectOptionsQuery vs CustomerSelectOptionsQuery**
   - Project tiene query dedicada + request + test
   - Customer (paquete) tiene query + request pero **NO test**
   - Epic usa `ProjectSelectOptionsQuery` (reutiliza) ✓

9. **[ ] Añadir tests faltantes de Customer (archived, select options, Dusk)**
   - Alinear coverage con Project/Epic

10. **[ ] Añadir Policy tests para Project y Epic**
    - Solo Customer los tiene (en paquete)

### MEDIO - Limpieza

11. **[ ] Eliminar directorio vacío `app/Queries/Customers/`**

12. **[ ] Unificar `PlanningListRequest` para extender `SearchableListRequest`** (como `TimelineListRequest`)

13. **[ ] Revisar `ArchitectureTest::RESOURCES` — añadir Customer o documentar por qué no**

14. **[ ] Decidir naming de rutas: `customers.` vs `customers13.customers.`**
    - Paquete usa prefijo, demo13 no → confusión

---

## 11. Preguntas para decisión

> **P1:** ¿Mantenemos tabla `customers` en BD principal y adaptamos el paquete, o migramos a `CUM_customers`?
> - Mantener `customers`: menos riesgo, compatible con datos existentes, requiere cambiar `$table` en modelo del paquete
> - Migrar a `CUM_customers`: "limpio" pero requiere migración de datos y fix de GENERATED column

> **P2:** ¿Eliminamos las rutas de customers de `routes/web.php` y usamos solo las del paquete?
> - Sí: limpio, pero los nombres de ruta cambian (`customers13.customers.*`)
> - No: mantener en demo13, quitar del paquete

> **P3:** ¿PlanningQuery y TimelineQuery deben heredar de ListQueryBase?
> - No son resource lists → mejor composition over inheritance

> **P4:** ¿Ejecutamos la suite de tests del paquete en CI separado o integrada?
> - El paquete tiene su propio `composer.json` y tests → CI independiente recomendado

---

## 12. Comandos de verificación

```bash
# Verificar imports rotos
grep -rn "App\\\\Models\\\\Customer" app/ routes/ database/ tests/

# Verificar joins a tabla customers
grep -rn "join.*customers" app/Queries/

# Verificar tests rotos
docker compose run --rm --no-deps -e XDEBUG_MODE=off --entrypoint ./vendor/bin/phpunit laravel13 --testsuite Unit --filter ResourceUniformityTest

# Verificar migración paquete
docker compose run --rm --no-deps -e XDEBUG_MODE=off --entrypoint ./vendor/bin/phpunit laravel13 --testsuite Unit --filter CustomerListQueryTest
```