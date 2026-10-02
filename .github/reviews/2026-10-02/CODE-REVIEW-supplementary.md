# Code Review — Auditoría independiente (2026-10-02)

> **Por qué este fichero existe en lugar de sobrescribir `CODE-REVIEW.md`.** Durante esta auditoría
> otra sesión de revisión estaba escribiendo en `.github/reviews/2026-10-02/`. Al intentar guardar
> este informe, el fichero `CODE-REVIEW.md` ya había sido sobrescrito por esa sesión (45 KB, con sus
> propios informes por especialista). Para no destruir trabajo ajeno —ni que el mío fuese destruido—
> este informe se guarda en una ruta sin colisión dentro del **mismo directorio fechado**, nunca en la
> raíz del repositorio.
>
> Este informe es **independiente y complementario**. Reconstruye los flujos, verifica cada hallazgo
> contra el código y el framework, y añade lo que el informe contiguo no cubre. Donde coincidimos, se
> dice explícitamente.

## Executive Summary

La aplicación está en muy buen estado: PHPStan nivel 9 sin errores, Pint limpio, `composer audit` sin
avisos y **Dusk 15/15 verde** (el informe contiguo lo registró como NOT RUN; aquí sí se ejecutó).
La arquitectura respeta `ARCHITECTURE.md`, los controladores son finos, la validación vive en Form
Requests y la integridad de unicidad está duplicada a propósito en validación **y** en base de datos.

Confirmo de forma independiente el defecto que hace rojo el CI: `EpicCommentTest:77` llama a
`viewData()` sobre una respuesta que nunca es una `View`.

Aporto cuatro cosas que el informe contiguo no cubre:

1. **El impacto real de `phpunit.xml` es pérdida de datos de desarrollo, no sólo falta de paridad.**
   Verificado empíricamente: el comando documentado en `AGENTS.md` ejecuta `migrate:fresh` sobre la
   base de datos **de desarrollo**.
2. **El escapado de `LIKE` depende del `sql_mode` del servidor** — un fallo de producción que no
   aparece en MariaDB pero que ya falla en el motor que el proyecto declara soportado.
3. **`epic_comments` ha quedado con deriva de esquema**: el commit `fcf8d8d` declara `softDeletes()` y
   `active` en una migración `create` que ya estaba ejecutada, y Verified contra la base real que las
   columnas **no existen** y `migrate` no las volverá a crear.
4. **Fallo silencioso del refresco de lista**: un error de red deja la tabla obsoleta sin avisar.

Sobre seguridad: los tres policies devuelven `true` siempre, pero es decisión de producto documentada
y **las 12 rutas de escritura autorizan**. No hay IDOR, ni XSS, ni inyección SQL.

## Detected Stack

Verificado contra `composer.json`, `composer.lock`, `package.json`, `docker-compose.yml`,
`.github/workflows/tests.yml` y `laravel-boost application-info`. **HEAD durante la auditoría:**
`fcf8d8d` ("migrations"), con `.github/tasks/*` sin commitear.

| Elemento | Valor |
|---|---|
| PHP | 8.4 (contenedor); CI 8.3; `composer.json` exige `^8.3` |
| Laravel | 13.34.0 |
| Frontend | Blade + Livewire 4.4.7 + Flux (free) 2.20.1 + `livewire/blaze`; Alpine |
| Assets | Vite 8 / `vite-plus` 0.3.0 + Tailwind 4.3.3; jQuery 3.7.1 + Select2 4.1.0 |
| Auth | `laravel/fortify` 1.40.0 (password, passkeys, 2FA, email verification, registro) |
| Base de datos | MariaDB 11.7 (Docker), motor `mysql`; SQLite `:memory:` como motor efímero de test |
| Colas | `database` (tablas de stock), **ningún job de aplicación** |
| Redis / Horizon / Telescope | **No existen** (sin cliente Redis configurado, sin Horizon instalado) |
| Tests | PHPUnit 12.5.37, `brianium/paratest` 7.20, `laravel/dusk` 8.7 |
| Análisis estático | `larastan` 3.12.2 / PHPStan 2.2.16 **nivel 9** |
| Formato | `laravel/pint` 1.32.1 |
| Docker | `docker-compose.yml` + `Dockerfile.dusk` (`webdevops/php-apache-dev:8.4` + chromium) |
| CI/CD | `.github/workflows/tests.yml` (jobs `changes`, `ci`, `dusk`), acciones fijadas por SHA; Dependabot semanal |
| API | **No existe** `routes/api.php`; sólo rutas web |

## Checks Executed

Todos dentro del contenedor `laravel13` / `laravel13-dusk`, que es el entorno que declara `AGENTS.md`.
Ningún comando modificó datos de aplicación.

| Check | Comando | Resultado |
|---|---|---|
| Static analysis | `./vendor/bin/phpstan analyse` (nivel 9) | **PASS** — `[OK] No errors` |
| Formato | `./vendor/bin/pint --parallel --test` | **PASS** — `PASS 128 files` |
| Auditoría de deps | `composer audit` | **PASS** — `No security vulnerability advisories found.` |
| Suite PHPUnit | `DB_CONNECTION=sqlite DB_DATABASE=":memory:" php artisan test --compact` | **FAIL** — `Tests: 7 failed, 230 passed (1196 assertions)` |
| Suite PHPUnit (2ª a 4ª ejecución) | ídem, tres veces más | **FAIL** — `7 failed, 230 passed`, estable |
| Suite Dusk | `php artisan dusk` (servicio `laravel13-dusk`) | **PASS** — `Tests: 15 passed (121 assertions)`, 87.99s |
| Esquema real | `information_schema` vía Boost (sólo `SELECT`) | Evidencia de DB-001 / DB-002 |

Notas sobre la ejecución:

- Se eligió **SQLite en memoria** por las reglas de seguridad de la revisión (no tocar ninguna base
  persistente). `tests/Browser/**` usa `DatabaseMigrations`, pero `DuskTestCase::getEnvironmentSetUp()`
  fuerza `laravel_test`, que es la base de test aislada que crea el propio CI
  (`.github/workflows/tests.yml:180-183`) y que `DuskTestCase::setUp()` verifica con un `assertSame`.
  **No se ha escrito en `laravel`.**
- Desglose de los 7 fallos de SQLite: 3 en `CustomerListQueryTest` (escapado de `LIKE`, ver BUG-002),
  1 en `EpicCommentTest` (BUG-001), 1 en `ProjectCrudTest` y 2 en `ProjectInputValidationTest`
  (artefactos del tipo `DATE` de SQLite, ver *Rejected Findings*).
- El primer run de la suite dio `9 failed, 228 passed (1176 assertions)`, incluyendo
  `ResourceActivationTest::test_inactive_list_paginates_only_inactive_records`. **No reproducido**:
  cuatro ejecuciones posteriores fueron idénticas entre sí (`7 failed, 230 passed`) y ese test pasa
  aislado. Se anota como observación no reproducida, no como hallazgo.
- **NOT RUN**: `npm run build`, `npm run lint`, `npm run typecheck`. `package.json` sólo define `build`
  y `dev`; no hay `lint` ni `typecheck` y CI (node 22) tampoco los ejecuta. El proyecto declara
  `node:24-bullseye`; no se ha ejecutado ningún build para no tocar assets generados.
- **NOT RUN**: `composer setup`, `migrate:fresh`, seeders y cualquier comando que escriba en base de
  datos, por regla de la revisión.

