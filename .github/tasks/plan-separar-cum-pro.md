# Tarea para agente: desacoplar `customers13` y `projects13`

## Alcance

Actualizar los paquetes para que puedan reutilizarse en aplicaciones distintas en estos modos:

1. `customers13` instalado por sí solo.
2. `projects13` instalado por sí solo.
3. Los dos paquetes instalados, sin relación entre sus recursos.
4. Los dos paquetes conectados mediante un tercer paquete Composer opcional.

El conector es el único que depende de `customers13` y `projects13`; los paquetes base no se
importan ni se requieren mutuamente. Al instalar el conector, debe ampliar las pantallas actuales,
no duplicar los CRUDs. La integración reutilizable vive en el conector, no exclusivamente en
`demo13`.

Los paquetes aún no están en producción: se pueden editar sus migraciones existentes. No hacen
falta migraciones de transición ni compatibilidad para datos de producción. No empezar cambios de
código hasta cerrar las decisiones de contrato y esquema que se indican más abajo.

## Diseño que hay que implementar

### Paquetes

- `amaia/customers13`: CRUD, modelo, listados y migraciones de clientes; no conoce proyectos,
  épicas, comentarios de épicas ni sus rutas/tablas.
- `amaia/projects13`: CRUD, planning, timeline, proyectos, épicas y comentarios; no requiere
  clientes, `customer_id`, sus rutas/tablas ni su modelo.
- Conector: adoptar el nombre `amaia/customer-projects13`, namespace `CustomerProjects13\\` y
  directorio `packages/customer-projects13`, salvo que el inventario del monorepo revele un
  conflicto concreto. Solo aquí se implementan la asociación y las funciones cruzadas.

`projects13` sin conector crea proyectos sin cliente. `customers13` sin conector no muestra
conteos, chips, enlaces ni restricciones de borrado basadas en proyectos.

### Contratos neutrales y valor por defecto

Definir una interfaz pequeña por paquete consumidor, y una implementación nula registrada por
cada Service Provider base. El conector sustituye esos bindings en su Service Provider. Verificar
el orden de registro y probar que el conector siempre sustituye el binding nulo en Composer
auto-discovery, independientemente del orden de resolución del contenedor.

El contrato de `projects13` debe cubrir como mínimo:

- reglas adicionales para `ProjectRequest`, incluida la traducción del nombre de `customer_id`
  para el resumen de errores;
- guardado integrado `saving(ProjectRequest, ?Project): array`, ejecutado dentro de la misma
  transacción que escribe el proyecto; valida/recupera y bloquea al cliente seleccionado, y devuelve
  únicamente atributos integrados validados, como `customer_id`;
- scopes para enriquecer las consultas de proyecto, épica, selector, planning y timeline. Deben
  recibir el alias correspondiente (`project_customers`, `epic_customers`, `planning_customers` o
  `timeline_customers`) y añadir los joins necesarios antes de búsqueda/orden;
- columnas de búsqueda dinámicas por scope. Sustituir `SEARCH_COLUMNS` constantes cuando el
  conector tenga que extenderlas; mantener estable el orden de búsqueda en modo base;
- datos adicionales de fila, etiqueta de proyecto (`label(Project)`), estado del formulario y
  payload de edición/reapertura de drawers.

El contrato de `customers13` debe cubrir como mínimo:

- scope de la consulta de clientes para `projects_exists`, conteos de proyectos/épicas/comentarios
  y sus subconsultas;
- extras de fila para conteos, hint/acciones de borrado/archivo y URL de proyectos;
- columnas y celdas adicionales que necesite la tabla de clientes.

Usar tipos PHP explícitos y PHPDoc para las formas de datos del contrato. No introducir una
interfaz genérica con método mágico sin tipo, resolverlo mediante `app()` en Blade ni pasar datos
del request sin validar.

### Formularios y escrituras integradas

Sin conector:

- el formulario de proyecto no renderiza ni envía `customer_id`;
- el `ProjectRequest` base no lo requiere;
- el modelo, factory y migración base de proyecto no conocen clientes.

Con conector:

- el formulario actual de proyecto incluye el selector, el field label, los errores y el flujo de
  búsqueda/creación rápida de cliente;
- `customer_id` es obligatorio, entero y debe corresponder a un cliente no eliminado;
- al crear o cambiar la asociación, el cliente debe estar activo;
- al editar, se puede guardar conservando el cliente actual aunque se haya archivado después; no se
  permite seleccionar otro cliente archivado;
- los errores siguen ligados al campo `customer_id`.

