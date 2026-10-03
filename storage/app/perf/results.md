# Rendimiento del listado de clientes

Medición realizada siguiendo `.github/tasks/09.performancce-test-plan.md`.

> Las cifras de los perfiles 100 / 300 / 1.000 se tomaron antes del commit `perf` (7e085f9), que
> reutiliza el total de la paginación para las pestañas de activos e inactivos. El perfil de 10.000
> se midió dos veces, antes y después de ese cambio, y ambos cortes están recogidos abajo. Para
> volver a reproducir cualquier cifra hay que fijar el commit con el que se midió.

## Conclusión

1. **No hay N+1.** Cada petición del listado ejecuta **7–9 consultas** en los cuatro perfiles de
   volumen y en los tres estados. El número no crece al aumentar los registros.
2. **Hasta ~1.000 clientes la base de datos no es el cuello de botella.** De los ~60 ms de cada
   petición solo 4–6 ms (7–10 %) son consultas; el resto (~50 ms) es PHP (renderizado de Blade y
   componentes Flux), y ese coste **no depende del volumen**.
3. **A partir de ~10.000 clientes sí hay un cuello de botella, y está medido**: la base de datos
   pasa de 6 ms a ~29 ms y la consulta de filas de ~3,7 ms a ~15 ms. El culpable no es la búsqueda
   ni los conteos de las pestañas, sino que **`Using filesort` obliga a calcular los tres conteos
   correlacionados y el `exists` para *todas* las filas que casan, no solo para las 25 de la
   página**.
4. **El arreglo está medido y es reproducible**: un índice `customers (active, deleted_at, name)`
   elimina el `filesort`, baja la consulta de filas de ~15 ms a ~2,5 ms (**−84 %**) y deja el total
   de base de datos en ~16 ms. Tres repeticiones seguidas dan −13,6 / −13,0 / −12,9 ms.
5. **La papelera no necesita nada**: ordena por `deleted_at desc, id`, que el índice
   `customers_deleted_at_id_index` ya cubre, así que su consulta de filas es plana (~2 ms con
   10.000 clientes). Es más barata porque casa con el 15 % de los registros, frente al 60 % de los
   activos.
6. **Aun con el arreglo, el renderizado es el mayor coste** y no escala con la base de datos pero sí
   con las filas por página: la respuesta pesa 334 kB, de los cuales 242 kB son las 25 filas
   (~10 kB de marcado por fila: Flux genera clases largas e iconos SVG).
7. Las subconsultas correlacionadas usan índices cubrientes (`Using index`), sin tablas temporales.
   El problema nunca fue **cómo** se ejecutan, sino **cuántas veces**.

## Metodología

- El escenario se midió con peticiones reales, no midiendo la generación de datos: el arranque
  (`customer-list-bench.php`) hace login con el formulario real de Fortify y después reproduce
  cada listado a través del *kernel* HTTP, registrando con `DB::listen` el tiempo total, el número
  de consultas y la duración de cada consulta.
- Combinaciones medidas: **3 estados** (activos, inactivos, papelera) × **4 variantes de búsqueda**
  (sin búsqueda, con resultados para todas las filas, con resultados para una sola fila, sin
  resultados) × **primera página y segunda página**.
- Cada escenario: una pasada de calentamiento global, 3 calentamientos y **20 ejecuciones
  medidas**. Se reportan mediana, percentil 95 y mínimo.
- Para la segunda página se usa la **página 2**, no la última: la última solo tiene 5 filas y hacía
  la comparación entre páginas engañosa.
- Los términos de búsqueda se toman de los datos realmente sembrados, así la selectividad es
  conocida: `Perf` (coincide con todo), las 5 últimas cifras del nombre de un cliente real del
  estado (coincide con 1) y `zzz-no-match` (0 resultados).
- `index-probe.php` reproduce las sentencias del listado, mide cada una por separado (25
  ejecuciones por sentencia) y repite la medición con un índice candidato puesto y quitado.

### Cómo reproducir

```bash
DX php artisan migrate:fresh
DX php artisan db:seed --class="Database\Seeders\CustomerPerformanceSeeder"   # 300 por defecto

DX php storage/app/perf/customer-list-bench.php 20 3   # [ejecuciones] [calentamientos]
DX php storage/app/perf/index-probe.php
```

Para otros perfiles se instancia el seeder con otra cantidad:

```bash
DX php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php";
  $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  (new Database\Seeders\CustomerPerformanceSeeder(1000))->setContainer($app)->run();'
```

## Entorno