## Critical Findings

Ninguno.

## High Findings

### TEST-001 — La base de datos de test de `phpunit.xml` es inerte: el comando documentado borra la base de datos de desarrollo

Severity: HIGH
Category: Testing / DevOps
File: phpunit.xml, docker-compose.yml, AGENTS.md, docker/run-phpunit-with-coverage.sh
Line: 26 (phpunit.xml), 181 (docker-compose.yml), 43 (AGENTS.md)
Confidence: HIGH

Coincide con `TEST-002` del informe contiguo, que lo enmarca como falta de paridad local/CI. Mi
verificación añade el dato que cambia la severidad: **no es sólo que las bases no coincidan; es que
la base de desarrollo recibe `migrate:fresh`.**

Problem:

`phpunit.xml` declara `DB_DATABASE=laravel_test` como `<env>` **sin `force="true"`**, pero Laravel
exporta los valores de `.env` con `putenv()` antes de que arranque PHPUnit. En ese momento
`getenv('DB_DATABASE')` ya vale `laravel`, así que PHPUnit **no** aplica el valor del XML. El comando
documentado en `AGENTS.md` (`DX php artisan test`) y el servicio `laravel13-phpunit` ejecutan los tests
con `RefreshDatabase` contra la base de datos de **desarrollo**.

Evidence:

- `vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:133-149`
  ```php
  if ($force || getenv($name) === false) { putenv("{$name}={$value}"); }
  ```
  Sin `force="true"`, el valor del XML se ignora si la variable ya existe.
- `vendor/laravel/framework/src/Illuminate/Support/Env.php:19` → `protected static $putenv = true;`
  y `Env.php:81-82` añade `PutenvAdapter`, por lo que `.env` sí llega a `putenv()`.
- Sonda de sólo lectura en el contenedor (bootstrap del kernel de consola; no toca la BD):
  ```
  getenv(DB_DATABASE)='laravel'
  getenv(DB_CONNECTION)='mysql'
  config(database.connections.mysql.database)=laravel
  ```
- `docker-compose.yml:181-192` — el servicio `laravel13-phpunit` no fija `DB_DATABASE`, y
  `docker/run-phpunit-with-coverage.sh` ejecuta `composer test:coverage` → `php artisan test --coverage`.
- Contraste que demuestra que la intención existe: `tests/DuskTestCase.php:34-38` fuerza
  `database.connections.mysql.database = laravel_test` en `getEnvironmentSetUp()` y
  `DuskTestCase.php:20` lo verifica con `assertSame`. PHPUnit no tiene equivalente.
- CI tampoco usa `laravel_test`: `.github/workflows/tests.yml:48-53` exporta `DB_DATABASE: laravel`,
  así que el job `ci` recorre exactamente el mismo camino y `phpunit.xml` es inerte allí también.

Impact:

El primer `php artisan test` —o `docker compose run laravel13-phpunit`— ejecuta `migrate:fresh` sobre
`laravel` y destruye los datos de desarrollo, contradiciendo la declaración de `phpunit.xml` y la nota
de `AGENTS.md` ("nunca en CI — shared DB"). El aislamiento que el proyecto cree tener no existe: un
test que dependa de datos ajenos, o una futura migración destructiva, afectarían a la base de
desarrollo sin que nadie lo note.

Recommendation:

Hacer real la base de test en los dos extremos (son cambios de configuración, no de código):

1. `phpunit.xml`: añadir `force="true"` a los `<env>` de base de datos (`DB_CONNECTION`,
   `DB_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`) para que el valor del XML gane.
2. `.github/workflows/tests.yml`, job `ci`: replicar el paso "Create Dusk database" del job `dusk`
   antes de `composer ci:check`, porque con `force="true"` la suite pasará a pedir `laravel_test`.

Verificar después con `DB_CONNECTION=mysql php artisan test --filter=ModelSchemaParityTest` y
comprobando que `laravel` conserva sus filas.

### DB-001 — `epic_comments` ha quedado con deriva de esquema: `deleted_at` y `active` no se aplicarán a ninguna base ya migrada

Severity: HIGH
Category: Database / Destructive migration
File: database/migrations/2026_09_30_175707_create_epic_comments_table.php
Line: 20-21
Confidence: HIGH

Hallazgo **nuevo**, posterior al baseline del informe contiguo (`5cd03c9`); introducido por el
commit `fcf8d8d` ("migrations") mientras esta auditoría estaba en curso.

Problem:

El commit `fcf8d8d` ha plegado la columna `active` dentro de las migraciones `create_*` y ha **borrado**
`2026_10_02_180040_add_active_to_domain_and_users_tables.php`, y además ha añadido
`softDeletes()` + `active` a `create_epic_comments_table`. Editar migraciones ya ejecutadas es
inofensivo para `customers`/`projects`/`epics`/`users` **sólo porque** la columna `active` ya existe
en las bases que corrieron la migración antigua. Para `epic_comments` no hay ninguna migración que
aporte `deleted_at` ni `active`: la única fuente es su migración `create`, que ya está registrada en
la tabla `migrations` y por tanto `php artisan migrate` no volverá a ejecutar.

Evidence:

- `database/migrations/2026_09_30_175707_create_epic_comments_table.php:20-21`
  ```php
  $table->softDeletes();
  $table->boolean('active')->default(true);
  ```
- `database/migrations/` ya **no** contiene `2026_10_02_180040_add_active_to_domain_and_users_tables.php`.
- Tabla `migrations` de la base `laravel` (consulta de sólo lectura):
  `2026_09_30_175707_create_epic_comments_table` está registrada (batch 1) y
  `2026_10_02_180040_add_active_to_domain_and_users_tables` también (batch 2), aunque su fichero ya no existe.
- Esquema real de `laravel` tras el commit (consulta de sólo lectura a `information_schema`):
  ```
  epic_comments -> id, epic_id, user_id, body, created_at, updated_at      (NO hay deleted_at, NO hay active)
  ```
  mientras que `customers`, `projects`, `epics` y `users` sí tienen `active`.
- Los modelos declaran esas columnas: `app/Models/EpicComment.php` todavía **no** usa `SoftDeletes`,
  pero `tests/Unit/ModelSchemaParityTest.php:28-42` recorre `SYSTEM_COLUMNS` —incluyendo `active` y
  `deleted_at`— y exigirá que todo modelo con `SoftDeletes` tenga `deleted_at` en el esquema.

Impact:

Cualquier base de datos que ya haya ejecutado `2026_09_30_175707` —la de desarrollo, la de test y
cualquier entorno compartido— se queda sin esas dos columnas para siempre, porque no queda ninguna
migración que las añada. En cuanto el código o los tests las usen, falla en runtime con
`Unknown column 'deleted_at'`, y `php artisan migrate`Informará que no hay nada pendiente. La única
salida sería `migrate:fresh`, es decir, destruir los datos. El CI no lo detectaría, porque crea la
base desde cero y las columnas sí se crean.

Recommendation:

Añadir una migración **nueva** que añada `deleted_at` y `active` a `epic_comments` con
`Schema::table(...)`, y no tocar las migraciones `create_*` ya ejecutadas. Si el objetivo del commit
`fcf8d8d` era simplemente "no dejar la migración `add_active` colgando en un proyecto sin historial
publicado", entonces el enfoque correcto es `migrate:fresh` sobre las bases locales **y** publicar el
esquema consolidated como migración inicial, no reescribir las anteriores.

## Medium Findings

