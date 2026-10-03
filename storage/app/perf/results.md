# Rendimiento del listado de clientes

Medición realizada siguiendo `.github/tasks/09.performancce-test-plan.md`.

## Conclusión

1. **No hay N+1.** Cada petición del listado ejecuta **8 consultas** en los tres estados y en
   los tres perfiles de volumen. El número no crece al aumentar los registros.
2. **La base de datos no es el cuello de botella.** De los ~60 ms de cada petición, solo 4–6 ms
   (7–10 %) son consultas. El resto (~50 ms) es PHP: renderizado de Blade y componentes Flux.
   Este coste **no depende del volumen**: 100 o 1.000 clientes dan prácticamente lo mismo.
3. **Pasar de 100 a 1.000 clientes (×10) añade solo ~1,5–2 ms de base de datos.**
4. **Las subconsultas correlacionadas de conteo son el coste de base de datos real**: ~5,5 ms de
   los 6,1 ms de la consulta principal con 1.000 clientes. Todas usan índices cubridentes, sin
   tablas temporales ni escaneos.
5. **Los escaneos completos y el `filesort` que aparecen en los planes son reales pero Baratos**
   (~1 ms). La sonda de un índice candidato `(active, deleted_at, name)` dio diferencias que
   oscilan entre negativo y positivo, es decir **no hay ganancia medible**, por lo que **no se
   propone ninguna optimización**.
6. El único punto a vigilar a futuro es el número de filas por página: los conteos correlacionados
   se ejecutan 4 veces por fila (proyectos, épicas, comentarios, `exists` de proyectos).
7. **El coste O(n) está diferido, no ausente**: hoy la base de datos es el 7–10 % de la petición,
   pero crece ~2,9 µs por cada cliente que casa y la consulta no se rompe hasta alrededor de
   20.000–25.000 clientes. Ver el apartado «Escalado».

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

## Número de consultas

Constante en todos los casos: **8 consultas** por petición (7 cuando el resultado está vacío, ya
que no se ejecuta la consulta de filas; 9 en la petición que inserta la fila de sesión en lugar de
actualizarla). Reparto:

1. `select * from sessions where id = ?` (recuperación de sesión)
2. `select exists(select * from users where id = ? and active = 1)` (`EnsureUserIsActive`)
3. `select count(*) from customers ...` (total de la paginación)
4. Consulta de filas, con los tres conteos correlacionados y el `exists` de proyectos
5. `select count(*) ... active = 1` (pestaña activos)
6. `select count(*) ... active = 0` (pestaña inactivos)
7. `select count(*) ... deleted_at is not null` (pestaña papelera)
8. `update sessions ...` (escritura de la sesión)

Al multiplicar por diez los registros, el número de consultas no se mueve: **no hay indicios de
N+1**. Los conteos vienen dentro de la misma consulta de filas, no en una consulta por fila.

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

Perfil 300, misma petición: la consulta de filas baja de 5,49 ms a **2,26 ms** y los totales de
pestañas rondan 0,24–0,37 ms. Es la única parte que crece con el volumen.

## Planes de ejecución (`EXPLAIN`, perfil 1.000)

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

## Sonda de índice

Índice candidato `customers (active, deleted_at, name)`, medido sobre 1.000 clientes con 25
ejecuciones por sentencia y repetido varias veces. Diferencia (después − antes) de cada sentencia:

| sentencia | rango de la diferencia |
|---|---|
| active / filas | −5,81 ms … +3,10 ms |
| active / conteos | −0,42 ms … +0,72 ms |
| inactive / filas | −4,56 ms … +1,77 ms |
| inactive / conteos | −0,38 ms … +0,43 ms |
| trash / filas | −1,55 ms … +2,44 ms |
| trash / conteos | −0,18 ms … +0,95 ms |

Las diferencias cambian de signo entre repeticiones y su magnitud es comparable a la propia
sentencia: **el índice no aporta una mejora medible a esta escala**. El ruido del contenedor llega
a duplicar el tiempo de la sentencia (`before` llegó a 10,81 ms en una repetición), así que el
límite de ruido de este entorno es del mismo orden que el efecto que se quiere medir; decidir sobre
índices con estos números exigiría un entorno más silencioso. Conforme a lo que pedía el plan, **no
se propone ninguna optimización**. Si el volumen creciera dos órdenes de magnitud habría que volver
a medir, porque los escaneos completos y el `filesort` sí son el punto por el que se rompería esta
consulta.