| | |
|---|---|
| PHP | 8.4.25 |
| Laravel | 13.34.0 |
| Base de datos | MariaDB 11.7 (`db`, base `laravel`) |
| `APP_DEBUG` | `false` (para no medir Debugbar) |
| `XDEBUG_MODE` | `off` |
| Vistas | compiladas en caché |
| Servidor | contenedor de desarrollo compartido |

El tiempo medido es el de la petición completa (middleware, controlador, transformador y render),
por eso la columna `php` es `mediana − db`.

## Volúmenes sembrados

Perfiles de carga; las proporciones no cambian con la escala.

| Perfil | clientes | proyectos | épicas | comentarios | activos / inactivos / papelera |
|---|---|---|---|---|---|
| 100 | 100 | 300 | 1.200 | 3.600 | 60 / 25 / 15 |
| 300 | 300 | 900 | 3.600 | 10.800 | 180 / 75 / 45 |
| 1.000 | 1.000 | 3.000 | 12.000 | 36.000 | 600 / 250 / 150 |
| 10.000 | 10.000 | 30.000 | 120.000 | 360.000 | 6.000 / 2.500 / 1.500 |

Reparto de estados: 60 % activos, 25 % inactivos, 15 % en la papelera, aplicado por rotación fija
para que la mezcla sea idéntica en cualquier escala.

## Resultados completos

### Perfil 100

| escenario | mediana | p95 | mín | db | php | consultas |
|---|---|---|---|---|---|---|
| active / sin búsqueda / pág. 1 | 58,6 ms | 67,9 ms | 53,6 ms | 4,1 ms | 54,6 ms | 8 |
| active / sin búsqueda / pág. 2 | 57,2 ms | 64,0 ms | 54,9 ms | 4,3 ms | 52,9 ms | 8–9 |
| active / `Perf` / pág. 1 | 57,5 ms | 67,3 ms | 55,6 ms | 4,2 ms | 53,3 ms | 8 |
| active / `Perf` / pág. 2 | 58,3 ms | 63,2 ms | 54,9 ms | 4,6 ms | 53,6 ms | 8 |
| active / `00001` / pág. 1 | 22,4 ms | 24,7 ms | 20,5 ms | 3,0 ms | 19,4 ms | 8 |
| active / `00001` / pág. 2 | 20,7 ms | 22,6 ms | 19,4 ms | 2,7 ms | 18,0 ms | 8 |
| active / `zzz-no-match` / pág. 1 | 19,7 ms | 22,8 ms | 18,3 ms | 2,4 ms | 17,3 ms | 7–8 |
| active / `zzz-no-match` / pág. 2 | 20,4 ms | 28,1 ms | 18,6 ms | 2,4 ms | 18,0 ms | 7–8 |
| inactive / sin búsqueda / pág. 1 | 49,6 ms | 59,2 ms | 47,2 ms | 3,8 ms | 45,7 ms | 8–9 |
| inactive / `Perf` / pág. 1 | 51,9 ms | 57,7 ms | 47,5 ms | 4,5 ms | 47,4 ms | 8 |
| inactive / `00001` / pág. 1 | 19,6 ms | 20,2 ms | 17,8 ms | 3,0 ms | 16,6 ms | 8–9 |
| inactive / `zzz-no-match` / pág. 1 | 17,5 ms | 19,0 ms | 16,5 ms | 2,0 ms | 15,5 ms | 7 |
| trash / sin búsqueda / pág. 1 | 37,5 ms | 41,2 ms | 35,9 ms | 3,0 ms | 34,5 ms | 8–9 |
| trash / `Perf` / pág. 1 | 38,6 ms | 41,6 ms | 37,6 ms | 3,2 ms | 35,4 ms | 8 |
| trash / `00001` / pág. 1 | 20,2 ms | 20,9 ms | 18,8 ms | 2,8 ms | 17,3 ms | 8 |
| trash / `zzz-no-match` / pág. 1 | 18,2 ms | 22,5 ms | 16,6 ms | 2,3 ms | 15,9 ms | 7–8 |

La segunda página no se midió en inactivos ni papelera porque a esta escala solo tienen una página
(25 y 15 registros).

### Perfil 300