### BUG-001 — `EpicCommentTest` no puede pasar nunca y deja sin cubrir el límite de comentarios recientes

Severity: MEDIUM
Category: Bugs / Correctness / Testing
File: tests/Feature/Epics/EpicCommentTest.php, app/Http/Controllers/EpicController.php
Line: 77 (test), 30 (controlador)
Confidence: HIGH

**Coincide con `BUG-001` del informe contiguo.** Aporto la cadena de pruebas a nivel de framework.

Problem:

El test pide `$response->viewData('list')`, pero los doce métodos `index()` devuelven
`view(...)->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results')`, y
`View::fragmentIf()` devuelve `$this->render()` —**un string**— cuando la condición es falsa. La
respuesta nunca es una `View`, así que `TestResponse::viewData()` aborta.

Evidence:

- `vendor/laravel/framework/src/Illuminate/View/View.php:114-122`
  ```php
  public function fragmentIf($boolean, $fragment)
  {
      if (value($boolean)) { return $this->fragment($fragment); }
      return $this->render();          // string
  }
  ```
- `vendor/laravel/framework/src/Illuminate/Routing/Router.php:928-929` envuelve ese string en
  `new Response($response->__toString(), 200, ['Content-Type' => 'text/html'])`, de modo que
  `$response->original` es un `Illuminate\Http\Response`, no una `View`.
- `vendor/laravel/framework/src/Illuminate/Testing/TestResponse.php:1429-1433` →
  `fail('The response is not a view.')`.
- Ejecución real:
  `php artisan test --filter=test_list_embeds_only_the_most_recent_comments_of_each_epic_but_counts_all`
  → `FAILED Tests\Feature\Epics\EpicCommentTest … The response is not a view. at tests/Feature/Epics/EpicCommentTest.php:77`.
- Fallo **independiente del motor**: es el único uso de `viewData()` en todo el repo
  (`grep -rn "viewData" tests/` → 1 resultado). Por eso aparece en MariaDB y en SQLite.

Impact:

En producción la lista funciona (el HTML se renderiza igual), así que el impacto de usuario es nulo. El
impacto real es de cobertura: este test era el único guardián de
`EpicListQuery::RECENT_COMMENTS_LIMIT = 20` y de `comments_count`, es decir, del hallazgo
PERF-INFO-001 cerrado en la revisión del 2026-09-30. Al no ejecutarse, la regresión que motivó aquello
volvería a entrar sin que nada la detecte.

Recommendation:

Elegir una de las dos, sin tocar nada más:

- Si el test quiere seguir leyendo los datos de la vista: en los doce `index()`, devolver la `View`
  cuando no llega la cabecera (`return $request->hasHeader(...) ? $view->fragment(...) : $view;`) en
  lugar de `fragmentIf()`. Toca 12 líneas y hace truthful el tipo de retorno.
- Si se prefiere conservar `fragmentIf()`: reescribir el test para pedir el fragmento con la cabecera
  `X-List-Fragment: true` (igual que `tests/Feature/ListSearchFragmentTest.php`) y leer el contador con
  el selector `data-test`.

### BUG-002 — El escapado de `LIKE` depende del motor y del `sql_mode` del servidor

Severity: MEDIUM
Category: Bugs / Correctness / Database
File: app/Queries/ListQueryBase.php, tests/Feature/Customers/CustomerListQueryTest.php
Line: 27 (ListQueryBase), 78 / 88 / 98 (test)
Confidence: HIGH

