# Guía de uso con tu configuración actual. Todo cuelga de tres piezas que ya tienes: 

* AGENTS.md (contexto que Copilot carga en cada sesión vía copilot-instructions.md)
* .github/instructions/ (reglas que se solas se inyectan al tocar cada ruta) 
* los / prompts + agentes de .github/

# Punto de partida común (los 3 flujos)

* Abre VS Code en la raíz del repo y comprueba en el chat: Boost MCP activo (laravel-boost en el selector de tools) y modo Agent (no "Ask": necesitas que edite archivos).
Nueva sesión por tarea. El contexto de AGENTS.md + las instrucciones de ruta entran solos; no se lo repitas.
* Los comandos seguros (php artisan test, pint --dirty, make:, npm run build, docker compose up) se ejecutan sin pedirte clic. Si pide otra cosa, revísala.

## Añadir una funcionalidad
1. En el chat escribe / y elige new-feature, pegando la descripción:
```
**/new-feature** CRUD de facturas: listado con búsqueda, formulario, papelera y restore, con i18n
```
2. Déjalo seguir su guion: lee arquitectura/reglas de ruta → plan corto (aquí corrige el alcance si algo no te cuadra) → tests Feature rojos → implementa siguiendo el patrón de los hermanos (XController + XTrashController, Form Request, policy, XListQuery, Transformers, componentes list/, Flux).
3. Supervisa los puntos de control, no el proceso:
   * El plan inicial: ¿querías eso o algo distinto?
   * Los lang/*.json en los 4 locales y los data-test si hay UI.
4. Verificación final: corre tú el test estrecho (DX php artisan test --compact <ruta>). Si hay UI, build ya hecho (auto-aprobado) o levanta dev (-p 5173:5173 ... run dev).
5. Revisa el diff y commitea tú.

Sin / también funciona: describe la feature y el agente acabará en lo mismo, pero el prompt le obliga a planear y a no montar abstracciones de más.

## Revisar incongruencias
Tres niveles, de menos a más:

### Rápido
* "Revisa los cambios sin commitear de mi rama"	
* Informe en el chat; solo entonces se carga review-rules.md

### Puntual
* Selecciona un agente concreto del selector del chat (#security-reviewer, #database-reviewer…)	
* Auditoría de un área, READ-ONLY

### Completa
* /full-review
* Informe en .github/reviews/YYYY-MM-DD/CODE-REVIEW.md con IDs estables (SEC-001, DB-004…) tras el abogado del diablo

Flujo recomendado (el de tu README): /full-review → abre el CODE-REVIEW.md → selecciona los hallazgos que te convenzan → sesión nueva → /fix-review con los IDs:

```
/fix-review SEC-002 y DB-004
```
Ese prompt reverifica que el hallazgo sigue existiendo, hace el cambio mínimo y ejecuta los tests. La revisión es asesoría: verifica a mano seguridad, auth y migraciones destructivas.

## Arreglar bugs
* Bug en comportamiento → sesión nueva y descríbelo con stack/error: "El botón de restore no aparece en la papelera de epics tras borrar con comentarios" → él reproduce con el test más estrecho, distingue si es bug de código o de test, cambio mínimo, Pint, rerun.
* Tests rojos → /fix-tests tests/Feature/Epics (o --filter=...). Distingue bug/test/entorno y nunca dice "en verde" sin haberlo ejecutado.
* Bug de UI/JS → pega error de consola (Boost MCP browser-logs si hay pestaña de debug) → el fix va con Dusk solo si es comportamiento JS (regla de tests.instructions.md).
* Hallazgo de revisión → no lo repitas: usa /fix-review con su ID para que respete la evidencia original.

## Reglas del juego
* Recordar una convención: record-rule está apagado → edita a mano .github/instructions/<zona>.instructions.md (mismo applyTo, añade el bullet). Ahí vive todo lo que no quieras repetir.
* Tokens: una sesión = una tarea; ciérrala. Las reglas de ruta solo entran cuando se toca la ruta, y los / solo cuando los invocas.
* Arquitectura (repositorios, DTOs, servicios…): el prompt y review-rules.md lo bloquean sin causa concreta — si de verdad lo necesitas, dilo explícitamente.
* Tras cada sesión: git diff + tests tú mismo + commit. La CI (master + PRs) y dependabot ya se encargan del resto.