# Consistencia entre modelos — auditoría y tareas

Fecha: 2026-10-05
Alcance: `Customer`, `Project`, `Epic`, `EpicComment`, `User` y su juego por recurso
(controller, requests, policy, list query, transformer, factory, vistas, tests).
Modo: **solo lectura**. No se ha modificado código de aplicación.

## Estado de la corrección (2026-10-05)

Por indicación del usuario, **todo lo relativo a `EpicComment` queda aplazado**.

### Corregidos y verificados

| ID | Qué | Dónde |
| --- | --- | --- |
| DB-001 | `migrate:fresh` sobre la BD de desarrollo: las FK de auditoría pasaron de `SET NULL` a `RESTRICT`, igual que las migraciones, el docblock y los tests | (BD; sin cambios de código) |
| MOD-005 | `start_date`/`end_date` documentados en Project y Epic; `deleted_at` en User | `app/Models/Project.php`, `Epic.php`, `User.php` |
| MOD-006 | Cast de `two_factor_confirmed_at` | `app/Models/User.php` |
| MOD-007 | `User::casts()` con forma `array{...}`, ahora comprobable por PHPStan | `app/Models/User.php` |
| MOD-004 | `orderBy('id')` en la lista activa, por consistencia — **no era bug** | `app/Queries/Customers/CustomerListQuery.php` |
| DUP-002 | `payloadFor()` delega en `editPayload()`: −17 líneas y un solo constructor del payload | `app/Transformers/EpicListTransformer.php` |
| TEST-001 | `CustomerTest` alineado con sus hermanos: `Tests\TestCase`, y `active` y `notes` cubiertos | `tests/Unit/Customers/CustomerTest.php` |
| TEST-002 (parte `User`) | `tests/Unit/Users/UserTest.php` nuevo: `initials()` (4 casos), `#[Fillable]`, `#[Hidden]` | `tests/Unit/Users/UserTest.php` |
| TEST-003 | `ARCHITECTURE.md` ya no sugiere que Dusk cubre los tres recursos | `ARCHITECTURE.md` |
| MOD-003 (parte) | El guard de soft-deletes incluye a `User`, que sí es soft-deletable y estaba omitido | `tests/Unit/ResourceUniformityTest.php` |
| OE-004 | `PER_PAGE` documenta que también topea los selects y que nada cubre ese tope | `app/Queries/ListQueryBase.php` |
| — | Comentario obsoleto de `config/validation.php`: el índice único va sobre `active_name` varchar(255), no sobre `name` | `config/validation.php` |

### Aplazados por instrucción del usuario (todo `EpicComment`)

MOD-001, MOD-002, MOD-003 (parte de `EpicComment`), MOD-008, TEST-002 (parte `EpicComment`),
OE-001, OE-003, DB-003.

**Corrección de alcance.** TEST-002 se aplazó entero la primera vez, y su mitad de `User`
no tenía nada que ver con `EpicComment`. Ya está hecha: `tests/Unit/Users/UserTest.php`
cubre `initials()` — que **no tenía ningún test en todo el repo** — más `#[Fillable]` y
`#[Hidden]`. Aplazado sólo lo que es de `EpicComment`.

### Reconsiderados y NO hechos

| ID | Por qué no |
| --- | --- |
| **DUP-003** | Al escribirlo los números no salen: con helper serían **20 líneas frente a 21**, y con relación dinámica se arriesga PHPStan nivel 9. Es el guard que agarra locks, lo más crítico del proyecto, y tres copias explícitas — cada una con sus tests `*_appears_during_the_delete_transaction` — son más auditables que la indirección. `AGENTS.md` prohíbe indirección sin causa concreta. |
| **DUP-004** | Borrar `InactiveController` es una decisión de gusto, no un defecto. Se ofrece; no se hace. |
| **OE-002** | Quitar la rama SQLite/PG depende de si el equipo quiere soportar esos motores. Decisión, no defecto. |
| **MOD-005 `active_name`** | No se documenta: ningún código de la app la lee, sólo la proyectan los `select('x.*')`. Sería ruido. |
| Test de desempate en `active()`/`inactive()` | **Imposible de escribir** para customers y projects, porque el nombre es único entre los no borrados. Ver MOD-004. |

### Verificaciones

| Comprobación | Resultado |
| --- | --- |
| `php artisan test` (suite completa) | **606 passed** (3124 assertions) |
| `phpunit --testsuite Unit` | 123 passed (1036 assertions) |
| `phpstan analyse` (nivel 9) | OK — sin errores |
| `pint --dirty --format agent` | passed |

**PHPStan destapó 2 errores durante el trabajo** (`ProjectListTransformer.php:87,109`) justo
porque MOD-005 hacía legible el tipo de `start_date`. Al principio lo había tipado
`Carbon|null` y el código llama a `->format()` sin comprobación. El esquema real dice
`start_date date NOT NULL`, así que el tipo correcto es `Carbon` y `end_date` sí nullable.
El error estaba en mi propio cambio, no en el código de la aplicación.

---

## Controles ejecutados