Hallazgo **nuevo**: el informe contiguo agrupa estos tres fallos bajo `TEST-003` ("el camino SQLite
documentado no funciona") sin identificar la causa ni el riesgo de producción.

Problem:

`ListQueryBase::paginate()` escapa `%`, `_` y `\` con `addcslashes()` y deja que el motor aplique su
carácter de escape implícito. MySQL/MariaDB usan `\` por defecto, así que hoy funciona; **SQLite no
tiene carácter de escape implícito**, así que `\%` se interpreta como barra invertida literal seguida
de comodín. `ARCHITECTURE.md:50-51` afirma "SQLite is used for isolated in-memory tests" y las
migraciones tienen ramas explícitas para `sqlite`/`pgsql`, luego SQLite es un motor soportado.

Evidence:

- `app/Queries/ListQueryBase.php:27` → `$escapedSearch = addcslashes($search, '%_\\');`
  y líneas 30-33 construyen `like "%{$escapedSearch}%"` **sin cláusula `ESCAPE`**.
- Ejecución real sobre SQLite: 3 tests fallan con `Failed asserting that two arrays are identical` en
  `test_search_treats_percent_as_a_literal_character` (línea 78),
  `test_search_treats_underscore_as_a_literal_character` (88) y
  `test_search_treats_backslash_as_a_literal_character` (98).
- El comportamiento depende del `sql_mode` del servidor, y hoy el proyecto lo controla por accidente:
  `docker-compose.yml:201` arranca MariaDB con `--sql-mode=""` (sin `NO_BACKSLASH_ESCAPES`) y el CI usa
  `mariadb:11.7` sin `command`, cuyo `sql_mode` por defecto tampoco lo incluye.

Impact:

Dos riesgos. (a) Los tests de escapado no pasan en el motor que el proyecto dice usar para tests
aislados, lo que impide cumplir esa promesa. (b) Si un servidor MySQL/MariaDB de producción o staging
activa `NO_BACKSLASH_ESCAPES`, la búsqueda de un literal `%`, `_` o `\` deja de funcionar y devuelve
filas de más, sin ningún error visible ni entrada de log. Es un fallo silencioso de integridad de
resultados, no un error de disponibilidad.

Recommendation:

Declarar el carácter de escape en la propia consulta en lugar de depender del `sql_mode` del
servidor. Cambio local en `ListQueryBase::paginate()`: sustituir el `where`/`orWhere` por
`whereRaw("{$column} like ? escape '\\\\'", ["%{$escapedSearch}%"])`. Verificar antes el SQL exacto que
genera `escape` en el driver `sqlite` del proyecto, y volver a pasar los tres tests en MariaDB.

### PERF-001 — Cada refresco de lista renderiza la página completa y carga listas de opciones sin límite

Severity: MEDIUM
Category: Performance
File: app/Http/Controllers/ProjectController.php, app/Http/Controllers/EpicController.php, vendor (View::fragment)
Line: 28 (ambos), 30 (ambos)
Confidence: HIGH

**Coincide con `PERF-001` del informe contiguo.** Aporto el coste de renderizado completo, que el
informe contiguo no menciona.

Problem:

Los controladores `index()` calculan **antes** de decidir si la respuesta será un fragmento, y
`View::fragment()` renderiza la vista completa para después extraer el trozo. Además, las opciones de
los desplegables se cargan con `->get()` sin paginar ni límite.

Evidence:

- `app/Http/Controllers/ProjectController.php:26-30`
  ```php
  return view('projects.list', [
      'projects' => $projects,
      'availableCustomers' => Customer::query()->orderBy('name')->get(['id', 'name']),  // sin límite
      'list' => $transformer->active($projects, $search),
  ])->fragmentIf($request->hasHeader('X-List-Fragment'), 'list-results');
  ```
- `app/Http/Controllers/EpicController.php:26-30` — idéntico, con
  `Project::query()->with('customer')->orderBy('name')->get(['id','name','customer_id'])` (2 consultas).
- `vendor/laravel/framework/src/Illuminate/View/View.php:87-91`
  ```php
  public function fragment($fragment) { return $this->render(function () { return $this->factory->getFragment($fragment); }); }
  ```
  es decir, renderiza layout, sidebar, cabecera, los dos modales y el modal de confirmación, y
  descarta todo salvo `[data-list-results]`.
- `resources/js/app.js:446-487` — `refreshList()` se dispara con `x-on:input.debounce.400ms`
  (`searchable-results.blade.php:5`) en cuanto la búsqueda pasa de 3 caracteres (`app.js:391`), y en
  cada click de paginación (`app.js:425-430`).

Impact:

Cada pulsación de teclado en la búsqueda y cada cambio de página ejecutan: 1 consulta paginada de la
lista, 1-2 consultas `get()` sin límite sobre `customers`/`projects` (que crecen de forma monótona y
no se pueden paginar hacia atrás), y el render Blade **completo** para extraer ~50 líneas. Con cientos
de clientes o proyectos el coste por pulsación crece linealmente. El `PER_PAGE = 5` del listado no
protege estas dos consultas.

Recommendation:

Sin cambiar el diseño del fragmento (que funciona y está bien probado):

1. Mover la carga de `availableCustomers` / `availableProjects` **dentro del fragmento**, o cachearla
   (`Cache::remember`) con invalidación al crear/editar el recurso correspondiente. Es el cambio de
   mayor impacto y es local.
2. Si el volumen llega a ser grande, sustituir el `get()` por un endpoint de búsqueda de opciones con
   `limit` (Select2 ya hace fetch asíncrono en el caso project → cliente).

### FRONT-001 — Un fallo en el refresco de lista deja la tabla obsoleta sin avisar

Severity: MEDIUM
Category: Frontend / Bugs
File: resources/js/app.js
Line: 478-481
Confidence: HIGH

Hallazgo **nuevo**, no aparece en el informe contiguo.

Problem:

`refreshList()` es `async` y se invoca sin `await` desde `searchInput`, `submitSearch`, `handleClick` y
`handlePopstate`. En el `catch` se relanza el error cuando la petición **no** fue abortada, lo que
produce una `unhandledrejection` y no deja estado de error para el usuario.

Evidence:

- `resources/js/app.js:478-481`
  ```js
  } catch (error) {
      if (!controller.signal.aborted) { throw error; }
  }
  ```
- Invocada sin `await` en `app.js:393` (`this.search(query)`), `app.js:412`, `app.js:420`, `app.js:429`
  y `app.js:375` (`popstate`).

Impact:

Si el refresco falla por un 500, una sesión caducada o una respuesta sin `data-list-results`
(`app.js:467`), la tabla se queda mostrando los resultados anteriores sin ninguna indicación. La
búsqueda parece "pegada" y el usuario no tiene forma de saber que los datos son viejos; en consola
sólo aparece el error de la promesa.

Recommendation:

Sustituir el `throw` por una señal de error en el estado de Alpine y mostrarla en el contenedor de
resultados, reutilizando el `emptyMessage` que ya viaja en `$list` (los tres `list.blade.php` ya
imprimen `{{ $list['emptyMessage'] }}` en la fila `@empty`). Cambio de ~5 líneas en `app.js`, sin tocar
Blade.

## Low Findings

### CONC-001 — Los guardas "el padre no tiene hijos" no son atómicos

Severity: LOW
Category: Concurrency / Data Integrity
File: app/Http/Controllers/CustomerController.php, app/Http/Controllers/ProjectController.php
Line: 86 (Customer), 74 (Project)
Confidence: MEDIUM

**Coincide con `CONC-001` del informe contiguo.**

Problem:

`destroy()` comprueba `children()->withTrashed()->exists()` y acto seguido hace `delete()`, sin
transacción ni bloqueo. Dos peticiones simultáneas pueden intercalar el `INSERT` del hijo entre la
comprobación y el `delete()` del padre.

Evidence:

- `app/Http/Controllers/CustomerController.php:86-91`
- `app/Http/Controllers/ProjectController.php:74-79`
- La FK `projects.customer_id` usa `restrictOnDelete`
  (`database/migrations/2026_09_28_173607_create_projects_table.php:20`), pero el borrado es lógico
  (`deleted_at`), así que la FK no interviene.
- La misma ventana existe en los `forceDelete` de `CustomerTrashController.php:61` y
  `ProjectTrashController.php:62`.

Impact:

Un cliente en la papelera con proyectos vivos, o un proyecto en la papelera con épicas vivas. **No hay
pérdida de datos**: `CustomerTrashController::destroy` y `ProjectTrashController::destroy` vuelven a
comprobar los hijos y bloquean el borrado permanente, y el `join` de las listas no filtra por
`deleted_at`. El efecto real es un registro irrecuperable desde la UI de la papelera sin intervención
manual, lo cual es improbable con 5 filas por página y un solo usuario.

Recommendation:

Aceptarlo como deuda y **no** añadir transacciones para esto: una transacción sin
`SELECT ... FOR UPDATE` no cierra la carrera. Si algún día se quiere cerrar, el patrón barato es
intentar el borrado y traducir el error de FK/índice, como ya se hace con `UniqueConstraintViolation`.
**No requiere cambio inmediato.**

### DB-002 — No hay índice sobre `deleted_at` en las tres tablas de dominio con soft delete

Severity: LOW
Category: Database / Performance
File: database/migrations/2026_09_25_000000_create_customers_table.php, 2026_09_28_173607_create_projects_table.php, 2026_09_30_175706_create_epics_table.php
Line: 16 (customers), 22 (projects), 22 (epics)
Confidence: HIGH

Hallazgo **nuevo y más preciso** que el `DB-001` del informe contiguo, que speak de "ningún índice
soporta ningún filtro u orden". Aquí el límite concreto es `deleted_at`.

Problem:

`customers`, `projects` y `epics` tienen `softDeletes()` pero ningún índice sobre `deleted_at`. Las tres
listas de papelera filtran por `deleted_at IS NOT NULL` y ordenan por `deleted_at DESC, id ASC`.

Evidence:

- Las migraciones sólo crean el índice único sobre la columna virtual `active_name`
  (`customers_active_name_unique`, `projects_active_name_unique`, `epics_project_active_name_unique`),
  en las líneas 28-31, 34-37 y 34-37 respectivamente. En InnoDB un índice secundario incluye la clave
  primaria, **no** `deleted_at`, así que no ayuda.
- `app/Queries/Customers/CustomerListQuery.php:48`, `ProjectListQuery.php:65`, `EpicListQuery.php:83`.
- Comprobado en el esquema real: ninguna de las tres tablas tiene un índice que empiece por `deleted_at`.

Impact:

Con el volumen actual es irrelevante. A escala, cada papelera es un escaneo completo de la tabla, y es
la operación que más crece con el uso (todo lo que se borra se queda). Ningún otro punto del código
empeora.

Recommendation:

**No añadirlo todavía.** Anotarlo como deuda conocida y añadirlo junto con el primer trabajo real de
rendimiento, en una migración nueva (no editando migraciones ya ejecutadas — ver DB-001).

### FRONT-002 — Lectura del token CSRF sin proteger en la creación inline de clientes

Severity: LOW
Category: Frontend / Bugs
File: resources/js/app.js
Line: 312
Confidence: HIGH

Hallazgo **nuevo**, no aparece en el informe contiguo.

Problem:

Dentro del manejador `select2:select`, el token CSRF se lee **fuera** del `try`. Si el `<form>` no
contiene `input[name="_token"]`, el acceso directo (`form.querySelector(...).value`) lanza un
`TypeError` no capturado.

Evidence:

- `resources/js/app.js:310-317`
  ```js
  const form = element.closest('form');
  const temporaryValue = String(customerOption.id);
  const csrfToken = form.querySelector('input[name="_token"]').value;   // <- fuera del try
  projectForm.form.customerCreateError = '';
  projectForm.form.customerCreating = true;
  try { ... } catch (error) { ... } finally { ... }
  ```

Impact:

Hoy no ocurre: `resources/views/projects/form.blade.php` renderiza el formulario dentro de
`<x-forms.tracked-resource>`, que incluye `@csrf` (`tracked-resource.blade.php:19`). El síntoma sería
un `TypeError` en consola y el desplegable de clientes sin inicializar, sin mensaje para el usuario.

Recommendation:

Mover esa línea (y la de `const temporaryValue`) dentro del `try`, y usar `?.value ?? ''` para que un
formulario sin token degrade en un error controlado en lugar de una excepción.

### DEV-001 — `laravel13` y `laravel13-npm*` publican la aplicación en todas las interfaces de red

Severity: LOW
Category: DevOps / Security
File: docker-compose.yml
Line: 11-13, 122
Confidence: HIGH

Equivalente a `SEC-001` del informe contiguo.

Problem:

`laravel13` publica `"80:80"` y `"${VITE_PORT}:${VITE_PORT}"` sin restringir a loopback, mientras que
`db`, `laravel13-myadmin` y `mailhog` sí usan `127.0.0.1:`. `laravel13-npm` y `laravel13-npm-all`
publican `3000:3000` en todas las interfaces.

Evidence:

- `docker-compose.yml:11-13` → `- "80:80"` / `- "${VITE_PORT:-5173}:${VITE_PORT:-5173}"`
- `docker-compose.yml:122` → `- "3000:3000"`
- Contraste: `docker-compose.yml:218-219` (`127.0.0.1:13306:3306`),
  `docker-compose.yml:222-223` (`127.0.0.1:4000:80`),
  `docker-compose.yml:233-234` (`127.0.0.1:${MAILHOG_PORT:-8025}:8025`).
- `docker compose ps` confirma `0.0.0.0:80->80/tcp` y `[::]:5173->5173/tcp` en la aplicación.
- `ARCHITECTURE.md:129-131` ya declara que los puertos administrativos son sólo de desarrollo local.

Impact:

En una máquina de desarrollo con red corporativa o Wi-Fi, cualquiera de la red alcanza la aplicación en
`:80` con la base de datos de desarrollo detrás (`DB_PASSWORD=laravel` en `.env.example:26-28`). Es
entorno local y está documentado, así que no es una vulnerabilidad; sí es una inconsistencia con el
resto de servicios del mismo fichero.

Recommendation:

Añadir `127.0.0.1:` a los puertos afectados, o documentar en `AGENTS.md` que son accesibles desde la
red por diseño. Una línea por servicio.

### DEV-002 — Los servicios auxiliares de Composer y npm pueden reescribir los lockfiles

Severity: LOW
Category: DevOps / Dependencies
File: docker-compose.yml
Line: 58-78, 93-113
Confidence: HIGH

Coincide con `DEP-001` y `DEP-002` del informe contiguo.

Problem:

Los perfiles `composer` y `npm-all` borran `composer.lock` / `package-lock.json` y reinstalan desde
cero, y ambos ejecutan además `npm install -g npm@latest` en tiempo de ejecución.

Evidence:

- `docker-compose.yml:70-76` → `rm -Rf vendor && rm -f composer.lock && composer install ...`
- `docker-compose.yml:104-108` → `npm install -g npm@latest ... && rm -f package-lock.json && rm -Rf node_modules && npm install`
- `AGENTS.md` prohíbe explícitamente modificar `composer.lock`.

Impact:

Un `docker compose --profile composer run laravel13-composer` deja `composer.lock` regenerado con las
versiones resueltas en ese momento, sin avisar. Con `npm-all` ocurre igual con `package-lock.json`. El
`npm@latest` instalado en tiempo de ejecución hace que dos ejecuciones del mismo comando no usen la
misma versión de npm, lo que rompe la reproducibilidad. Sólo afecta a entornos locales.

Recommendation:

Documentar en `AGENTS.md` que `laravel13-composer` y `laravel13-npm-all` son comandos de
**re-resolución** de dependencias y que hay que revisar el diff del lockfile después. Para eliminar el
riesgo: quitar el `rm -f <lockfile>` de esos perfiles — `composer install` y `npm ci` ya respetan el
lockfile existente.

### OBS-001 — `.env.example` no distingue el nivel de log de producción

Severity: LOW
Category: Observability
File: .env.example, config/logging.php
Line: 21, 64
Confidence: HIGH

Hallazgo **nuevo** y distinto del `OBS-001` del informe contiguo (que trata de telemetría de
aplicación).

Problem:

`.env.example` fija `LOG_LEVEL=debug`, que es el valor por defecto de **todos** los canales de
`config/logging.php` (líneas 64, 71, 79, 95, 107, 118, 125). Además fija `LOG_CHANNEL=daily` y
`LOG_STACK=single`, mientras que `config/logging.php:21` usa `'stack'` como valor por defecto: copiar
el ejemplo cambia el comportamiento respecto a los defaults del framework.

Evidence:

- `.env.example:18-21` → `LOG_CHANNEL=daily`, `LOG_STACK=single`, `LOG_LEVEL=debug`
- `config/logging.php:21` → `'default' => env('LOG_CHANNEL', 'stack')`
- Ningún sitio diferencia `LOG_LEVEL` por `APP_ENV`.

Impact:

Un despliegue que copie `.env.example` a `.env` y sólo cambie `APP_ENV=production` y `APP_DEBUG=false`
—los dos únicos cambios que exige `ARCHITECTURE.md:132-133`— escribirá el log completo en fichero, en
el servidor de aplicación, en lugar de a `stderr` como se espera en contenedores. Con `debug` se
registran queries y sus bindings: emails, nombres de clientes y `customer_id` en texto plano. Además el
fichero rota por día sin límite de retención.

Recommendation:

Poner `LOG_LEVEL=info` y `LOG_CHANNEL=stack` en `.env.example`, y añadir `LOG_LEVEL=warning` /
`LOG_CHANNEL=stderr` al checklist de Production Assumptions de `ARCHITECTURE.md:127-133`. No hace falta
cambiar `config/logging.php`.

### MAINT-001 — El bloque de captura de colisión UNIQUE sigue repetido en los tres controladores de papelera

Severity: LOW
Category: Maintainability / Consistency
File: app/Http/Controllers/CustomerTrashController.php, app/Http/Controllers/ProjectTrashController.php, app/Http/Controllers/EpicTrashController.php
Line: 41-47, 42-48, 47-53
Confidence: HIGH

Equivalente a `CLEAN-001` del informe contiguo.

Problem:

La revisión del 2026-09-30 centralizó el camino "store/update" en
`UniqueConstraintViolation::rethrowAsValidationError()`, pero el camino "restore" sigue con el
`try/catch` + `causedBy()` + `restoreConflictResponse()` inline en los tres controladores, que sólo
difieren en el mensaje.

Evidence:

- `CustomerTrashController.php:39-47`, `ProjectTrashController.php:40-48`, `EpicTrashController.php:45-53`
  — misma estructura literal; y `restoreConflictResponse()` privado duplicado en los tres
  (`CustomerTrashController.php:71-75`, `ProjectTrashController.php:72-76`, `EpicTrashController.php:71-75`).
- Cobertura existente y suficiente: `test_restore_returns_conflict_when_unique_name_is_taken_after_precheck`
  y `test_restore_returns_conflict_when_name_becomes_active_after_precheck` para los tres recursos.

Impact:

Bajo. Es la única inconsistencia que queda del MAINT-002 ya resuelto, y cualquier cambio futuro en el
tratamiento del error (mensaje, atributo, métrica) hay que aplicarlo en tres sitios.

Recommendation:

Opcional y no urgente. Si se hace, que sea local: unificar el mensaje y poco más. Clasificación por
`maintainability-reviewer`: **aceptar como deuda**.

## Informational Findings

### PERF-002 — Coste constante por petición y ordenación de nulos no uniforme

Severity: INFO
Category: Performance
File: app/Http/Middleware/EnsureUserIsActive.php, app/Queries/Projects/ProjectListQuery.php, app/Queries/Epics/EpicListQuery.php
Line: 24, 32-33, 43-46
Confidence: HIGH

Equivalente a `PERF-003` del informe contiguo, más el segundo punto.

- `EnsureUserIsActive.php:24` ejecuta un `User::whereKey(...)->where('active', true)->exists()` en
  **cada** petición web autenticada, además de la resolución de sesión. Es 1 query más por request
  sobre una tabla de 1 fila; irrelevante a esta escala, y es el precio de la revocación inmediata de
  sesión que describe `ARCHITECTURE.md:36-38`. No cambiar sin revisarlo.
- `EpicListQuery` empuja las fechas nulas al final (`orderByRaw('epics.start_date IS NULL')`, líneas 43
  y 45); `ProjectListQuery` no lo hace (líneas 32-33). Es correcto —`projects.start_date` es
  obligatoria— pero `projects.end_date` es nullable y en MySQL los `NULL` salen primero, así que los
  proyectos sin fecha de fin se agrupan al principio de la columna. **No es un defecto**: es una
  decisión de ordenación.

### SEC-001 — No hay CSP configurado y el refresco de lista inyecta HTML de la misma origen

Severity: INFO
Category: Security
File: resources/js/app.js, resources/views/partials/head.blade.php
Line: 463, 14
Confidence: HIGH

`app.js:463` hace `template.innerHTML = await response.text()` con la respuesta del propio servidor y
luego `Alpine.morph` la inyecta en el DOM. Es HTML de la misma origen y ya renderizado, sin datos
controlados por el atacante, así que **no es XSS**. Se anota sólo porque no hay ninguna cabecera CSP
en `partials/head.blade.php` ni en `config/`, de modo que una inyección futura en cualquier plantilla
no tendría ninguna barrera adicional. Endurecimiento opcional, no una vulnerabilidad.

### INFO-002 — Cobertura de verificación de esta revisión

Módulos revisados y **sin hallazgo**: los 12 controladores (autorización, thinness, flash, conflictos
de papelera), los 12 Form Requests (trim en `prepareForValidation`, `Rule::unique(...)->whereNull('deleted_at')`,
`Rule::exists(...)->whereNull('deleted_at')`, `errorBag` del comentario), los 5 modelos
(`#[Fillable]`, casts, `#[UsePolicy]`, `withTrashed()` en relaciones de padres), los 3 policies,
las 3 clases `*ListQuery` + `ListQueryBase`, los 3 transformers, `UniqueConstraintViolation`,
`EnsureUserIsActive`, `FortifyServiceProvider`, `AppServiceProvider`, `bootstrap/app.php`,
`routes/web.php`, `routes/settings.php`, las migraciones, los 4 factories, `resources/js/app.js` y
`resources/js/passkeys.js`, los 16 Blade de lista/formulario y los componentes `x-list.*`.

