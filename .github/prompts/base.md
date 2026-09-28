# CONTEXTO GLOBAL DE LA REVISIÓN

Estoy desarrollando una aplicación web con:

* Laravel 13
* PHP 8.3+
* Eloquent ORM
* MySQL
* PHPUnit y/o Pest
* Docker
* Composer
* Node/NPM
* Vite

El frontend puede utilizar:

* Blade
* Livewire
* Vue
* Inertia

Debes detectar automáticamente qué tecnologías utiliza realmente el proyecto y revisar únicamente las que correspondan.

## REGLAS GENERALES

No quiero una revisión basada en preferencias personales.

Distingue siempre entre:

1. ERROR REAL
2. RIESGO REAL
3. MALA PRÁCTICA
4. DEUDA TÉCNICA
5. MEJORA OPCIONAL
6. PREFERENCIA DE ESTILO

No conviertas una preferencia de estilo en un problema.

Prioriza problemas que puedan producir:

* bugs
* vulnerabilidades
* pérdida o corrupción de datos
* problemas de concurrencia
* problemas de rendimiento
* problemas de mantenibilidad
* comportamiento inesperado
* dificultad para evolucionar el sistema

## IMPORTANTE

Antes de recomendar una solución:

1. Comprende el código existente.
2. Comprende las relaciones entre las diferentes piezas.
3. Comprueba cómo se utiliza realmente el código.
4. No asumas que un patrón es incorrecto sin entender su contexto.
5. No propongas introducir una abstracción si no aporta un beneficio claro.
6. No sobrearquitectures la aplicación.
7. Respeta las convenciones actuales de Laravel 13.
8. No recomiendes APIs obsoletas si existe una alternativa actual.
9. Si tienes acceso a internet, consulta primero la documentación oficial de Laravel correspondiente a la versión instalada.
10. Si existe Laravel Boost en el proyecto, utiliza su contexto y documentación específica.

## NO INVENTAR

Si no puedes demostrar un problema a partir del código disponible, indícalo como:

"POSIBLE RIESGO — requiere verificar X"

No lo presentes como vulnerabilidad o bug confirmado.

## FORMATO DE CADA HALLAZGO

Para cada problema encontrado utiliza:

### [SEVERIDAD] Título

**Categoría:** Bug / Seguridad / Arquitectura / Rendimiento / Mantenibilidad / etc.

**Ubicación:**
`archivo:línea`

**Problema:**
Descripción concreta.

**Por qué importa:**
Explica el impacto real.

**Evidencia:**
Fragmento o comportamiento del código que demuestra el problema.

**Solución recomendada:**
Explica qué cambiarías.

**Ejemplo:**
Incluye código cuando sea útil.

**Confianza:**
Alta / Media / Baja

## SEVERIDAD

CRÍTICA
Puede provocar compromiso grave del sistema, pérdida importante de datos o indisponibilidad.

ALTA
Problema serio que debería corregirse antes de producción.

MEDIA
Problema relevante pero con impacto limitado o condicionado.

BAJA
Mejora recomendable o deuda técnica menor.

INFO
Observación sin necesidad inmediata de modificación.

## AL FINAL

Termina siempre con:

### RESUMEN

* Problemas críticos:
* Problemas altos:
* Problemas medios:
* Problemas bajos:
* Mejoras opcionales:

### TOP 10

Lista únicamente los 10 cambios que más valor aportarían, ordenados por impacto técnico.

No incluyas recomendaciones puramente estéticas en el TOP 10.
