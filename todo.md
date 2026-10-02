[] 
[] 
[] añade el campo active a cada modelo (no esta prodcción, se puede cambiar las migraciones)
en el listado index se mostrarán solo las que tengan active = 1 y no esten borradas
en el listado papelera se mostrarán todas las eliminadas, independientemente del campo active
añadir un nuevo listado, antes dela papelera, que muestre todas los que tengan active = 0 y no esten borradas. en este listado las acciones que se muestran serán reactivar, que lo que hace es poner active = 1
en este nuevo listado, se mostrarán las mismas columnas que en el index y al final la fecha ultima modificacion
en los listados del index, si un registro no se puede borrar, en vez del icono de la papelera deberá aparecer el icono de bloquear, que pedirá confirmación y hara active = 0
[] github vs opencode (task bak dago)
----------------------------------------------------------------------------------
[ ] eres un cliente muy esquisito, repasa la apliación en busca de fallos y propon todas las mejorass que se te ocurran. escribe un task en .github
[ ] ahora busca incongruencias entre los modelos, que tienen de diferente, que se gestiona de forma diferente en un modelo que en otro, que está testeado en uno si y en otro no, que código tienen repetido. escribe un task en .github
[ ] repasa la configuración de los AGENTES y analiza si hay alguna manera de ahorrar tokens.