Comprobaciones de seguridad **negativas** (todas sin hallazgo): no existe `{!! !!}` sobre entrada de
usuario (el único `{!! !!}` es `$qrCodeSvg` en `⚡two-factor-setup-modal.blade.php:225`, generado en
servidor por Fortify/`bacon-qr-code` a partir del secreto 2FA del propio usuario); las únicas
expresiones raw son cuatro `orderByRaw` con constantes (`EpicListQuery.php:43,45,63,65`); no hay
`DB::statement`/`DB::raw`/`selectRaw`/`whereRaw` con entrada de usuario; `customer_id` y `project_id`
no son mass-assignables desde el request, y el `user_id` del comentario se asocia desde
`$request->user()`, no desde input; `.env` está gitignored y `git ls-files` no devuelve ningún `.env`,
`storage/` ni `dc-data/`; las acciones del workflow están fijadas por SHA.

## Security

Ningún hallazgo confirmado de vulnerabilidad. Tabla de verificación:

| # | Observación | Clasificación | Veredicto |
|---|---|---|---|
| 1 | `CustomerPolicy`, `ProjectPolicy` y `EpicPolicy` devuelven `true` en todos los métodos | Decisión de producto | **No es vulnerabilidad.** `ARCHITECTURE.md:41-44` la documenta explícitamente y las 12 rutas de escritura (store/update/destroy/deactivate/reactivate/restore/forceDelete/comment) llaman todas a `authorize()` en el controlador o a `FormRequest::authorize()`. Verificado ruta por ruta sobre `routes/web.php`. |
| 2 | Rama JSON de `CustomerController::store` (`expectsJson()`) → `201 {id, name}` | Revisión | Correcto: sólo expone `id` y `name` del registro creado; 409 en conflicto de papelera; `CustomerRequest` sigue validando y autorizando. Cubierto por `test_customer_store_returns_created_customer_as_json` y `test_json_customer_store_reports_deleted_name_conflicts`. |
| 3 | `resources/js/app.js:318` hace `fetch` a `customers.store` con `X-CSRF-TOKEN` | Revisión | Correcto: `VerifyCsrfToken` acepta la cabecera `X-CSRF-TOKEN`, y la ruta vive en el grupo `auth`+`verified`. |
| 4 | Credenciales de desarrollo en `docker-compose.yml` y `.env.example` | Endurecimiento | `MYSQL_ROOT_PASSWORD: tormenta`, `DB_PASSWORD=laravel`. Sólo desarrollo, declarado en `ARCHITECTURE.md:129-131`, con la BD ligada a `127.0.0.1:13306`. No es un secreto filtrado. |
| 5 | `Dockerfile.dusk` ejecuta `USER root` sin volver a cambiar de usuario | Endurecimiento | No explotable: `docker-compose.yml:7` y `:36` fijan `user: "${DC_UID}:${DC_GID}"` para los dos servicios que usan esa imagen. |
| 6 | `SESSION_ENCRYPT=false` en `.env.example:32` | Endurecimiento | Correcto por defecto: la cookie va firmada con `APP_KEY`, y `config/session.php:172` pone `secure` a `true` cuando `APP_ENV=production`. `SESSION_SECURE_COOKIE` está comentado con nota explicativa (líneas 35-36). |
| 7 | Rate limiting de login / 2FA / passkeys | Revisión | Correcto y completo (`FortifyServiceProvider.php:122-143`). `config/fortify.php` delega en esos limiters, así que `EnsureLoginIsNotThrottled` sí se aplica. El diff sin commitear de este fichero además endurece el manejo de tipos no-string de la clave de throttling. |
| 8 | Contraseñas por defecto en producción | Revisión | `AppServiceProvider.php:40-48` aplica `Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()` sólo en producción. Correcto. |
| 9 | `DB::prohibitDestructiveCommands` | Revisión | `AppServiceProvider.php:36-38` lo activa en producción. Defensa adicional útil (y no exime de arreglar DB-001). |