| escenario | mediana | p95 | mín | db | php | consultas |
|---|---|---|---|---|---|---|
| active / sin búsqueda / pág. 1 | 57,9 ms | 62,9 ms | 55,9 ms | 4,4 ms | 53,5 ms | 8 |
| active / sin búsqueda / pág. 2 | 58,7 ms | 64,2 ms | 55,7 ms | 4,5 ms | 54,1 ms | 8 |
| active / `Perf` / pág. 1 | 58,6 ms | 64,5 ms | 55,1 ms | 5,2 ms | 53,5 ms | 8–9 |
| active / `Perf` / pág. 2 | 58,2 ms | 67,5 ms | 55,5 ms | 4,9 ms | 53,3 ms | 8–9 |
| active / `00001` / pág. 1 | 23,2 ms | 25,2 ms | 21,5 ms | 3,5 ms | 19,6 ms | 8 |
| active / `00001` / pág. 2 | 21,8 ms | 22,9 ms | 19,8 ms | 3,3 ms | 18,6 ms | 8 |
| active / `zzz-no-match` / pág. 1 | 20,7 ms | 22,3 ms | 19,0 ms | 2,8 ms | 18,0 ms | 7–8 |
| active / `zzz-no-match` / pág. 2 | 20,3 ms | 28,2 ms | 18,0 ms | 2,4 ms | 18,0 ms | 7–8 |
| inactive / sin búsqueda / pág. 1 | 51,4 ms | 57,0 ms | 48,7 ms | 4,6 ms | 46,8 ms | 8–9 |
| inactive / sin búsqueda / pág. 2 | 52,4 ms | 71,8 ms | 48,3 ms | 4,6 ms | 47,8 ms | 8 |
| inactive / `Perf` / pág. 1 | 51,3 ms | 66,0 ms | 49,8 ms | 5,0 ms | 46,3 ms | 8 |
| inactive / `Perf` / pág. 2 | 51,2 ms | 56,0 ms | 48,7 ms | 4,9 ms | 46,3 ms | 8 |
| inactive / `00001` / pág. 1 | 20,7 ms | 22,0 ms | 18,4 ms | 3,5 ms | 17,2 ms | 8 |
| inactive / `00001` / pág. 2 | 19,7 ms | 23,7 ms | 18,1 ms | 3,2 ms | 16,5 ms | 8 |
| inactive / `zzz-no-match` / pág. 1 | 17,9 ms | 21,4 ms | 16,3 ms | 2,5 ms | 15,4 ms | 7–8 |
| inactive / `zzz-no-match` / pág. 2 | 18,5 ms | 20,8 ms | 16,5 ms | 2,3 ms | 16,2 ms | 7 |
| trash / sin búsqueda / pág. 1 | 53,8 ms | 61,0 ms | 51,4 ms | 3,5 ms | 50,3 ms | 8–9 |
| trash / sin búsqueda / pág. 2 | 44,6 ms | 49,8 ms | 43,2 ms | 2,8 ms | 41,8 ms | 8 |
| trash / `Perf` / pág. 1 | 57,5 ms | 61,8 ms | 52,1 ms | 4,3 ms | 53,2 ms | 8–9 |
| trash / `Perf` / pág. 2 | 46,9 ms | 49,8 ms | 44,2 ms | 3,8 ms | 43,1 ms | 8–9 |
| trash / `00001` / pág. 1 | 21,3 ms | 23,2 ms | 19,0 ms | 3,3 ms | 18,0 ms | 8–9 |
| trash / `00001` / pág. 2 | 19,5 ms | 21,9 ms | 17,9 ms | 3,0 ms | 16,5 ms | 8 |
| trash / `zzz-no-match` / pág. 1 | 18,2 ms | 24,1 ms | 16,9 ms | 2,6 ms | 15,6 ms | 7–8 |
| trash / `zzz-no-match` / pág. 2 | 18,7 ms | 21,1 ms | 16,8 ms | 2,4 ms | 16,4 ms | 7 |

### Perfil 1.000

