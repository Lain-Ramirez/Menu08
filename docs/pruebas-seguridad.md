# Pruebas de seguridad

Ejecutadas contra **https://adso.menu08.com**, no en local. **27 comprobaciones nuevas, 0 fallos**,
más la auditoría de código de las sentencias preparadas en `aplicacion/modelos/`.
Cubren inyección SQL, secuencias de comandos en sitios cruzados (XSS), sesión, testigo CSRF,
control de acceso por rol y carga de archivos, que es el alcance del issue #25.

Varios de estos frentes ya tenían evidencia de rondas anteriores — CSRF y control de acceso en
[`pruebas-autenticacion.md`](pruebas-autenticacion.md), carga de archivos en
[`pruebas-panel-carta.md`](pruebas-panel-carta.md), aislamiento de `menu08_app/` en
[`despliegue.md`](despliegue.md) — así que aquí se citan esos resultados y solo se repite una
comprobación fresca de cada uno, en vez de rehacer lo ya probado. Lo que no tenía ninguna prueba
todavía era la inyección SQL y el XSS persistente, así que ahí se concentra el trabajo nuevo.

## CP-SEG-01 — Inyección SQL en el inicio de sesión

`POST /ingresar` con cadenas de inyección en `correo` (y una en `contrasena`), token CSRF válido
en cada intento:

| Carga en `correo` | Código | Cuerpo de la respuesta |
|---|---|---|
| `prueba@menu08.local'` | 401 | mensaje genérico de credenciales incorrectas |
| `' OR '1'='1` | 401 | mensaje genérico de credenciales incorrectas |
| `plataforma@menu08.local'-- ` | 401 | mensaje genérico de credenciales incorrectas |
| `x" OR ""="` (en `correo` y `contrasena`) | 401 | mensaje genérico de credenciales incorrectas |

Ninguna respuesta trajo `PDOException`, `SQLSTATE` ni traza de error — se comprobó con `grep`
sobre el HTML completo de cada respuesta. Ninguna cadena consiguió iniciar sesión como
`plataforma@menu08.local` ni como ningún otro usuario. `Usuario::porCorreo()` usa
`WHERE correo = :correo` con sentencia preparada, así que la cadena entera viaja como un solo
valor y nunca como fragmento de SQL.

## CP-SEG-02 — Inyección SQL en la ruta pública de la carta

No existe una búsqueda de productos en el servidor — el filtro de CAJA
(`aplicacion/vistas/caja/venta.php`) es JavaScript puro sobre las fichas ya cargadas, y no dispara
ninguna consulta por tecla. El punto de entrada real con texto libre de un visitante anónimo es el
segmento `{slug}` de `GET /carta/{slug}`, así que se probó ahí:

| Slug enviado | Código |
|---|---|
| `festin-rodante' OR '1'='1` | 404 |
| `festin-rodante' UNION SELECT 1--` | 404 |
| `x' OR 1=1 -- ` | 404 |

**404 en los tres casos**, sin mensaje de PDO. `FoodTruck::porSlug()` filtra con
`WHERE slug = :slug AND activo = 1`, sentencia preparada: ninguna cadena coincide con un slug real,
así que el controlador lanza `RutaNoEncontrada` antes de que la inyección tenga nada que alterar.

## CP-SEG-03 — Inyección SQL en el filtro de categoría del panel

`GET /panel/productos?categoria=...`, con sesión de `food_truck`:

| Valor de `categoria` | Código | Resultado |
|---|---|---|
| `8' OR '1'='1` | 200 | listado normal, sin mensaje de PDO |
| `8 OR 1=1` | 200 | listado normal, sin mensaje de PDO |
| `-1) UNION SELECT * FROM usuarios-- ` | 200 | listado normal, sin mensaje de PDO |

`ProductoControlador::listado()` hace `(int) ($_GET['categoria'] ?? 0)` **antes** de que el valor
llegue al modelo: la cadena nunca sobrevive como texto, se trunca a un entero en PHP. Es una
defensa por tipo, no por escapado, y ocurre antes de tocar la base de datos.

