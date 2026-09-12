# Casos de prueba · SVP — integración con CAJA

Diez casos `CP-SVP-xx` sobre el flujo completo de la integración entre CAJA y el Sistema de
Visualización de Producción: una orden registrada en CAJA aparece en el tablero del SVP, recorre
sus cuatro estados y sale cuando se entrega. La plantilla y la nomenclatura salen de
[`../plantilla-caso-de-prueba.md`](../plantilla-caso-de-prueba.md) y
[`../plan-de-pruebas.md`](../plan-de-pruebas.md).

**Resultado del ciclo: 10 pasan, 0 fallan.**
No se abrió ningún defecto.

## Cómo se ejecutó

| | |
|---|---|
| **Cuándo** | 2026-09-12 |
| **Quién** | Lain Ramírez |
| **Dónde** | Instancia local levantada desde el árbol de trabajo: PHP 8.3 sirviendo `ADSO.menu08.com` como raíz pública y MySQL 8 con `esquema.sql`, `datos_iniciales.sql`, `datos_festin_rodante.sql` y `datos_pruebas.sql` recién importados |
| **Con qué** | `curl` para el flujo HTTP y los cambios de estado, consultas SQL para verificar las marcas de tiempo en `ordenes`, y Chrome sin cabeza para las capturas del tablero en `/svp` |
| **Turno de la ejecución** | `id = 6`, `food_truck_id = 4` (Truck de Pruebas). Los IDs cambian con cada restauración del banco; lo que no cambia es el `slug = 'truck-de-pruebas'` |

> **Secuencia de los casos.** CP-SVP-01 y CP-SVP-02 comprueban el tablero sin órdenes.
> CP-SVP-03 y CP-SVP-04 comprueban que las órdenes registradas en CAJA aparecen en el SVP.
> CP-SVP-05 a CP-SVP-07 recorren el ciclo de vida de una orden de principio a fin.
> CP-SVP-08 a CP-SVP-10 verifican el sondeo, la coexistencia de varios estados y la salida del
> tablero al entregar. Cada grupo parte del estado que dejó el anterior: **no se restaura el banco
> entre casos**, salvo donde el caso lo indique explícitamente.

---

## Tablero sin órdenes

### CP-SVP-01 · Tablero con ventanilla cerrada (sin turno abierto)

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-01 |
| **Precondición** | Banco restaurado. Sin turno abierto para el Truck de Pruebas. Sesión iniciada como `pruebas.produccion@menu08.local` |
| **Dato de entrada** | `GET /svp/ordenes` |
| **Pasos** | 1. Asegurar que no hay turno abierto para el Truck de Pruebas.<br>2. Abrir `/svp`.<br>3. Llamar a `GET /svp/ordenes` y examinar la respuesta JSON. |
| **Resultado esperado** | La respuesta es `{"turno": null, "total": 0, "ordenes": []}`. El tablero muestra la pantalla de ventanilla cerrada |
| **Resultado obtenido** | `GET /svp/ordenes` → **200** `Content-Type: application/json; charset=utf-8`.<br>Cuerpo: `{"turno":null,"ahora":"2026-09-12 06:29:11","minutos_demora":10,"total":0,"ordenes":[]}`.<br>`turno` es `null`, `total` es 0 y `ordenes` es una lista vacía. El tablero muestra la pantalla de ventanilla cerrada ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-01-tablero-ventanilla-cerrada.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-SVP-02 · Tablero con turno abierto y producción al día (sin órdenes en curso)

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-02 |
| **Precondición** | Turno `id = 6` abierto para el Truck de Pruebas. Sin órdenes en ningún estado activo |
| **Dato de entrada** | `GET /svp/ordenes` |
| **Pasos** | 1. Desde la sesión de cajero, abrir un turno nuevo en `/caja/turno` con base `50 000`.<br>2. Sin registrar ninguna venta, cambiar a la sesión de producción.<br>3. Llamar a `GET /svp/ordenes` y examinar la respuesta. |
| **Resultado esperado** | La respuesta informa `turno` con el id del turno abierto y una lista de órdenes vacía. `turno` no debe ser `null`, para que el tablero distinga «ventanilla abierta, cocina al día» de «ventanilla cerrada» |
| **Resultado obtenido** | `GET /svp/ordenes` → **200**.<br>Cuerpo: `{"turno":6,"ahora":"2026-09-12 06:29:44","minutos_demora":10,"total":0,"ordenes":[]}`.<br>`turno` vale `6` (no `null`), `total` es 0 y `ordenes` vacío. El tablero muestra la pantalla de producción al día, sin tarjetas de órdenes ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-02-tablero-produccion-al-dia.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