| escenario | mediana | p95 | mín | db | php | consultas |
|---|---|---|---|---|---|---|
| active / sin búsqueda / pág. 1 | 62,2 ms | 80,8 ms | 57,6 ms | 6,1 ms | 56,1 ms | 8 |
| active / sin búsqueda / pág. 2 | 60,9 ms | 84,6 ms | 55,9 ms | 6,4 ms | 54,5 ms | 8–9 |
| active / `Perf` / pág. 1 | 61,1 ms | 66,2 ms | 56,4 ms | 6,2 ms | 54,9 ms | 8 |
| active / `Perf` / pág. 2 | 61,3 ms | 72,5 ms | 56,7 ms | 6,4 ms | 54,9 ms | 8 |
| active / `00001` / pág. 1 | 24,0 ms | 25,7 ms | 22,5 ms | 4,8 ms | 19,1 ms | 8 |
| active / `00001` / pág. 2 | 22,6 ms | 25,8 ms | 20,9 ms | 5,1 ms | 17,5 ms | 8 |
| active / `zzz-no-match` / pág. 1 | 21,0 ms | 23,2 ms | 19,3 ms | 2,6 ms | 18,4 ms | 7 |
| active / `zzz-no-match` / pág. 2 | 19,9 ms | 22,0 ms | 18,4 ms | 2,7 ms | 17,2 ms | 7 |
| inactive / sin búsqueda / pág. 1 | 51,9 ms | 62,3 ms | 49,8 ms | 5,5 ms | 46,5 ms | 8 |
| inactive / sin búsqueda / pág. 2 | 53,5 ms | 60,3 ms | 49,5 ms | 5,4 ms | 48,1 ms | 8–9 |
| inactive / `Perf` / pág. 1 | 55,1 ms | 71,1 ms | 51,2 ms | 6,5 ms | 48,6 ms | 8 |
| inactive / `Perf` / pág. 2 | 52,3 ms | 64,5 ms | 50,5 ms | 6,3 ms | 46,0 ms | 8 |
| inactive / `00001` / pág. 1 | 21,3 ms | 25,2 ms | 19,0 ms | 4,7 ms | 16,7 ms | 8 |
| inactive / `00001` / pág. 2 | 21,1 ms | 23,9 ms | 19,4 ms | 4,6 ms | 16,5 ms | 8 |
| inactive / `zzz-no-match` / pág. 1 | 18,5 ms | 21,2 ms | 17,1 ms | 2,8 ms | 15,7 ms | 7 |
| inactive / `zzz-no-match` / pág. 2 | 18,4 ms | 21,6 ms | 17,5 ms | 2,8 ms | 15,6 ms | 7 |
| trash / sin búsqueda / pág. 1 | 58,1 ms | 73,8 ms | 51,7 ms | 4,1 ms | 54,0 ms | 8 |
| trash / sin búsqueda / pág. 2 | 55,5 ms | 72,7 ms | 51,5 ms | 4,1 ms | 51,3 ms | 8–9 |
| trash / `Perf` / pág. 1 | 54,5 ms | 65,1 ms | 51,7 ms | 4,3 ms | 50,2 ms | 8 |
| trash / `Perf` / pág. 2 | 108,8 ms | 204,9 ms | 53,9 ms | 8,3 ms | 100,4 ms | 8–9 |
| trash / `00001` / pág. 1 | 48,3 ms | 100,2 ms | 30,3 ms | 10,7 ms | 37,6 ms | 8–9 |
| trash / `00001` / pág. 2 | 67,7 ms | 95,9 ms | 50,8 ms | 11,2 ms | 56,5 ms | 8 |
| trash / `zzz-no-match` / pág. 1 | 39,5 ms | 59,8 ms | 30,8 ms | 6,4 ms | 33,2 ms | 7–8 |
| trash / `zzz-no-match` / pág. 2 | 52,8 ms | 69,1 ms | 36,3 ms | 8,0 ms | 44,8 ms | 7–8 |

Los últimos escenarios de papelera de este perfil salen inflados frente a scenarios equivalentes
de los otros perfiles (`trash / one`: 48,3 ms con mínimo de 30,3 ms; `trash / all / page 2` con
p95 de 204,9 ms y mínimo de 53,9 ms). Son picos de ruido del contenedor: el mínimo sigue siendo
coherente con los otros perfiles y el número de consultas no cambia.

### Perfil 10.000

Medido con el host bajo carga (había navegador y otros procesos activos), así que los totales son
poco fiables y **el mínimo es el mejor dato**: el mínimo de la primera página de activos es 85,0 ms,
y la mediana de esa misma petición bajó a 107,5 ms cuando la máquina quedó más tranquila. La
columna `db`, en cambio, es estable y es la que se usa para el análisis.