`saving()` se ejecuta dentro de `DB::transaction()`, bloquea con `lockForUpdate()` el cliente
seleccionado y devuelve el atributo integrado. El controlador crea/actualiza el proyecto base y
aplica los atributos devueltos mediante `forceFill()` antes de persistir; nunca usar `forceFill()`
con el request bruto ni con campos no validados. Esto es necesario porque `customer_id` no forma
parte de `Project` base ni de su `#[Fillable]`.

### Extensiones Eloquent y consultas

- Registrar, desde el conector, las relaciones dinámicas de Eloquent
  `Project::resolveRelationUsing('customer', ...)` y
  `Customer::resolveRelationUsing('projects', ...)` / `('epics', ...)` si son necesarias para
  conservar `withCount`, `withExists`, `Project::factory()->for($customer)` y las APIs actuales
  del conector.
- No declarar esas relaciones ni tipos `Customers13`/`Projects13` en los modelos base.
- Si PHPStan no reconoce las relaciones dinámicas, añadir stubs/configuración de análisis del
  conector o un ignore estrecho y explicado; no degradar el nivel global ni añadir casts amplios.
- Asegurar que cada scope integra su join antes de que el `ListQueryBase` aplique búsqueda.
  Convertir las constantes `SEARCH_COLUMNS` inspeccionadas por reflexión en tests a métodos que
  añadan las columnas del conector solo cuando esté instalado.
- `ProjectSelectOptionsQuery` debe recibir el scope/label opcional y no usar `orWhereHas('customer')`
  ni `fullName()` en el paquete base.
- `EpicListTransformer` debe poder producir proyecto y épica sin cliente. En modo integrado añade
  `customer`, `projectLabel` y el label de proyecto al payload del drawer.
- `PlanningQuery` no debe devolver `counts['customers']` si ninguna tarjeta lo consume.
- En modo base, planning/timeline usan `trail: []`; proteger `resources/views/components/planning/board.blade.php`
  y `resources/views/components/timeline/board.blade.php` contra acceso a `$row['trail'][0]` cuando
  no hay trail. Con conector, el trail vuelve a incluir el nombre del cliente.
- No extraer la lógica duplicada de timeline/planning ni hacer limpieza general en esta tarea; no
  es necesaria para separar los paquetes.

### Vistas, JavaScript y traducciones

- Usar configuración de vistas con valores nulos por defecto, por ejemplo
  `projects13.views.form_fields`, `projects13.views.list_columns` y equivalentes de clientes;
  insertarlos con `@includeIf`. El paquete base no debe referenciar rutas integradas ni renderizar
  campos ocultos del conector.
- Las extensiones deben cubrir formulario/listado de proyectos, tabla de épicas, planning, timeline
  y tabla de clientes, incluidos headers, celdas y `colspan` dinámico (actualmente la lista de
  épicas asume 7 columnas).
- Auditar `customers13/resources/views/components/planning/customer-chips.blade.php`: trasladarlo
  al conector si se usa solo en la integración; si no se usa en ningún sitio, eliminarlo en vez de
  mantener un componente muerto.
- El código JS del selector está actualmente en `demo13/resources/js/app.js`. Trasladar la
  inicialización Select2 y la creación rápida de clientes al asset del conector; el conector debe
  documentar/exponer cómo lo consume cada aplicación (entry point/publicación Vite) y declarar o
  documentar las dependencias de frontend necesarias. `demo13` no puede ser el único propietario
  de esa lógica.
- Mover al conector los textos exclusivos de integración (`Customer`, `Select a customer`,
  `Open customer form`, errores de carga/creación, etc.) y mantener paridad para `eu`, `es`, `fr` y
  `en`.

## Cambios de archivos que deben investigarse e implementarse

### `packages/projects13`

- `composer.json`: quitar `amaia/customers13` de `require` y su repositorio path.
- Modelo `src/Models/Project.php`: quitar import/relación `Customer`, `fullName()`, `customer_id`,
  propiedades asociadas y el campo de `#[Fillable]`.
- Requests: desacoplar `ProjectRequest`, `PlanningListRequest` y `TimelineListRequest` del modelo y
  policy de clientes; el contrato de integración contribuye reglas/autorización donde corresponda.
- Controllers: quitar queries de clientes en `ProjectController` y eager loads del cliente en
  `EpicController`; ejecutar la contribución de `saving()` dentro de la transacción correcta.
- Queries: desacoplar `ProjectListQuery`, `ProjectSelectOptionsQuery`, `EpicListQuery`,
  `PlanningQuery` y `TimelineQuery`, manteniendo filtros/orden base y permitiendo los scopes del
  conector.