| Comprobación | Comando / método | Resultado |
| --- | --- | --- |
| Suite Unit | `docker compose exec -e XDEBUG_MODE=off laravel13 ./vendor/bin/phpunit --testsuite Unit` | OK — 122 tests, 1030 assertions |
| Análisis estático | `./vendor/bin/phpstan analyse` (nivel 9) | OK — sin errores |
| Estilo | `./vendor/bin/pint --test` | PASS — 204 ficheros |
| Esquema real | Boost `database-schema` + `SHOW CREATE TABLE` (MariaDB 11.7.2) | Ejecutado |

Las tablas están vacías, así que cualquier cifra de filas de `EXPLAIN` es meaningless y no
se usa como evidencia. Sólo `possible_keys` / `key` / `Extra`, que son estructurales.

## Diagnóstico en una frase

`Customer`, `Project`, `Epic` y `User` son gemelos. **`EpicComment` no lo es** — su tabla
heredó `addCommonColumns()` y tiene `active`, `deleted_at` y `notes`, pero el modelo no
usa ninguno de los dos primeros y **nadie lee el tercero**. Y hay un test que fija esa
divergencia a propósito (`ArchitectureTest:130-142`, lo llama "reserved"), así que no es un
descuido: es una decisión sin resolver que nadie escribió en ningún sitio.

---

## 1. Modelos que gestionan lo mismo de forma diferente

### MOD-001 — `EpicComment` ignora dos columnas que su tabla tiene, y es intencional
**Severidad: MEDIUM · Categoría: Arquitectura · Confianza: HIGH**

Ficheros: `app/Models/EpicComment.php:31`, `app/Support/Database/helpers.php:15-23`,
`database/migrations/2026_09_30_175707_create_epic_comments_table.php:20`

Esquema real (motor `mysql`):

```
epic_comments.active      tinyint(1)  NOT NULL  default 1
epic_comments.deleted_at  timestamp   NULL
KEY epic_comments_deleted_at_id_index (deleted_at, id)
```

| | `SoftDeletes` | `$attributes=['active'=>true]` | cast `active` | `@property` de esas columnas |
| --- | --- | --- | --- | --- |
| `Customer.php:38,43,53` | sí | sí | sí | sí |
| `Project.php:40,45,57` | sí | sí | sí | sí |
| `Epic.php:37,42,54` | sí | sí | sí | sí |
| `User.php:43,48,60` | sí | sí | sí | sí |
| **`EpicComment.php:31`** | **no** | **no** | **no** | **no** |

Impacto real:
- `EpicComment::delete()` hace **hard delete** y `TracksAuditColumns.php:35` ni registra el
  hook `trashed`, así que `deleted_by` nunca se escribe aunque la columna exista.
- `$comment->active` devuelve `int 1`, no `bool` — y `null` en una instancia sin guardar,
  porque `EpicCommentController.php:39` usa `make()`.
- `epic_comments_deleted_at_id_index` nunca puede ser selectivo: la columna es `NULL`
  siempre. Índice muerto.

**No es un descuido.** `ArchitectureTest:130-142` lo fija a propósito:

```php
// tests/Unit/ArchitectureTest.php:136-141
$this->assertNotContains(SoftDeletes::class, class_uses_recursive($comment));
$this->assertArrayNotHasKey('active', $comment->getCasts());
```

con un docblock que dice *"epic_comments keeps deleted_at and active columns that no model
uses… They are reserved, so this test records the fact"*.

Recomendación — decidir y **escribirlo en `ARCHITECTURE.md`**, que es donde falta:
- **(a) Recomendada, menor:** `EpicComment` no es soft-deletable ni activable. Sacar
  `addCommonColumns()` de esa migración y declarar solo `notes` + timestamps. Es lo que el
  código ya hace; el test de `ArchitectureTest` se adapta y sigue vigilando.
- (b) Tratarlo como hermano completo: `SoftDeletes` + `$attributes` + cast + `@property`.

Cualquiera de las dos es válida. Lo que no es válido es seguir como ahora: la decisión
existe sólo en un docblock de test.

### MOD-002 — `EpicCommentFactory` es la única factory sin `HasStates`
**Severidad: LOW · Categoría: Consistencia · Confianza: HIGH**

Ficheros: `database/factories/EpicCommentFactory.php` vs `CustomerFactory.php:13`,
`ProjectFactory.php:14`, `EpicFactory.php:14`, `UserFactory.php:15`

Correcto si se aplica MOD-001(a): el modelo no tiene `active` ni `SoftDeletes`, así que
`inactive()`/`trashed()` no tendrían a qué aplicarse. El problema es que **nada en el
fichero lo dice** — no se distingue de un descuido.

Recomendación: una línea en el docblock de la factory. Con MOD-001(b), añadir `HasStates`.

### MOD-003 — dos guards se contradicen sobre `EpicComment`, y ninguno explica por qué
**Severidad: MEDIUM · Categoría: Testing · Confianza: HIGH**

Ficheros: `tests/Unit/ArchitectureTest.php:130-142` vs `tests/Unit/ResourceUniformityTest.php:156,173`

Éste es el hallazgo de fondo. Hay dos guards y **se contradicen**:

| Guard | Afirmación sobre `EpicComment` |
| --- | --- |
| `ArchitectureTest:136-141` | "no usa `SoftDeletes`, no castea `active`" → **intencional, reservado** |
| `ResourceUniformityTest:173` | *"Every model whose table carries the active flag must cast it"* → `EpicComment` **no está en la lista** |

