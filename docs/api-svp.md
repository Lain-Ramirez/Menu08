# Contrato del servicio del Sistema de Visualización de Producción

Servicio que consume el tablero del SVP por sondeo periódico. Devuelve las órdenes en curso del
turno vigente.

## Consulta de órdenes en curso

```
GET /svp/ordenes
```

**Autenticación:** sesión iniciada con rol `produccion` o `food_truck`.
**Parámetros:** ninguno. El food truck se toma de la sesión, nunca de la petición.
**Respuesta:** `Content-Type: application/json; charset=utf-8`.

Incluye las órdenes en estado `pendiente`, `en_preparacion` y `lista`. Las `entregada` quedan
fuera: ya salieron por la ventanilla.

### Respuesta correcta · 200

```json
{
  "turno": 3,
  "minutos_demora": 10,
  "total": 2,
  "ordenes": [
    {
      "id": 4,
      "numero": "T3-002",
      "estado": "pendiente",
      "estado_nombre": "Pendiente",
      "minutos": 2,
      "demorada": false,
      "nota": "Sin cebolla",
      "items": [
        { "nombre": "Maduritos", "cantidad": 3 }
      ]
    },
    {
      "id": 3,
      "numero": "T3-001",
      "estado": "en_preparacion",
      "estado_nombre": "En preparacion",
      "minutos": 14,
      "demorada": true,
      "nota": null,
      "items": [
        { "nombre": "Maduritos", "cantidad": 1 }
      ]
    }
  ]
}
```

| Campo | Significado |
|---|---|
| `turno` | Identificador del turno abierto, o `null` solo si no hay ninguno. Un turno abierto se informa aunque no tenga órdenes en curso |
| `minutos_demora` | Umbral a partir del cual una orden se marca como demorada |
| `total` | Cantidad de órdenes en curso |
| `ordenes[].minutos` | Minutos transcurridos desde que CAJA registró la orden |
| `ordenes[].demorada` | `true` cuando `minutos >= minutos_demora` |
| `ordenes[].items[]` | Nombre y cantidad, copiados al momento de la venta |

El orden del arreglo es por estado y, dentro de cada estado, por antigüedad: lo más urgente
primero.

### Turno abierto y sin órdenes en curso · 200

```json
{ "turno": 3, "minutos_demora": 10, "total": 0, "ordenes": [] }
```

La ventanilla está abierta y producción va al día. El tablero muestra la pantalla vacía, pero
sabe que el turno sigue corriendo.

### Sin turno abierto · 200

```json
{ "turno": null, "minutos_demora": 10, "total": 0, "ordenes": [] }
```

No es un error: el truck simplemente no está vendiendo.

**`turno` es lo que separa los dos casos**, y por eso se informa aunque la lista venga vacía. Si
el servicio respondiera `null` en ambos, el tablero no podría distinguir «cocina al día» de
«ventanilla cerrada», que son dos pantallas distintas.

### Sin sesión · 401

```json
{ "error": "no_autenticado", "mensaje": "Debe iniciar sesion para consultar este servicio." }
```

### Rol sin permiso · 403

```json
{ "error": "rol_no_autorizado", "mensaje": "El rol \"cajero\" no tiene acceso a este servicio." }
```

### Fallo del servidor · 500

```json
{ "error": "fallo_interno", "codigo": 500 }
```

En entorno de desarrollo se añade un campo `mensaje` con el detalle. En producción nunca.

## Por qué los errores también son JSON

El tablero sondea esta ruta cada pocos segundos desde JavaScript. Si al caducar la sesión el
servidor devolviera la página HTML de acceso, el cliente intentaría interpretarla como JSON y
fallaría con un error de sintaxis que no dice nada del problema real. Devolviendo siempre un
objeto, el tablero puede distinguir «no hay sesión» de «no hay órdenes» y actuar en consecuencia.

Esto vale para `GET /svp/ordenes`, que es la única ruta del servicio. Un método equivocado
(`POST /svp/ordenes`) o una subruta inexistente (`/svp/ordenes/9`) no coinciden con ninguna ruta
registrada y responden **404 en HTML**, como cualquier otra dirección desconocida del sitio. El
tablero solo hace `GET` sobre la ruta exacta, así que no se topa con ese caso.

## Costo de la consulta

Cada respuesta se resuelve con **dos sentencias preparadas como máximo**: una para las órdenes y
otra para sus ítems. Si el turno abierto no tiene nada en curso, la segunda ni siquiera se
prepara. El turno vigente se localiza con una subconsulta dentro de cada una, en vez de gastar
una consulta aparte. Como el tablero sondea de forma continua, cada consulta de más se paga
muchas veces por minuto.

La primera consulta arranca en `turnos_caja` y baja a `ordenes` con un `LEFT JOIN`, no al revés.
Es lo que permite informar el turno cuando está abierto y vacío: si arrancara en `ordenes`, sin
filas no habría de dónde sacar el identificador del turno.
