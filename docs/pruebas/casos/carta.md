# Casos de prueba · CARTA

Dieciséis casos `CP-CARTA-xx` sobre el flujo completo del módulo: autenticación con roles,
administración de categorías y productos, carta pública por slug y código QR. La plantilla y la
nomenclatura salen de [`../plantilla-caso-de-prueba.md`](../plantilla-caso-de-prueba.md) y
[`../plan-de-pruebas.md`](../plan-de-pruebas.md).

**Resultado del ciclo: 14 pasan, 2 fallan.** Los dos fallos están en
[`../registro-de-defectos.md`](../registro-de-defectos.md) como `DEF-01` y `DEF-02`.

## Cómo se ejecutó

| | |
|---|---|
| **Cuándo** | 2026-09-11 |
| **Quién** | Lain Ramírez |
| **Dónde** | Instancia local levantada desde el árbol de trabajo: PHP 8.3 sirviendo `ADSO.menu08.com` como raíz pública y MySQL 8 con `esquema.sql`, `datos_iniciales.sql`, `datos_festin_rodante.sql` y `datos_pruebas.sql` recién importados |
| **Con qué** | `curl` para el flujo HTTP, consultas SQL para comprobar la fila que queda en la base, y Chrome sin cabeza para las capturas y las medidas de maquetación |
| **Por qué no contra el sitio publicado** | El ciclo cubre código que todavía no está mezclado en `production`. La única comprobación que sí se hizo contra `adso.menu08.com` es `CP-CARTA-15`, que existe justamente para contrastar |

> **Lo que no se ejecutó aquí.** La lectura del código QR **con un teléfono** es de `CP-CARTA-13` y
> queda pendiente: lo que sí se comprobó es qué dirección lleva codificada el archivo descargado,
> decodificándolo con `zbarimg`. Es la mitad que se puede automatizar; la otra mitad se hace con el
> teléfono en la mano.

---

## Autenticación

### CP-CARTA-01 · Ingreso con el rol food_truck

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-01 |
| **Precondición** | Banco restaurado. Sin sesión iniciada |
| **Dato de entrada** | `pruebas.foodtruck@menu08.local` / `Menu08*Demo2026` |
| **Pasos** | 1. Abrir `/ingresar`.<br>2. Escribir correo y contraseña.<br>3. Enviar. |
| **Resultado esperado** | 302 hacia `/panel`, y `/panel` responde 200 con el nombre del food truck de la sesión |
| **Resultado obtenido** | `POST /ingresar` → **302** a `http://127.0.0.1:8010/panel`. `GET /panel` → **200**, encabezado «Truck de Pruebas» |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-01-panel-tras-ingresar.png` |
| **Defecto** | — |

### CP-CARTA-02 · Ingreso con la contraseña equivocada

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-02 |
| **Precondición** | Sin sesión iniciada |
| **Dato de entrada** | `pruebas.foodtruck@menu08.local` / `equivocada` |
| **Pasos** | 1. Abrir `/ingresar`.<br>2. Escribir el correo correcto y una contraseña falsa.<br>3. Enviar.<br>4. Pedir `/panel`. |
| **Resultado esperado** | Se rechaza sin abrir sesión y sin decir cuál de los dos datos falló |
| **Resultado obtenido** | **401** con el aviso «Error. Correo o contraseña incorrectos.» — no distingue entre correo y contraseña. `GET /panel` después → **302** a `/ingresar` |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-02-credenciales-equivocadas.png` |
| **Defecto** | — |

### CP-CARTA-03 · Cierre de sesión

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-03 |
| **Precondición** | Sesión iniciada como `food_truck` |
| **Dato de entrada** | — |
| **Pasos** | 1. Pedir `/salir`.<br>2. Pedir `/panel`. |
| **Resultado esperado** | La sesión se cierra y el panel deja de ser alcanzable |
| **Resultado obtenido** | `GET /salir` → **302** a `/ingresar`. `GET /panel` después → **302** a `/ingresar`, con el formulario de acceso |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-03-sin-sesion-el-panel-manda-al-ingreso.png` |
| **Defecto** | — |

### CP-CARTA-04 · Un cajero no entra al panel de CARTA

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-04 |
| **Precondición** | Sesión iniciada con `pruebas.cajero@menu08.local` |
| **Dato de entrada** | Ruta `/panel/productos` escrita a mano |
| **Pasos** | 1. Entrar como cajero.<br>2. Pedir `/panel/productos`.<br>3. Pedir `/caja`, que sí es su módulo. |
| **Resultado esperado** | 403 con la vista de error del proyecto; su propio módulo sigue funcionando |
| **Resultado obtenido** | Ingreso → **302** a `/caja`. `GET /panel/productos` → **403**, título «Error 403 · Menu08», texto «No tiene permiso para ver esta pagina.». `GET /caja` → **302** (a abrir turno, que es lo suyo) |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-04-cajero-sin-acceso-al-panel.png` |
| **Defecto** | — |

