export const meta = {
  name: 'disenar-agenda-paradas-36',
  description: 'Disenar el backend de la agenda de paradas (issue #36) de Menu08: consulta de parada vigente nocturna, mapeo de criterios y plan de archivos',
  phases: [
    { title: 'Diseno', detail: 'Tres disenos independientes de la consulta vigente + tres auditorias del contexto' },
    { title: 'Verificacion', detail: 'Un adversario por diseno con vectores concretos' },
    { title: 'Juicio', detail: 'Juez que elige y sintetiza, mas critico de completitud' },
  ],
}

const RAIZ = '/home/i7/projects/work/Menu08'

const CONTEXTO = `
Proyecto Menu08 en ${RAIZ}. LEE PRIMERO ${RAIZ}/CLAUDE.md entero: fija reglas innegociables
(todo en espanol sin acentos dentro del codigo PHP, MVC propio sin frameworks ni Composer,
PDO preparado, food_truck_id siempre de la sesion, 404 y no 403 para recursos de otro food truck,
el tablero se llama SVP y jamas se escribe la sigla inglesa, NO hacer commits).

Archivos clave que debes leer antes de responder:
- ${RAIZ}/menu08_app/basedatos/esquema.sql  (tabla ubicaciones, lineas ~80-110)
- ${RAIZ}/menu08_app/basedatos/datos_pruebas.sql  (banco de pruebas, incluye 4 ubicaciones)
- ${RAIZ}/menu08_app/aplicacion/modelos/Categoria.php  y  Producto.php  (patron de modelo)
- ${RAIZ}/menu08_app/aplicacion/controladores/CategoriaControlador.php  (patron de controlador)
- ${RAIZ}/menu08_app/aplicacion/controladores/CartaControlador.php
- ${RAIZ}/menu08_app/aplicacion/nucleo/Validador.php , Controlador.php , ConexionBD.php
- ${RAIZ}/menu08_app/aplicacion/vistas/panel/categorias.php  (patron de vista funcional)
- ${RAIZ}/menu08_app/configuracion/rutas.php
- ${RAIZ}/POSTMAN.md  y  ${RAIZ}/postman/Menu08.postman_collection.json
- ${RAIZ}/docs/pruebas-svp-estado.md  y  ${RAIZ}/docs/basedatos.md

DATOS DE LA TABLA ubicaciones:
  id, food_truck_id, nombre VARCHAR(120) NOT NULL, referencia VARCHAR(200) NULL,
  latitud DECIMAL(10,7) NULL, longitud DECIMAL(10,7) NULL,
  dia_semana TINYINT UNSIGNED NOT NULL (1 lunes ... 7 domingo, CHECK 1..7),
  hora_inicio TIME NOT NULL, hora_fin TIME NOT NULL (si es <= hora_inicio, cierra al dia siguiente),
  activa TINYINT(1) NOT NULL DEFAULT 1, creado_en, actualizado_en.
  KEY ix_ubicaciones_agenda (food_truck_id, dia_semana, activa).

ISSUE #36 "Programar la agenda de paradas del food truck", Fase 3 - Backend y API.
Criterios de aceptacion literales:
 1. Desde el panel se listan, crean, editan, activan y desactivan paradas con punto, referencia,
    dia de la semana, hora de inicio y hora de fin, y toda consulta filtra por el food_truck_id
    de la sesion: el identificador de una parada de otro food truck devuelve 404.
 2. El formulario rechaza un dia fuera del rango de 1 a 7 y una hora mal formada, mostrando el
    mensaje junto al campo correspondiente.
 3. La consulta de parada vigente resuelve la jornada que cruza la medianoche: una parada
    declarada de 18:00 a 01:00, consultada a las 00:30, se devuelve como vigente.
 4. Una parada con activa = 0 no se devuelve ni en la consulta de parada vigente ni en la agenda
    que consume la carta publica.
 5. Ninguna sentencia de Ubicacion.php concatena valores de $_GET o $_POST: todas preparadas.
 6. Un POST sin token CSRF valido se rechaza con 403 y la base de datos no registra ningun cambio.
Tareas: crear modelos/Ubicacion.php, la consulta de vigente con hora_fin <= hora_inicio como
jornada del dia siguiente, controladores/UbicacionControlador.php, anadir a Validador.php reglas
de dia de la semana, hora y coordenada opcional, registrar rutas exigiendo sesion, y dejar en
docs/ la comprobacion manual del caso nocturno.

RESTRICCIONES DEL ENTORNO: no hay php ni cliente mysql en esta maquina (solo curl). La evidencia
se obtiene contra https://adso.menu08.com despues de que el desarrollador suba el ZIP.
`