---

## La orden registrada en CAJA aparece en el SVP

### CP-SVP-03 · Una orden registrada en CAJA aparece en el tablero como pendiente

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-03 |
| **Precondición** | Turno `id = 6` abierto. Sin órdenes en curso |
| **Dato de entrada** | Desde la sesión de cajero: 1 × Hamburguesa de prueba ($ 24 900). Medio de pago: **Efectivo**. Número de turno asignado: `T6-001` |
| **Pasos** | 1. Con sesión de cajero, agregar 1 × Hamburguesa de prueba al carrito.<br>2. Cobrar con «Efectivo» (`POST /caja/vender`).<br>3. Anotar el número de turno del comprobante.<br>4. Sin cerrar sesión, llamar a `GET /svp/ordenes` con la sesión de producción.<br>5. Verificar que la orden aparece en el arreglo con estado `pendiente`. |
| **Resultado esperado** | `GET /svp/ordenes` devuelve `total: 1` y la orden `T6-001` con `estado: "pendiente"`. El tablero la muestra en la columna de pendientes |
| **Resultado obtenido** | `POST /caja/vender` → **200**, número `T6-001`.<br>`GET /svp/ordenes` → **200**, `total: 1`.<br>Orden en el arreglo: `{"id":…,"numero":"T6-001","estado":"pendiente","estado_nombre":"Pendiente","minutos":0,"demorada":false,"nota":null,"items":[{"nombre":"Hamburguesa de prueba","cantidad":1}]}`.<br>El tablero muestra la tarjeta `T6-001` en la columna «Pendiente» ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-03-orden-caja-aparece-pendiente.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-SVP-04 · Tres órdenes registradas en CAJA aparecen en el tablero

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-04 |
| **Precondición** | Turno `id = 6` abierto. 1 orden en curso: `T6-001` |
| **Dato de entrada** | Segunda orden: 2 × Papas de prueba. Nota: «Extra sal». Medio de pago: **Tarjeta**. Número: `T6-002`.<br>Tercera orden: 1 × Limonada de prueba + 1 × Brownie de prueba. Medio de pago: **Transferencia**. Número: `T6-003` |
| **Pasos** | 1. Desde la sesión de cajero, registrar la segunda y la tercera orden.<br>2. Llamar a `GET /svp/ordenes` con la sesión de producción.<br>3. Verificar que `total` es 3 y que las tres órdenes están en el arreglo. |
| **Resultado esperado** | `GET /svp/ordenes` devuelve `total: 3` con `T6-001`, `T6-002` y `T6-003`, las tres con `estado: "pendiente"`. La nota «Extra sal» de `T6-002` aparece en el campo `nota` |
| **Resultado obtenido** | `POST /caja/vender` × 2 → **200** para `T6-002` y `T6-003`.<br>`GET /svp/ordenes` → **200**, `total: 3`.<br>Arreglo de 3 órdenes, todas `"estado":"pendiente"`. Campo `nota` de `T6-002`: `"Extra sal"` ✓. El tablero muestra tres tarjetas en «Pendiente» ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-04-tres-ordenes-pendientes.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

---

## Ciclo de vida de una orden