- Transformers/vistas: `ProjectListTransformer`, `EpicListTransformer`,
  `resources/views/projects/list.blade.php`, `projects/form.blade.php`,
  `epics/list.blade.php`, planning/timeline y sus boards.
- Alpine/list state: eliminar del estado base `customer_id`, `customer_name`,
  `customerCreating`, `customerCreateError`, el `submit-disabled` que espera `customerCreating` y
  `create-fields` del modal de conflicto de papelera. El conector aporta ese estado al formulario,
  modal y payloads cuando está instalado.
- Factory/seeders: `ProjectFactory`, `DevelopProjectSeeder` y
  `EdgeCaseProjectSeeder` deben crear datos base sin cliente; los escenarios integrados con
  Customer van a factories/seeders/tests del conector.
- Migración `src/Database/Migrations/2026_09_28_173607_create_projects_table.php`: eliminar
  `customer_id` y FK.
- Pruebas: además de los tests unitarios, clasificar y adaptar `ProjectCrudTest`,
  `ProjectEpicPackageTest`, `ResourceListTest`, `PlanningQueryTest`, `TimelineQueryTest`,
  `ConcurrentNameWriteTest`, tests de seeders, requests de planning/timeline y tests Browser. Los
  casos que realmente prueban asociación pasan al conector; los demás se mantienen autónomos.
- `tests/TestCase.php`: no registrar `Customers13\ServiceProvider`. Quitar fixtures y autoload-dev
  de Customers13 que ya no se necesiten.

### `packages/customers13`

- `composer.json`: confirmar que no requiere `projects13`; eliminar el autoload-dev
  `Projects13\\Models\\` cuando las pruebas ya no lo necesiten.
- `src/Models/Customer.php`: quitar relaciones/imports `Projects13`, propiedades de proyectos,
  épicas y comentarios y cualquier override de borrado únicamente ligado a proyectos.
- No quitar `Customer::delete()` sin resolver la transacción del guard integrado: conservar un
  override neutro/transaccional en el modelo base (aunque no tenga guard propio), o una solución
  equivalente que mantenga la transacción exterior hasta terminar el soft delete.
- `src/ServiceProvider.php`: retirar el guard directo a proyectos; el conector registra el guard
  integrado sin debilitar su aislamiento transaccional.
- `CustomerListQuery`, `CustomerListTransformer` y las vistas: remover conteos, subconsultas,
  hints/acciones dependientes de tablas `PRO_*`, enlaces a `projects.index` y chips integrados del
  modo base; permitir su enriquecimiento opcional por el conector.
- Revisar `resources/views/components/planning/customer-chips.blade.php` como se indica arriba.
- Mantener `src/Database/Migrations/2026_09_25_000000_create_cum_customers_table.php` autónoma.
- Pruebas/fixtures: dividir `CustomerCrudTest`, `CustomerTrashTest` y cualquier otro caso que cree
  `Projects13` entre pruebas autónomas de customer y pruebas integradas del conector. Retirar del
  TestCase base las tablas `PRO_*`, rutas `projects.*` y fixtures
  `tests/Fixtures/Projects13/Models/*` cuando ya no sean requeridas.

### Nuevo paquete `packages/customer-projects13`

- Crear `composer.json` con dependencias runtime solo a `amaia/customers13`,
  `amaia/projects13` y las dependencias realmente necesarias; configurar PSR-4
  `CustomerProjects13\\` y auto-discovery del Service Provider.
- Registrar las dos implementaciones de integración; añadir migraciones, configuración, traducción
  y asset/frontend del conector.
- Implementar relaciones Eloquent dinámicas si se decide usarlas.
- Implementar la asociación `projects.customer_id` con FK `RESTRICT`, selector/opciones, reglas,
  locking, búsqueda/orden, labels, extras de listados y protección contra borrado de clientes que
  tienen proyectos.
- Proteger soft delete y force delete. El force delete dependerá de la FK `RESTRICT` y del manejo
  de `QueryException` en el trash controller. Añadir pruebas que verifiquen el mensaje esperado.
- Preservar el guard bajo una transacción exterior: el lock adquirido por el hook `deleting` debe
  seguir vigente hasta que se aplique el soft delete. Añadir regresión explícita para BUG-001.
  Auditar los hooks equivalentes de `Project` y `Epic` para que no se introduzca una regresión
  análoga; conservar su semántica y transacción.
- Añadir tests independientes del conector para: cuatro modos de instalación, reglas/formulario,
  scopes/aliases, transformers, vistas/columnas, factories dinámicas, FK, soft/force delete y
  concurrencia.

### Decisiones de esquema y migraciones

Antes de escribir migraciones, decidir y documentar el tratamiento de `customer_id`:

- El formulario integrado exige el valor, pero `projects13` base no tiene la columna.
- Añadir una columna `NOT NULL` con FK desde una migración posterior puede fallar con SQLite,
  incluso en instalaciones limpias, especialmente con foreign keys activas.
- Elegir expresamente entre una FK nullable (la validación del conector exige cliente, aunque la BD
  permita NULL fuera de la aplicación) o una estrategia de recreación de tabla compatible con
  SQLite y MySQL/MariaDB para obtener `NOT NULL`.
- No dejar la elección como implícita ni cambiar semántica según driver sin tests.
- Implementar `down()` quitando primero la FK y luego la columna/tabla de asociación.
- Verificar en tests que SQLite tiene `PRAGMA foreign_keys = ON` y que una referencia inválida y
  un force delete restringido fallan de verdad.

### Aplicación anfitriona y distribución

- `demo13` instala el conector para probar la composición, pero los paquetes siguen funcionando
  fuera de este host.
- Actualizar `composer.json`/lockfiles de paquetes y del host; las rutas `path` relativas sirven
  para el monorepo local, no para aplicaciones externas. Documentar la instalación externa mediante
  VCS o el registry Composer que se vaya a usar.
- Decidir versión antes de publicar: retirar `Project::customer()`, `Customer::projects()` y
  `customer_id` son cambios incompatibles. Si `1.x` ya se distribuyó, preparar versiones mayores
  compatibles con SemVer; si no se ha publicado, documentar la versión inicial correcta.
- Auditar `basics13/composer.json` por dependencias de tooling que se instalen en runtime
  (`laravel/boost`, Chisel, log viewer mencionados en la revisión). Moverlas a `require-dev` o
  `suggest` únicamente tras confirmar que no se usan durante la ejecución; si requiere ampliar el
  alcance, dejarlo como follow-up aprobado.

## Pruebas y automatización

- Mantener pruebas unitarias de cada paquete base sin proveedores, clases, tablas ni rutas del otro.
- Añadir tests de integración al conector en vez de usar fixtures falsos `Projects13\Models` en
  `customers13`; cargar las clases reales de ambos paquetes.
- Añadir a `packages/testing13/runAll.sh` una ejecución por paquete aislado y otra del conector con
  sus dependencias. Actualizar la configuración de CI correspondiente para instalar Composer
  dependencies por matriz; no asumir que el lockfile del monorepo prueba aislamiento.
- Añadir un test PHPUnit por paquete base que inspeccione `src/`, `resources/` y `routes/` y falle
  ante referencias de producción prohibidas:
  - `customers13`: `Projects13`, tablas `PRO_*` y rutas `projects.*`.
  - `projects13`: `Customers13`, `CUM_customers`, `customer_id` y rutas `customers.*`.
  Excluir tests, fixtures y dependencias vendorizadas del análisis.
- Actualizar tests de arquitectura, schema parity, uniformity, requests, transformers, deep links,
  Dusk y seeders para la matriz autónoma/optativa.
- Validar con `migrate:fresh` en MySQL/MariaDB y SQLite, suites aisladas y suite del conector; correr
  Pint, PHPStan nivel 9 y los tests de arquitectura del host.

## Casos de aceptación de validación integrada

- `customer_id` ausente: error `required`.
- No entero, cliente inexistente o soft-deleted: rechazo ligado a `customer_id`.
- Crear o cambiar al cliente inactivo: rechazo.
- Guardar conservando el mismo cliente que se archivó luego: aceptación.
- Cambiar a un cliente activo válido: aceptación.
- En modo sin conector, los requests/proyectos no consultan ni requieren clientes.

## Matriz de aceptación de instalación

| Instalación | Aceptación |
| --- | --- |
| Solo `customers13` | CRUD, estados y trash funcionan; sin clases, tablas, chips ni rutas de proyectos. |
| Solo `projects13` | CRUD de proyectos/épicas, planning y timeline funcionan; sin `customer_id`, `CUM_customers` ni rutas de clientes. |
| Ambos sin conector | CRUDs funcionan independientemente, sin asociación ni esquema cruzado. |
| Ambos con conector | Asociación, selector, validación, búsquedas, orden, etiquetas, conteos, enlaces, FK y guards mantienen el comportamiento acordado. |

## Fuera de alcance

- Extraer lógica común de planning/timeline o refactorizar sus algoritmos.
- Corregir de forma general todas las traducciones Basque o mensajes preexistentes de `customers13`.
- Cambiar dependencias de `basics13` sin comprobar uso runtime y aprobación expresa.
- Cambiar UI o reglas no relacionadas con la integración.