El segundo se contradice a sí mismo: su docblock (`:167-170`) promete cobertura universal
pero el bucle escribe la lista a mano:

```php
// ResourceUniformityTest.php:173
foreach ([Customer::class, Project::class, Epic::class, User::class] as $modelClass) {
```

`EpicComment` queda fuera, y su tabla **sí** lleva `active`. Lo mismo en `:156`, que para
soft deletes usa `[Customer, Project, Epic]` y omite a `User` — que sí es soft-deletable
(`User.php:43`).

Impacto: la deriva está documentada en un sitio y contradicha en otro. Un developer que
lea sólo `ResourceUniformityTest` concluirá que `EpicComment` se coló; uno que lea sólo
`ArchitectureTest` concluirá que está bien. Los dos tienen razón a medias.

Recomendación: decidir primero MOD-001, luego:
1. Poner la decisión en `ARCHITECTURE.md` (hoy no está en ningún sitio legible).
2. Derivar las listas de `Schema::getColumnListing()` en vez de escribirlas, **con una
   lista de exclusión explícita** para los modelos exentos y el motivo al lado. Así el
   guard sigue siendo útil y la excepción queda visible.
3. Invertir también `ModelSchemaParityTest:105-111`, que sólo comprueba
   `SoftDeletes ⇒ columna`, nunca `columna ⇒ SoftDeletes`. Es el mismo agujero visto desde
   el otro test.

### MOD-004 — `CustomerListQuery::active()` sin desempate por `id` — CONSISTENCIA, no bug
**Severidad: LOW (bajado de MEDIUM) · Categoría: Consistencia · Confianza: HIGH**

Ficheros: `app/Queries/Customers/CustomerListQuery.php:24` vs
`app/Queries/Projects/ProjectListQuery.php:35` y `app/Queries/Epics/EpicListQuery.php:44`

**Corrección tras verificar el índice único.** La primera versión de este hallazgo decía que
dos clientes con el mismo nombre podían repetirse o desaparecer al paginar. **Eso no puede
ocurrir**: `customers_active_name_unique (active_name)` sobre
`IF(deleted_at IS NULL, name, NULL)` hace el nombre único entre los no borrados, así que
las filas de la lista activa **nunca** empatan en `name`. Igual para `projects`. Sólo en
`epics` el empate es alcanzable (índice sobre `(project_id, active_name)`), y `epics` ya
desempata por `id`.

Lo que queda es una inconsistencia real y menor: la lista activa de customers no desempata
como sí hacen su propia lista de inactivos (`:37`), la de papelera (`:49`) y las de projects
y epics.

**Aplicado:** `->orderBy('id')` en `CustomerListQuery.php:24`. El comentario explica que es
por consistencia, sin insinuar un bug que no existe.

**No aplicado:** el test que recomendaba para exigir el desempate en `active()`/`inactive()`
— es **imposible de escribir** para customers y projects, porque no se pueden crear dos
registros activos con el mismo nombre. El guard actual ya cubre `trashed()`, que es donde
los empates sí existen.

### MOD-005 — columnas del esquema sin documentar en el `@property` de 4 modelos
**Severidad: LOW · Categoría: Documentación · Confianza: HIGH**

PHPStan nivel 9 lee estos docblocks, así que la omisión no es cosmética.

| Falta | Dónde | Pero |
| --- | --- | --- |
| `start_date`, `end_date` | `Project.php:18-34`, `Epic.php:17-31` | **sí** están casteados (`Project.php:55-56`, `Epic.php:53-54`) y leídos por `EpicListTransformer.php:95-96,117-118` |
| `deleted_at` | `User.php:20-37` | `User.php:43` **sí** usa `SoftDeletes`; los otros 4 modelos sí lo documentan |
| `active_name` | `Customer`, `Project`, `Epic` | columna generada en `helpers.php:64`, proyectada por `select('customers.*')` en `CustomerListQuery.php:83` |

`ModelSchemaParityTest:42` mete `active_name` en `SYSTEM_COLUMNS`, así que el test no puede
detectarlo.

Recomendación: añadir las líneas. Sin cambio de código.

### MOD-006 — `two_factor_confirmed_at` es la única columna timestamp sin cast
**Severidad: LOW · Categoría: Consistencia · Confianza: HIGH**

Ficheros: `app/Models/User.php:57-64`, migración `2025_08_14_170933:17`

`User::casts()` castea `active`, `email_verified_at` y `password`, pero no
`two_factor_confirmed_at`. Fortify tampoco lo castea — lo lee pero nunca lo formatea
(`TwoFactorAuthenticatable.php:25`, `InteractsWithTwoFactorState.php:49,63`).

Impacto: latente. `$user->two_factor_confirmed_at` es string, así que un futuro
`->diffForHumans()` o `->toIso8601String()` lanza. El patrón que usa la app está en
`EpicCommentController.php:28`.

Recomendación: `'two_factor_confirmed_at' => 'datetime',` en `User.php:59-63`.

### MOD-007 — `User::casts()` con una firma que PHPStan no puede comprobar
**Severidad: LOW · Categoría: Consistencia · Confianza: HIGH**

Ficheros: `app/Models/User.php:52-56` vs `Project.php:49-51`

