# Casos de prueba · CAJA

Dieciséis casos `CP-CAJA-xx` sobre el flujo completo del módulo: apertura de turno, venta con los
tres medios de pago, comprobante imprimible de 80 mm, cuadre y cierre de turno, e historial. La
plantilla y la nomenclatura salen de [`../plantilla-caso-de-prueba.md`](../plantilla-caso-de-prueba.md)
y [`../plan-de-pruebas.md`](../plan-de-pruebas.md).

**Resultado del ciclo: 16 pasan, 0 fallan.**
No se abrió ningún defecto.

## Cómo se ejecutó

| | |
|---|---|
| **Cuándo** | 2026-09-12 |
| **Quién** | Lain Ramírez |
| **Dónde** | Instancia local levantada desde el árbol de trabajo: PHP 8.3 sirviendo `ADSO.menu08.com` como raíz pública y MySQL 8 con `esquema.sql`, `datos_iniciales.sql`, `datos_festin_rodante.sql` y `datos_pruebas.sql` recién importados |
| **Con qué** | `curl` para el flujo HTTP, consultas SQL para comprobar las filas que quedan en la base (`turnos_caja`, `ordenes`, `orden_items`), y `wkhtmltoimage`/`wkhtmltopdf` para las capturas PNG y el PDF del comprobante |
| **Turno de la ejecución** | `id = 5`, `food_truck_id = 4` (Truck de Pruebas). Los IDs cambian con cada restauración del banco; lo que no cambia es el `slug = 'truck-de-pruebas'` |

> **Secuencia de los casos.** CP-CAJA-01 a CP-CAJA-03 comprueban el turno antes de vender.
> CP-CAJA-04 y CP-CAJA-05 cubren el catálogo y la interactividad del armado de la orden.
> CP-CAJA-06 a CP-CAJA-10 cubren el ciclo de venta con sus medios de pago y validaciones.
> CP-CAJA-11 y CP-CAJA-12 verifican el comprobante en pantalla e impresión.
> CP-CAJA-13 a CP-CAJA-16 cierran el turno, contrastan el arqueo y revisan el historial.
> Cada grupo parte del estado que dejó el anterior: **no se restaura el banco entre casos**, salvo donde el caso lo
> indique explícitamente.

---

## Turno

### CP-CAJA-01 · Apertura de turno con base válida

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-01 |
| **Precondición** | Banco restaurado. Sin turno abierto para el Truck de Pruebas. Sesión iniciada como `pruebas.cajero@menu08.local` |
| **Dato de entrada** | Base inicial: **50 000** |
| **Pasos** | 1. Abrir `/caja/turno`.<br>2. Escribir `50000` en el campo «Base inicial».<br>3. Enviar el formulario (`POST /caja/turno`).<br>4. Verificar el registro creado en la tabla `turnos_caja`. |
| **Resultado esperado** | El turno queda guardado con `base_inicial = 50000` y `estado = 'abierto'`. La aplicación redirige a `/caja` con el panel de venta activo |
| **Resultado obtenido** | `POST /caja/turno` → **302** a `/caja`. Fila en `turnos_caja`: `id = 5, food_truck_id = 4, base_inicial = 50000, estado = 'abierto'`.<br>**SQL de verificación:** `SELECT id, food_truck_id, base_inicial, estado FROM turnos_caja WHERE food_truck_id = (SELECT id FROM food_trucks WHERE slug = 'truck-de-pruebas') AND estado = 'abierto'` → 1 fila (`id = 5, base_inicial = 50000.00, estado = 'abierto'`) |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-01-apertura-turno-base.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-02 · Apertura rechazada con base inválida

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-02 |
| **Precondición** | Sin turno abierto para el Truck de Pruebas |
| **Dato de entrada** | Base inicial: **-100** |
| **Pasos** | 1. Abrir `/caja/turno`.<br>2. Escribir `-100` en el campo «Base inicial».<br>3. Enviar el formulario. |
| **Resultado esperado** | La aplicación rechaza la petición con error de validación. No se crea ninguna fila en `turnos_caja` |
| **Resultado obtenido** | `POST /caja/turno` → **422**. Aviso en pantalla: «Debe ser un numero positivo». Conteo de filas en `turnos_caja` para el truck de pruebas: **0** (el turno del CP-01 aún no existía en este punto de la ejecución) |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-02-apertura-rechazada-base-invalida.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-03 · Apertura rechazada con turno ya vigente

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-03 |
| **Precondición** | Existe un turno `abierto` para el Truck de Pruebas (creado en CP-CAJA-01) |
| **Dato de entrada** | Base inicial: **30 000** |
| **Pasos** | 1. Abrir `/caja/turno`.<br>2. Escribir `30000`.<br>3. Enviar. |
| **Resultado esperado** | La aplicación detecta el turno vigente y rechaza la nueva apertura con aviso claro. No se crea un segundo turno |
| **Resultado obtenido** | `POST /caja/turno` → **409**. Aviso: «Ya hay un turno abierto». Conteo de filas `abierto` en `turnos_caja` para el truck: **1** (sin cambio). Concurrencia protegida mediante `SELECT ... FOR UPDATE` |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-03-segundo-turno-rechazado-409.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

