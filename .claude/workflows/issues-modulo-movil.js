export const meta = {
  name: 'issues-modulo-movil',
  description: 'Redactar, refutar y armonizar los trece issues del modulo movil GA8-220501096-AA2-EV02',
  phases: [
    { title: 'Redaccion', detail: 'un redactor por grupo tematico de issues' },
    { title: 'Refutacion', detail: 'verificador adversarial contra el codigo real y las APIs de Android' },
    { title: 'Coherencia', detail: 'critico unico sobre los trece cuerpos juntos' },
    { title: 'Correccion', detail: 'aplicar los arreglos que sobrevivan' },
  ],
}

const ESPEC = '/tmp/claude-1000/-home-i5-projects-private-Menu08/6d09292a-8745-4b9c-b8af-b342f3fff6b6/scratchpad/espec-issues.md'
const RAIZ = '/home/i5/projects/private/Menu08'

const REGLAS = [
  'REGLA ABSOLUTA: nunca escribas la cadena formada por el caracter arroba seguido de la palabra claude. Ni en el cuerpo, ni en un ejemplo, ni en un comentario. En GitHub es una mencion que notifica.',
  'Todo el texto en espanol, con acentos normales (es Markdown, no codigo PHP).',
  'Las casillas van SIN marcar: "- [ ]", nunca "- [x]".',
  'No propongas ninguna biblioteca de terceros, ni en PHP ni en Android.',
  'No uses la sigla inglesa del tablero de cocina; el tablero se llama SVP.',
  'Respeta el esqueleto de secciones al caracter, en el orden dado.',
].join('\n')

const GRUPOS = [
  {
    clave: 'servicios',
    numeros: [2, 3, 10],
    foco: 'Los dos servicios JSON nuevos en el repositorio Menu08 y su documentacion. Lee de verdad ' +
      RAIZ + '/menu08_app/aplicacion/modelos/Ubicacion.php, ' +
      RAIZ + '/menu08_app/aplicacion/controladores/SvpControlador.php, ' +
      RAIZ + '/menu08_app/aplicacion/controladores/AutenticacionControlador.php, ' +
      RAIZ + '/menu08_app/aplicacion/nucleo/Controlador.php, ' +
      RAIZ + '/menu08_app/configuracion/rutas.php, ' +
      RAIZ + '/docs/api-svp.md y ' + RAIZ + '/POSTMAN.md antes de escribir. Cita nombres reales. ' +
      'El #3 tiene que dejar clarisima la regla: actualiza latitud y longitud de la parada vigente, ' +
      'y si no hay ninguna vigente en ese momento crea una parada nueva con el punto actual, dentro ' +
      'de una transaccion con SELECT ... FOR UPDATE. Piensa que valores toman nombre, referencia, ' +
      'dia_semana, hora_inicio y hora_fin en esa parada nueva y escribelo como criterio verificable.',
  },
  {
    clave: 'cliente',
    numeros: [4, 5, 9],
    foco: 'El andamiaje del proyecto Android, la capa de red con sesion, y el endurecimiento. ' +
      'Sin dependencias de terceros: HttpsURLConnection, CookieManager, corrutinas del propio Kotlin. ' +
      'El #5 debe explicar por que los POST viajan como application/x-www-form-urlencoded con el ' +
      'campo _token: porque el servidor lee el token unicamente de $_POST[_token] y asi no se toca ' +
      'el nucleo. Se concreto con minSdk, targetSdk y applicationId, y justifica el minSdk elegido ' +
      'contra las APIs de ubicacion que se van a usar.',
  },
  {
    clave: 'pantallas',
    numeros: [6, 7, 8],
    foco: 'Las dos pantallas y la logica de ubicacion. Reparte limpio: #6 la pantalla de ingreso, ' +
      '#7 lo que se ve en la pantalla de ubicacion y sus estados, #8 el permiso en tiempo de ' +
      'ejecucion, la captura del punto y el envio. Cubre los casos feos y hazlos criterios: permiso ' +
      'denegado, permiso denegado para siempre, ubicacion aproximada en lugar de fina desde Android ' +
      '12, proveedor de GPS apagado, sin cobertura de red, punto viejo devuelto por ' +
      'getLastKnownLocation, sesion caducada a mitad de uso.',
  },
  {
    clave: 'entrega',
    numeros: [1, 11, 12, 13],
    foco: 'El issue paraguas de trazabilidad, la firma del APK, las pruebas en dispositivo y la ' +
      'documentacion de este repositorio. El #1 enumera los otros doce y separa que se hace en el ' +
      'repositorio Menu08 (#2, #3, #10) de lo que se hace en este. El #12 sigue el estilo de ' +
      RAIZ + '/docs/pruebas-agenda-paradas.md: salida real, intentos fallidos con su causa, y una ' +
      'seccion honesta de lo que no se cubrio. Leelo antes de escribir.',
  },
]

