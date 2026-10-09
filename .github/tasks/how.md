# Guía de uso con tu configuración actual. Todo cuelga de tres piezas que ya tienes: 

* AGENTS.md (contexto que Copilot carga en cada sesión vía copilot-instructions.md)
* .github/instructions/ (reglas que se inyectan solas al tocar cada ruta: http, models, views y tests)
* los agentes de .github/agents/ y los flujos guiados (skills) de .github/skills/

## Y en OpenCode

Nada está duplicado: los agentes viven **una sola vez** en `.github/agents/` (los que Copilot ya leía) y OpenCode los lee a través de symlinks en `.opencode/agents/`, así que corriges un agente en un único sitio y los dos clientes lo toman.

* `.github/agents/*.agent.md` → symlink en `.opencode/agents/*.md` (18 agentes: 17 revisores de solo lectura + `review-orchestrator`, que sí escribe reports en `.github/reviews/**`)
* `.github/skills/<nombre>/SKILL.md` → sin symlink: `"skills": [".github/skills"]` ya los carga, en ambos clientes. **No hay comandos `/`**: los antiguos prompts se convertieron en skills, así que se activan por nombre (skill tool), no escribiéndolos con `/`
* `.github/instructions/*` → también sin symlink; OpenCode no resuelve `applyTo`, así que su router es AGENTS.md (ver abajo)
* `opencode.jsonc` en la raíz: MCP de Boost y permisos (espejo de `chat.tools.terminal.autoApprove`)

Los 7 flujos guiados son: `new-feature`, `new-resource`, `full-review`, `fix-review`, `fix-tests`, `consistency-review` y `ux-implement`.

El frontmatter de los agentes es compartido, y **cada cliente lee una key distinta para lo mismo**:

* `mode: subagent` → la usan los dos (sin ella OpenCode registra el agente como `primary` y te aparece en el ciclo del Tab).
* `permissions:` (array `action`/`resource`/`effect`) → **solo Copilot**. OpenCode la ignora y se la manda al provider como parámetro muerto.
* `permission:` (objeto, en singular) → **solo OpenCode**. Es la que aplica de verdad: `edit: deny`, `bash: deny`.

Por eso los agentes llevan las dos keys: si quitas una, el revisor pierde el solo lectura en uno de los dos clientes. Verifícalo con `opencode debug agents`: cada revisor debe salir con `edit deny` y `shell * deny`. No uses `tools:`; la docs la marca como *deprecated* y su sustituta es `permission`.

Lo único que cambia en el flujo: no hay `applyTo`, así que el agente tiene que abrir a mano el `.instructions.md` de la zona que toca antes de editar. Y la skill `full-review` corre en sesión hija con el agente `review-orchestrator`, que lanza a los especialistas.

`.opencode/` está en `.gitignore` (son enlaces, no contenido). **En un ordenador nuevo, después de clonar, ejecuta:**

```
bash /packages/basics13/tooling/link-opencode-assets.sh
```

Es idempotente: puedes ejecutarlo cada vez que clones o añadas un agente. Sin él, OpenCode arranca sin agentes. También avisa si el sistema no soporta symlinks y limpia enlaces colgantes de agentes que hayas borrado.

Un detalle operativo: al editar un agente, OpenCode recarga con ficheros normales pero **no** a través del symlink. Si no ves el cambio, `opencode service restart`. Las skills se recargan solas (viven en `.github/skills`, sin symlink).

# Punto de partida común (los 3 flujos)

* Abre VS Code en la raíz del repo y comprueba en el chat: Boost MCP activo (laravel-boost en el selector de tools) y modo Agent (no "Ask": necesitas que edite archivos).
Nueva sesión por tarea. El contexto de AGENTS.md + las instrucciones de ruta entran solos; no se lo repitas.
* Los comandos seguros (php artisan test, pint --dirty, make:, npm run build, docker compose up) se ejecutan sin pedirte clic. Si pide otra cosa, revísala.

