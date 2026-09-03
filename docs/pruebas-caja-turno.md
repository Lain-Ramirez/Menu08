# Pruebas del turno de caja

Ejecutadas contra **https://adso.menu08.com**. **9 comprobaciones, 0 fallos**, más un defecto de
interfaz encontrado y corregido.

## Apertura

| Comprobación | Resultado |
|---|---|
| Registro creado | `#1 base=200000.00 usuario=2 abierto=2026-09-03 11:55:14`, estado `abierto` |
| Segundo turno con uno vigente | **409** y `turnos_caja` sigue con **una sola fila** |

La apertura ocurre dentro de una transacción con `SELECT … FOR UPDATE`. Sin ese bloqueo, dos
cajeros pulsando «Abrir» a la vez —algo real en un food truck con un equipo compartido— crearían
dos turnos y las ventas quedarían repartidas entre ambos, dejando los dos cuadres mal.

## Cierre

Se declaró un conteo físico de `195.000` sobre `200.000` esperados:

| Campo | Valor guardado |
|---|---|
| `total_ventas` | 0.00 |
| `total_declarado` | 195000.00 |
| `diferencia` | **-5000.00** |
| `estado` | cerrado |

La pantalla del turno lo presenta como **faltante**. El total vendido se recalcula desde
`ordenes` en el momento del cierre y no se arrastra de un contador acumulado: el cuadre refleja
lo que hay en la base, no lo que alguien fue sumando por el camino.

## Sin turno abierto

| Comprobación | Resultado |
|---|---|
| `GET /caja` | **302** hacia `/caja/turno` |
| Mensaje | «No hay un turno abierto. Abra el turno para poder vender.» |

## Consulta posterior

`GET /caja/turnos/1` responde 200 con el resumen del turno ya cerrado, incluido el cuadre y la
marca de faltante.

## Defecto encontrado y corregido

Al intentar abrir un segundo turno, el servidor respondía **409 y no creaba la fila** —las dos
cosas correctas— pero **el mensaje de error no se veía**.

La causa: al fallar la apertura se volvía a renderizar la vista del turno, y como ya existía uno
vigente, la vista pasaba a mostrar la rama de **cierre**. El error estaba registrado como error
del campo `base_inicial`, que en esa rama no existe, así que no se pintaba en ninguna parte.

El usuario recibía una pantalla titulada «Cerrar turno» sin ninguna explicación de por qué su
apertura no había funcionado.

**Corregido:** el aviso viaja aparte de los errores de campo y la vista lo muestra al principio,
valga la rama que se pinte.

## Lo que estas pruebas no cubren todavía

Dos criterios dependen de que existan órdenes, que llegan con su propio issue:

- El desglose por medio de pago y la cantidad de órdenes del turno. La consulta ya agrupa por
  `medio_pago` sobre `ordenes` y `orden_items`, y con cero órdenes devuelve cero, que es el caso
  «turno sin órdenes» de la tarea. Faltan los casos con una y con varias órdenes de distinto medio.
- Que un turno cerrado no admita nuevas órdenes.
