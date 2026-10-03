[] demasiado espacio a la izquierda en la pantalla del protatil
[] avisos validaciones, en el ikono info mostrar todas las validaciones, que debe ser unico, que la fecha fin debe se maryor que la de incio, y asi con todas las validaciones que ser hagan sobre ese campo
[] crear el CRUD de usuarios
crear un nuevo archivo de configuracion y configurar la ruta register, por defecto sera false y entonces no se podran registrar nuevos usuarios desde fuera
[] añadir created_by, updated_by, deleted_by en tdodas las tablas (modificar migrations, no crear nueva) Crear un Trait que modifique los valorres de esos campos cada vez que se hace una operacón sobre los dtos de la tabla
[] añadir datatables
[] installar logviewsee
[] gestion de roles y permisos
[x] en los select hay dos opciones: 
    1. si el formulario se abre en modo create, en el select o select2 solo se mostrarán los que tengan active=1
    2. si el formulario se abre en modo edición, en el select o select2 se mostraran el elemento sseleccionado (tenga o no active=1) y el resto de elementos seran solo los que tengan acitive=1 
[] if (in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true)) { se usa en varios sitiios, crea una funcion isSqlite como helper para poder reuitlzarla
 [] cachear listados select public function selectOptions y Observer para limpiar la cache ante cualquier modificación en el original
 [] listado bezeroak, quitar sortze-data y añadir columnas con el número de proyectos y el número de epicas (formato, como el del número de comments en el listado epics)
 [] si hubiera muchos datos en todoas lass atablas, analiza el compoartamiento de las tres listados (N+1, excesivo tiempo de carga)
 [] test sin db prioridad
 [] en vez de cada modelo tener su propio el cliente se ha guardado correctamente o el cliente se a borrado o nuevo cliente y todos los que hay del mismo estilo, utilizar terminología genérica siempre que el usuario entienda dónde esta. Utilizar se ha guardado correctamente o se ha creado correctamete o no se puede eliminar y similare. Con esto, reducimos las traducciones y tenemos más código que se podría pasar a las bases y reutilizar, lo mismo con los test.
 [] al filtrar no se muestrra el numero total de resultado sencontrados

----------------------------------------------------------------------------------
[] cladue => diseinuarena pendiente (tokenak berreskuratu arte)
----------------------------------------------------------------------------------
[ ] eres un cliente muy esquisito, repasa la apliación en busca de fallos y propon todas las mejorass que se te ocurran. escribe un task en .github
[ ] ahora busca incongruencias entre los modelos, que tienen de diferente, que se gestiona de forma diferente en un modelo que en otro, que está testeado en uno si y en otro no, que código tienen repetido, donde hay sobreingenieria. escribe un task en .github
[ ] /full-review del proyecto
[ ] /fix-review 06.model-consistency-audit.md antes de modificar pide confirmación y al terminar marca la tarea como realizada
[ ] repasa la configuración de los AGENTES y analiza si hay alguna manera de ahorrar tokens y de mejorar la forma de trabajar.
[ ] prepara un plan que cree seeders para llenar las tablas con cientos de registros y luego analice el rendimiento del listado
[ ] Repasar los errores que da el SonarQube y buscar una posible solucion. Preguntar antes de cambiar nada