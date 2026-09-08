export const meta = {
  name: 'readme-submodulo-movil',
  description: 'Actualizar el README de Menu08 para documentar el submodulo movil: panel de propuestas, sintesis y verificacion',
  phases: [
    { title: 'Propuestas', detail: 'tres enfoques independientes de la actualizacion' },
    { title: 'Sintesis', detail: 'juez unico que elige y injerta lo mejor de cada uno' },
    { title: 'Verificacion', detail: 'comprobacion adversarial contra el repositorio real' },
  ],
}

const RAIZ = '/home/i5/projects/private/Menu08'
const README = RAIZ + '/README.md'

const HECHOS = `
HECHOS VERIFICADOS DEL REPOSITORIO (no los contradigas, no inventes otros):

- El submodulo esta anadido y YA CONFIRMADO por el desarrollador en el commit 7a2b0d9.
  .gitmodules contiene:
      [submodule "movil"]
              path = movil
              url = git@github.com:Lain-Ramirez/GA8-220501096-AA2-EV02.git
              branch = production
- El submodulo apunta a la rama 'production' del repositorio publico
  Lain-Ramirez/GA8-220501096-AA2-EV02, hoy en el commit 2dfd55e.
- Ese repositorio HOY solo contiene un README.md con su titulo. El proyecto Android
  todavia no existe: sus quince issues estan abiertos, ninguno cerrado.
- Que sera: una aplicacion Android nativa en Kotlin, SIN bibliotecas de terceros, con
  DOS funciones y nada mas: iniciar sesion con los usuarios que ya existen en Menu08, y
  un boton que lee el GPS y reporta donde esta parado el food truck. El boton actualiza
  la latitud y longitud de la parada vigente y, si no hay ninguna vigente, registra una
  parada nueva.
- Es la evidencia SENA GA8-220501096-AA2-EV02 (APK). Por eso el alcance es tan corto.
- Los servicios que consumira, POST /movil/ingresar y POST /movil/ubicacion, se
  programaran en ESTE repositorio (Menu08) y TODAVIA NO EXISTEN: no estan en
  menu08_app/configuracion/rutas.php. No los documentes como si funcionaran.
- Dentro de Menu08 el submodulo se ve en la carpeta 'movil/'. Desde la raiz de aquel
  repositorio, 'aplicacion/src/main/...' es aqui 'movil/aplicacion/src/main/...'.
- La carpeta 'postman/' EXISTE en la raiz y hoy NO aparece en el arbol de carpetas del
  README, igual que 'movil/'. Tambien falta, aunque no lo pidiera nadie.
- Las carpetas que SI son espejo del servidor siguen siendo dos y solo dos:
  menu08_app/ y ADSO.menu08.com/. El submodulo NO se despliega al hosting y NO va en
  el ZIP de despliegue.
`

const REGLAS = `
REGLAS QUE NO SE NEGOCIAN:

1. REGLA ABSOLUTA: nunca escribas el caracter arroba seguido de la palabra claude.
2. Todo en espanol. El README es Markdown, asi que lleva acentos normales.
3. El tablero se llama SVP (Sistema de Visualizacion de Produccion). Nunca la sigla
   inglesa de ese tipo de pantalla de cocina.
4. La plataforma tiene TRES modulos: CARTA, CAJA y SVP. El APK NO es un cuarto modulo
   y no debe presentarse como tal: es un anexo, una evidencia formativa con un alcance
   deliberadamente corto. No toques la tabla de los tres modulos de la cabecera ni la
   frase "CARTA se construye primero".
5. Nada de bibliotecas de terceros, ni mencionarlas como opcion.
6. No documentes rutas, archivos ni funciones que todavia no existen como si existieran.
   Si hablas de lo que vendra, dilo en futuro y con su issue.
7. Respeta el tono del README: frases cortas, concretas, sin adjetivos de relleno, con
   el "por que" al lado de cada decision.
`

const ENFOQUES = [
  {
    clave: 'minimo',
    guia: 'Enfoque QUIRURGICO. Cambia lo estrictamente necesario para que el README deje ' +
      'de ser falso: la frase que dice que son "las dos carpetas de primer nivel", el arbol ' +
      'de carpetas y el git clone que sin --recursive deja movil/ vacio. Nada mas. Prefiere ' +
      'anadir tres lineas a anadir tres parrafos.',
  },
  {
    clave: 'completo',
    guia: 'Enfoque de DOCUMENTACION COMPLETA. Ademas de lo anterior, considera si merece la ' +
      'pena una subseccion breve que explique que es el modulo movil, por que vive en otro ' +
      'repositorio como submodulo, y como se trabaja con el (traer, actualizar, y que pasa ' +
      'al cambiar de rama). Piensa tambien si la seccion "Estado del desarrollo" deberia ' +
      'enlazar los issues de aquel repositorio. Justifica cada anadido: si algo no gana su ' +
      'sitio, no lo metas.',
  },
  {
    clave: 'recien-llegado',
    guia: 'Enfoque del RECIEN LLEGADO. Ponte en quien clona el repositorio hoy por primera ' +
      'vez y no sabe que existe un submodulo. Que le va a fallar, en que orden, y que frase ' +
      'del README se lo habria evitado. El clon sin --recursive deja movil/ vacia y sin aviso; ' +
      'un git pull no actualiza el submodulo solo. Escribe lo minimo que le ahorra cada ' +
      'tropiezo, en el sitio donde lo va a leer justo antes de tropezar.',
  },
]