const DISENOS = [
  {
    key: 'sql-puro',
    angulo: `Angulo A: TODA la logica en una sola sentencia SQL preparada. La consulta recibe
      el dia y la hora como parametros y resuelve en el WHERE los tres casos (jornada diurna del
      dia, jornada nocturna que empieza hoy, jornada nocturna que empezo ayer con el envolvimiento
      domingo->lunes). Recuerda la trampa documentada en CLAUDE.md: PDO va SIN emulacion de
      preparadas, un marcador nombrado solo puede aparecer UNA vez por sentencia.`,
  },
  {
    key: 'php-ventanas',
    angulo: `Angulo B: PHP calcula las ventanas candidatas (dia de hoy y dia de ayer) y la SQL
      queda lo mas simple posible: se traen las paradas activas de esos dos dias y PHP decide
      cual esta vigente comparando marcas de tiempo reales con DateTimeImmutable. Argumenta el
      coste (filas traidas) y la ganancia (logica testeable sin base de datos).`,
  },
  {
    key: 'hibrido',
    angulo: `Angulo C: hibrido. SQL filtra por food_truck_id, activa y los dos dias candidatos
      usando el indice ix_ubicaciones_agenda, y la comparacion horaria se hace en la propia SQL
      pero expresada con una normalizacion (por ejemplo minutos desde el inicio de la jornada)
      que sea facil de leer y de explicar en la sustentacion del SENA.`,
  },
]

const DISENO_SCHEMA = {
  type: 'object',
  properties: {
    resumen: { type: 'string', description: 'Dos o tres frases explicando el enfoque' },
    firmas: {
      type: 'array',
      description: 'Firmas PHP propuestas para el modelo Ubicacion',
      items: {
        type: 'object',
        properties: {
          firma: { type: 'string' },
          proposito: { type: 'string' },
        },
        required: ['firma', 'proposito'],
      },
    },
    codigo_vigente: { type: 'string', description: 'Codigo PHP completo del metodo vigente(), listo para pegar, con comentarios en espanol sin acentos' },
    codigo_agenda: { type: 'string', description: 'Codigo PHP del metodo que alimenta la agenda de la carta publica' },
    zona_horaria: { type: 'string', description: 'Como se decide el momento actual y por que (servidor UTC vs America/Bogota), y si se inyecta el momento para poder probarlo' },
    casos_borde: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          caso: { type: 'string' },
          resultado: { type: 'string' },
          justificacion: { type: 'string' },
        },
        required: ['caso', 'resultado', 'justificacion'],
      },
    },
    riesgos: { type: 'array', items: { type: 'string' } },
  },
  required: ['resumen', 'firmas', 'codigo_vigente', 'codigo_agenda', 'zona_horaria', 'casos_borde', 'riesgos'],
}

const VEREDICTO_SCHEMA = {
  type: 'object',
  properties: {
    vectores: {
      type: 'array',
      description: 'Vectores de prueba concretos ejecutados mentalmente contra el codigo propuesto',
      items: {
        type: 'object',
        properties: {
          entrada: { type: 'string' },
          esperado: { type: 'string' },
          obtenido: { type: 'string' },
          pasa: { type: 'boolean' },
        },
        required: ['entrada', 'esperado', 'obtenido', 'pasa'],
      },
    },
    defectos: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          gravedad: { type: 'string' },
          descripcion: { type: 'string' },
          correccion: { type: 'string' },
        },
        required: ['gravedad', 'descripcion', 'correccion'],
      },
    },
    veredicto: { type: 'string', description: 'CORRECTO, CORREGIBLE o DESCARTAR' },
  },
  required: ['vectores', 'defectos', 'veredicto'],
}

phase('Diseno')