const ESQUEMA_ISSUES = {
  type: 'object',
  properties: {
    issues: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          numero: { type: 'integer' },
          cuerpo: { type: 'string' },
        },
        required: ['numero', 'cuerpo'],
      },
    },
  },
  required: ['issues'],
}

const ESQUEMA_CRITICA = {
  type: 'object',
  properties: {
    veredicto: { type: 'string' },
    arreglos: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          numero: { type: 'integer' },
          problemas: { type: 'array', items: { type: 'string' } },
        },
        required: ['numero', 'problemas'],
      },
    },
  },
  required: ['veredicto', 'arreglos'],
}

const redactados = await pipeline(
  GRUPOS,
  (g) => agent(
    'Redactas issues para un proyecto formativo SENA. Lee entera la especificacion en ' + ESPEC +
    ' y trabaja desde el repositorio ' + RAIZ + '.\n\n' + REGLAS +
    '\n\nRedacta el CUERPO COMPLETO en Markdown de estos issues: #' + g.numeros.join(', #') +
    '.\n\nFoco de este grupo: ' + g.foco +
    '\n\nCada cuerpo empieza por el bloque de cita con Orden de construccion, Depende de y Bloquea a ' +
    'exactamente como dice la tabla de la seccion 5 de la especificacion, y termina con la linea de ' +
    'guiones y el pie de Estimacion y Fase. Cinco o seis criterios y cinco o seis tareas por issue. ' +
    'Cada criterio con un numero, un codigo de respuesta HTTP, un nombre de archivo o una condicion ' +
    'concreta dentro. Copia la voz del ejemplo verbatim de la seccion 7: frases cortas, sin relleno. ' +
    'Devuelve un objeto con un elemento por issue.',
    { label: 'redacta:' + g.clave, phase: 'Redaccion', schema: ESQUEMA_ISSUES }
  ),
  (borrador, g) => borrador === null ? null : agent(
    'Eres un verificador adversarial. Tu trabajo es REFUTAR, no aprobar. Lee ' + ESPEC +
    ' y comprueba contra el codigo real en ' + RAIZ + ' y contra la documentacion oficial de Android.\n\n' +
    REGLAS +
    '\n\nEstos son los borradores del grupo ' + g.clave + ':\n\n' +
    JSON.stringify(borrador.issues, null, 2) +
    '\n\nBusca, con la suposicion de partida de que hay errores:\n' +
    '1. Metodos, columnas, clases, constantes o rutas PHP citados que NO existen en el repositorio ' +
    'y que el issue tampoco declara como nuevos. Verificalo abriendo los archivos.\n' +
    '2. Afirmaciones falsas sobre Android: APIs que no existen, que existen en otro nivel de API ' +
    'del declarado como minSdk, permisos mal nombrados, comportamientos que se inventaron.\n' +
    '3. Criterios que no se pueden comprobar, o que dependen del juicio de quien revisa.\n' +
    '4. Criterios o tareas que se pisan con otro issue de la tabla de la seccion 5.\n' +
    '5. Bibliotecas de terceros coladas.\n' +
    '6. Desviaciones del esqueleto: encabezados cambiados, casillas marcadas, secciones fuera de orden, ' +
    'dependencias que no coinciden con la tabla.\n\n' +
    'Corrige TODO lo que encuentres y devuelve los cuerpos ya arreglados, completos, con el mismo ' +
    'formato. Si un borrador ya estaba bien, devuelvelo tal cual.',
    { label: 'refuta:' + g.clave, phase: 'Refutacion', schema: ESQUEMA_ISSUES }
  )
)