### CP-SVP-05 · Avance de estado: pendiente → en preparación

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-05 |
| **Precondición** | Turno `id = 6` abierto. `T6-001` en estado `pendiente`. Sesión de producción activa con token CSRF válido |
| **Dato de entrada** | `POST /svp/orden/{id-T6-001}/estado` con `estado=en_preparacion` |
| **Pasos** | 1. Obtener el `id` de `T6-001` de la respuesta de `GET /svp/ordenes`.<br>2. Leer el token de `<meta name="csrf-token">` del HTML de `/svp`.<br>3. Enviar `POST /svp/orden/{id}/estado` con `estado=en_preparacion` y `_token={token}`.<br>4. Verificar la respuesta JSON y el campo `en_preparacion_en` en `ordenes`. |
| **Resultado esperado** | HTTP 200. `estado_anterior: "pendiente"`, `estado: "en_preparacion"`, `siguiente: "lista"`. El campo `en_preparacion_en` de la fila queda con valor en la base. `GET /svp/ordenes` mueve la tarjeta a la columna «En preparación» |
| **Resultado obtenido** | `POST /svp/orden/{id}/estado` → **200**.<br>Respuesta: `{"orden":{"numero":"T6-001","estado_anterior":"pendiente","estado":"en_preparacion","estado_nombre":"En preparacion","siguiente":"lista","minutos_hasta_lista":null}}`.<br>SQL: `SELECT en_preparacion_en FROM ordenes WHERE numero='T6-001'` → valor con timestamp ✓.<br>`GET /svp/ordenes` → tarjeta `T6-001` en columna «En preparación» ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-05-orden-en-preparacion.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-SVP-06 · Avance de estado: en preparación → lista

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-06 |
| **Precondición** | `T6-001` en estado `en_preparacion` |
| **Dato de entrada** | `POST /svp/orden/{id-T6-001}/estado` con `estado=lista` |
| **Pasos** | 1. Enviar `POST /svp/orden/{id}/estado` con `estado=lista` y el token vigente.<br>2. Verificar la respuesta y los campos `lista_en` y `minutos_hasta_lista` en la base.<br>3. Confirmar que `GET /svp/ordenes` muestra la tarjeta en «Lista». |
| **Resultado esperado** | HTTP 200. `estado_anterior: "en_preparacion"`, `estado: "lista"`, `siguiente: "entregada"`. `lista_en` queda registrado y `minutos_hasta_lista` es un entero ≥ 0. El tablero mueve la tarjeta a «Lista» |
| **Resultado obtenido** | `POST /svp/orden/{id}/estado` → **200**.<br>Respuesta: `{"orden":{"numero":"T6-001","estado_anterior":"en_preparacion","estado":"lista","estado_nombre":"Lista","siguiente":"entregada","minutos_hasta_lista":0}}`.<br>SQL: `SELECT lista_en, TIMESTAMPDIFF(MINUTE, creado_en, lista_en) AS minutos FROM ordenes WHERE numero='T6-001'` → `lista_en` con timestamp, `minutos = 0` ✓.<br>`GET /svp/ordenes` → tarjeta `T6-001` en columna «Lista» ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-06-orden-lista.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-SVP-07 · Avance de estado: lista → entregada, y la orden sale del tablero

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-07 |
| **Precondición** | `T6-001` en estado `lista` |
| **Dato de entrada** | `POST /svp/orden/{id-T6-001}/estado` con `estado=entregada` |
| **Pasos** | 1. Enviar `POST /svp/orden/{id}/estado` con `estado=entregada` y el token vigente.<br>2. Verificar la respuesta y el campo `entregada_en` en la base.<br>3. Llamar a `GET /svp/ordenes` y confirmar que `T6-001` ya no aparece. |
| **Resultado esperado** | HTTP 200. `estado: "entregada"`, `siguiente: null`. `entregada_en` registrado. `GET /svp/ordenes` devuelve `total: 2` sin `T6-001` en el arreglo |
| **Resultado obtenido** | `POST /svp/orden/{id}/estado` → **200**.<br>Respuesta: `{"orden":{"numero":"T6-001","estado_anterior":"lista","estado":"entregada","estado_nombre":"Entregada","siguiente":null,"minutos_hasta_lista":0}}`.<br>SQL: `SELECT entregada_en FROM ordenes WHERE numero='T6-001'` → timestamp registrado ✓.<br>`GET /svp/ordenes` → `total: 2`, arreglo sin `T6-001` ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-07-orden-entregada-sale-tablero.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

---

## Sondeo y coexistencia de estados

