[] crear el CRUD de usuarios
crear un nuevo archivo de configuracion y configurar la ruta register, por defecto sera false y entonces no se podran registrar nuevos usuarios desde fuera
[] añadir datatables
[] gestion de roles y permisos
[x] en los select hay dos opciones: 
    1. si el formulario se abre en modo create, en el select o select2 solo se mostrarán los que tengan active=1
    2. si el formulario se abre en modo edición, en el select o select2 se mostraran el elemento sseleccionado (tenga o no active=1) y el resto de elementos seran solo los que tengan acitive=1 
[] cachear listados select public function selectOptions y Observer para limpiar la cache ante cualquier modificación en el original

[] crea un plan: crear un nuevo paquete customers13 y pasar todo lo relacionado con Customer, la tabla pasa a llamarse PRO_projects, PRO_epics, PRO_epic_comments (cambiar migration, no esta en producción) todo lo que se pase al nuevo paquete quitar de demo13 y adaptar los namespaces y lo que sea necesario. este nuevo paquete dependara en DEV de basics13 para que puedan pasar los test

[] composer.json require y require-dev pasar a basics los que se pueda (debugbar no)


----------------------------------------------------------------------------------
[] cladue => diseinuarena pendiente (tokenak berreskuratu arte)
----------------------------------------------------------------------------------

# REVIEWS
[ ] eres un cliente muy esquisito, repasa la apliación en busca de fallos y propon todas las mejorass que se te ocurran. escribe un task en .github
[ ] ahora busca incongruencias entre los modelos, que tienen de diferente, que se gestiona de forma diferente en un modelo que en otro, que está testeado en uno si y en otro no, que código tienen repetido, donde hay sobreingenieria. escribe un task en .github
[ ] skill full-review del proyecto
[ ] skill fix-review .github/reviews/<fecha>/CODE-REVIEW.md antes de modificar pide confirmación y al terminar marca la tarea como realizada

[ ] Repasar los errores que da el SonarQube y buscar una posible solucion. Preguntar antes de cambiar nada

# PERFORMANCE
[ ] prepara un plan o acualiza el que ya existe que cree seeders para llenar las tablas con cientos de registros y luego analice el rendimiento del listado

# AGENTS
[ ] busca incongruencias entre lo que hay en el código y lo que hay dentro de la carpeta .github. muestrame una lista y vete preguntandome una por una, dame una posible solución y pide confirmación atnes de hacer nada 
[ ] analiza tambien el fichero how.md y comparalo con lo que hay en la carpeta .github y busca incongurencias. muestrame una lista y vete preguntandome una por una, dame una posible solución y pide confirmación atnes de hacer nada 
[ ] repasa la configuración de los AGENTES y analiza si hay alguna manera de ahorrar tokens y de mejorar la forma de trabajar.