## Escalado: qué pasa si la base de datos crece

**Sí habrá problemas, y se sabe por dónde antes de que aparezcan.** Lo que sigue sale de los tres
perfiles ya medidos, sin necesidad de un perfil mayor.

### La consulta de filas crece con las filas que casan, no con las de la página

Agrupando por número de clientes que casan con el filtro (no por volumen total):

| clientes que casan (activos) | consulta de filas | incremento |
|---|---|---|
| 60 | 2,09 ms | — |
| 180 | 2,49 ms | +0,40 ms por 120 filas |
| 600 | 3,70 ms | +1,21 ms por 420 filas |

El ajuste es casi perfecto a una recta: **≈ 2,0 ms fijos + 2,9 µs por cada cliente que casa**. La
papelera se comporta igual (1,04 → 1,47 → 1,65 ms para 15/45/150 filas).

Es decir, **los conteos correlacionados no se ejecutan 25 veces (las de la página), sino una vez por
cada cliente que casa**. La causa está en el plan: como no hay índice que cubra
`active = 1 order by name`, MariaDB hace `Using filesort`, y para ordenar necesita materializar la
lista de selección —con los tres `count(*)` y el `exists` dentro— de todas las filas, no solo de las
25 que devuelve.

### Qué escala y qué no

| parte | coste | escala con |
|---|---|---|
| conteos correlacionados + `exists` | ~2,9 µs por fila | clientes que casan (**O(n)**, no O(página)) |
| 4 escaneos completos de `customers` (paginación + 3 pestañas) | ~0,75 µs por fila y escaneo | total de clientes (**O(n)**, 4 veces) |
| búsqueda `LIKE '%texto%'` | un escaneo más | clientes que casan |
| sesión + usuario activo | ~1,5 ms fijos | no escala |
| renderizado Blade/Flux | ~50 ms | **no escala** (25 filas siempre) |

### Proyección

| clientes | db | petición total |
|---|---|---|
| 1.000 | 6,1 ms (medido) | ~62 ms (medido) |
| 10.000 | ~65 ms (estimado) | ~120 ms (estimado) |
| 100.000 | ~650 ms (estimado) | ~700 ms (estimado) |

El punto de inflexión está alrededor de **20.000–25.000 clientes** (unos 75.000 proyectos, 300.000
épicas y 900.000 comentarios): ahí la petición deja de estar en ~60 ms y se va a 200 ms o más. Por
debajo de 10.000 clientes el listado aguanta bien, porque hoy el renderizado (~50 ms) pesa más que
la base de datos.

### Qué lo arreglaría

Un índice que permita leer **solo las 25 filas de la página ya ordenadas** (por ejemplo
`customers (active, deleted_at, name)`) convierte los conteos en O(página): constante, y con ella
desaparecen el `filesort` y los 2,9 µs por fila. Los cuatro escaneos completos se quedarían
(~0,3 ms por escaneo con 100.000 filas) y pasarían a ser la parte irrelevante.

La sonda **no lo confirmó** a 1.000 clientes: las diferencias cambiaban de signo y el ruido del
contenedor llegaba a duplicar el tiempo de la sentencia. Es coherente con el modelo —a esa escala la
parte variable son 1,7 ms de 3,7 ms—, pero hace falta medirlo donde la parte variable domine el
tiempo. Por eso se lanzó además un perfil de **10.000 clientes** (≈520.000 filas); su resultado
completaría este apartado y decidiría si el índice empieza a pagar. La conclusión de este
documento (no se propone optimización) sigue en pie hasta que ese dato esté disponible.

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

## Archivos

| Archivo | Contenido |
|---|---|
| `database/seeders/CustomerPerformanceSeeder.php` | Semilla de carga. Independiente, no está conectada a `DatabaseSeeder`; el número de clientes es el único parámetro (300 por defecto). |
| `storage/app/perf/customer-list-bench.php` | Medición de los tres estados del listado con sus búsquedas y páginas. |
| `storage/app/perf/index-probe.php` | Sonda de índice candidato sobre las sentencias del listado. |

Verificación tras los cambios: `pint` sin cambios pendientes, `phpstan` nivel 9 sin errores y
`php artisan test --parallel` con **OK (370 tests, 1841 aserciones)**.