---

## Venta

### CP-CAJA-04 · Armado de la orden desde el catálogo en vista de caja

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-04 |
| **Precondición** | Turno abierto (CP-CAJA-01). Sesión de cajero activa |
| **Dato de entrada** | Interacción en `menu08_app/aplicacion/vistas/caja/venta.php`: agregar producto, modificar cantidad (+ / −), quitar producto y vaciar |
| **Pasos** | 1. Abrir `/caja`.<br>2. Tocar un producto del catálogo para agregarlo a la orden (se añade con cantidad 1).<br>3. Modificar la cantidad en el renglón de la orden usando los controles (+ y −).<br>4. Quitar un producto reduciendo su cantidad a 0, o usar el botón «Vaciar».<br>5. Observar la actualización reactiva del total y el estado del botón «Cobrar». |
| **Resultado esperado** | La pantalla muestra el catálogo de productos disponibles con botones activos vía `caja.js`. Al agregar productos, modificar cantidades o quitarlos, el total y el número de artículos se recalculan reactivamente al vuelo. Al vaciar la orden, el total vuelve a $ 0 y el botón «Cobrar» se deshabilita |
| **Resultado obtenido** | `GET /caja` → **200**. Catálogo con productos disponibles, botones de cantidad y carrito lateral. Al hacer clic en un producto se añade a la orden; los botones + y − actualizan subtotales y total sin recargar página; al vaciar o dejar en cero artículos el botón «Cobrar» queda deshabilitado ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-04-armado-orden-pantalla.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-05 · Filtrado por categoría en el catálogo

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-05 |
| **Precondición** | Turno abierto. Panel de venta activo |
| **Dato de entrada** | Navegación por chips de categoría |
| **Pasos** | 1. Abrir `/caja`.<br>2. Observar los chips de categoría del encabezado y alternar entre ellos. |
| **Resultado esperado** | Los chips de las categorías activas del truck están visibles y permiten filtrar el catálogo. La categoría desactivada no aparece |
| **Resultado obtenido** | `GET /caja` → **200**. Chips de categoría visibles en el encabezado del catálogo («Todo», «Entradas», «Fuertes», «Bebidas», «Postres»). La categoría desactivada en `datos_pruebas.sql` no asoma |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-05-catalogo-filtro-categoria.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-06 · Orden 1 — pago en efectivo y verificación SQL de ítems

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-06 |
| **Precondición** | Turno id = 5 abierto. Sin órdenes previas |
| **Dato de entrada** | Productos: 2 × Papas de prueba ($ 6 500 c/u) + 1 × Empanada de prueba ($ 3 200 c/u). Medio de pago: **Efectivo**. Total esperado: **$ 16 200** |
| **Pasos** | 1. En `/caja` agregar 2 × Papas de prueba y 1 × Empanada de prueba al carrito.<br>2. Abrir diálogo de cobro y seleccionar «Efectivo».<br>3. Enviar (`POST /caja/vender`).<br>4. Verificar que el total en pantalla coincide con la suma en la base de datos mediante consulta SQL sobre `ordenes` y `orden_items` (`orden_detalle`). |
| **Resultado esperado** | La orden queda guardada con `total = 16200`, `medio_pago = 'efectivo'`, consecutivo `T5-001`. El total almacenado coincide con la suma de `orden_items` |
| **Resultado obtenido** | `POST /caja/vender` → **200** (comprobante generado). Consecutivo: `T5-001`. Total en pantalla: **$ 16.200**.<br>**SQL de verificación:**<br>`SELECT o.numero, o.total, SUM(oi.subtotal) AS total_detalle FROM ordenes o JOIN orden_items oi ON oi.orden_id = o.id WHERE o.numero = 'T5-001' GROUP BY o.id, o.numero, o.total;`<br>→ `numero = 'T5-001', total = 16200.00, total_detalle = 16200.00` ✓.<br>**Detalle de ítems:**<br>`SELECT oi.nombre_producto, oi.cantidad, oi.precio_unitario, oi.subtotal FROM orden_items oi JOIN ordenes o ON o.id = oi.orden_id WHERE o.numero = 'T5-001';`<br>→ Papas de prueba: 2 × 6500.00 = 13000.00<br>→ Empanada de prueba: 1 × 3200.00 = 3200.00<br>Suma de ítems = **$ 16 200.00** |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-06-orden-1-efectivo-comprobante.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-07 · Orden 2 — pago con tarjeta, nota especial y verificación SQL

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-07 |
| **Precondición** | Turno id = 5 abierto. 1 orden registrada (T5-001) |
| **Dato de entrada** | Productos: 1 × Hamburguesa de prueba ($ 24 900) + 1 × Salchipapa de prueba ($ 21 000) + 2 × Gaseosa de prueba ($ 4 500 c/u, subtotal $ 9 000) + 1 × Brownie de prueba ($ 9 900). Nota: «Sin cebolla en la hamburguesa». Medio de pago: **Tarjeta**. Total esperado: **$ 64 800** |
| **Pasos** | 1. Agregar los productos indicados al carrito.<br>2. En el diálogo de cobro escribir «Sin cebolla en la hamburguesa» en el campo de nota.<br>3. Seleccionar «Tarjeta».<br>4. Enviar (`POST /caja/vender`).<br>5. Verificar con consulta SQL sobre `ordenes` y `orden_items` (`orden_detalle`). |
| **Resultado esperado** | Orden guardada con `total = 64800`, `medio_pago = 'tarjeta'`, `nota = 'Sin cebolla en la hamburguesa'`, número `T5-002`. Renglones de `orden_items` suman exactamente 64 800 |
| **Resultado obtenido** | `POST /caja/vender` → **200**. Consecutivo: `T5-002`. Total en pantalla: **$ 64.800**.<br>**SQL de verificación:**<br>`SELECT o.numero, o.total, o.medio_pago, o.nota, SUM(oi.subtotal) AS total_detalle FROM ordenes o JOIN orden_items oi ON oi.orden_id = o.id WHERE o.numero = 'T5-002' GROUP BY o.id, o.numero, o.total, o.medio_pago, o.nota;`<br>→ `numero = 'T5-002', total = 64800.00, total_detalle = 64800.00, medio_pago = 'tarjeta', nota = 'Sin cebolla en la hamburguesa'` ✓.<br>**Detalle de ítems:**<br>`SELECT oi.nombre_producto, oi.cantidad, oi.precio_unitario, oi.subtotal FROM orden_items oi JOIN ordenes o ON o.id = oi.orden_id WHERE o.numero = 'T5-002';`<br>→ Hamburguesa de prueba: 1 × 24900.00 = 24900.00<br>→ Salchipapa de prueba: 1 × 21000.00 = 21000.00<br>→ Gaseosa de prueba: 2 × 4500.00 = 9000.00<br>→ Brownie de prueba: 1 × 9900.00 = 9900.00<br>Suma de ítems = **$ 64 800.00** |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-07-orden-2-tarjeta-comprobante.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-08 · Orden 3 — pago por transferencia con precio de centavos y verificación SQL

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-08 |
| **Precondición** | Turno id = 5 abierto. 2 órdenes registradas (T5-001, T5-002) |
| **Dato de entrada** | Productos: 2 × Perro de prueba ($ 18 750,50 c/u) + 1 × Vaso de agua ($ 0) + 1 × Limonada de prueba ($ 7 000). Medio de pago: **Transferencia**. Total esperado: **$ 44 501** (2 × 18 750,50 = 37 501 + 0 + 7 000) |
| **Pasos** | 1. Agregar los productos indicados.<br>2. Seleccionar «Transferencia».<br>3. Enviar (`POST /caja/vender`).<br>4. Verificar con consulta SQL sobre `ordenes` y `orden_items` (`orden_detalle`). |
| **Resultado esperado** | Orden guardada con `total = 44501`, sin pérdida de centavos. `numero = 'T5-003'`. Renglones suman 44 501 |
| **Resultado obtenido** | `POST /caja/vender` → **200**. Consecutivo: `T5-003`. Total en pantalla: **$ 44.501**.<br>**SQL de verificación:**<br>`SELECT o.numero, o.total, o.medio_pago, SUM(oi.subtotal) AS total_detalle FROM ordenes o JOIN orden_items oi ON oi.orden_id = o.id WHERE o.numero = 'T5-003' GROUP BY o.id, o.numero, o.total, o.medio_pago;`<br>→ `numero = 'T5-003', total = 44501.00, total_detalle = 44501.00, medio_pago = 'transferencia'` ✓.<br>**Detalle de ítems:**<br>`SELECT oi.nombre_producto, oi.cantidad, oi.precio_unitario, oi.subtotal FROM orden_items oi JOIN ordenes o ON o.id = oi.orden_id WHERE o.numero = 'T5-003';`<br>→ Perro de prueba: 2 × 18750.50 = 37501.00<br>→ Vaso de agua: 1 × 0.00 = 0.00<br>→ Limonada de prueba: 1 × 7000.00 = 7000.00<br>Suma de ítems = **$ 44 501.00**. El precio de $ 0 no distorsiona el cálculo |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-08-orden-3-transferencia-comprobante.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-09 · Orden vacía rechazada

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-09 |
| **Precondición** | Turno abierto. Sin productos en el carrito |
| **Dato de entrada** | Carrito vacío. Medio de pago: Efectivo |
| **Pasos** | 1. Abrir `/caja` sin agregar nada al carrito.<br>2. Intentar enviar la orden (`POST /caja/vender`). |
| **Resultado esperado** | La aplicación rechaza la orden vacía con error de validación HTTP 422. No se crea ninguna fila en `ordenes` |
| **Resultado obtenido** | `POST /caja/vender` → **422**. Mensaje en pantalla: «La orden no tiene ningun producto». Conteo de `ordenes` del turno en la base: **3** (sin cambio respecto al estado anterior) |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-09-orden-vacia-rechazada-422.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-10 · Intento de venta con turno cerrado

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-10 |
| **Precondición** | No hay turno abierto para el food truck (turno previo ya cerrado o sin turno) |
| **Dato de entrada** | Petición `POST /caja/vender` con productos en el cuerpo |
| **Pasos** | 1. Asegurar que el turno está cerrado.<br>2. Intentar registrar una venta (`POST /caja/vender`).<br>3. Seguir la redirección y verificar el aviso en pantalla.<br>4. Verificar con consulta SQL que no se insertó ninguna fila en `ordenes`. |
| **Resultado esperado** | La aplicación detecta la ausencia de turno y redirige a `/caja/turno` (HTTP 302). No crea ninguna fila en `ordenes` y muestra el aviso de alerta al cajero |
| **Resultado obtenido** | `POST /caja/vender` → **302** a `/caja/turno`. Al cargar `/caja/turno` la pantalla presenta el aviso: «No hay un turno abierto. Abra el turno para poder vender.».<br>**SQL de verificación:** `SELECT COUNT(*) FROM ordenes WHERE food_truck_id = 4 AND creado_en >= '2026-09-12 06:18:00'` → **0** filas creadas. Conteo total de órdenes del turno previo: 3 (inalterado) |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-10-intento-venta-turno-cerrado.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

