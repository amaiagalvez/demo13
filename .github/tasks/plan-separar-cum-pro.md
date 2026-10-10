# Tarea para agente: desacoplar `customers13` y `projects13`

## Entrega solicitada

Después de aprobar este plan, mover este documento a `.github/tasks/plan.md` dentro del
repositorio.

## Objetivo

Implementar una arquitectura de paquetes reutilizable en aplicaciones diferentes:

1. `amaia/customers13` funciona por sí solo y no importa ni requiere código de proyectos/épicas.
2. `amaia/projects13` funciona por sí solo y no importa ni requiere código de clientes.
3. Si una aplicación quiere ambos módulos relacionados, instala un tercer paquete opcional de
   integración (nombre propuesto: `amaia/customer-projects13`) que depende de ambos y añade esa
   integración. No poner la integración exclusivamente en `demo13` ni crear dependencias circulares.

Al usar los dos paquetes base sin el conector, los recursos funcionan independientemente: un
proyecto no tiene cliente y un cliente no muestra información de proyectos. Al instalar el
conector, debe ampliar las pantallas actuales (selector, etiquetas, conteos y enlaces), sin crear
un segundo CRUD paralelo.

Los paquetes no están en producción. Se permite editar las migraciones existentes. No crear una
migración de transición productiva ni añadir lógica de compatibilidad para datos de producción.

## Arquitectura objetivo

- Mantener los contratos de extensión del lado del paquete que consume cada capacidad, con
  comportamiento autónomo por defecto y sin referencias a clases del paquete vecino.
- Registrar las implementaciones de esos contratos desde el Service Provider del conector.
- Mantener en el conector todo lo que requiera consultar o modificar datos de ambos dominios.
- El conector será propietario de la asociación `projects.customer_id` y su clave foránea a
  `CUM_customers`; las migraciones base de `projects13` no deben crearla.
- Usar los mecanismos de extensión existentes de Laravel/Blade y las convenciones del monorepo.
  Mantener los contratos pequeños y tipados; no añadir repositorios, DTOs, servicios genéricos o
  capas adicionales que no sean necesarias para la integración concreta.
- `demo13` puede instalar los tres paquetes y probar su composición, pero no debe ser el único lugar
  donde viva la integración reutilizable.

## Comportamiento requerido al combinar los paquetes

### Formulario y validación de proyecto

- Sin conector: no renderizar el selector de cliente, no enviar `customer_id` y no exigirlo.
- Con conector: renderizar el selector actual de cliente integrado en el formulario/lista de
  proyectos; permitir seleccionar/buscar clientes y conservar los deep links actuales aplicables.
- Con conector, `customer_id` es obligatorio, entero y debe corresponder a un cliente no eliminado.
- Al crear un proyecto o cambiar su cliente, el cliente seleccionado debe estar activo.
- Al editar, si el proyecto conserva su cliente actual, permitir guardar aunque ese cliente se haya
  archivado después. No ofrecer ni aceptar otro cliente archivado.
- Los errores de validación deben seguir asociados a `customer_id`.
- Las validaciones que consulten el modelo o tabla de clientes pertenecen al conector, no a
  `Projects13\Http\Requests\ProjectRequest`.

### Formularios, listados y queries combinadas

- El conector debe aportar el nombre del cliente en filas/etiquetas de proyectos y épicas, y en los
  datos de selección/restauración de formularios.
- Cuando exista búsqueda/ordenación por cliente en proyectos, épicas, planificación y timeline,
  mantener el comportamiento actual.
- El formulario de proyecto conserva búsqueda y creación rápida de cliente del flujo actual al usar
  el conector.
- La planificación cuenta clientes solo cuando la integración está activa.
- En `customers13`, mostrar conteos de proyectos, épicas y comentarios y el enlace a proyectos solo
  cuando se instala el conector.
- Mantener estados activo/archivado/eliminado, comportamiento de soft delete, conteos activos y
  orden estable.

### Integridad y concurrencia

- El conector crea `customer_id` y una FK a `CUM_customers` con política de borrado restrictiva.
- No permitir mover a papelera ni borrar definitivamente un cliente que tenga proyectos, incluidos
  los proyectos soft-deleted. Se debe mantener el guard transaccional y bloqueo `lockForUpdate()`
  que evitan carreras entre crear un proyecto y borrar su cliente.
- Al crear/actualizar un proyecto, conservar la serialización respecto al cliente escogido dentro
  de la transacción, equivalente a los bloqueos actuales.
- Sin conector, `customers13` debe poder eliminar clientes según sus propias reglas; `projects13`
  debe operar sin columna, FK o modelo de cliente.

## Cambios detallados por paquete

### `packages/projects13`

1. `composer.json`
   - Eliminar `amaia/customers13` de `require` y su repositorio `path`.
   - Mantener `amaia/basics13`.
2. `src/Models/Project.php`
   - Quitar import, relación `customer()`, `fullName()` y anotaciones/tipos de Customer.
   - Quitar `customer_id` de atributos asignables y documentados en el modelo base.