## Bugs / Correctness

- **BUG-001** (MEDIUM) — test que no puede pasar; guard de comentarios recientes sin cubrir.
- **BUG-002** (MEDIUM) — escapado de `LIKE` dependiente del motor y del `sql_mode` del servidor.
- **FRONT-001** (MEDIUM) — fallo de refresco de lista silencioso.
- **FRONT-002** (LOW) — token CSRF leído fuera del `try`.
- **CONC-001** (LOW) — guardas de hijos no atómicos, sin pérdida de datos.

## Database

- **DB-001** (HIGH) — deriva de esquema en `epic_comments`: `deleted_at` y `active` declarados sólo en
  una migración `create` ya ejecutada.
- **DB-002** (LOW) — sin índice sobre `deleted_at` en las tres tablas de dominio.
- Integridad de unicidad: correcta y duplicada a propósito. Las tres tablas de dominio usan una columna
  virtual `active_name` (`IF(deleted_at IS NULL, name, NULL)`) con índice único, y las migraciones
  tienen la variante `CREATE UNIQUE INDEX ... WHERE deleted_at IS NULL` para `sqlite`/`pgsql`. Los tres
  Form Requests replican la regla con `Rule::unique(...)->whereNull('deleted_at')`, y
  `UniqueConstraintViolation` cierra la ventana de carrera (`causedBy()` acepta SQLSTATE `23505` y
  `23000` con driver code 19 o 1062, es decir PostgreSQL y MySQL/MariaDB, y **no** confunde el 1452 de
  FK). `ModelSchemaParityTest` verifica que `active_name` está en `SYSTEM_COLUMNS`, así que nadie la
  añade a `#[Fillable]` por error.