const disenosVerificados = pipeline(
  DISENOS,
  d => agent(`${CONTEXTO}

TU TAREA: disena la consulta de parada vigente y la agenda semanal del modelo Ubicacion.

${d.angulo}

Escribe codigo PHP real, en el estilo exacto de ${RAIZ}/menu08_app/aplicacion/modelos/Categoria.php:
clase final, metodos estaticos, ConexionBD::obtener()->prepare(), comentarios en espanol SIN acentos,
docblocks con @return list<array<string, mixed>>. El food_truck_id siempre como parametro.

Piensa a fondo el caso de la jornada nocturna y el envolvimiento de la semana (una parada del
domingo, dia 7, de 20:00 a 02:00, consultada el lunes, dia 1, a la 01:00, DEBE salir vigente).
Decide y justifica si el limite hora_fin es inclusivo o exclusivo, y que significa hora_inicio
igual a hora_fin. Decide el ORDER BY que hace determinista el resultado cuando dos paradas se
solapan. No inventes columnas que no existen.`,
    { label: `diseno:${d.key}`, phase: 'Diseno', schema: DISENO_SCHEMA }),

  (diseno, d) => agent(`${CONTEXTO}

TU TAREA: eres un adversario. Intenta REFUTAR este diseno de la consulta de parada vigente.
Por defecto asume que tiene un fallo hasta que compruebes lo contrario.

DISENO "${d.key}":
${JSON.stringify(diseno, null, 2)}

Ejecuta mentalmente, uno por uno, al menos estos vectores contra el codigo literal propuesto,
y anade los que se te ocurran:
 1. Parada dia 6 (sabado) 18:00-01:00 activa. Consulta: domingo (dia 7) 00:30 -> debe ser VIGENTE.
 2. La misma parada. Consulta: sabado (dia 6) 00:30 -> NO debe ser vigente.
 3. La misma parada. Consulta: sabado 17:59:59 -> NO. Sabado 18:00:00 -> SI. Domingo 00:59:59 -> SI.
    Domingo 01:00:00 -> decidir y justificar.
 4. Parada dia 7 (domingo) 20:00-02:00. Consulta: lunes (dia 1) 01:00 -> debe ser VIGENTE
    (envolvimiento 7 -> 1).
 5. Parada dia 3 11:00-15:00. Consulta miercoles 14:59 -> SI; miercoles 15:00 -> NO;
    jueves 00:30 -> NO.
 6. Parada con activa = 0 en cualquier ventana -> NUNCA vigente, y ausente de la agenda publica.
 7. Parada con hora_inicio = hora_fin = 12:00 -> que devuelve y es coherente con el comentario
    del esquema.
 8. Dos paradas solapadas el mismo dia -> el resultado es determinista.
 9. Inyeccion: el dia y la hora llegan de $_GET o $_POST manipulados -> comprueba que TODO va
    por marcadores y que ningun marcador nombrado se repite en la misma sentencia (PDO sin
    emulacion falla con HY093 si se repite).
10. Un food_truck_id distinto no ve la parada de otro.

Se literal: si el SQL propuesto repite un marcador, dilo. Si el calculo del dia de ayer usa
una resta que da 0 en lunes, dilo. Marca pasa=false en cuanto el resultado obtenido no coincida
con el esperado.`,
    { label: `refutar:${d.key}`, phase: 'Verificacion', schema: VEREDICTO_SCHEMA, effort: 'high' })
      .then(v => ({ key: d.key, diseno, veredicto: v }))
)

