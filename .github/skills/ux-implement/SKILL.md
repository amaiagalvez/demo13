---
name: ux-implement
description: Implement a batch of UX improvements following the ux-design skill
disable-model-invocation: true
---
# IMPLEMENTAR MEJORAS DE UX

Lee:

.github/copilot-instructions.md

.github/skills/ux-design/SKILL.md

AGENTS.md

El usuario indicará una tanda o hallazgos concretos.

Ejemplos:

Implementa la tanda 1.

Implementa UX-002 y UX-005.

---

# TANDAS

## Tanda 1 — Base y listados de clientes

1. Cabecera de página: H1, descripción y botón primario con texto. Búsqueda en la misma zona.
2. Tabla: nombre como acción de edición, acciones a la derecha (menú "⋯" si hay más de dos), columna con nº de proyectos, fecha legible.
3. Candado explicado con tooltip y enlace a los proyectos.
4. Pestañas Activos | Papelera (n) en lugar del icono de papelera.
5. Sidebar: marca propia, textos en euskera, sin Repository/Documentation, iconos distintos para Proiektuak y Epikak.
6. Tokens de color y foco en `resources/css/app.css` (`@theme`), aplicados de forma global.

## Tanda 2 — Feedback y formularios

Toasts con "Desegin", estados vacíos, vista de tarjetas en móvil, modal pequeño para formularios de un campo, foco y validación en línea.

## Tanda 3 — Jerarquía y pulido

Migas con jerarquía real, enlaces cliente → proyectos → épicas, accesibilidad, microinteracciones, revisión de textos.

Aplica la misma tanda a clientes, proyectos y épicas reutilizando los componentes `x-list.*`.

---

# PARA CADA CAMBIO

1. Inspecciona las vistas y componentes afectados y sus tests.
2. Aplica el cambio más pequeño y seguro. Sin refactors no relacionados.
3. Conserva todos los `data-test`. Si hay que cambiar uno, actualiza el test Dusk correspondiente y dilo.
4. No añadas ni cambies dependencias, ni crees carpetas base, sin aprobación.
5. Añade o actualiza los textos en los 4 locales.
6. Añade o ajusta tests cuando aporten cobertura real (PHPUnit y Dusk). Sigue `testing-best-practices`.

# VALIDACIÓN

Ejecuta solo lo que sea apropiado:

DX ./vendor/bin/pint --dirty --format agent

DX php artisan test --compact

docker compose run --rm --no-deps --entrypoint npm laravel13-npm run build

Dusk afectado, por ejemplo: docker compose exec -e XDEBUG_MODE=off laravel13-dusk php artisan dusk tests/Browser/Customers (nunca en `laravel13`: ver `tests.instructions.md`)

Nota: existe un fallo previo en Dusk (`ProjectCustomerSelectTest` espera inglés con `APP_LOCALE=eu`); no lo atribuyas a tus cambios.

# INFORME FINAL

Para cada cambio indica:

- tanda o ID
- archivos modificados
- tests añadidos o cambiados
- comandos ejecutados y su resultado real
- riesgo restante

Nunca afirmes que un test pasó si no se ejecutó.

Pide al usuario que revise visualmente en claro, oscuro y móvil.