3. `src/Http/Requests/ProjectRequest.php`
   - Quitar las reglas de cliente del Request base. La validación condicional la aporta el contrato
     registrado por el conector; sin contrato, la petición base solo valida campos propios.
4. `src/Http/Controllers/ProjectController.php`
   - Eliminar consultas directas a `Customer` para selected customer/hasCustomers y los locks
     directos a la tabla de clientes.
   - Mantener autorización y escrituras del proyecto; el conector debe aplicar el bloqueo/guard
     transaccional necesario para la asociación.
5. Requests de pantallas combinadas:
   - `src/Http/Requests/PlanningListRequest.php`
   - `src/Http/Requests/TimelineListRequest.php`
   - Eliminar autorización que consulte `Customer::class`; mantener autorización de la capacidad
     propia de proyectos y comportamiento válido sin `customers13`.
6. Queries:
   - `src/Queries/Projects/ProjectListQuery.php`
   - `src/Queries/Projects/ProjectSelectOptionsQuery.php`
   - `src/Queries/Epics/EpicListQuery.php`
   - `src/Queries/Planning/PlanningQuery.php`
   - `src/Queries/Timeline/TimelineQuery.php`
   - Eliminar imports/joins/relaciones directas de clientes en el código base.
   - Exponer puntos de extensión para las columnas/datos cruzados que el conector necesita, sin
     alterar resultados en modo autónomo.
7. `src/Transformers/ProjectListTransformer.php`
   - Hacer que la salida base no requiera nombre ni id de cliente. El conector podrá enriquecer
     payloads con esos datos.
8. `src/Http/Controllers/EpicController.php`
   - Quitar eager load de `project.customer` y datos de formulario que solo existen por la
     integración. Conector añade etiqueta al selector si está activo.
9. `src/Database/Factories/ProjectFactory.php`
   - Quitar Customer como factory/requisito predeterminado. La factory base debe crear proyectos
     sin cliente.
10. Migraciones:
    - Editar `src/Database/Migrations/2026_09_28_173607_create_projects_table.php` para que no cree
      `customer_id` ni FK a `CUM_customers`.
    - Revisar el resto de migraciones de proyecto/epic/comentarios por referencias a clientes.
11. Vistas:
    - `resources/views/projects/form.blade.php`
    - `resources/views/projects/list.blade.php`
    - Quitar el selector, error y rutas `customers.*` del render base.
    - Añadir un punto de extensión claro y opcional para que el conector inserte el selector y las
      columnas/acciones integradas. En ausencia del conector, no debe haber rutas rotas ni campos
      ocultos enviados.
    - Revisar `resources/views/epics/list.blade.php` si consume datos de cliente.
12. Pruebas:
    - `tests/TestCase.php`: no registrar `Customers13\ServiceProvider`.
    - `tests/Feature/ProjectEpicPackageTest.php`: verificar jerarquía proyecto-epic, sin crear
      Customer.
    - `tests/Unit/Projects/ProjectTest.php`, `tests/Unit/Validation/ProjectRequestTest.php`,
      `tests/Unit/Queries/ProjectListQueryTest.php`, `tests/Unit/Queries/EpicListQueryTest.php`:
      ajustar expectativas al comportamiento base sin clientes.
    - Quitar fixtures/dev autoload que solo simulen la dependencia si dejan de ser necesarios.

### `packages/customers13`

1. `composer.json`
   - Asegurar que no haya `amaia/projects13` en `require` ni como repositorio de paquete.
   - Eliminar `Projects13\\Models\\` del autoload-dev si ya no se necesitan fixtures del paquete
     vecino.
2. `src/Models/Customer.php`
   - Quitar imports de `Projects13\Models\Project` y `Epic`, relaciones `projects()` y `epics()`,
     y propiedades derivadas de proyectos/épicas/comentarios.
   - Quitar override de `delete()` si su única función es soportar el guard de proyectos; el guard
     combinado lo registra el conector.
3. `src/ServiceProvider.php`
   - Quitar `blockDeletionWithChildren()` y su hook a relaciones de proyecto.
   - El conector registra el hook de borrado con la misma seguridad transaccional cuando se instala.
4. `src/Queries/Customers/CustomerListQuery.php`
   - Retirar eager counts y subqueries que dependan de `PRO_projects`, `PRO_epics` o
     `PRO_epic_comments`. La consulta base solo lee clientes.
   - Dejar punto de extensión opcional para enriquecer contadores en combinación.
5. `src/Transformers/CustomerListTransformer.php`
   - Quitar conteos, hints y enlaces a `projects.index` del resultado base.
   - El conector añade conteos/acciones/enlaces cuando esté instalado.
6. Migración:
   - `src/Database/Migrations/2026_09_25_000000_create_cum_customers_table.php` debe continuar
     creando clientes sin conocer proyectos.