---

## Panel de CARTA

### CP-CARTA-05 · Alta de categoría

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-05 |
| **Precondición** | Sesión `food_truck` del truck de pruebas |
| **Dato de entrada** | Nombre `Postres de prueba`, orden `9` |
| **Pasos** | 1. Abrir `/panel/categorias`.<br>2. Llenar el formulario.<br>3. Enviar. |
| **Resultado esperado** | 302 de vuelta al listado, fila nueva en `categorias` con `activo = 1` y la categoría visible en la pantalla |
| **Resultado obtenido** | **302** a `/panel/categorias`. En la base: `11 | Postres de prueba | orden=9 | activo=1`. En el listado: 2 apariciones (celda y acciones) |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-09-categorias-con-la-desactivada.png` (la misma captura recoge el resultado de CP-CARTA-05, CP-CARTA-06 y CP-CARTA-09) |
| **Defecto** | — |

### CP-CARTA-06 · Edición de categoría

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-06 |
| **Precondición** | Existe la categoría de CP-CARTA-05 |
| **Dato de entrada** | Nombre `Postres de la casa`, orden `2`, sobre el `id` existente |
| **Pasos** | 1. Abrir `/panel/categorias/{id}`.<br>2. Cambiar nombre y orden.<br>3. Guardar. |
| **Resultado esperado** | La fila se actualiza en lugar de crear otra |
| **Resultado obtenido** | **302** a `/panel/categorias`. En la base: `Postres de la casa | orden=2`, con el mismo `id` |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-09-categorias-con-la-desactivada.png` |
| **Defecto** | — |

### CP-CARTA-07 · Alta de producto con foto

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-07 |
| **Precondición** | Existe la categoría `Postres de la casa` |
| **Dato de entrada** | Nombre `Brownie fotografiado`, precio `11900`, disponible, y un JPG de 205 KB |
| **Pasos** | 1. Abrir `/panel/productos/nuevo`.<br>2. Llenar el formulario y elegir la foto.<br>3. Enviar. |
| **Resultado esperado** | 302 al listado, fila en `productos` con el nombre del archivo, archivo escrito en `subidas/` y foto visible en la carta pública |
| **Resultado obtenido** | **302** a `/panel/productos`. En la base: `32 | Brownie fotografiado | 11900.00 | foto=bed80f5ed9a77bff06b1e9d61f803533.jpg`. El archivo queda en `subidas/` con 205 567 bytes y se sirve por HTTP con **200 `image/jpeg`**. La carta pública lo muestra con su foto |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-07-productos-con-foto.png` |
| **Defecto** | — |

> El nombre del archivo guardado **no** es el que subió el usuario: `GestorImagenes` lo renombra a
> un hash. Es lo correcto —evita colisiones y nombres con rutas dentro—, y conviene saberlo al
> comprobar la fila a mano.

### CP-CARTA-08 · Producto marcado como no disponible

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-08 |
| **Precondición** | Existe el producto de CP-CARTA-07, disponible |
| **Dato de entrada** | El `id` del producto |
| **Pasos** | 1. Abrir `/panel/productos`.<br>2. Pulsar el cambio de disponibilidad.<br>3. Abrir `/carta/truck-de-pruebas`. |
| **Resultado esperado** | Según el criterio del issue: **no aparece** en la carta pública y sigue listado en el panel |
| **Resultado obtenido** | **302** al listado y `disponible = 0` en la base. En el panel: sigue listado ✔. En la carta pública: **sí aparece**, atenuado y con la etiqueta «No disponible» |
| **Estado** | **Falla** |
| **Evidencia** | `evidencias/carta/CP-CARTA-08-no-disponible-en-la-carta.png` |
| **Defecto** | **DEF-01** |

### CP-CARTA-09 · Baja lógica de categoría

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-09 |
| **Precondición** | `Postres de la casa` activa, con un producto colgando |
| **Dato de entrada** | El `id` de la categoría |
| **Pasos** | 1. Abrir `/panel/categorias`.<br>2. Pulsar «Desactivar» y aceptar el diálogo.<br>3. Abrir la carta pública.<br>4. Volver al panel. |
| **Resultado esperado** | El bloque y sus productos desaparecen de la carta; la categoría sigue en el panel, marcada como inactiva |
| **Resultado obtenido** | **302** al listado, `activo = 0`. En la carta: **0 apariciones** del bloque y **0** de su producto —antes había 2 y 1—. En el panel: sigue listada con la etiqueta de inactiva |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-09-categorias-con-la-desactivada.png` |
| **Defecto** | — |