## Añadir una funcionalidad
1. Activa la skill `new-feature` y pásale la descripción:
```
skill new-feature — CRUD de facturas: listado con búsqueda, formulario, papelera y restore, con i18n
```
2. Déjalo seguir su guion: lee arquitectura/reglas de ruta → plan corto (aquí corrige el alcance si algo no te cuadra) → tests Feature rojos → implementa siguiendo el patrón de los hermanos (XController + XTrashController, Form Request, policy, XListQuery, Transformers, componentes list/, Flux).
3. Supervisa los puntos de control, no el proceso:
   * El plan inicial: ¿querías eso o algo distinto?
   * Los lang/*.json en los 4 locales y los data-test si hay UI.
4. Verificación final: el agente ya ha corrido los tests y los 4 tests de arquitectura (los de `AGENTS.md`) y te ha dado su resultado real. Tú comprueba que cuadra con el diff y, si tocaba UI, que el build se hizo (auto-aprobado) o levanta dev (-p 5173:5173 ... run dev).
5. Revisa el diff y commitea tú.

Sin skill también funciona: describe la feature y el agente acabará en lo mismo. La skill ya no aporta la regla de no montar abstracciones de más (eso vive ahora en `AGENTS.md`, siempre activo); lo que aporta es el guion fijo: plan antes de escribir, TDD, los 4 tests de arquitectura al final y el informe.

Si lo que quieres es un **recurso CRUD entero** (modelo + migración + vistas), usa la skill `new-resource` y pásale el nombre: `skill new-resource — Invoice`. Monta el set completo calcado a Customer (XController + XTrashController, XRequest/XListRequest/XRestoreRequest, policy, XListQuery, XListTransformer, vistas con x-list.table y x-forms.tracked-resource, lang en 4 locales) siguiendo TDD, y termina exigiendo pint, PHPStan y los tests de arquitectura en verde. `new-feature` sigue siendo lo indicado para features que no son un recurso completo.

## Revisar incongruencias
Cuatro niveles, de menos a más:

### Rápido
* "Revisa los cambios sin commitear de mi rama"	
* Informe en el chat; solo entonces se carga review-rules.md

### Puntual
* Selecciona un agente concreto del selector del chat (#security-reviewer, #database-reviewer…)	
* Auditoría de un área, READ-ONLY

### Completa
* skill `full-review`
* Informe en .github/reviews/YYYY-MM-DD/CODE-REVIEW.md con IDs estables (SEC-001, DB-004…) tras el abogado del diablo

### Consistencia entre recursos
* skill `consistency-review`
* Compara customers/projects/epics (o el recurso nuevo) contra los patrones canónicos de forms y listados y lista las divergencias con IDs (PAT-001…). Útil tras varios `new-resource` seguidos: la forma del último recurso nuevo se va desvianzando sin que nadie se dé cuenta.

Flujo recomendado (el de tu README): `full-review` → abre el CODE-REVIEW.md → selecciona los hallazgos que te convenzan → sesión nueva → skill `fix-review` con los IDs:

```
skill fix-review — SEC-002 y DB-004
```
Esa skill reverifica que el hallazgo sigue existiendo, hace el cambio mínimo y ejecuta los tests. La revisión es asesoría: verifica a mano seguridad, auth y migraciones destructivas.

## Arreglar bugs
* Bug en comportamiento → sesión nueva y descríbelo con stack/error: "El botón de restore no aparece en la papelera de epics tras borrar con comentarios" → él reproduce con el test más estrecho, distingue si es bug de código o de test, cambio mínimo, Pint, rerun.
* Tests rojos → skill `fix-tests` (tests/Feature/Epics o --filter=...). Distingue bug/test/entorno y nunca dice "en verde" sin haberlo ejecutado.
* Bug de UI/JS → pega error de consola (Boost MCP browser-logs si hay pestaña de debug) → el fix va con Dusk solo si es comportamiento JS (regla de tests.instructions.md).
* Hallazgo de revisión → no lo repitas: usa la skill `fix-review` con su ID para que respete la evidencia original.

## Comprobaciones automáticas (no hace falta pedirlas)
Al correr `php artisan test` (y en la CI) pasan también cuatro tests de arquitectura que vigilan la coherencia del proyecto:
* **tests/Unit/ArchitectureTest.php** — cada recurso trae su set completo (controller, controller de papelera, requests, policy, list query, transformer, vistas list/form) y los controllers son thin: sin `DB::`/`Schema::`/`dd()`/`paginate()` y con FormRequest declarado en todo endpoint que recibe input.
* **tests/Unit/ValidationCoverageTest.php** — todo lo que un modelo puede asignar en masa (`#[Fillable]`) y todo lo que los controllers leen con `$request->string()/boolean()` tiene su regla en un FormRequest. Sube un campo, una migration o un flag de formulario sin validar y se pone rojo.
* **tests/Unit/ModelSchemaParityTest.php** — modelos y esquema no se desincronizan: una columna nueva sin `#[Fillable]` falla hasta que la añades (o la declaras como columna de servidor).
* **tests/Unit/ResourceUniformityTest.php** — los recursos no se desvían entre sí: mismo orden de reglas y mismo `reuse_deleted_name` en los `XRequest`, mismas reglas de búsqueda en los `XListRequest`, mismas habilidades de policy con su call-site HTTP, factories con los mismos estados, mismo estado `inactive` en todo modelo con flag `active`, mismo page size y desempate en las list queries, y el mismo sobre de acciones y `editPayload` allowlisted en los transformers. Un recurso nuevo que se desvíe se pone rojo aquí.

El resto del automatismo:
* `composer ci:check` = `config:clear` + pint --test + PHPStan (nivel 9) + suite completa con cobertura mínima del 80% y Clover en `storage/logs/coverage.xml` (el mismo umbral que exige la CI).
* Tests en paralelo en local: `DX php artisan test --parallel --compact <ruta>` — los flags van **antes** de la ruta. Nunca en CI: los jobs comparten una BD. Requiere el grant `laravel\_test\_%` (ya aplicado, y en `packages/basics13/tooling/mysql/init/02-parallel-test-databases.sql` para volúmenes nuevos).
* Restaurar desde la papelera ya pide `XRestoreRequest` (policy + `resolve_name_conflict` validado). Si añades un endpoint con input, la validación va en su FormRequest, nunca en el controller.
* CI (master y PRs): cache de Composer y npm, job Dusk, y se salta entera si el diff solo toca `.md`. Dependabot cubre composer y npm.

## Reglas del juego
* Recordar una convención: record-rule está apagado → edita a mano .github/instructions/<zona>.instructions.md (http, models, views, tests; mismo applyTo, añade el bullet). Ahí vive todo lo que no quieras repetir.
* Tokens: una sesión = una tarea; ciérrala. Las reglas de ruta solo entran cuando se toca la ruta, y las skills solo cuando las activas.
* Arquitectura (repositorios, DTOs, servicios…): el prompt y review-rules.md lo bloquean sin causa concreta — si de verdad lo necesitas, dilo explícitamente.
* Tras cada sesión: git diff + tests tú mismo + commit.