const todos = redactados.filter(Boolean).flatMap((r) => r.issues).sort((a, b) => a.numero - b.numero)
log('Cuerpos redactados y refutados: ' + todos.length + ' de 13')

const critica = await agent(
  'Eres el critico de coherencia. Lee ' + ESPEC + ' y luego los TRECE cuerpos juntos:\n\n' +
  JSON.stringify(todos, null, 2) +
  '\n\n' + REGLAS +
  '\n\nRevisa lo que solo se ve mirandolos a la vez:\n' +
  '1. Las referencias cruzadas: que el Depende de y el Bloquea a de cada issue cuadren con la tabla ' +
  'de la seccion 5 Y sean reciprocos entre si. Si el #5 dice que bloquea al #8, el #8 tiene que decir ' +
  'que depende del #5.\n' +
  '2. Solapes: dos issues que reclaman el mismo trabajo, o un trabajo necesario que no reclama nadie. ' +
  'Recorre el camino completo: ingreso en el APK, pulsar el boton, permiso, punto capturado, POST, ' +
  'transaccion, respuesta pintada. Di que eslabon falta si falta alguno.\n' +
  '3. Terminologia: que el mismo concepto se llame igual en los trece (parada vigente, punto, ' +
  'ubicacion, token, sesion, aplicacion movil).\n' +
  '4. Contradicciones tecnicas entre issues: distinto minSdk, distinto nombre de ruta, distinto ' +
  'formato de cuerpo, distinto codigo de error para el mismo caso.\n' +
  '5. Que el reparto declarado en la seccion 5 no se contradiga con lo que el cuerpo pide hacer.\n\n' +
  'Devuelve un veredicto en una frase y, por cada issue con problemas, la lista concreta de arreglos. ' +
  'Un issue sin problemas NO aparece en la lista. No inventes problemas para parecer util.',
  { label: 'coherencia', phase: 'Coherencia', schema: ESQUEMA_CRITICA, effort: 'high' }
)

const porArreglar = (critica && critica.arreglos ? critica.arreglos : []).filter((a) => a.problemas.length > 0)
log('Veredicto de coherencia: ' + (critica ? critica.veredicto : 'sin veredicto') +
    ' | issues con arreglos: ' + (porArreglar.length ? porArreglar.map((a) => '#' + a.numero).join(', ') : 'ninguno'))

if (porArreglar.length === 0) {
  return { issues: todos, veredicto: critica ? critica.veredicto : null, arreglados: [] }
}

const gruposConArreglos = GRUPOS
  .map((g) => ({ grupo: g, arreglos: porArreglar.filter((a) => g.numeros.includes(a.numero)) }))
  .filter((x) => x.arreglos.length > 0)

const corregidos = await parallel(gruposConArreglos.map((x) => () => agent(
  'Aplica arreglos concretos a issues ya redactados. Lee ' + ESPEC + ' para el esqueleto y las reglas.\n\n' +
  REGLAS +
  '\n\nCuerpos actuales:\n\n' +
  JSON.stringify(todos.filter((i) => x.arreglos.some((a) => a.numero === i.numero)), null, 2) +
  '\n\nArreglos exigidos por el critico de coherencia:\n\n' +
  JSON.stringify(x.arreglos, null, 2) +
  '\n\nAplica cada arreglo. No reescribas lo que no esta senalado, no cambies la voz y no toques el ' +
  'esqueleto. Si un arreglo te parece equivocado porque contradice la especificacion o el codigo real, ' +
  'NO lo apliques y explica por que en una linea al final del cuerpo precedida por "NOTA DEL REDACTOR:" ' +
  'para que el humano la vea y la borre. Devuelve los cuerpos completos ya corregidos.',
  { label: 'corrige:' + x.grupo.clave, phase: 'Correccion', schema: ESQUEMA_ISSUES }
)))

const mapa = new Map(todos.map((i) => [i.numero, i]))
corregidos.filter(Boolean).flatMap((r) => r.issues).forEach((i) => mapa.set(i.numero, i))

const finales = Array.from(mapa.values()).sort((a, b) => a.numero - b.numero)
log('Corregidos: ' + porArreglar.map((a) => '#' + a.numero).join(', '))

return { issues: finales, veredicto: critica ? critica.veredicto : null, arreglados: porArreglar.map((a) => a.numero) }