> Conviene leer este caso junto al CP-CARTA-08: **desactivar la categoría sí esconde el producto**
> de la carta, y marcarlo como no disponible no. Son dos caminos que el dueño puede confundir.

---

## Carta pública y código QR

### CP-CARTA-10 · La carta pública dice lo que dice la base

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-10 |
| **Precondición** | Festín Rodante con sus 5 categorías activas y 20 productos |
| **Dato de entrada** | `/carta/festin-rodante`, sin sesión |
| **Pasos** | 1. Abrir la carta.<br>2. Comparar los títulos de bloque con `categorias`.<br>3. Comparar los primeros productos y sus precios con `productos`. |
| **Resultado esperado** | Mismos bloques, mismo orden y mismos precios que la base, con el orden del modelo: categoría, luego disponibles primero, luego `orden` |
| **Resultado obtenido** | **200**. Bloques: `Para picar | De la parrilla | Con la mano | Para bajarlo | El dulce`, idénticos y en el mismo orden que la base. Primeros productos de «Para picar»: `Morcilla santandereana $ 14.900`, `Mazorca desgranada $ 16.900`, `Patacon con hogao $ 12.900`, `Chorizo con arepita $ 15.900` — coinciden uno a uno con la consulta ordenada como la ordena `Producto::catalogoCarta` |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-10-carta-publica.png` |
| **Defecto** | — |

> Al escribir el caso se dio por hecho que el orden era «categoría, luego `orden` del producto», y
> la primera comparación falló. El orden real antepone `disponible DESC`: lo agotado baja al final
> de su bloque. La consulta del modelo tenía razón y el caso estaba mal escrito; queda anotado
> porque es justo la clase de error que un caso mal redactado convierte en un defecto inexistente.

### CP-CARTA-11 · Slug inexistente

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-11 |
| **Precondición** | Ninguna |
| **Dato de entrada** | `/carta/no-existe` |
| **Pasos** | 1. Pedir la dirección.<br>2. Buscar en la respuesta rutas internas, trazas o mensajes de PDO. |
| **Resultado esperado** | 404 con la vista de error del proyecto, sin filtrar nada del servidor |
| **Resultado obtenido** | **404**, título «Error 404 · Menu08», texto «Error 404». Búsqueda de `menu08_app`, `/home/` y `Stack trace`: **0 coincidencias**. Búsqueda de `PDO`, `SQLSTATE` y `Exception`: **0 coincidencias** |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-11-slug-inexistente-404.png` |
| **Defecto** | — |

### CP-CARTA-12 · Food truck desactivado

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-12 |
| **Precondición** | El truck de pruebas, activo, con su carta respondiendo 200 |
| **Dato de entrada** | `UPDATE food_trucks SET activo = 0` sobre ese truck |
| **Pasos** | 1. Desactivar el truck en la base.<br>2. Pedir su carta.<br>3. Volver a activarlo.<br>4. Pedirla otra vez. |
| **Resultado esperado** | Con el truck inactivo la carta no existe para el público; al reactivarlo vuelve |
| **Resultado obtenido** | Con `activo = 0` → **404**. Con `activo = 1` → **200** |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-12-truck-desactivado-404.png` |
| **Defecto** | — |

### CP-CARTA-13 · Código QR

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-13 |
| **Precondición** | Sesión `food_truck` del truck de pruebas |
| **Dato de entrada** | — |
| **Pasos** | 1. Abrir `/panel/qr`.<br>2. Descargar desde `/panel/qr/descargar`.<br>3. Decodificar el archivo.<br>4. **Pendiente:** leerlo con un teléfono y ver a dónde lleva. |
| **Resultado esperado** | El panel muestra el código, la descarga entrega una imagen y lo que codifica es la carta pública de ese truck |
| **Resultado obtenido** | `/panel/qr` → **200**, con el código en pantalla. `/panel/qr/descargar` → **200 `image/png`**, 2 757 bytes, `PNG image data, 410 x 410`. Decodificado con `zbarimg`: **`http://127.0.0.1:8010/carta/truck-de-pruebas`** — la carta del truck que lo generó, construida sobre `url_base` |
| **Estado** | **Pasa** (la lectura con teléfono queda pendiente) |
| **Evidencia** | `evidencias/carta/CP-CARTA-13-codigo-qr.png` |
| **Defecto** | — |