## CP-SEG-04 — Inyección SQL en el armado de la orden (CAJA)

`POST /caja/vender` construye un `IN (?,?,...)` cuya cantidad de signos de interrogación sale de
`count($lineas)`, con `$lineas` ya filtrada por `CajaControlador::cantidadesEnviadas()`: una clave
que no cumpla `/^\d+$/` se descarta en silencio, la clave incluida.

**Intento 1 — todas las líneas maliciosas**, con sesión de `cajero` y turno abierto:

```
cantidad[1' OR '1'='1]=2
cantidad[999999) UNION SELECT id,nombre,precio FROM usuarios-- ]=1
```

Resultado: **422**, «La orden no tiene ningún producto», sin mensaje de PDO. Las dos claves se
descartaron antes de llegar a `Orden::registrar()`, así que `$lineas` quedó vacía y la transacción
nunca se abrió.

**Intento 2 — una línea real mezclada con una maliciosa:**

```
cantidad[16]=1
cantidad[999999) UNION SELECT id,correo,contrasena FROM usuarios-- ]=5
```

Resultado: **302** a `/caja/comprobante/…`, orden registrada por **$ 14.900** — exactamente el
precio vigente de una unidad del producto 16 («Morcilla santandereana»), sin la más mínima huella
de la clave maliciosa ni de su cantidad `5`. Verificado en el listado de `GET /caja`, sección
«Órdenes de este turno»: el total de esa orden es `$ 14.900`.

> Esta orden quedó registrada de verdad en la base — **`T5-017`, comprobante en
> `/caja/comprobante/45`, nota «PRUEBA SEGURIDAD CP-SEG - descartar»** — porque `Orden::registrar()`
> no separa un modo de prueba, y confirmarlo sin escribir una venta real habría probado la ruta de
> rechazo, no la de éxito. Queda en el turno #5 vigente del food truck de demostración; el cajero
> puede identificarla por esa nota si prefiere excluirla del cuadre.

## CP-SEG-05 — Secuencias de comandos en sitios cruzados (XSS persistente)

Se creó un producto en el panel (`POST /panel/productos`, categoría 8) con:

```
nombre:      <script>alert(1)</script>
descripcion: <img src=x onerror=alert(2)>
```

| Dónde se mira | Resultado |
|---|---|
| Listado `GET /panel/productos` | `&lt;script&gt;alert(1)&lt;/script&gt;` |
| Formulario de edición, atributo `value` del nombre | `value="&lt;script&gt;alert(1)&lt;/script&gt;"` |
| Formulario de edición, contenido del `<textarea>` de la descripción | `&lt;img src=x onerror=alert(2)&gt;` |
| Carta pública `GET /carta/festin-rodante`, producto disponible | `<h3>&lt;script&gt;alert(1)&lt;/script&gt;</h3>` y `<p>&lt;img src=x onerror=alert(2)&gt;</p>` |
| Carta pública, mismo producto marcado no disponible | Igual de escapado, mostrado atenuado con la etiqueta «No disponible» |

**Ni una sola vez el navegador vería una etiqueta ejecutable.** `Vista::e()` corre en las tres
plantillas involucradas (`panel/productos.php`, `panel/producto_formulario.php`, `carta/publica.php`),
sin excepción por campo.

> Queda un residuo de esta prueba en el catálogo real: el producto **id 35**, categoría
> «Para picar», con nombre `<script>alert(1)</script>`. Se marcó **no disponible** para que no se
> ofrezca en la ventanilla, pero como el modelo de datos no borra productos —solo los da de baja,
> para que las órdenes históricas conserven su referencia— seguirá en `panel/productos` hasta que
> alguien lo edite con un nombre real o lo borre directamente en la base. No se le tocó la foto.

## Sesión, testigo CSRF y control de acceso por rol

