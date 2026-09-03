# Pruebas del registro de órdenes

Ejecutadas contra **https://adso.menu08.com**. **14 comprobaciones, 0 fallos.**

## El total lo calcula el servidor

Se envió la venta con **un total falso en el formulario** (`total=1`, `precio=1`) junto a tres
unidades de un producto de 5.000:

| En la base | Valor |
|---|---|
| `ordenes.total` | **15000.00** = 3 × 5.000 |
| `ordenes.numero` | `T2-001` |
| `ordenes.estado` | pendiente |
| `ordenes.medio_pago` | efectivo |
| `orden_items` | `"Maduritos"` · 5000.00 × 3 = 15000.00 |

El importe enviado por el navegador se descartó. Los precios se leen de `productos` dentro de la
misma transacción que escribe la venta.

`orden_items` guarda el nombre y el precio **copiados** al momento de la venta, no una referencia:
si mañana cambia el precio del producto, esta orden conserva el suyo.

## La transacción es real

Se creó un disparador en MySQL que hace fallar **toda** inserción en `orden_items`:

```sql
CREATE TRIGGER falla_items BEFORE INSERT ON orden_items FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fallo forzado para probar la transaccion';
```

Con el disparador activo se intentó una venta:

| Antes | Petición | Después |
|---|---|---|
| `ordenes=1` `orden_items=1` | 500 | `ordenes=1` `orden_items=1` |

La cabecera de la orden llegó a insertarse y **se deshizo**. No quedó ninguna fila huérfana. No es
una comprobación por inspección: es un fallo provocado a propósito.

## Rechazos

Ninguno escribió en la base. Tras los tres, `ordenes` seguía con una sola fila.

| Caso | Código |
|---|---|
| Orden sin ítems | 422 |
| Cantidad cero | 422 |
| Producto inexistente | 422 |

## Comprobante

`GET /caja/comprobante/1` responde 200 y muestra el número `T2-001`, el producto, el total
`15.000`, la nota para producción y el medio de pago. Lleva reglas de impresión que ocultan la
navegación, para que salga limpio en la impresora de rollo.

## Los dos criterios que quedaban abiertos en el turno de caja

**Desglose por medio de pago.** El resumen del turno muestra ahora datos reales: una orden en
efectivo por 15.000.

**Un turno cerrado no admite órdenes.** Se cerró el turno #2 y se envió una venta con token
válido: **302** hacia `/caja/turno` con el mensaje «No hay un turno abierto», y `ordenes` no
recibió filas.

Un primer intento devolvió 403 en lugar de la redirección esperada. La causa: cerrar el turno
rota el token contra falsificación de peticiones, así que el formulario que el cajero tenía
abierto quedó invalidado y el corte ocurrió antes de llegar a la comprobación del turno. El
resultado era correcto por partida doble, pero no probaba lo que se quería, así que se repitió
con un token fresco.

## Cuadre del turno #2

| | |
|---|---|
| Base inicial | 200.000 |
| Vendido | 15.000 |
| Declarado | 215.000 |
| **Diferencia** | **0.00 — cuadra** |

El total vendido lo recalculó el cierre desde `ordenes`, sin arrastrar contadores.

## Dinero en enteros

Los importes se calculan en centavos con aritmética entera y solo se formatean al final. Acumular
dinero en coma flotante arrastra errores de redondeo que en una jornada de decenas de ventas
descuadran la caja por unos pesos, y el cuadre del turno es justamente lo que los delataría.
Verificado con seis casos, incluidos `9.999,99 × 99` y decimales que en flotante fallan.
