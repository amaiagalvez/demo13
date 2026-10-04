[] crear el CRUD de usuarios
crear un nuevo archivo de configuracion y configurar la ruta register, por defecto sera false y entonces no se podran registrar nuevos usuarios desde fuera
[] añadir datatables
[] gestion de roles y permisos
[x] en los select hay dos opciones: 
    1. si el formulario se abre en modo create, en el select o select2 solo se mostrarán los que tengan active=1
    2. si el formulario se abre en modo edición, en el select o select2 se mostraran el elemento sseleccionado (tenga o no active=1) y el resto de elementos seran solo los que tengan acitive=1 
[] 
[] cachear listados select public function selectOptions y Observer para limpiar la cache ante cualquier modificación en el original
 [] test sin db prioridad
 [] al filtrar no se muestrra el numero total de resultado sencontrados
[] en vez de RESULTS_LIMIT usar PER_PAGE

----------------------------------------------------------------------------------
[] cladue => diseinuarena pendiente (tokenak berreskuratu arte)
----------------------------------------------------------------------------------
[ ] eres un cliente muy esquisito, repasa la apliación en busca de fallos y propon todas las mejorass que se te ocurran. escribe un task en .github
[x] ahora busca incongruencias entre los modelos, que tienen de diferente, que se gestiona de forma diferente en un modelo que en otro, que está testeado en uno si y en otro no, que código tienen repetido, donde hay sobreingenieria. escribe un task en .github → 10.model-divergence-review.md
[x] /full-review del proyecto → 10.model-divergence-review.md (el refactor en curso del working tree también está revisado en su sección 2)
[ ] /fix-review .github/tasks/10.model-divergence-review antes de modificar pide confirmación y al terminar marca la tarea como realizada
[ ] repasa la configuración de los AGENTES y analiza si hay alguna manera de ahorrar tokens y de mejorar la forma de trabajar.
[ ] prepara un plan que cree seeders para llenar las tablas con cientos de registros y luego analice el rendimiento del listado
[ ] Repasar los errores que da el SonarQube y buscar una posible solucion. Preguntar antes de cambiar nada

