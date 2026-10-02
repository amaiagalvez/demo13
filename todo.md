[] crear el CRUD de usuarios
crear un nuevo archivo de configuracion y configurar la ruta register, por defecto sera false y entonces no se podran registrar nuevos usuarios desde fuera
[] añadir created_by, updated_by, deleted_by en tdodas las tablas (modificar migrations, no crear nueva) Crear un Trait que modifique los valorres de esos campos cada vez que se hace una operacón sobre los dtos de la tabla
[] añadir datatables
[] en los select hay dos opciones: 
    1. si el formulario se abre en modo create, en el select o select2 solo se mostrarán los que tengan active=1
    2. si el formulario se abre en modo edición, en el select o select2 se mostraran el elemento sseleccionado (tenga o no active=1) y el resto de elementos seran solo los que tengan acitive=1 
[] github vs opencode (task bak dago)
----------------------------------------------------------------------------------
[ ] eres un cliente muy esquisito, repasa la apliación en busca de fallos y propon todas las mejorass que se te ocurran. escribe un task en .github
[ ] ahora busca incongruencias entre los modelos, que tienen de diferente, que se gestiona de forma diferente en un modelo que en otro, que está testeado en uno si y en otro no, que código tienen repetido, donde hay sobreingenieria. escribe un task en .github
[ ] repasa la configuración de los AGENTES y analiza si hay alguna manera de ahorrar tokens.