```php
// Project.php:49-51 — forma canónica
@return array{start_date: 'date', end_date: 'date', active: 'boolean'}

// User.php:52-56 — genérica
@return array<string, string>
```

Impacto: `User` es el único modelo cuyos casts PHPStan no puede validar. Un typo ahí
(`'datetime'` → `'datatime'`) es silencioso; en los otros tres rompe el build.

Recomendación: alinear con la forma canónica:
`@return array{active: 'boolean', email_verified_at: 'datetime', password: 'hashed'}`.

### MOD-008 — `withTrashed()` repartido entre dos mecanismos, y 3 de 6 son inalcanzables
**Severidad: LOW · Categoría: Mantenibilidad · Confianza: MEDIUM**

Ficheros: `Project.php:66`, `Epic.php:63`, `EpicComment.php:38` vs
`app/Providers/AppServiceProvider.php:61,70` y `CustomerListQuery.php:25`

Tres relaciones lo llevan horneado en la definición; tres sitios de llamada lo aplican
`.withTrashed()` sobre la marcha.

Dato relevante: los tres horneados son **inalcanzables en la práctica**, porque los guards
de borrado hacen imposible un padre en la papelera con hijos vivos
(`AppServiceProvider.php:55-80`). El framework ya cubre los dos `HasManyThrough` por su
cuenta (`HasOneOrManyThrough.php:127-131`), así que los docblocks de `Customer::epics()`
(`Customer.php:66`) y `Project::comments()` (`:78`) son correctos sin ninguna llamada
explícita.

El único que **sí** es load-bearing es `EpicComment::user()` (`EpicComment.php:46`), que
deliberadamente **no** lleva `withTrashed()`: por eso un autor en la papelera resuelve a
`null` y `EpicCommentController.php:27` pinta `__('Deleted user')`. Añadir `withTrashed()`
ahí dejaría esa rama muerta y rompería
`tests/Feature/Epics/EpicCommentTest.php:112-116`.

Recomendación: **no** añadir `withTrashed()`. Poner un comentario en `EpicComment::user()`
explicando que la omisión es la que hace funcionar la etiqueta "Deleted user". Opcional:
notar en los otros tres que son defensivos por el guard de borrado.

---

## 2. Tests que existen en un recurso y no en otro

Recuento de métodos `test_*`:

| Suite | Customers | Projects | Epics |
| --- | --- | --- | --- |
| `tests/Feature/<R>` | 54 | 65 | 79 |
| `tests/Unit/<R>` | 4 | 3 | 3 |
| `tests/Browser/<R>` | 16 | 6 | 9 |

### TEST-001 — `CustomerTest` no comprueba lo que `ProjectTest` y `EpicTest` sí
**Severidad: MEDIUM · Categoría: Testing · Confianza: HIGH**

Ficheros: `tests/Unit/Customers/CustomerTest.php` vs
`tests/Unit/Projects/ProjectTest.php` y `tests/Unit/Epics/EpicTest.php`

Tres diferencias en el mismo test de modelo:

1. **Clase base distinta.** `CustomerTest.php:6` extiende `PHPUnit\Framework\TestCase`;
   `ProjectTest.php:5` y `EpicTest.php:5` extienden `Tests\TestCase`.
2. **No comprueba `active`.** Project y Epic hacen `fill(['active' => false])` y afirman
   `assertTrue($project->active)`. **Customer no comprueba nada de eso** — y `Customer.php:43`
   sí declara ese default.
3. **No comprueba `notes`.** `notes` es `#[Fillable]` en los cinco modelos; ningún test de
   modelo lo toca.

Impacto: el guard que protege "`active` nunca es asignable en masa"
(`ARCHITECTURE.md:67-69`) no tiene cobertura unitaria en el cliente, que es el primer
recurso y el que todos copian.

*Precedente en el repo:* la suite de feature ya lo hace bien con data providers
(`ResourceNotesTest`, `ResourceActivationTest` recorren los tres recursos). La excepción es
justamente la suite unitaria de modelo, que compara recurso a recurso a mano. El arreglo es
seguir el patrón existente.

### TEST-002 — `EpicComment` y `User` no tienen ningún test unitario de modelo
**Severidad: LOW · Categoría: Testing · Confianza: HIGH**

Ficheros: existen `tests/Unit/Customers|Projects|Epics/*Test.php`; no hay equivalente para
`EpicComment` ni `User`

`EpicComment` es el único modelo sin `SoftDeletes` **y** sin cast de `active` (MOD-001), y no
hay ni un test que fije ese comportamiento a nivel de modelo. Lo único que lo ata es
`ArchitectureTest:136-141`, que comprueba la ausencia de dos rasgos del framework, no el
comportamiento.

Recomendación: tras resolver MOD-001, un `tests/Unit/EpicComments/EpicCommentTest.php` que
afirme `fill()` con `body` y que `epic_id`/`user_id` no son asignables.

### TEST-003 — `Customer` es el único con Dusk de principio a fin
**Severidad: LOW · Categoría: Testing · Confianza: HIGH**

Ficheros: `tests/Browser/Customers/CustomerCrudTest.php` (16 tests) vs
`tests/Browser/Projects/ProjectCrudTest.php` (6),
`tests/Browser/Epics/EpicCrudTest.php` (9)