const ESQUEMA_EDICIONES = {
  type: 'object',
  properties: {
    ediciones: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          sitio: { type: 'string', description: 'donde cae, p.ej. "Estructura de carpetas"' },
          viejo: { type: 'string', description: 'texto EXACTO y literal del README actual, unico en el archivo' },
          nuevo: { type: 'string', description: 'texto que lo sustituye' },
          motivo: { type: 'string', description: 'por que hace falta, en una frase' },
        },
        required: ['sitio', 'viejo', 'nuevo', 'motivo'],
      },
    },
    notas: { type: 'string', description: 'lo que consideraste y descartaste, y por que' },
  },
  required: ['ediciones', 'notas'],
}

phase('Propuestas')
const propuestas = await parallel(ENFOQUES.map((e) => () => agent(
  'Vas a proponer como actualizar el README de un proyecto formativo para documentar un ' +
  'submodulo de git recien anadido.\n\nLee ENTERO el archivo ' + README + ' antes de escribir ' +
  'nada, y explora ' + RAIZ + ' para comprobar lo que afirmes.\n' + HECHOS + REGLAS +
  '\n\n' + e.guia +
  '\n\nDevuelve una lista de ediciones. Cada una lleva el texto VIEJO copiado LITERALMENTE del ' +
  'README —debe aparecer una sola vez en el archivo, palabra por palabra, con sus acentos y su ' +
  'espaciado exactos, para poder aplicarlo con una sustitucion automatica— y el texto NUEVO que ' +
  'lo reemplaza. Si anades algo donde antes no habia nada, usa como VIEJO una linea vecina que ' +
  'sea unica y repitela dentro del NUEVO. Comprueba tu mismo la unicidad de cada VIEJO con grep ' +
  'antes de darlo por bueno.',
  { label: 'propone:' + e.clave, phase: 'Propuestas', schema: ESQUEMA_EDICIONES }
)))

const vivas = propuestas.filter(Boolean)
log('Propuestas recibidas: ' + vivas.length + ' de ' + ENFOQUES.length +
    ' (' + vivas.map((p) => p.ediciones.length + ' ediciones').join(', ') + ')')

phase('Sintesis')
const sintesis = await agent(
  'Eres el juez. Tienes tres propuestas independientes para actualizar el mismo README, hechas ' +
  'con enfoques distintos: quirurgico, documentacion completa y recien llegado.\n\n' +
  'Lee ENTERO ' + README + ' y explora ' + RAIZ + '.\n' + HECHOS + REGLAS +
  '\n\nPROPUESTAS:\n\n' + JSON.stringify(vivas, null, 2) +
  '\n\nElige la mejor como base e injerta de las otras dos lo que de verdad aporte. Criterio: el ' +
  'README debe dejar de mentir y debe evitarle tropiezos a quien clone, sin engordar. Un anadido ' +
  'que no salva un error concreto ni responde una pregunta real sobra: quitalo y dilo en las notas. ' +
  'Vigila que las tres propuestas no se pisen entre si dejando el mismo dato en dos sitios.\n\n' +
  'Devuelve el conjunto final de ediciones, sin solapes, cada una con su VIEJO literal y unico.',
  { label: 'juez', phase: 'Sintesis', schema: ESQUEMA_EDICIONES, effort: 'high' }
)

phase('Verificacion')
const verificado = await agent(
  'Eres el verificador adversarial. Supon que estas ediciones tienen errores y encuentralos.\n\n' +
  'Lee ENTERO ' + README + ' y comprueba contra el repositorio real en ' + RAIZ + '.\n' +
  HECHOS + REGLAS +
  '\n\nEDICIONES PROPUESTAS:\n\n' + JSON.stringify(sintesis, null, 2) +
  '\n\nComprueba una por una:\n' +
  '1. Que cada texto VIEJO aparece EXACTAMENTE UNA VEZ en ' + README + '. Compruebalo de verdad, ' +
  'con grep -c y una cadena fija; si aparece cero veces o dos, arreglalo hasta que aparezca una. ' +
  'Este es el fallo mas probable y el que rompe la aplicacion automatica.\n' +
  '2. Que toda orden de git que aparezca es correcta y hace lo que el texto dice. En particular ' +
  'comprueba la diferencia real entre clonar con y sin --recursive, y que orden actualiza un ' +
  'submodulo ya clonado. No escribas una orden que no hayas razonado.\n' +
  '3. Que ninguna ruta, archivo o carpeta citada sea inventada: comprueba que existe.\n' +
  '4. Que no se documenta como existente nada que todavia no exista (las rutas /movil/... no ' +
  'estan en menu08_app/configuracion/rutas.php).\n' +
  '5. Que el APK no queda presentado como un cuarto modulo de la plataforma.\n' +
  '6. Que el resultado de aplicar TODAS las ediciones es un Markdown coherente: sin frases ' +
  'cortadas, sin datos duplicados en dos sitios, con los bloques de codigo bien cerrados y el ' +
  'arbol de carpetas alineado como estaba.\n' +
  '7. Ortografia y acentos del espanol, y el tono del README.\n\n' +
  'Corrige TODO lo que falle y devuelve el conjunto final. En las notas, di que encontraste.',
  { label: 'verifica', phase: 'Verificacion', schema: ESQUEMA_EDICIONES, effort: 'high' }
)

log('Ediciones finales: ' + (verificado ? verificado.ediciones.length : 0))
return verificado || sintesis