Ya probado a fondo en [`pruebas-autenticacion.md`](pruebas-autenticacion.md) (19 comprobaciones:
fijación de sesión, `/salir`, las tres rutas privadas sin sesión y con rol equivocado, POST sin
token y con token alterado) y reforzado en
[`pruebas-caja-orden.md`](pruebas-caja-orden.md#el-total-lo-calcula-el-servidor) y
[`pruebas-panel-carta.md`](pruebas-panel-carta.md#token-contra-falsificación-de-peticiones). Aquí
solo se repitió una comprobación de cada uno, para dejar constancia fresca en esta ronda:

| Comprobación | Resultado |
|---|---|
| `POST /panel/productos` sin `_token` | **403**, y el producto de prueba **no** apareció después en el listado |
| `POST /caja/vender` sin `_token`, con turno abierto y una línea válida | **403**, y no se registró ninguna orden nueva en el turno |
| `GET /panel` con sesión de `cajero` | **403** |

## Carga de archivos

El caso concreto que pide la tarea —subir un `.php` renombrado a `.jpg`— ya está ejecutado y
documentado en [`pruebas-panel-carta.md`](pruebas-panel-carta.md#carga-de-imágenes): **422**,
«Solo se admiten imagenes JPG, PNG o WEBP», porque `GestorImagenes::tipoReal()` lee el contenido
real del archivo con `finfo`/`getimagesize`, nunca la extensión ni el `Content-Type` que manda el
navegador.

No se repitió esa subida en esta ronda: este entorno de pruebas bloquea que `curl` adjunte
archivos locales a una petición saliente (protección contra exfiltración de archivos del propio
entorno), así que no hay forma de reproducir un `-F foto=@archivo` desde aquí. En su lugar se
verificaron las otras dos capas de la misma defensa, directamente sobre el servidor:

| Comprobación | Resultado |
|---|---|
| `GET /subidas/prueba-seguridad.php` | 404 |
| `GET /subidas/prueba.phtml` | 404 |
| `GET /subidas/festin-morcilla-santandereana.jpg` (control, debe existir) | 200 |

`ADSO.menu08.com/subidas/.htaccess` trae `php_flag engine off`, `RemoveHandler` para `.php` y
variantes, y `Require all denied` sobre esas extensiones — pero como ningún archivo con esas
extensiones existe de verdad ahí (la aplicación nunca crea uno: `GestorImagenes::guardar()`
siempre renombra a `bin2hex(random_bytes(16))` más la extensión que corresponde al tipo real
detectado), la petición nunca llega a esa regla y el 404 lo devuelve el *front controller* por
ruta inexistente. El resultado práctico es el mismo —nada se ejecuta, nada se sirve— pero por una
razón distinta a la que se probó en `pruebas-panel-carta.md`, y queda anotado en vez de darlo por
sentado.

## `almacenamiento/bitacora/` y `configuracion/` inalcanzables

Ya probado el 9 de septiembre de 2026 en
[`despliegue.md`](despliegue.md#el-código-privado-no-se-alcanza-por-http). Repetido hoy para esta
ronda:

| Petición | Código |
|---|---|
| `/menu08_app/configuracion/rutas.php` | 404 |
| `/menu08_app/configuracion/configuracion.php` | 404 |
| `/menu08_app/almacenamiento/bitacora/` | 404 |
| `/menu08_app/aplicacion/nucleo/ConexionBD.php` | 404 |

Sigue siendo 404 por la misma razón de siempre: `menu08_app/` es hermana de
`ADSO.menu08.com/`, no está bajo la raíz del sitio. No depende de una regla que alguien pueda
desactivar sin querer.

## Auditoría de sentencias preparadas en `aplicacion/modelos/`

Revisadas las siete clases: `Categoria`, `FoodTruck`, `Producto`, `Orden`, `TurnoCaja`,
`Ubicacion`, `Usuario`. **Ningún valor de entrada llega a una consulta por concatenación.**

- Todas usan `prepare()` + `execute()` con marcadores nombrados, salvo dos excepciones que no son
  un problema:
  - `FoodTruck::publicos()` usa `->query()` directo, pero la sentencia es literal y fija —no
    interpola ningún valor—, así que no hay nada que inyectar.
  - `Orden::registrar()` arma un `IN (?,?,?)` con `implode(',', array_fill(...))`, pero la
    cantidad de signos de interrogación sale de `count($lineas)` —un entero de PHP, no de texto
    del usuario— y los valores se pasan todos por `execute()`. Es el patrón correcto para una
    lista de tamaño variable con marcadores sin nombre.
- `Producto::delFoodTruck()` concatena un fragmento de SQL (`' AND p.categoria_id = :cat'`), pero
  el fragmento es literal y el valor real sigue yendo por el marcador `:cat`.
- `Ubicacion::COLUMNAS_CONTRATO` es una constante con nombres de columna fijos, no un valor de
  entrada.

**No se abrió ningún defecto nuevo por esta auditoría.**

## Reejecución de los defectos de fases anteriores

El issue pide reejecutar los casos que fallaron en CARTA, CAJA y el Sistema de Visualización de
Producción tras corregirlos. Los seis defectos de esas fases ya se corrigieron y reverificaron
**dentro de su propio documento**, no quedó ninguno pendiente de repetir:

| Defecto | Fase | Dónde se corrigió y reverificó |
|---|---|---|
| Formato de precio con separador de miles rechazado | CARTA | [`pruebas-panel-carta.md`](pruebas-panel-carta.md#el-defecto-que-encontraron-estas-pruebas) |
| Bits de información de formato transpuestos en el generador de QR | CARTA | [`pruebas-carta-qr.md`](pruebas-carta-qr.md#1-la-información-de-formato-del-qr-transpuesta) |
| Colisión de variables en `Vista::renderizar` (exponía una ruta interna del servidor) | CARTA | [`pruebas-carta-qr.md`](pruebas-carta-qr.md#2-colisión-de-variables-en-el-renderizador-de-vistas) |
| Mensaje de error invisible al rechazar un segundo turno | CAJA | [`pruebas-caja-turno.md`](pruebas-caja-turno.md#defecto-encontrado-y-corregido) |
| Rol `produccion` sin token CSRF propio (dependía de uno heredado que vence) | SVP | [`pruebas-svp-estado.md`](pruebas-svp-estado.md#defecto-encontrado-y-corregido) |
| Servicio de órdenes no distinguía turno vacío de «sin turno» | SVP | [`pruebas-svp-ordenes.md`](pruebas-svp-ordenes.md#defecto-encontrado-y-corregido) |

De los seis, el que tenía relevancia directa de seguridad es la colisión de variables en
`Vista::renderizar`: sin querer, exponía la ruta absoluta del servidor
(`/home/sfacturs2/menu08_app/...`) en el atributo `src` de una imagen. Es divulgación de
información interna, no ejecución de código, pero es exactamente la clase de fuga que esta ronda
debía buscar. Sigue corregido: revisado el código de `Vista.php` en este ciclo, la extracción usa
`EXTR_OVERWRITE` con las claves internas bajo el prefijo `__vista_`, tal como quedó documentado.

## Lo que estas pruebas no cubren

- **La subida de un archivo ejecutable disfrazado de imagen** no se repitió en vivo en esta ronda
  por la restricción de `curl` ya explicada; se apoya en la prueba de
  [`pruebas-panel-carta.md`](pruebas-panel-carta.md) más la revisión de código de esta ronda.
  Quien repita esta prueba con acceso de navegador debería confirmarla de nuevo.
- **XSS solo se probó en `nombre` y `descripcion` de producto.** Otros campos de texto libre
  —`ubicaciones.referencia`, la descripción del food truck, la nota de una orden— pasan por el
  mismo `Vista::e()` pero no se probaron uno por uno.
- **No hay límite de intentos de ingreso**, ya anotado en
  [`pruebas-autenticacion.md`](pruebas-autenticacion.md#lo-que-no-cubre-esta-prueba): sigue sin
  cubrirse.
- **Inyección SQL bajo concurrencia** (varias peticiones maliciosas simultáneas contra el mismo
  `SELECT … FOR UPDATE`) no se probó; el alcance fue por sentencia, no por condición de carrera.
- **El producto de prueba id 35** queda en la base marcado no disponible, como se anotó arriba, a
  la espera de que el dueño del food truck lo reemplace o lo borre.