`Project` y `Epic` tienen Dusk de CRUD pero no cubren papelera, inactivos ni el drawer de
comentarios. `ARCHITECTURE.md:103` dice *"Customer CRUD UI behavior is covered by Laravel
Dusk tests"*, lo que es cierto pero fácil de leer como "los tres lo están".

Recomendación: anotar la asimetría en `ARCHITECTURE.md`. Si se quiere paridad, empezar por
la papelera de epics, que es el flujo más largo.

---

## 3. Código repetido

### DUP-001 — once FormRequests son alias de una línea
**Severidad: INFO · Categoría: Duplicación · Confianza: HIGH**

Medido con `diff` (sólo cambian las líneas 5, 7 y 11):

- `Customer|Project|Epic ListRequest` — 13 líneas c/u
- `Customer|Project SelectOptionsRequest` — 13 líneas c/u
- `Customer|Project|Epic RestoreRequest` y `...DestroyRequest` — 21 líneas c/u, con un
  `findTrashed()` byte-idéntico salvo el nombre de clase (`CustomerDestroyRequest.php:16-20`)

Por qué existe: `ArchitectureTest` exige el set, y Laravel resuelve el FormRequest desde
el type hint del controller. **Ambas razones son reales.**

**Recomendación explícita: NO colapsarlos.** Eliminar las subclase rompería los type hints
y el test de arquitectura a cambio de ~150 líneas. Duplicación conocida y aceptada.

### DUP-002 — `EpicListTransformer::payloadFor()` es `editPayload()` copiada
**Severidad: MEDIUM · Categoría: Duplicación · Confianza: HIGH**

Ficheros: `app/Transformers/EpicListTransformer.php:110-125` vs `:131-145`

Dos arrays de 10 claves, idénticos, con una diferencia: `payloadFor()` calcula
`'project_label' => $epic->project->fullName()` en línea, `editPayload()` delega en
`projectLabel()` (`:147-150`), que hace exactamente lo mismo.

Impacto: **dos sitios que tocar** al añadir un campo al payload del drawer. Y
`ResourceUniformityTest:286-297` sólo fija las claves de `editPayload`, así que la deriva de
`payloadFor` se detecta sólo cuando el drawer se rompe.

Canónico = `editPayload()`, la que el guard comprueba.

Recomendación: reducir `payloadFor()` a `loadMissing('project.customer')` +
`return $this->editPayload($epic);` y borrar `projectLabel()`.

### DUP-003 — `blockDeletionWithChildren()` escribe el mismo bloque tres veces
**Severidad: MEDIUM · Categoría: Duplicación · Confianza: HIGH**

Ficheros: `app/Providers/AppServiceProvider.php:55-62`, `:64-71`, `:73-80`

Tres copias de 7 líneas: transacción, `lockForUpdate()`, `firstOrFail()`, `exists()`. La
única diferencia real es la relación (`projects`/`epics`/`comments`) y que la tercera no
lleva `->withTrashed()` — correcto, porque comentarios no se papelcean.

Impacto: `ARCHITECTURE.md:83-87` llama a esto *"the one transaction boundary that does
exist"*. Es decir, la invariante de seguridad del proyecto está escrita tres veces y las
tres tienen que mantenerse en paso.

Recomendación: un método privado `refuseDeleteWithChildren(Model $m, string $relation)`
y reducir cada closure a una línea. El guard sigue siendo explícito y greppable por modelo.

### DUP-004 — los tres controllers de inactivos: 51 líneas c/u, ~40 idénticas
**Severidad: LOW · Categoría: Sobreingeniería · Confianza: MEDIUM**

Ficheros: `CustomerInactiveController.php`, `ProjectInactiveController.php`,
`EpicInactiveController.php` (51 c/u) vs `app/Http/Controllers/InactiveController.php` (46)

Medido con `diff`: difieren **sólo** en el nombre del recurso.

El punto: la base extrae `deactivateRecord()`/`reactivateRecord()` (`:17-35`, 19 líneas) y
dos stubs de ruta de 3 líneas, pero **deja duplicadas las 12 líneas de `index()`**, que es
lo que más se repite. La abstracción no extrae el código repetido.

Balance: base 46 + 3×51 = 199 líneas, frente a ~3×45 = 135 sin base. **−28 líneas y un
concepto menos** si se borra la base.

**Recomendación: judgement call.** Si se quiere cortar, borrar `InactiveController` y que
cada controller lleve sus propios cuerpos de 9 líneas. **No hacerlo** si se prefiere la
simetría con `TrashController` (que sí está justificada — ver DUP-005).

### DUP-005 — `TrashController` sí paga por sí mismo: no tocarlo
**Severidad: INFO · Categoría: Mantenibilidad · Confianza: HIGH**

Ficheros: `app/Http/Controllers/TrashController.php:30-95`; subclases de 73/73/78 líneas

La base contiene lógica real — `restoreTrashed()` (23 líneas), `destroyTrashed()`,
`takenBy()`, `forceDeleteLocked()` — usada por las tres subclases, que sólo nombran modelo,
ruta y (en Epic) el `takenBy()` con ámbito de proyecto. Se anota para que la siguiente
revisión no lo "simplifique" fuera. Es el contraste que hace defendible el sí de DUP-004.

---

## 4. Sobreingeniería y código muerto