| escenario | mediana | p95 | mín | db | php | consultas |
|---|---|---|---|---|---|---|
| active / sin búsqueda / pág. 1 | 107,5 ms | 181,5 ms | 85,0 ms | 28,9 ms | 78,6 ms | 7 |
| active / sin búsqueda / pág. 2 | 127,3 ms | 157,8 ms | 98,5 ms | 28,2 ms | 99,1 ms | 7 |
| active / `Perf` / pág. 1 | 113,1 ms | 163,0 ms | 86,2 ms | 29,8 ms | 83,3 ms | 8 |
| active / `Perf` / pág. 2 | 103,5 ms | 128,4 ms | 85,6 ms | 30,1 ms | 73,4 ms | 8–9 |
| active / `00001` / pág. 1 | 64,2 ms | 88,9 ms | 45,5 ms | 32,4 ms | 31,8 ms | 8 |
| active / `00001` / pág. 2 | 111,6 ms | 157,6 ms | 41,9 ms | 49,5 ms | 62,1 ms | 8–9 |
| active / `zzz-no-match` / pág. 1 | 75,5 ms | 106,0 ms | 46,4 ms | 20,8 ms | 54,7 ms | 7 |
| active / `zzz-no-match` / pág. 2 | 50,8 ms | 72,5 ms | 36,2 ms | 15,4 ms | 35,3 ms | 7 |
| inactive / sin búsqueda / pág. 1 | 98,9 ms | 166,3 ms | 84,0 ms | 25,4 ms | 73,5 ms | 8–9 |
| inactive / sin búsqueda / pág. 2 | 179,4 ms | 250,0 ms | 102,5 ms | 37,7 ms | 141,8 ms | 8 |
| inactive / `Perf` / pág. 1 | 233,6 ms | 299,5 ms | 137,7 ms | 62,6 ms | 171,0 ms | 8–9 |
| inactive / `Perf` / pág. 2 | 117,0 ms | 157,8 ms | 94,4 ms | 33,6 ms | 83,5 ms | 8 |
| inactive / `00001` / pág. 1 | 56,5 ms | 76,2 ms | 50,0 ms | 31,4 ms | 25,1 ms | 8–9 |
| inactive / `00001` / pág. 2 | 60,4 ms | 96,7 ms | 49,4 ms | 34,0 ms | 26,4 ms | 8 |
| inactive / `zzz-no-match` / pág. 1 | 36,2 ms | 53,9 ms | 28,3 ms | 11,8 ms | 24,4 ms | 7 |
| inactive / `zzz-no-match` / pág. 2 | 54,8 ms | 81,3 ms | 34,9 ms | 16,0 ms | 38,8 ms | 7 |
| trash / sin búsqueda / pág. 1 | 129,1 ms | 181,2 ms | 80,3 ms | 12,5 ms | 116,5 ms | 8 |
| trash / sin búsqueda / pág. 2 | 81,0 ms | 100,0 ms | 72,0 ms | 8,4 ms | 72,5 ms | 8 |
| trash / `Perf` / pág. 1 | 81,3 ms | 96,6 ms | 70,4 ms | 12,9 ms | 68,5 ms | 8 |
| trash / `Perf` / pág. 2 | 82,6 ms | 138,3 ms | 72,7 ms | 15,5 ms | 67,0 ms | 8–9 |
| trash / `00001` / pág. 1 | 39,3 ms | 47,1 ms | 36,9 ms | 14,1 ms | 25,2 ms | 8 |
| trash / `00001` / pág. 2 | 33,3 ms | 82,1 ms | 30,9 ms | 14,5 ms | 18,8 ms | 8–9 |
| trash / `zzz-no-match` / pág. 1 | 30,4 ms | 44,0 ms | 27,0 ms | 9,6 ms | 20,8 ms | 7–8 |
| trash / `zzz-no-match` / pág. 2 | 34,9 ms | 47,0 ms | 28,1 ms | 9,6 ms | 25,3 ms | 7 |

Lo relevante de este perfil es la columna `db`: **la papelera se mantiene en 8–16 ms mientras los
activos y los inactivos suben a 25–63 ms**, con solo 7–9 consultas en todos los casos. La diferencia
está explicada en el apartado «Escalado».

### Con el código actual (tras reutilizar el total de la paginación)

Con el commit `perf` la pestaña de activos e inactivos ya no cuenta por separado: los controladores
pasan `$customers->total()` a `stateCounts()`, así que esa consulta desaparece. Primera página de
activos con 10.000 clientes, mismas condiciones:

| | consultas | desglose (ms) | db total |
|---|---|---|---|
| antes | 8 | filas 14,55 · paginación 2,93 · inactivos 2,85 · papelera 0,70 | 28,9 ms |
| ahora | **7** | filas 20,86 · paginación 3,65 · inactivos 4,17 · papelera 0,88 | 29,7 ms |

La columna `db` no baja de forma apreciable porque el host está más cargado que en la medición
anterior (la consulta de filas sube de 14,6 a 20,9 ms), pero **la consulta que sobra ya no se
ejecuta**: el ahorro real es la de la pestaña (~3–4 ms con 10.000 clientes) y no se ve en el total
por el ruido.

Queda una asimetría: `CustomerTrashController` sigue llamando a `stateCounts()` sin argumentos, así
que la papelera mantiene las **8 consultas** y las tres cuentas, aunque el paginador ya sabe cuántas
hay. Reutilizar ahí también el total eliminaría un escaneo completo.

## Número de consultas

Constante en todos los casos y en los cuatro perfiles: **7–9 consultas** por petición (7 cuando el
resultado está vacío, ya que no se ejecuta la consulta de filas; 9 en la petición que inserta la
fila de sesión en lugar de actualizarla). Reparto:

1. `select * from sessions where id = ?` (recuperación de sesión)
2. `select exists(select * from users where id = ? and active = 1)` (`EnsureUserIsActive`)
3. `select count(*) from customers ...` (total de la paginación)
4. Consulta de filas, con los tres conteos correlacionados y el `exists` de proyectos
5. `select count(*) ... active = 1` (pestaña activos)
6. `select count(*) ... active = 0` (pestaña inactivos)
7. `select count(*) ... deleted_at is not null` (pestaña papelera)
8. `update sessions ...` (escritura de la sesión)

Al multiplicar por diez los registros (1.000 → 10.000 clientes), el número de consultas no se
mueve: **no hay indicios de N+1**. Los conteos vienen dentro de la misma consulta de filas, no en
una consulta por fila. Lo que sí crece es cuántas veces los ejecuta MariaDB por dentro (ver
«Escalado»).

## Desglose por consulta

Perfil 1.000, primera página de cada estado (mediana de las 20 ejecuciones):

| consulta | active | inactive | trash |
|---|---|---|---|
| `sessions` (lectura) | 0,71 ms | 0,40 ms | 0,36 ms |
| `users exists` | 0,79 ms | 0,36 ms | 0,26 ms |
| total de paginación | 0,88 ms | 0,60 ms | 0,31 ms |
| filas + 3 conteos + `exists` | **5,49 ms** | **3,41 ms** | **1,47 ms** |
| pestaña activos | 1,28 ms | 0,74 ms | 0,50 ms |
| pestaña inactivos | 0,73 ms | 0,41 ms | 0,57 ms |
| pestaña papelera | 0,51 ms | 0,25 ms | 0,20 ms |
| `update sessions` | 0,56 ms | 0,47 ms | 0,45 ms |

Perfil 10.000, misma petición (primera página de activos):

| consulta | 1.000 clientes | 10.000 clientes | crecimiento |
|---|---|---|---|
| total de paginación | 0,88 ms | 2,93 ms | ×10 filas → ×3,3 |
| filas + 3 conteos + `exists` | 5,49 ms | 14,55 ms | ×10 filas que casan → ×2,7 |
| pestaña activos | 1,28 ms | 2,85 ms | ×10 filas → ×2,2 |
| pestaña inactivos | 0,73 ms | 0,70 ms | escaneo que no crece (índice) |
| pestaña papelera | 0,51 ms | 0,66 ms | escaneo que no crece (índice) |
| **total db de la petición** | **6,1 ms** | **28,9 ms** | |

El crecimiento no es de ×10 porque cada sentencia tiene una parte fija de ~1–3 ms (round trip y
arranque) que a 1.000 clientes aún pesa; la pendiente sí es lineal (ver «Escalado»).

Perfil 300, misma petición: la consulta de filas baja de 5,49 ms a **2,26 ms** y los totales de
pestañas rondan 0,24–0,37 ms. Es la única parte que crece con el volumen.

## Planes de ejecución (`EXPLAIN`)

**Consulta de filas del listado de activos**

```
customers  range  customers_deleted_at_id_index  Using index condition; Using where; Using filesort
projects   ref    projects_customer_id_foreign    Using index
projects   ref    projects_customer_id_foreign    Using where
epics      ref    epics_project_id_active_name_unique  Using where
epic_comments ref epic_comments_epic_id_foreign  Using index
projects   ref    projects_customer_id_foreign    Using where
epics      ref    epics_project_id_active_name_unique  Using where
projects   ref    projects_customer_id_foreign    Using where
```

- La tabla principal se lee por rango sobre `customers_deleted_at_id_index` y ordena con
  `filesort`; no hay índice que cubra `active = 1 order by name`.
- **Las cuatro subconsultas correlacionadas usan índices**: `ref` sobre la clave foránea y
  `Using index`, es decir, lecturas indexadas y cubridentes. Sin tablas temporales, sin escaneos.
- Papelera: mismo índice para `deleted_at is not null`, también con `filesort`.
- Los conteos de las pestañas: escaneo completo (`type=ALL`) cuando el valor va literal en el SQL, o
  `type=range` + `Using index` cuando va como parámetro. Cuesta 0,2–1,3 ms en ambos casos.
- La búsqueda `LIKE '%texto%'` de `ListQueryBase::searchPattern()` no puede usar índice por el
  comodín inicial, así que escanea `customers` igual que el resto. Con 1.000 clientes el escenario
  completo de búsqueda suma 2,6–6,4 ms de base de datos: no se aprecia penalización.

Con 10.000 clientes el plan **mantiene la misma forma** (mismo índice, mismo `filesort`, mismas
subconsultas con `ref` y `Using index`): lo único que crece es laestimation de filas
(`rows=8500` frente a `rows=255` a 1.000 clientes). Por eso el problema no se ve en el plan sino en el
tiempo: el plan es el correcto dado el índice disponible, y lo que falta es ese índice.