const AUDITORIAS = [
  {
    key: 'criterios',
    prompt: `TU TAREA: mapear criterio por criterio el issue #36 contra la realidad del repositorio,
      y senalar TODO desajuste antes de que se escriba una linea de codigo.

      Presta atencion especial a estos puntos, verificandolos leyendo el codigo:
      a) El issue dice "rutas exigiendo sesion con rol negocio o plataforma". Comprueba en
         ${RAIZ}/menu08_app/basedatos/esquema.sql que roles existen realmente en el ENUM de usuarios,
         y como resuelven este mismo asunto CategoriaControlador y ProductoControlador. Determina
         cual es el rol correcto y explica que pasaria si un usuario con rol plataforma entrara
         (mira Controlador::foodTruckActual() y Sesion::foodTruckId()).
      b) La frontera con el issue #37 (Fase 4, "Maquetar la agenda de paradas y el bloque donde
         estamos hoy de la carta"). Que parte de la vista corresponde a #36 y cual a #37. Mira el
         precedente: los issues #16 y #17 de Fase 4 siguen abiertos y sin embargo panel/categorias.php
         y carta/publica.php ya existen en el repositorio. Deduce la convencion del proyecto.
      c) El criterio 4 habla de "la agenda que consume la carta publica". Determina si #36 debe
         tocar CartaControlador.php y la vista carta/publica.php, o solo dejar el metodo del modelo.
      d) Como se evidencia el criterio 3 (caso de las 00:30) sin poder esperar a las 00:30 y sin
         php ni mysql en la maquina local. Propon una forma honesta y reproducible.

      Devuelve una tabla criterio -> artefacto concreto -> como se evidencia, y una lista separada
      de DESAJUSTES que hay que consultar con el desarrollador antes de codificar.`,
  },
  {
    key: 'archivos',
    prompt: `TU TAREA: producir la lista exhaustiva y ordenada de archivos a crear o modificar
      para cerrar el issue #36, con el detalle suficiente para que otro los escriba sin volver a
      leer todo el repositorio.

      Para cada archivo: ruta exacta, si es nuevo o modificado, y que exactamente cambia.
      Incluye, y verifica leyendo el codigo real:
      - modelos/Ubicacion.php: la lista completa de metodos con firma.
      - controladores/UbicacionControlador.php: acciones, en que orden se llama a exigirRol,
        verificarCsrf, foodTruckActual, cuando se lanza RutaNoEncontrada, cuando se responde 422
        repintando la vista, cuando se llama Csrf::rotar() y a donde se redirige.
      - nucleo/Validador.php: firmas exactas de los metodos nuevos (dia de la semana, hora,
        coordenada opcional) coherentes con texto(), precio() y entero(). Ojo con el detalle de
        que texto() guarda null en limpios cuando el campo opcional viene vacio.
      - configuracion/rutas.php: las lineas exactas a anadir y donde, respetando el orden y los
        comentarios de seccion existentes.
      - vistas/panel/ubicaciones.php: que estructura, copiando el patron de panel/categorias.php.
      - vistas/panel/inicio.php: el enlace de navegacion que falta.
      - PanelControlador.php: si el resumen del panel debe contar paradas.
      - basedatos/datos_pruebas.sql y datos_iniciales.sql: si hay que tocarlos.
      Di tambien que NO hay que tocar y por que.`,
  },
  {
    key: 'docs',
    prompt: `TU TAREA: definir el rastro documental que exige el proyecto para el issue #36.

      Lee ${RAIZ}/POSTMAN.md completo y ${RAIZ}/postman/Menu08.postman_collection.json, y
      ${RAIZ}/docs/pruebas-svp-estado.md y ${RAIZ}/docs/pruebas-caja-orden.md.

      CLAUDE.md exige que POSTMAN.md y la coleccion se actualicen en el MISMO cambio que la ruta.
      Devuelve:
      1. La seccion exacta que hay que anadir a POSTMAN.md: donde encaja, con que numeracion,
         que rutas, que rol, que campos de cuerpo exactos y que codigos de respuesta, imitando
         el estilo y el tono de las secciones existentes.
      2. Que items hay que anadir a la coleccion de Postman: nombre, metodo, url, cuerpo, y si
         hacen falta pruebas (tests) o variables nuevas, siguiendo la estructura del JSON actual.
      3. El esqueleto de docs/pruebas-agenda-paradas.md: que comprobaciones lleva, con que
         comandos curl reales contra https://adso.menu08.com (incluido el rescate del _token
         antes de cada POST), y que debe decir la seccion honesta de lo que las pruebas NO cubren.
      4. Si algun otro documento de docs/ queda desactualizado por este cambio (basedatos.md,
         nucleo.md, README.md) y que linea concreta habria que tocar.`,
  },
]

const auditorias = parallel(AUDITORIAS.map(a => () =>
  agent(`${CONTEXTO}\n\n${a.prompt}`, { label: `auditar:${a.key}`, phase: 'Diseno' })
    .then(texto => ({ key: a.key, texto }))))