### OE-001 — `epic_comments.notes` se puede escribir pero nunca se lee
**Severidad: MEDIUM · Categoría: Bug/Corrección · Confianza: HIGH**

Confirmado por dos revisores independientes y verificado a mano. Cadena completa:

| Punto | Fichero:línea |
| --- | --- |
| Columna | `app/Support/Database/helpers.php:17` |
| `#[Fillable]` | `app/Models/EpicComment.php:27` |
| Validada | `app/Http/Requests/EpicCommentRequest.php:38` |
| **UI que la pide** | `resources/views/epics/form.blade.php:93-100` (textarea `commentNotes`) |
| **UI que la lee** | **ninguna** — `epics/form.blade.php:134-144` pinta `comment.author`, `comment.dateTime`, `comment.body` |
| **Serialización** | `EpicCommentController.php:25-30` devuelve sólo `id`, `author`, `dateTime`, `body` |

Impacto: el usuario escribe notas en cada comentario y **ninguna superficie puede volver a
mostrarlas**. `EpicCommentController` sólo tiene `show` y `store` — no hay ruta de edición,
así que tras la request el valor es irrecuperable.

Contraste: el `notes` de customers/projects/epics **sí** se lee, vía `editPayload` en los
transformers (`EpicListTransformer.php:116`). `epic_comments` no tiene ese camino.

Recomendación: decisión de producto, no técnica.
- Si las notas de comentario no se van a mostrar: borrar columna, `notes` del `#[Fillable]`,
  la regla, el textarea y sus espejos JS (`epics/list.blade.php:24,107,131`).
- Si sí: añadir `notes` al mapa de `EpicCommentController.php:25-30` y al render de
  `epics/form.blade.php:134-144`.

### OE-002 — una segunda ruta de motor de base de datos que nadie usa
**Severidad: LOW · Categoría: Sobreingeniería · Confianza: HIGH**

Ficheros: `app/Support/Database/helpers.php:7-10` y `:52-61`,
`tests/Feature/Support/Database/DatabaseHelpersTest.php:83,114-115`

`isSqliteOrPgsql()` bifurca `addUniqueActiveNameIndex()` entre índice parcial (SQLite/PG) y
columna generated + único (MySQL/MariaDB). El proyecto es **sólo** MySQL/MariaDB:
`phpunit.xml:26` fija `DB_CONNECTION=mysql`, `.env.example:26` idem,
`ARCHITECTURE.md:52-53` lo dice, y el motor real confirmado es `mysql`.

El test que la cubre **falsea su propia premisa**: no hay SQLite ni PostgreSQL en el
proyecto, así que `DatabaseHelpersTest` verifica la selección de rama falseando el nombre
del driver, no un comportamiento real.

Refuerza el punto: los índices de FK (`projects.customer_id`, `epic_comments.epic_id`,
`epic_comments.user_id`) existen en MariaDB **sólo porque InnoDB los crea
automáticamente** — `Blueprint::foreign()` no emite índice. En la rama SQLite/PG que el
propio helper soporta, esos índices **no existirían**.

Contra-argumento honesto: es seguro barato. Si el equipo lo valora, **dejarlo y sólo
documentarlo**.

Nota: `database/database.sqlite` existe en disco pero está en `.gitignore`
(`database/.gitignore`: `*.sqlite*`) y no está versionado — no cuenta como evidencia.

### OE-003 — el docblock de `RECENT_COMMENTS_LIMIT` describe algo que no ocurre
**Severidad: LOW · Categoría: Documentación · Confianza: HIGH**

Ficheros: `app/Queries/Epics/EpicListQuery.php:16-18` vs `:118`

```php
// EpicListQuery.php:16-18
/** Only the most recent comments are embedded in each list row; the total is in comments_count. */
public const RECENT_COMMENTS_LIMIT = 20;
```

Pero `withProjectAndCustomer()` (`:111-119`) sólo hace `->withCount('comments')` — **no
embebe ningún comentario**. El único consumidor es
`EpicCommentController.php:20` (`->limit(...)`) en el endpoint `show`, y el test
`EpicCommentTest.php:80` confirma que la carga es on-demand.

Impacto: la constante es `public` para que un controller alcance dentro de una clase de
query, y su docblock afirma una invariante que nadie mantiene. Quien optimice el list query
buscará algo que no está.

Recomendación: corregir el docblock. Dejar la visibilidad como está.

### OE-004 — `PER_PAGE` nombra dos cosas distintas
**Severidad: INFO · Categoría: Nomenclatura · Confianza: HIGH**

Ficheros: `app/Queries/ListQueryBase.php:14`, `CustomerSelectOptionsQuery.php:30`,
`ProjectSelectOptionsQuery.php:38`

`PER_PAGE = 25` es el tamaño de página de los listados, y a la vez el tope de resultados de
los select remotos. `ResourceUniformityTest:206-234` sólo lo comprueba contra el page size
de las listas.

Impacto: hoy ninguno — pero cambiar el page size a 10 recortaría en silencio el desplegable
de los selects a 10, y ningún test lo cubre.

Recomendación: constante propio para los selects, o una línea en el docblock diciendo que
están ligados a propósito. Relacionado con `todo.md:11`.

### DB-001 — la base de datos de desarrollo no coincide con las migraciones
**Severidad: MEDIUM · Categoría: Drift de entorno · Confianza: HIGH**