7. Pruebas y fixtures:
   - `tests/TestCase.php`: quitar rutas fixture `projects.*` si ya no son parte del paquete base.
   - Retirar de las pruebas unitarias/feature base la creación de modelos Projects13.
   - Eliminar o adelgazar `tests/Fixtures/Projects13/Models/*` y las tablas `PRO_*` de
     `tests/Fixtures/database/migrations/2026_09_24_000000_create_host_tables.php` si ya no son
     necesarias para pruebas autónomas.
   - Las pruebas de bloqueos, conteos y enlaces combinados pasan a las pruebas del conector.
   - Mantener pruebas base de CRUD, trash, restauración y borrado de Customer sin instalar
     `projects13`.

### Nuevo paquete conector opcional

1. Crear paquete Composer con nombre propuesto `amaia/customer-projects13` (ajustar nombre/directorio
   a las convenciones del monorepo), con dependencias explícitas a `amaia/customers13`,
   `amaia/projects13` y las dependencias compartidas requeridas.
2. Añadir su Service Provider mediante auto-discovery, registrar implementaciones para los dos
   puntos de extensión neutrales y cargar únicamente las vistas/rutas/migraciones de integración.
3. Implementar la migración de asociación:
   - Añadir `customer_id` a `projects` con FK a `CUM_customers` y `restrictOnDelete()`.
   - La migración debe ser posterior a las tablas que referencia.
   - No editar migraciones ejecutadas en producción (no hay producción); editar la migración base de
     `projects13` para retirar columna/FK y eliminar fixtures incompatibles.
4. Proporcionar validación y opciones para el selector, etiqueta de cliente y query augmentations
   para listas, épicas, planning y timeline.
5. Proporcionar stats/guard/enlace en Customer, incluida eliminación segura bajo concurrencia.
6. Ampliar las vistas actuales mediante sus puntos de extensión (selector de cliente en proyecto,
   columnas y enlaces de conteos donde hoy existen). No duplicar rutas CRUD ni plantillas completas.
7. Añadir pruebas propias del conector para reglas de integración, vistas, queries, FK, guard de
   borrado y operaciones dentro de transacciones.

### Aplicación `demo13`

1. Añadir el paquete conector como dependencia de desarrollo/local mediante la convención `path`
   existente para los paquetes hermanos; configurar su autoload y actualizar el lockfile raíz.
2. Mantener `customers13` y `projects13` como dependencias explícitas independientes; el host
   decide si agrega también el conector.
3. Ajustar los tests de integración y Browser de la aplicación: los flujos que prueban la relación
   cliente-proyecto requieren instalar el conector; flujos propios de cada paquete deben poder
   ejecutarse sin él.
4. Revisar los tests de arquitectura/model-schema/uniformidad afectados por el nuevo modelo:
   `tests/Unit/ArchitectureTest.php`, `tests/Unit/ModelSchemaParityTest.php`,
   `tests/Unit/ResourceUniformityTest.php`, además de tests de requests, transformers, deep links y
   browser selector.
5. Actualizar la documentación arquitectónica del host para registrar que la asociación pertenece
   al conector y no a los paquetes base.

## Orden recomendado de implementación

1. Crear tests de contrato/modo autónomo y fijar la matriz de instalaciones.
2. Añadir contratos neutrales y puntos de extensión en los paquetes base, sin romper el modo base.
3. Retirar dependencias mutuas/imports de `customers13` y `projects13`; ajustar migraciones,
   factories, queries, transformers, controllers, requests y vistas.
4. Crear el paquete conector y trasladar allí asociación, validación, selector, enriquecimiento de
   listados, relaciones/contadores y guard de borrado.
5. Integrar el conector en `demo13`, ajustar pruebas end-to-end y documentación.
6. Ejecutar pruebas específicas, arquitectura, Pint, PHPStan y verificar que cada paquete instala/
   prueba en aislamiento.

## Matriz de aceptación

| Instalación | Resultado que debe verificarse |
|---|---|
| Solo `customers13` | CRUD, estados y trash funcionan; no se cargan modelos, tablas ni rutas de proyectos. |
| Solo `projects13` | CRUD de proyectos/épicas, planning y timeline funcionan sin `customer_id`, `CUM_customers` ni rutas de clientes. |
| Ambos sin conector | Ambos CRUD funcionan de forma independiente; no se presupone asociación o esquema cruzado. |
| Ambos con conector | Asociación, selector, validación, búsquedas, orden, etiquetas, conteos, enlaces, FK y protección concurrente conservan el comportamiento acordado. |

Casos mínimos de validación integrada:

- Sin `customer_id`: error `required`.
- ID no entero, inexistente o de cliente soft-deleted: rechazo.
- Cliente inactivo distinto del actual: rechazo al crear o cambiar.
- Guardar sin cambiar el cliente actual, archivado después de asociarlo: aceptación.
- Cliente activo válido: aceptación.

Comprobación de desacoplamiento:

- Buscar en `customers13` referencias de producción a `Projects13`, `PRO_projects`,
  `PRO_epics`, `PRO_epic_comments` y rutas `projects.*`: ninguna fuera de fixtures/tests del
  conector.
- Buscar en `projects13` referencias a `Customers13`, `CUM_customers`, `customer_id` y rutas
  `customers.*`: ninguna en el paquete base; referencias solo en el conector y pruebas integradas.
- Instalar y probar cada paquete base sin instalar el vecino ni el conector.