### CP-CARTA-14 · Un food truck no administra el catálogo de otro

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-14 |
| **Precondición** | Sesión `food_truck` del truck de pruebas. En la base hay otro truck, Festín Rodante, con sus productos |
| **Dato de entrada** | `id` de un producto y de una categoría de Festín Rodante |
| **Pasos** | 1. Pedir `/panel/productos/{id ajeno}`.<br>2. Pedir `/panel/categorias/{id ajeno}`. |
| **Resultado esperado** | 404, **no** 403: un 403 confirmaría que ese identificador existe |
| **Resultado obtenido** | Las dos → **404** con la vista de error del proyecto |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/carta/CP-CARTA-14-producto-de-otro-truck-404.png` |
| **Defecto** | — |

### CP-CARTA-15 · Contraste con la carta publicada

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-15 |
| **Precondición** | Ninguna |
| **Dato de entrada** | `https://adso.menu08.com/carta/festin-rodante` y la misma carta en local |
| **Pasos** | 1. Descargar las dos.<br>2. Comparar clases, bloques y número de productos. |
| **Resultado esperado** | Misma maqueta; las diferencias, si las hay, son de datos |
| **Resultado obtenido** | **200** en las dos, con la **misma maqueta**: mismas hojas, mismos bloques y las mismas clases `carta-ahora-*` y `carta-parada-*`. Tres diferencias, todas de datos: en producción el truck tiene logotipo y descripción y en el banco no —`logo` y `descripcion` van a `NULL` en `datos_festin_rodante.sql`, así que `.carta-logo` y `.carta-descripcion` no se ejercitan en local—; producción está «Abierto ahora» con una parada reportada desde la aplicación móvil y local está «Cerrado ahora» con la siguiente; y producción tiene 8 paradas en la semana frente a 7 |
| **Estado** | **Pasa**, con observación |
| **Evidencia** | `evidencias/carta/CP-CARTA-15-carta-en-produccion.png` |
| **Defecto** | — |

> **Observación para el banco de pruebas.** Ningún food truck del banco tiene logotipo ni
> descripción, así que dos bloques de la cabecera de la carta no se prueban nunca en local. Se
> cubren aquí con la captura de producción; si se quiere probarlos en el banco hay que añadir el
> dato a `datos_pruebas.sql` junto con un archivo en `subidas/`.

### CP-CARTA-16 · La carta a 320 px

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CARTA-16 |
| **Precondición** | Ninguna |
| **Dato de entrada** | `/carta/festin-rodante` a 320, 360 y 768 px de ancho |
| **Pasos** | 1. Abrir la carta a cada ancho.<br>2. Comparar `scrollWidth` con `clientWidth` del documento.<br>3. Buscar elementos cuyo borde derecho pase del ancho de la ventana. |
| **Resultado esperado** | El documento **no se desplaza de lado** a ninguno de los tres anchos |
| **Resultado obtenido** | 768 px → `scrollWidth = clientWidth = 768`, ninguno desborda ✔. 360 px → `360 = 360` ✔. **320 px → `scrollWidth = 324` contra `clientWidth = 320`**: el precio del primer producto termina en 324, cuatro píxeles fuera. El documento se desplaza de lado |
| **Estado** | **Falla** |
| **Evidencia** | `evidencias/carta/CP-CARTA-10-carta-publica-320px.png` y `CP-CARTA-10-carta-publica-oscuro-320px.png` |
| **Defecto** | **DEF-02** |

> La barra de categorías también asoma fuera de la ventana, y **eso no es un defecto**: tiene su
> propio `overflow-x`, así que lo que se desplaza es la barra y no el documento. Se anota para que
> la próxima medición no lo cuente como fallo.

---

**Issue #22 · Fase 5 - Pruebas**