Este es el hallazgo más sorprendente de la revisión y **no** es una incongruencia entre
modelos, pero pertenece aquí porque invalida la base de las pruebas de MOD-001.

Verificado con `SHOW CREATE TABLE epic_comments` sobre la BD `laravel` en vivo:

```sql
CONSTRAINT `epic_comments_created_by_foreign` FOREIGN KEY (`created_by`)
  REFERENCES `users` (`id`) ON DELETE SET NULL,   -- audit: SET NULL
CONSTRAINT `epic_comments_updated_by_foreign` FOREIGN KEY (`updated_by`)
  REFERENCES `users` (`id`) ON DELETE SET NULL,
CONSTRAINT `epic_comments_deleted_by_foreign` FOREIGN KEY (`deleted_by`)
  REFERENCES `users` (`id`) ON DELETE SET NULL,
CONSTRAINT `epic_comments_epic_id_foreign` FOREIGN KEY (`epic_id`)
  REFERENCES `epics` (`id`),                     -- sin cláusula: RESTRICT
CONSTRAINT `epic_comments_user_id_foreign` FOREIGN KEY (`user_id`)
  REFERENCES `users` (`id`)                      -- sin cláusula: RESTRICT
```

En **la misma tabla**, las FK de auditoría son `SET NULL` y las de negocio son `RESTRICT`.

Las migraciones no pueden producir eso:
- `app/Support/Database/helpers.php:34-36` usa `->constrained('users')` sin `nullOnDelete()`.
- `grep -rn "nullOnDelete\|cascadeOnDelete\|onDelete" app/ database/` → **ningún resultado**.

Y el resto del proyecto dice `RESTRICT` a propósito:
- `app/Support/Database/helpers.php:30`: *"Foreign keys **restrict** deleting a user while
  an audit record still points to them."*
- `tests/Feature/Support/Database/DatabaseHelpersTest.php:68-69`:
  `$author->forceDelete(); self::fail('A user with audit references must not be force deleted.');`
- `tests/Unit/Concerns/TracksAuditColumnsTest.php:191-192`: mismo patrón.

Impacto: contra la BD de desarrollo, un `forceDelete()` de un usuario con registros de
auditoría **tiene éxito** y pone las columnas a `NULL`, donde los tests dicen que debe
fallar. Quien depure un borrado de usuario en local sacará la conclusión contraria a la de
CI.

Recomendación: **`migrate:fresh` sobre la BD de desarrollo** para re-derivarla de las
migraciones, y confirmar que los dos tests pasan contra `laravel_test`. **No** "arreglar"
`helpers.php` para que case con la BD de desarrollo: el docblock y los tests son la
evidencia de intención más fuerte.

*No verificado:* si `laravel_test` tiene `RESTRICT` o `SET NULL`. El MCP sólo alcanza la
conexión por defecto. Lo más probable es `RESTRICT`, porque `RefreshDatabase` re-migra en
cada run.

---

## Hallazgos descartados (abogado del diablo)

| Descartado | Por qué |
| --- | --- |
| **`FieldHints` (222 líneas) es código muerto** | **Falso, y por poco.** Lo usa `resources/views/components/forms/field-label.blade.php:24` y `:31`. Casi se reporta. |
| **`MaxLength` es sobreingeniería** | **Falso.** 6 ficheros de `app/` lo usan; existe para tipar un `config()` que devuelve `mixed`. |
| Colapsar los 11 FormRequests alias | **Rechazado.** Rompería los type hints y `ArchitectureTest`. Ver DUP-001. |
| **MOD-001: "el guard dejó colarse la deriva"** | **Falso.** `ArchitectureTest:130-142` la fija a propósito. Corregido: el problema es que dos guards se contradicen, no que uno falle. Ver MOD-003. |
| Añadir 3 índices compuestos para los listados | **Rechazado.** `EXPLAIN` confirma `Using filesort`, pero las tablas están vacías, `PER_PAGE=25` y no hay benchmark. `review-rules.md` prohíbe optimizar especulativamente. Reevaluar con el seeder de rendimiento de `todo.md:24`. |
| `epic_comments (epic_id, created_at)` | **Aceptado sólo como parte de OE-002/MOD-001**: se sustituye el índice muerto `(deleted_at,id)` por uno útil en la misma migración. No es optimización, es cambiar índice muerto por vivo. |
| Derivar nombres de ruta desde el modelo en los controllers | **Rechazado.** Refactor sin problema demostrado; el mapa de los transformers es una tabla de datos legítima leída 10 veces. |
| Unificar las tres `selectableParentRule()` | **Rechazado.** 2 sitios, padres y reglas distintas; un trait sería peor. |
| Borrar `ListTransformer::searchPlaceholder()` | **Rechazado.** 15 líneas de docblock defensivo; es estilo, no defecto. |
| `restoreTrashed()` sin lock | **Verificado correcto.** `TrashController.php:30-53` no bloquea, pero el índice único `active_name` es el guard real: `TrashController.php:40-46` captura el `QueryException`. La pre-comprobación `nameIsTaken()` es sólo para el mensaje. Sin defecto. |
| `active_name` generated column | **Verificado correcto** en MariaDB. Diseño coherente; los 3 supuestos (NULLs múltiples en índice único, recálculo en UPDATE de restore, `STORED` en `SELECT *`) son estándar pero **no ejecutados** aquí. |
| `end_date` divergente (`after_or_equal` Project vs `after` Epic) | **Falso.** Intencionado y documentado en `ARCHITECTURE.md:75-79`. |
| Los 4 bloques `$attributes` idénticos | **Rechazado.** Un trait para un literal de 3 líneas añade indirección. |
| `#[Fillable]` en distinto orden | **Rechazado.** Todos replican el orden de `rules()` de su request. |
| Divergencia de fechas en `ProjectFactory` vs `EpicFactory` | **Falso.** Replica exactamente `after_or_equal` vs `after`. |
| Docblocks ausentes en migraciones | **Rechazado.** Estilo puro; `AGENTS.md` no lo pide. |