const [verificados, auditados] = await Promise.all([disenosVerificados, auditorias])

const vivos = verificados.filter(Boolean)
const contextoAuditorias = auditados.filter(Boolean).map(a => `### Auditoria ${a.key}\n${a.texto}`).join('\n\n')

log(`${vivos.length} disenos verificados, ${auditados.filter(Boolean).length} auditorias completas`)

phase('Juicio')

const JUICIO_SCHEMA = {
  type: 'object',
  properties: {
    ganador: { type: 'string' },
    razon: { type: 'string' },
    codigo_final_vigente: { type: 'string', description: 'Codigo PHP definitivo del metodo vigente(), ya corregido con lo que encontraron los adversarios' },
    codigo_final_agenda: { type: 'string', description: 'Codigo PHP definitivo de la agenda semanal para la carta publica' },
    injertos: { type: 'array', items: { type: 'string' }, description: 'Ideas tomadas de los disenos perdedores' },
    decisiones: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          asunto: { type: 'string' },
          decision: { type: 'string' },
          motivo: { type: 'string' },
        },
        required: ['asunto', 'decision', 'motivo'],
      },
    },
    consultar_al_desarrollador: {
      type: 'array',
      description: 'Lo que hay que preguntar ANTES de codificar, segun la regla de CLAUDE.md sobre criterios que no caben en el esquema',
      items: {
        type: 'object',
        properties: {
          asunto: { type: 'string' },
          opciones: { type: 'string' },
          recomendacion: { type: 'string' },
        },
        required: ['asunto', 'opciones', 'recomendacion'],
      },
    },
  },
  required: ['ganador', 'razon', 'codigo_final_vigente', 'codigo_final_agenda', 'injertos', 'decisiones', 'consultar_al_desarrollador'],
}

const juicio = await agent(`${CONTEXTO}

TU TAREA: eres el juez. Tienes tres disenos de la consulta de parada vigente, cada uno ya
sometido a un adversario, y tres auditorias del contexto. Elige el mejor, injerta lo bueno de
los otros y entrega el codigo definitivo, ya corregido con todos los defectos que encontraron
los adversarios.

Criterio de eleccion, en este orden: (1) que resuelva bien TODOS los vectores, incluido el
envolvimiento domingo->lunes; (2) que sea explicable en una sustentacion del SENA por un
aprendiz, porque este es un proyecto formativo; (3) que respete las reglas de CLAUDE.md;
(4) que use el indice ix_ubicaciones_agenda.

DISENOS Y VEREDICTOS:
${JSON.stringify(vivos, null, 2)}

AUDITORIAS DEL CONTEXTO:
${contextoAuditorias}

En "consultar_al_desarrollador" recoge solo lo que de verdad exige una decision humana antes de
codificar (por ejemplo el rol del issue frente al ENUM real, o la frontera con el issue #37),
con una recomendacion clara para cada punto.`,
  { label: 'juez', phase: 'Juicio', schema: JUICIO_SCHEMA, effort: 'high' })

const critico = await agent(`${CONTEXTO}

TU TAREA: eres el critico de completitud. Revisa el plan resultante y di QUE FALTA.

PLAN:
${JSON.stringify(juicio, null, 2)}

AUDITORIAS:
${contextoAuditorias}

Pregunta concretamente: hay algun criterio de aceptacion del issue #36 que este plan no cierre?
Hay algun invariante de CLAUDE.md que el plan debilite? Falta algun archivo en la lista? La
evidencia propuesta es real o es una promesa? Hay algo que rompa lo ya construido (CAJA, SVP,
la carta publica, el QR)? Se olvida POSTMAN.md o la coleccion? Se esta metiendo alcance del
issue #37 sin decirlo? Hay algun riesgo de zona horaria en el servidor compartido?

Responde en prosa breve y directa, en espanol, con una lista numerada de huecos concretos y
como taparlos. Si no falta nada relevante, dilo sin rellenar.`,
  { label: 'critico', phase: 'Juicio', effort: 'high' })

return { juicio, critico, auditorias: auditados.filter(Boolean), veredictos: vivos.map(v => ({ key: v.key, veredicto: v.veredicto })) }