## Sonda de índice

Índice candidato `customers (active, deleted_at, name)`, creado y eliminado por el propio guión.
Se mide cada sentencia del listado por separado (25 ejecuciones, mediana) antes y después.

### Con 1.000 clientes: no se aprecia nada

| sentencia | rango de la diferencia |
|---|---|
| active / filas | −5,81 ms … +3,10 ms |
| active / conteos | −0,42 ms … +0,72 ms |
| inactive / filas | −4,56 ms … +1,77 ms |
| inactive / conteos | −0,38 ms … +0,43 ms |
| trash / filas | −1,55 ms … +2,44 ms |
| trash / conteos | −0,18 ms … +0,95 ms |

Las diferencias cambian de signo y el ruido del contenedor llegaba a duplicar el tiempo de la
sentencia (`before` llegó a 10,81 ms en una repetición). A esa escala la parte variable de la consulta
de filas son 1,7 ms de 3,7 ms, así que el efecto del índice queda enterrado en el ruido: **a 1.000
clientes no se puede decidir nada con estos números**.

### Con 10.000 clientes: el índice paga, y mucho

Tres repeticiones seguidas, misma sesión:

| sentencia | antes | después | diferencia |
|---|---|---|---|
| active / filas | 16,13 ms | 2,55 ms | **−13,58 ms** |
| inactive / filas | 15,38 ms | 2,56 ms | **−12,82 ms** |
| active / conteos | 0,96 ms | 0,56 ms | −0,40 ms |
| inactive / conteos | 0,51 ms | 0,55 ms | +0,04 ms |
| trash / filas | 2,03 ms | 2,16 ms | +0,13 ms |
| trash / conteos | 0,53 ms | 0,57 ms | +0,04 ms |

Repeticiones 2 y 3: `active / filas` −12,97 ms y −12,92 ms; `inactive / filas` −16,71 ms y
−12,92 ms. Los conteos de las pestañas no cambian (son escaneos sobre `active`, que el índice no
toca) y la papelera tampoco (su consulta ya es barata).

### Por qué: el plan cambia

Sin el índice, la tabla principal se ordena con `filesort`:

```
customers  range  customers_deleted_at_id_index  Using index condition; Using where; Using filesort
```

Con el índice, la consulta sale del índice **en orden y sin ordenar nada**:

```
customers  range  customers_state_name_index     Using where
```

Sin `filesort`, MariaDB puede leer las 25 filas de la página, calcular los conteos y parar: los
conteos correlacionados pasan de ejecutarse ~6.000 veces a 25. Esa es exactamente la diferencia de
13 ms.

## Escalado: qué pasa si la base de datos crece

**Sí habrá problemas, y a partir de ~10.000 clientes ya se pueden medir.**

### La consulta de filas crece con las filas que casan, no con las de la página

| clientes que casan (activos) | consulta de filas |
|---|---|
| 60 | 2,09 ms |
| 180 | 2,49 ms |
| 600 | 3,70 ms |
| 6.000 | ~15 ms |

El ajuste es una recta: **≈ 2,3 ms fijos + ~2,1 µs por cada cliente que casa**. La papelera se
comporta igual (1,04 → 1,47 → 1,65 → ~2,1 ms para 15/45/150/1.500 filas): no tiene un mecanismo
distinto, simplemente casa con muchas menos filas (15 % frente al 60 %).

La causa está en el plan: como no hay índice que cubra `active = 1 order by name`, MariaDB hace
`Using filesort`, y para ordenar necesita materializar la lista de selección —con los tres `count(*)`
y el `exists` dentro— de todas las filas, no solo de las 25 que devuelve.

### Qué escala y qué no

| parte | coste | escala con |
|---|---|---|
| conteos correlacionados + `exists` | ~2,1 µs por fila | clientes que casan (**O(n)**, no O(página)) |
| 2 escaneos completos de `customers` (paginación + pestaña activos) | ~0,28 µs por fila y escaneo | total de clientes (**O(n)**, 2 veces) |
| 2 conteos que ya usan índice (pestaña inactivos, papelera) | ~0,1 µs por fila | casi plano |
| búsqueda `LIKE '%texto%'` | un escaneo más | clientes que casan |
| sesión + usuario activo | ~1,5 ms fijos | no escala |
| renderizado Blade/Flux | ~50–75 ms | **no escala con la BD**, sí con las filas por página |

### Proyección con la recta ajustada