---

## Plan de acciones

Ordenado por impacto.

### 1. El drift de la base de datos  ← verificar antes de nada más
- [ ] **DB-001** — `migrate:fresh` sobre la BD de desarrollo para re-derivarla de las
      migraciones. Confirmar después que `DatabaseHelpersTest` y `TracksAuditColumnsTest`
      pasan contra `laravel_test`. Si `laravel_test` también estuviera en `SET NULL`, esos
      dos tests deberían estar fallando ya: comprobarlo.

### 2. Decidir `EpicComment` y escribir la decisión
- [ ] **MOD-001** — Elegir (a) skinny helper sin `active`/`softDeletes`, o (b) modelo
      completo. Recomendada (a). Si es (a), sustituir el índice muerto
      `(deleted_at,id)` por `(epic_id, created_at)`, que es lo que realmente consulta
      `EpicCommentController.php:16-21`.
- [ ] **MOD-003** — Poner la decisión en `ARCHITECTURE.md`; derivar las listas de
      `Schema::getColumnListing()` con exclusión explícita y motivo; invertir la comprobación
      de `ModelSchemaParityTest:105-111`.
- [ ] **MOD-002** — Una línea en el docblock de `EpicCommentFactory` explicando por qué no
      lleva `HasStates`.
- [ ] **TEST-002** — Test unitario de `EpicComment` fijando lo que se decida.

### 3. `epic_comments.notes`: decisión de producto
- [ ] **OE-001** — Decidir: mostrar las notas en `EpicCommentController.php:25-30` +
      `epics/form.blade.php:134-144`, o borrarlas de punta a punta. Hoy el usuario escribe
      algo que no vuelve a ver nunca.

### 4. El bug de paginación
- [ ] **MOD-004** — `->orderBy('id')` en `CustomerListQuery.php:24`, y extender
      `ResourceUniformityTest:206` para exigir desempate en `active()` e `inactive()`.

### 5. Documentación que hoy miente
- [ ] **DB-003** (`ARCHITECTURE.md:80-81`) — Dice *"Epic comments are removed when their epic
      is permanently deleted and keep a null author when the user is deleted"*. **Las dos
      partes son incorrectas**: el FK `epic_comments.epic_id` es `RESTRICT` (sin cascada) y
      el guard de `AppServiceProvider.php:73-80` impide borrar el epic con comentarios, así
      que un epic con comentarios **nunca** se borra en definitivo. Y `user_id` es `NOT NULL`
      con `RESTRICT`: un borrado duro de un usuario que comentó lo rechaza la base de datos;
      el "autor nulo" sólo existe en Eloquent y sólo para borrado lógico, porque
      `User` usa `SoftDeletes` y `EpicComment::user()` omite `withTrashed()`.
- [ ] **MOD-005** — Añadir `start_date`/`end_date` a los `@property` de Project y Epic,
      `deleted_at` al de User, `active_name` a los tres.
- [ ] **MOD-006 / MOD-007** — Cast de `two_factor_confirmed_at`; forma de `User::casts()`.
- [ ] **OE-003** — Corregir el docblock de `RECENT_COMMENTS_LIMIT`.
- [ ] **TEST-003** — Anotar en `ARCHITECTURE.md:103` que Dusk cubre customers en completo.
- [ ] **MOD-008** — Comentario en `EpicComment::user()`.

### 6. Duplicación que sí tiene problema
- [ ] **DUP-002** — `payloadFor()` → delegar en `editPayload()`.
- [ ] **DUP-003** — `blockDeletionWithChildren()` a un método privado.
- [ ] **TEST-001** — Alinear `CustomerTest` con sus hermanos: `Tests\TestCase` + los dos
      casos de `active`.

### 7. Opcional (decisión de gusto)
- [ ] **DUP-004** — Borrar `InactiveController`: −28 líneas y una indirección menos.
- [ ] **OE-002** — Decidir si el proyecto sigue soportando SQLite/PostgreSQL.
- [ ] **OE-004** — Constante propia para el tope de los selects.

---

## Comprobación final obligatoria

```
DX php artisan test --compact tests/Unit/ArchitectureTest tests/Unit/ValidationCoverageTest \
  tests/Unit/ModelSchemaParityTest tests/Unit/ResourceUniformityTest
DX ./vendor/bin/phpstan analyse
DX ./vendor/bin/pint --dirty
```

> `pint --dirty` es el correcto aquí: `pint --test` sólo informa y no reescribe ficheros,
> así que no sirve para dejar el código formateado tras editar.