- Claves foráneas: `projects.customer_id` `restrictOnDelete`, `epics.project_id` `restrictOnDelete`,
  `epic_comments.epic_id` `cascadeOnDelete` (necesario para
  `test_permanently_deleting_an_epic_removes_its_comments`), `epic_comments.user_id` `nullOnDelete`
  (necesario para `test_comments_keep_existing_when_their_author_is_deleted`). Todas correctas y todas
  cubiertas por test.
- `withExists([... withoutGlobalScope(SoftDeletingScope::class)])` en `CustomerListQuery:23` y
  `ProjectListQuery:30` es coherente con los `->withTrashed()->exists()` de los `destroy`: la UI ofrece
  "desactivar" exactamente cuando el servidor impediría borrar.
- Sin N+1: `withExists`, `withCount('comments')`, `with('comments.user')` y `with('project.customer')`
  cubren todas las relaciones que tocan los transformers. `EpicListTransformer::columns()` (líneas
  181-184) accede a `$epic->project->customer` sobre una relación ya eagerly cargada.

## Performance

- **PERF-001** (MEDIUM) — render completo + consultas de opciones sin límite en cada refresco.
- **DB-002** (LOW) — falta índice en `deleted_at`.
- **PERF-002** (INFO) — 1 query extra por request autenticado; ordenación de nulos no uniforme.
- Positivo: paginación en 5, `paginate()` (no `get()`) en las nueve listas, `select('epics.*')` /
  `select('projects.*')` tras los `join` para no duplicar columnas, `withCount` en vez de `count()` por
  fila, y el fragmento Blade evita recargar la tabla desde el navegador. El diseño de lista incremental
  es una buena decisión para este tamaño de aplicación.

## Architecture

Coherente con `ARCHITECTURE.md` en lo esencial: los controladores no contienen reglas de negocio
(`tests/Unit/ArchitectureTest.php::test_controllers_stay_thin_and_free_of_raw_input` lo verifica con
grep sobre el código fuente), la consulta y la presentación están en `app/Queries` y
`app/Transformers`, no hay repositorios ni capas de dominio, y la sección "Deliberately Rejected
Patterns" se respeta.

Una desviación menor respecto del documento: `ARCHITECTURE.md:86` dice "Blade / Livewire / Vue /
Inertia:" y a continuación sólo describe Blade con Livewire y Flux. No hay Vue ni Inertia instalados;
la línea es ambigua y puede inducir a error a quien lea el documento por primera vez.
`ARCHITECTURE.md:129` ("CI runs PHP 8.3 and Node 22; local Docker currently runs PHP 8.4") es exacto
(`tests.yml:80` y `:99`; `Dockerfile.dusk:1` y `${DC_PHP:-8.4}`). Sin problema.

La duplicación estructural entre los tres módulos (controladores, requests, transformers, vistas) es
alta, pero es **deliberada y está documentada** (`AGENTS.md`: "Follow sibling files' structure"), y la
parte que sí eraskejable ya se extrajo (`x-list.*`, `ListQueryBase`, `UniqueConstraintViolation`).
`tests/Unit/ArchitectureTest.php` convierte esa convención en un test, que es la forma correcta de
protegerla. No recomiendo más abstracción.

## Testing

- **TEST-001** (HIGH) — la base de test declarada no es la base de test real, y la suite escribe en la
  base de desarrollo.
- Cobertura: 237 tests PHPUnit (230 pasan en SQLite; 1 falla en MariaDB según el informe contiguo) +
  15 Dusk verdes, sin tests skipped. La suite cubre autorización (guests y unverified), validación de
  cada campo de cada Form Request, reglas de negocio de papelera, carreras de unicidad en los tres
  recursos, orden de listados, búsqueda, fragmento de lista, paridad de traducciones en 4 idiomas,
  paridad modelo/esquema y cobertura de validación contra `#[Fillable]`. Los meta-tests
  (`ArchitectureTest`, `ValidationCoverageTest`, `ModelSchemaParityTest`,
  `Translations\TranslationFilesTest`) son originales y evitan regresiones de arquitectura cheaply.
- Falta de cobertura relevante: ninguna assertion sobre el payload de `availableCustomers` /
  `availableProjects` (que es donde está PERF-001), ni sobre el orden de las opciones.
- **Dusk: 15/15 verde en esta ejecución**, incluidos los tests que la revisión del 2026-09-30 había
  marcado como "pre-existing failure" (`ProjectCustomerSelectTest`) — ya no falla. El informe contiguo
  lo registró como NOT RUN.

## Production

- No hay configuración de despliegue en el repositorio (ni Dockerfile de producción, ni manifests).
  `ARCHITECTURE.md:127-133` lo asume explícitamente. **INFO**, no hallazgo: es una aplicación de
  demostración/entrenamiento, no un servicio.
- Lo que bloquearía un despliegue real es DB-001 (deriva de esquema), TEST-001 (migraciones
  destructivas sobre la base equivocada) y OBS-001 (nivel de log). Los tres están en el plan de acción.
- `APP_DEBUG=false` en `.env.example:4` es el default correcto.
- La suite PHPUnit se ejecuta en el job `ci` y Dusk en un job aparte con su propia base
  (`tests.yml:107-138`), como recomienda `ARCHITECTURE.md:113-114`. Correcto.
- Todas las acciones del workflow están fijadas por SHA y `permissions: contents: read`. Bien.

## Maintainability