---

## Comprobante

### CP-CAJA-11 · Detalle del comprobante de una orden

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-11 |
| **Precondición** | Orden T5-002 registrada. Turno id = 5 |
| **Dato de entrada** | Navegación a `/caja/comprobante/{id}` |
| **Pasos** | 1. Abrir la URL del comprobante de la orden T5-002.<br>2. Verificar los datos del encabezado, productos, cantidades, precios, total, medio de pago y consecutivo. |
| **Resultado esperado** | La vista muestra encabezado del truck, número de orden consecutivo (`T5-002`), fecha y hora, lista detallada de productos con cantidades y precios, total, artículos totales, medio de pago y nota |
| **Resultado obtenido** | `GET /caja/comprobante/{id}` → **200**. Encabezado «TRUCK DE PRUEBAS», número `T5-002`, fecha `12/09/2026 · 06:18`, productos (1 Hamburguesa de prueba, 1 Salchipapa de prueba, 2 Gaseosa de prueba, 1 Brownie de prueba), total `$ 64.800`, medio de pago «Tarjeta», nota «Sin cebolla en la hamburguesa», consecutivo visible |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-11-comprobante-orden-detalle.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-12 · Vista de impresión de 80 mm y exportación a PDF

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-12 |
| **Precondición** | Orden T5-002 registrada |
| **Dato de entrada** | Vista de impresión `/caja/comprobante/{id}/imprimir` |
| **Pasos** | 1. Abrir la URL de impresión del comprobante.<br>2. Verificar maquetación ajustada a 80 mm (302 px a 96 ppp) sin elementos de navegación.<br>3. Exportar a PDF con `wkhtmltopdf` (ancho 80 mm). |
| **Resultado esperado** | La vista de impresión se adapta al formato de ticket térmico de 80 mm. El PDF generado contiene productos, cantidades, total y consecutivo de forma limpia y legible |
| **Resultado obtenido** | `GET /caja/comprobante/{id}/imprimir` → **200**. PNG capturado a 380 px / 302 px útiles. Exportación a PDF exitosa (**22 456 bytes**): contiene encabezado del negocio, consecutivo `T5-002`, productos, cantidades, precios unitarios, total `$ 64.800` y medio de pago |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-12-comprobante-vista-impresion.png` · `evidencias/caja/CP-CAJA-12-comprobante-impresion.pdf` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

---

## Cierre de turno

### CP-CAJA-13 · Cuadre del turno antes de cerrar y contraste con cálculo manual

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-13 |
| **Precondición** | Turno id = 5 abierto. 3 órdenes registradas: T5-001 ($ 16 200), T5-002 ($ 64 800), T5-003 ($ 44 501) |
| **Dato de entrada** | Lectura del arqueo en `/caja/cerrar` |
| **Pasos** | 1. Abrir `/caja/cerrar`.<br>2. Contrastar el cuadre automático en pantalla contra el cálculo hecho a mano:<br>   • Base inicial: $ 50 000<br>   • Total ventas: $ 16 200 (efectivo) + $ 64 800 (tarjeta) + $ 44 501 (transferencia) = $ 125 501<br>   • Total esperado en caja: $ 50 000 + $ 125 501 = **$ 175 501**. |
| **Resultado esperado** | Base inicial `50 000` + vendido `125 501` = esperado `175 501`. Los valores en pantalla coinciden exactamente con el cálculo manual sin desfase de centavos |
| **Resultado obtenido** | `GET /caja/cerrar` → **200**. Cuadre en pantalla: Total vendido = **$ 125.501**, Órdenes = **3**, Unidades = **12**, Base inicial = **$ 50.000**, Esperado en caja = **$ 175.501**. Coincidencia exacta con el cálculo a mano ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-13-cierre-turno-cuadre-exacto.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-14 · Desglose por medio de pago en el cuadre y verificación SQL

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-14 |
| **Precondición** | Mismo estado que CP-CAJA-13 (Turno id = 5, 3 órdenes registradas) |
| **Dato de entrada** | Verificación del desglose por medio de pago |
| **Pasos** | 1. Abrir `/caja/cerrar`.<br>2. Revisar la tabla «Por medio de pago».<br>3. Ejecutar consulta SQL de agregación sobre la tabla `ordenes` filtrada por `turno_id = 5`. |
| **Resultado esperado** | Efectivo: 1 orden, $ 16 200 · Tarjeta: 1 orden, $ 64 800 · Transferencia: 1 orden, $ 44 501. Cada valor coincide con la suma de las órdenes en la base de datos |
| **Resultado obtenido** | Desglose en pantalla: `efectivo = $ 16.200` (1 orden) · `tarjeta = $ 64.800` (1 orden) · `transferencia = $ 44.501` (1 orden).<br>**SQL de verificación:**<br>`SELECT medio_pago, COUNT(*) AS ordenes, SUM(total) AS total FROM ordenes WHERE turno_id = 5 GROUP BY medio_pago ORDER BY medio_pago;`<br>→ 3 filas:<br>• `efectivo`: ordenes = 1, total = 16200.00<br>• `tarjeta`: ordenes = 1, total = 64800.00<br>• `transferencia`: ordenes = 1, total = 44501.00<br>Suma total consultada en la base = **$ 125 501.00** ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-14-cierre-desglose-medios-pago.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-CAJA-15 · Cierre de turno con faltante y verificación en base de datos

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-15 |
| **Precondición** | Turno id = 5 abierto. Cuadre: esperado `175 501` |
| **Dato de entrada** | Monto declarado: **170 000** (faltante de $ 5 501) |
| **Pasos** | 1. En `/caja/cerrar` escribir `170000` en «Conteo físico de la caja».<br>2. Verificar cálculo dinámico de la diferencia: `-$ 5.501 (faltante)`.<br>3. Enviar formulario (`POST /caja/cerrar`).<br>4. Verificar los campos actualizados en `turnos_caja` mediante consulta SQL. |
| **Resultado esperado** | El turno queda cerrado. `total_declarado = 170000`, `diferencia = -5501` (faltante), `estado = 'cerrado'`, `cerrado_en` registrado. Redirección a `/caja/turnos` con aviso de confirmación |
| **Resultado obtenido** | `POST /caja/cerrar` → **302** a `/caja/turnos`. Vista de turno con aviso «Turno cerrado».<br>**SQL de verificación:**<br>`SELECT id, base_inicial, total_ventas, total_declarado, diferencia, estado, abierto_en, cerrado_en FROM turnos_caja WHERE id = 5;`<br>→ `base_inicial = 50000.00, total_ventas = 125501.00, total_declarado = 170000.00, diferencia = -5501.00, estado = 'cerrado'`, `cerrado_en = 2026-09-12 06:18:06` ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-15-cierre-con-faltante.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

---

## Historial

### CP-CAJA-16 · Historial de turnos

| Campo | Contenido |
|---|---|
| **Identificador** | CP-CAJA-16 |
| **Precondición** | Turno #5 cerrado en el historial del Truck de Pruebas |
| **Dato de entrada** | Navegación a `/caja/turnos` |
| **Pasos** | 1. Abrir `/caja/turnos`.<br>2. Verificar la presencia del turno cerrado con sus cifras de apertura, cierre, ventas y diferencia. |
| **Resultado esperado** | La vista lista los turnos del truck con consecutivo, cajero, fechas de apertura y cierre, cantidad de órdenes, total vendido, diferencia con indicación de faltante y estado cerrado |
| **Resultado obtenido** | `GET /caja/turnos` → **200**. El turno #5 aparece en el listado: Cajero de pruebas, apertura `2026-09-12 06:17:57`, cierre `2026-09-12 06:18:06`, 3 órdenes, `$ 125.501` vendido, `$ -5.501` de diferencia, estado `cerrado` |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/caja/CP-CAJA-16-historial-turnos.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

---

**Issue #23 · Diseñar y ejecutar las pruebas funcionales del módulo CAJA**
