# Pruebas de la carta pública y del código QR

Ejecutadas contra **https://adso.menu08.com**. **26 comprobaciones, 0 fallos.**

## El código QR se lee de verdad

No se comprobó mirándolo: se descargó el PNG **que genera el servidor** y se pasó por `jsQR`,
el mismo decodificador que usan las cámaras web.

| Origen | Resultado |
|---|---|
| El PNG que muestra `/panel/qr` | `https://adso.menu08.com/carta/festin-rodante` · versión 4 |
| El PNG de «Descargar» | `https://adso.menu08.com/carta/festin-rodante` · versión 4 |

La descarga llega con `Content-Type: image/png` y
`Content-Disposition: attachment; filename="qr-festin-rodante.png"`.

Antes se había verificado el codificador con **7 direcciones de 16 a 190 caracteres**, que
abarcan las versiones 2 a 10 del formato: las siete se decodifican.

## Carta pública

Sin sesión iniciada, tal como la abre el cliente haciendo fila:

| Comprobación | Resultado |
|---|---|
| `GET /carta/festin-rodante` | 200 |
| Nombre del food truck | aparece |
| Producto y su categoría | aparecen agrupados |
| Precio | `5.000`, en formato colombiano |
| Elementos del panel | ninguno: no hay «Salir» ni «Ingresar» |

| Caso | Resultado |
|---|---|
| Slug inexistente | **404**, sin filtrar datos de ningún otro truck |
| Food truck con `activo = 0` | **404** |
| Reactivado | 200, la carta vuelve |
| Producto con `disponible = 0` | hasta el #17 desaparecía de la carta; **desde el #17 se muestra atenuado y con la etiqueta «No disponible»**, y sigue sin aparecer en CAJA |
| Carta sin productos visibles | muestra «La carta se esta preparando», no una página rota |

## Maquetación de la carta · issue #17

Ejecutadas el 5 de septiembre de 2026 contra el sitio publicado, midiendo en Chrome sin interfaz
a 360 px —el ancho del teléfono con el que se abre la carta en la fila— y a 1280 px.

| Criterio del #17 | Cómo se comprobó | Resultado |
|---|---|---|
| Productos por categoría con foto | HTML servido | 20 `<img class="carta-foto">` y 20 precios |
| Sin desplazamiento horizontal a 360 px | `document.documentElement.scrollWidth` | **360**, igual que `innerWidth` |
| Enlaces de la barra ≥ 44 px | alto medido del más bajo | **48 px** |
| Texto de producto ≥ 14 px | `getComputedStyle` sobre nombre, descripción y precio | **14 px** el menor |
| Agotado atenuado y etiquetado | opacidad medida + HTML | **0.45** y «No disponible» |
| Estado vacío | categorías del truck de pruebas desactivadas y restauradas | «La carta se esta preparando» |
| Sin el marco del panel | recuento en el HTML | `cabecera`, `pie`, `SENA ADSO`, `navegacion` y `csrf-token`: **0 apariciones** |
| Pantalla del QR | `GET /panel/qr` con sesión | 200, QR de 328×328 y PNG de 2 780 bytes |
| A 1280 px | `scrollWidth` | 1265, sin barra horizontal |

La carta pública dejó de usar `plantillas/base.php` y pasa por `plantillas/publica.php`, que abre
el documento sin barra de usuario, sin navegación y sin el pie del proyecto formativo. Tampoco
publica el testigo CSRF: sin sesión no hay formulario que proteger.

### Que CAJA no se llevara por delante lo agotado

El criterio de mostrar los agotados obligaba a traerlos de la base, y la consulta que lo hacía
—`Producto::catalogoPublico()`— la comparten la carta y el catálogo de venta. En CAJA un agotado
que se puede pulsar es una venta que no se puede entregar, así que se añadió
`Producto::catalogoCarta()` aparte en vez de cambiar la existente.

Comprobado después del cambio, con sesión de cajero: el producto agotado aparece **0 veces** en
`GET /caja` y sí en la carta. Son dos preguntas distintas y son dos consultas distintas.

### Un falso positivo, anotado a propósito

Durante la verificación se midió `display: block` en `.carta-fila`, que debía ser `flex`, y
estuvo a punto de darse por defecto de la hoja. **Era la caché del navegador de pruebas**, que
reutilizaba su perfil entre ejecuciones y servía la versión anterior de `carta.css`. Con perfil
limpio y caché desactivada, `display: flex` y el nombre y el precio comparten renglón.

Queda escrito porque el error de método —medir contra una caché propia— es más fácil de repetir
que el defecto que se creyó encontrar.


## Dos defectos que encontraron estas pruebas

### 1. La información de formato del QR, transpuesta

La primera versión del codificador producía un código que **parecía** correcto y que ningún
decodificador podía leer. Los quince bits de información de formato estaban escritos
intercambiando fila por columna, en las dos copias que lleva el símbolo.

Se detectó porque el codificador se escribió primero como referencia ejecutable y se pasó por un
decodificador real antes de portarlo a PHP. Mirando el dibujo no se distingue: la única forma de
saber que un QR sirve es leerlo.

### 2. Colisión de variables en el renderizador de vistas

`Vista::renderizar` tenía una variable local `$archivo` con la ruta de la plantilla y usaba
`extract($datos, EXTR_SKIP)`. La vista del código QR pasa una clave llamada `archivo`, y
`EXTR_SKIP` la descartó en silencio: la vista recibió la ruta de sí misma en lugar del nombre
del PNG.

Sin error, sin aviso y sin nada en la bitácora. La página respondía 200 y la etiqueta `<img>`
apuntaba a `/subidas//home/sfacturs2/menu08_app/aplicacion/vistas/panel/qr.php`.

No era un problema del código QR: cualquier vista que recibiera una clave llamada `archivo`,
`plantilla`, `datos` o `ruta` habría mostrado el valor interno en su lugar.

**Corregido:** la plantilla se ejecuta en un método aparte cuyas variables llevan el prefijo
`__vista_`, esas claves se eliminan de los datos antes de extraerlos, y se pasó a
`EXTR_OVERWRITE` para que los datos de la vista tengan prioridad.

Se encontró pidiendo por `curl` el HTML que genera el servidor y leyendo el `src` real de la
etiqueta, no mirando la pantalla.

## Lo que estas pruebas no cubren

- **Un teléfono real.** Todo se midió con la emulación de Chrome sin interfaz. La «Definición de
  hecho» del #17 pide abrir la carta desde un teléfono de verdad, y eso sigue pendiente.
- **El bloque «Dónde estamos»** lista las ocho paradas de la semana sin resumir. Encabezarlo con
  la parada vigente es el issue **#37**, no el #17.
- **El modo oscuro.** `md3.css` lo define bajo `html.o` y `carta.css` no escribe ni un color
  literal, así que debería funcionar, pero no hay interruptor con el que comprobarlo.