- **MAINT-001** (LOW) — bloque de colisión UNIQUE aún duplicado en los 3 `restore`.
- Convenciones consistentes y bien documentadas en `AGENTS.md`; el árbol respeta
  `resources/views/{customers,projects,epics}/{list,form}.blade.php` con las partes compartidas en
  `resources/views/components/list/`, tal y como promete el project map, y `ArchitectureTest` lo
  convierte en test.
- `.github/docs/architecture/ARCHITECTURE.md` está actualizado y es honesto (incluye "Known
  Technical Debt"). El único defecto es la ambigüedad de la línea "Vue / Inertia" de la sección
  Frontend.
- `.github/docs/review-rules.md` es inusualmente bueno: obliga a distinguir bug / riesgo / preferencia y
  prohíbe inventar evidencia. Debe conservarse.
- Deuda técnica visible y bien clasificada en `.github/tasks/todo.md`: `created_by/updated_by/deleted_by`,
  DataTables, debugbar y el filtrado por `active=1` en los desplegables. El último punto explica por qué
  `availableCustomers`/`availableProjects` incluyen registros inactivos (ver *Rejected Findings*).

## Rejected Findings

Descartados tras contrastarlos con la evidencia (regla de `devil-advocate.agent.md`):

- **"IDOR/BOLA: los policies devuelven `true`"** — Decisión de producto documentada
  (`ARCHITECTURE.md:41-44`). Verificadas una por una las 12 rutas de escritura: todas autorizan.
- **"Los desplegables de clientes/proyectos incluyen registros inactivos"** — Requisito pendiente
  explícito en `todo.md` ("en los select… sólo se mostrarán los que tengan active=1"), no un defecto.
  Reportarlo sería presentar una preferencia como bug.
- **"Los 3 fallos de `ProjectCrudTest`/`ProjectInputValidationTest` en SQLite son bugs de la
  aplicación"** — Falso positivo. `assertDatabaseHas('projects', ['start_date' => '2026-10-15'])`
  compara contra el valor físico de la columna. SQLite no tiene tipo `DATE`, así que Eloquent escribe
  `'2026-10-15 00:00:00'`; MariaDB lo truncaría a `'2026-10-15'`. Artefacto del motor de test elegido.
- **"`collect($list['rows'])->firstWhere('id', (int) $id)['actions'][0]['epic'] ?? null` emite warnings
  de PHP"** — Verificado en el contenedor con PHP 8.4: el operador `??` suprime el acceso a offset
  sobre `null` y el resultado es `NULL` sin ninguna advertencia. Código frágil pero correcto.
- **"`{!! $qrCodeSvg !!}` es XSS"** — La salida la genera `twoFactorQrCodeSvg()` de Fortify con
  `bacon-qr-code` a partir del secreto 2FA del propio usuario. No hay entrada de atacante.
- **"Extraer una base común para los tres `*ListTransformer`"** — La repetición es real (~200 líneas
  cada uno) pero es el patrón documentado y columnas y acciones difieren por recurso. La abstracción
  sería más compleja que la duplicación. Rechazado como refactor innecesario.
- **"El contenedor `laravel13` corre como root"** — `docker-compose.yml:7` fija `user:`. No explotable.
- **"La cookie de sesión no es `secure` por defecto"** — `config/session.php:172` la activa en
  producción. Correcto.
- **"`EnsureUserIsActive` corre antes de `Authenticate` y por tanto no ve al usuario"** — Falso
  positivo: `$request->user()` resuelve por `Auth::user()` mediante el user-resolver registrado en
  `AuthServiceProvider`, no depende de que `Authenticate` se haya ejecutado. Confirmado además por los
  diez tests de `tests/Feature/Auth/InactiveUserTest.php`, que pasan.
- **"`addcslashes` sobre el término de búsqueda es insuficiente como escapado de `LIKE`"** — Fusionado
  en BUG-002, que sí tiene evidencia; no se reporta dos veces.
- **"El bloque Redis de `.env.example` implica que se usa Redis"** — Andamiaje del skeleton de Laravel.
  No hay cliente Redis configurado ni Horizon instalado, ni `CACHE_STORE=redis` ni
  `QUEUE_CONNECTION=redis`. No es un hallazgo de dependencias.
- **"Falta CSP"** — Sólo INFO (SEC-001); no hay datos de atacante en el flujo.
- **"`ResourceActivationTest::test_inactive_list_paginates_only_inactive_records` es flaky"** — Un
  primer run lo reportó, pero no se reproduce: cuatro ejecuciones estables y pasa aislado. No se
  reporta como hallazgo.

## Action Plan

Ordenado por impacto. Ninguna sugerencia es estética. `P0`–`P3`.

**P0 — antes de tocar nada más**

1. **DB-001.** Añadir una migración **nueva** que cree `deleted_at` y `active` en `epic_comments`, y no
   reescribir migraciones `create_*` ya ejecutadas. Verificar con `Schema::getColumnListing` sobre la
   base existente, no sólo con una base creada desde cero.
2. **TEST-001.** Hacer real la base de test: `force="true"` en los `<env>` de base de datos de
   `phpunit.xml` **y** crear `laravel_test` en el job `ci` de `.github/workflows/tests.yml` (copiar el
   paso del job `dusk`). Sin esto, el siguiente `php artisan test` borra la base de desarrollo.

**P1 — defectos y coberturas muertas**

3. **BUG-001.** Arreglar `EpicCommentTest` (o los doce `index()`, ver las dos opciones en el hallazgo).
   Es la única cobertura del límite de comentarios y el CI está rojo por ello.
4. **BUG-002.** Declarar `ESCAPE` explícito en `ListQueryBase::paginate()` para que la búsqueda no
   dependa del `sql_mode` del servidor. Comprobar los 3 tests de escapado en MariaDB.
5. **FRONT-001.** Gestionar el error de `refreshList()` en estado Alpine en lugar de relanzarlo.

**P2 — rendimiento y robustez**

6. **PERF-001.** Sacar `availableCustomers` / `availableProjects` del camino de refresco (moverlos
   dentro del fragmento o cachearlos). Es el único hallazgo con crecimiento lineal respecto al tamaño
   de los datos.
7. **CONC-001.** Dejar documentado como deuda; **no** añadir transacciones.
8. **DEV-001 / DEV-002.** `127.0.0.1:` en los puertos de `laravel13` y `laravel13-npm*`; documentar en
   `AGENTS.md` que los perfiles `composer` y `npm-all` re-resuelven los lockfiles.
9. **OBS-001.** `LOG_LEVEL=info` y `LOG_CHANNEL=stack` en `.env.example`, más una línea sobre
   `stderr` en las Production Assumptions de `ARCHITECTURE.md`.
10. **FRONT-002.** Mover la lectura del token CSRF dentro del `try`.
11. **DB-002.** No actuar todavía; añadir el índice de `deleted_at` junto con el primer trabajo real de
    rendimiento, en una migración nueva.
12. **MAINT-001.** Opcional; clasificado como deuda aceptada.

**P3 — documentación**

13. Corregir la línea "Blade / Livewire / Vue / Inertia:" de `ARCHITECTURE.md:86` para que sólo liste
    el stack real, y anotar que la suite PHPUnit necesita una base de datos aislada real (ver TEST-001).
14. Revisar en GitHub Actions el job `dusk` una vez, tal y como ya está anotado en el propio
    `ARCHITECTURE.md:137-140` (deuda que el proyecto ya se ha registrado).