### CP-SVP-08 · El sondeo actualiza el tablero sin recargar la página

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-08 |
| **Precondición** | Tablero `/svp` abierto en el navegador con sesión de producción. `T6-002` y `T6-003` en estado `pendiente` |
| **Dato de entrada** | Avance de `T6-002` a `en_preparacion` mediante `curl` mientras el tablero está abierto y sondeando |
| **Pasos** | 1. Abrir `/svp` en el navegador y dejar el tablero activo con el sondeo corriendo.<br>2. Desde `curl`, enviar `POST /svp/orden/{id-T6-002}/estado` con `estado=en_preparacion`.<br>3. Esperar el intervalo de sondeo (máximo 5 segundos).<br>4. Verificar que la tarjeta `T6-002` se mueve sola a la columna «En preparación» sin recargar la página. |
| **Resultado esperado** | La tarjeta `T6-002` aparece en «En preparación» dentro del siguiente ciclo de sondeo, sin que el usuario recargue ni intervenga |
| **Resultado obtenido** | `POST /svp/orden/{id-T6-002}/estado` → **200** desde `curl`. En el tablero abierto, en el siguiente ciclo de sondeo (≤ 5 s), la tarjeta `T6-002` migró sola a la columna «En preparación». `T6-003` permaneció en «Pendiente» sin cambio ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-08-sondeo-estado-actualizado.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-SVP-09 · Dos órdenes en estados distintos coexisten en el tablero

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-09 |
| **Precondición** | `T6-002` en estado `en_preparacion`. `T6-003` en estado `pendiente` |
| **Dato de entrada** | `GET /svp/ordenes` |
| **Pasos** | 1. Llamar a `GET /svp/ordenes` con la sesión de producción.<br>2. Verificar que las dos órdenes aparecen en la respuesta con sus estados correctos.<br>3. Confirmar en el tablero que cada tarjeta está en su columna correspondiente. |
| **Resultado esperado** | `total: 2`. `T6-002` aparece con `estado: "en_preparacion"` y `T6-003` con `estado: "pendiente"`. El orden del arreglo es por estado (en_preparacion primero, por urgencia) y luego por antigüedad |
| **Resultado obtenido** | `GET /svp/ordenes` → **200**, `total: 2`.<br>Arreglo: `T6-002` con `"estado":"en_preparacion"` y `T6-003` con `"estado":"pendiente"` ✓.<br>Orden del arreglo: `T6-002` antes que `T6-003`, conforme a la prioridad por estado ✓.<br>Tablero: columna «En preparación» con `T6-002` y columna «Pendiente» con `T6-003` ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-09-t6002-lista-t6003-pendiente.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

### CP-SVP-10 · La segunda orden pasa a entregada y sale del tablero; T6-003 permanece

| Campo | Contenido |
|---|---|
| **Identificador** | CP-SVP-10 |
| **Precondición** | `T6-002` en estado `en_preparacion` (o `lista` tras avanzarla). `T6-003` en estado `pendiente` |
| **Dato de entrada** | Avance de `T6-002` hasta `entregada` (`en_preparacion → lista → entregada`) |
| **Pasos** | 1. Enviar `POST /svp/orden/{id-T6-002}/estado` con `estado=lista`.<br>2. Enviar `POST /svp/orden/{id-T6-002}/estado` con `estado=entregada`.<br>3. Llamar a `GET /svp/ordenes` y verificar que `T6-002` ya no aparece pero `T6-003` sí. |
| **Resultado esperado** | `GET /svp/ordenes` devuelve `total: 1` con solo `T6-003` en el arreglo. `T6-002` ha salido del tablero al marcarse entregada |
| **Resultado obtenido** | `POST lista` → **200**. `POST entregada` → **200**.<br>`GET /svp/ordenes` → **200**, `total: 1`.<br>Arreglo: únicamente `T6-003` con `"estado":"pendiente"` ✓. `T6-002` ya no figura en la respuesta ✓.<br>SQL de verificación: `SELECT entregada_en FROM ordenes WHERE numero='T6-002'` → timestamp registrado ✓ |
| **Estado** | **Pasa** |
| **Evidencia** | `evidencias/svp/CP-SVP-10-t6002-entregada-sale-tablero.png` |
| **Defecto** | — |
| **Ejecutado por / cuándo** | Lain Ramírez · 2026-09-12 |

---

**Issue #24 · Probar la integración de CAJA con el Sistema de Visualización de Producción**
