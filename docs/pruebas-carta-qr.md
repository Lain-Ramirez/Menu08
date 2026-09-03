# Pruebas de la carta pública y del código QR

Ejecutadas contra **https://adso.menu08.com**. **17 comprobaciones, 0 fallos.**

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
| Producto con `disponible = 0` | desaparece de la carta |
| Carta sin productos visibles | muestra «La carta se esta preparando», no una página rota |

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