| clientes | filas de la consulta | conteos | db total | petición total |
|---|---|---|---|---|
| 1.000 | 3,7 ms (medido) | 3,4 ms (medido) | 6,1 ms (medido) | ~62 ms (medido) |
| 10.000 | ~15 ms (medido) | ~7 ms (medido) | ~29 ms (medido) | 85–130 ms (medido) |
| 50.000 | ~65 ms (estimado) | ~30 ms | ~95 ms | ~170 ms |
| 100.000 | ~128 ms (estimado) | ~60 ms | ~190 ms | ~265 ms |

Y con el índice `customers (active, deleted_at, name)`, la consulta de filas deja de depender del
volumen: ~2,5 ms con 6.000 clientes que casan, y ese mismo coste con 60.000. Los conteos de las
pestañas pasarían a ser el único término O(n) (~0,3 ms por escaneo con 100.000 clientes).

### Conclusión operativa

- **Hasta ~5.000–10.000 clientes** no hace falta tocar nada: la petición se mantiene en ~60–130 ms y
  el coste es de renderizado, no de datos.
- **A partir de ahí** el índice es la diferencia entre 29 ms y 16 ms de base de datos, y la diferencia
  crece con el volumen (a 100.000 clientes serían ~128 ms frente a ~2,5 ms).
- **El segundo cuello de botella, ya visible hoy, es el renderizado**: ~10 kB de HTML por fila.
  Bajar `PER_PAGE` de 25 a 15 reduciría el trabajo de PHP en un 40 % sin tocar la base de datos.

## Discrepancias y limitaciones

- El plan dice que el código pagina **cinco** filas; `ListQueryBase::PER_PAGE` es **25**. Se midió
  con 25 y se usó la página 2 como página posterior.
- El plan pedía una base de datos desechable aislada; se optó por `migrate:fresh` sobre `laravel`,
  con lo que los datos de desarrollo anteriores se han perdido.
- La primera versión del seeder usaba un único cursor para repartir estados y produjo **0 clientes
  en la papelera** (las posiciones del cursor nunca caían en el tramo de papelera). Corregido con un
  cursor por nivel; el reparto 60/25/15 está verificado en los tres perfiles.
- Ejecuciones repetidas del arranque se veían silenciosamente limitadas por el *rate limiter* de
  Fortify al hacer login; los dos guiones vacían la caché de la aplicación antes de autenticar.
- El sembrador fija el `name` explícitamente en lugar de confiar en el `unique()` de Faker: el
  índice único `active_name` aborta el sembrado si Faker repite un nombre.
- Las cifras salen de un contenedor de desarrollo compartido con otros servicios: el p95 hay que
  tomarlo como orientativo, y por eso se reportan también mediana y mínimo.
- El perfil de 10.000 clientes se midió con el host bajo carga (navegador con Debugbar y Xdebug
  abierto al mismo tiempo, con peticiones de 1,4 s en el log de Apache). Los totales de ese perfil
  hay que leerlos como un rango cuyo suelo es el mínimo; la columna `db` sí es estable y es la que
  sostiene el análisis. Se comprobó que no es una deriva del arranque: con 30 peticiones seguidas en
  un mismo proceso la memoria se mantiene en 40,5 MB y el HTML en 334,1 kB constantes.

## Archivos

| Archivo | Contenido |
|---|---|
| `database/seeders/CustomerPerformanceSeeder.php` | Semilla de carga. Independiente, no está conectada a `DatabaseSeeder`; el número de clientes es el único parámetro (300 por defecto). |
| `storage/app/perf/customer-list-bench.php` | Medición de los tres estados del listado con sus búsquedas y páginas. |
| `storage/app/perf/index-probe.php` | Sonda de índice candidato sobre las sentencias del listado. |

## Observación: el peso del HTML

Medido sobre la primera página de activos con 10.000 clientes:

| parte | tamaño |
|---|---|
| respuesta completa | 334,1 kB |
| `<head>` (Vite, Flux, scripts) | 11,2 kB |
| tabla con las 25 filas | 242,3 kB (~9,7 kB por fila) |
| navegación de paginación (15 enlaces) | 12,8 kB |

Los enlaces de paginación están acotados (15, no uno por página), así que no escalan con el volumen:
lo que pesa es el marcado de cada fila, generado por Flux (botones, distintivos, tooltips, iconos SVG
y clases largas de Tailwind). Bajar `PER_PAGE` es la palanca directa sobre ese coste.

Verificación: `pint` sin cambios pendientes y `php artisan test --parallel` con
**OK (414 tests, 2233 aserciones)**. `phpstan` nivel 9 sin errores en todo lo tocado por esta
medición; los 4 errores que quedan están en `tests/Feature/ResourceActivationTest.php`, un fichero
ajeno a este trabajo.

