# Contrato del servicio del Sistema de Visualización de Producción

Tres operaciones: el tablero de producción **consulta** las órdenes en curso por sondeo periódico y **avanza**
cada orden por su ciclo de vida, y la pantalla pública de la ventanilla **consulta** los turnos listos y en preparación.
Todas responden JSON siempre, también al fallar.

| Operación | Ruta |
|---|---|
| Órdenes en curso del turno vigente | `GET /svp/ordenes` |
| Avanzar una orden de estado | `POST /svp/orden/{id}/estado` |
| Turnos públicos para la ventanilla | `GET /turnos/{slug}/ordenes` |

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
  "ahora": "2026-09-11 19:44:07",
  "minutos_demora": 10,
  "total": 2,
  "ordenes": [
    {
      "id": 4,
      "numero": "T3-002",
      "estado": "pendiente",
      "estado_nombre": "Pendiente",
      "creado_en": "2026-09-11 19:42:11",
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
      "creado_en": "2026-09-11 19:30:02",
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
| `ahora` | Reloj del servidor en el instante de responder. El tablero le resta el suyo para saber su desfase y contar el tiempo contra la hora buena |
| `minutos_demora` | Umbral a partir del cual una orden se marca como demorada |
| `total` | Cantidad de órdenes en curso |
| `ordenes[].creado_en` | Cuándo la registró CAJA. Es la «hora de recepción» que imprime la tarjeta y el punto de partida del cronómetro |
| `ordenes[].minutos` | Minutos transcurridos desde que CAJA registró la orden |
| `ordenes[].demorada` | `true` cuando `minutos >= minutos_demora` |
| `ordenes[].items[]` | Nombre y cantidad, copiados al momento de la venta |

El orden del arreglo es por estado y, dentro de cada estado, por antigüedad: lo más urgente
primero.

**Por qué viajan `minutos` y `creado_en`, que son el mismo dato contado de dos maneras.** `minutos`
y `demorada` los calcula el servidor y valen para cualquier cliente que solo quiera leer la lista.
El tablero del #20 necesita además la marca: con ella lleva su propio cronómetro, un segundo a la
vez, sin pedir la lista otra vez sólo para que el reloj avance. Y como el reloj de la tableta de la
ventanilla puede estar mal puesto en hora, la respuesta trae también `ahora`: el tablero calcula el
desfase entre los dos relojes y lo descuenta, de modo que el tiempo que se ve en la pared es el del
servidor aunque el dispositivo no sepa qué hora es. Sin eso, el realce de demora saltaría cuando no
toca —o no saltaría nunca—.

Las dos son añadidos del #20 y **no quitan nada**: un cliente escrito contra el contrato anterior
sigue funcionando igual.

### Turno abierto y sin órdenes en curso · 200

```json
{ "turno": 3, "ahora": "2026-09-11 19:44:07", "minutos_demora": 10, "total": 0, "ordenes": [] }
```

La ventanilla está abierta y producción va al día. El tablero muestra la pantalla vacía, pero
sabe que el turno sigue corriendo.

### Sin turno abierto · 200

```json
{ "turno": null, "ahora": "2026-09-11 19:44:07", "minutos_demora": 10, "total": 0, "ordenes": [] }
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

## Cambio de estado

```
POST /svp/orden/{id}/estado
```

**Autenticación:** sesión iniciada con rol `produccion` o `food_truck`.
**Cuerpo:** `estado`, el estado de destino, y `_token`, el token contra falsificación de
peticiones.

El ciclo de vida **solo avanza**, nunca retrocede. Una orden entregada ya salió por la ventanilla
y no vuelve a la plancha:

```
pendiente → en_preparacion → lista → entregada
```

La tabla de transiciones vive en un único sitio, `Orden::TRANSICIONES`; el controlador no la
repite, solo traduce sus negativas al código HTTP que les toca.

### Respuesta correcta · 200

```json
{
  "orden": {
    "id": 3,
    "numero": "T3-001",
    "estado_anterior": "en_preparacion",
    "estado": "lista",
    "estado_nombre": "Lista",
    "siguiente": "entregada",
    "creado_en": "2026-09-03 10:38:12",
    "en_preparacion_en": "2026-09-03 10:44:01",
    "lista_en": "2026-09-03 10:51:30",
    "entregada_en": null,
    "minutos_hasta_lista": 13
  }
}
```

| Campo | Significado |
|---|---|
| `estado_anterior` | De dónde venía, para que el tablero sepa qué tarjeta mover |
| `siguiente` | Estado al que podría pasar después, o `null` si ya está entregada |
| `minutos_hasta_lista` | Minutos entre `creado_en` y `lista_en`; `null` mientras no esté lista |

Las marcas de tiempo no se pisan: cada estado guarda la suya en su propia columna, así que el
tiempo que tardó una orden sigue siendo consultable después de entregarla.

### Transición no permitida · 422

```json
{
  "error": "transicion_invalida",
  "mensaje": "La orden T3-001 esta \"pendiente\": solo puede pasar a \"en_preparacion\", no a \"entregada\"."
}
```

**La orden no se modifica.** Se comprueba dentro de la misma transacción que la actualizaría, con
la fila bloqueada, así que dos pantallas pulsando a la vez no pueden colarla dos casillas.

Una orden ya entregada responde igual, con otro motivo:

```json
{ "error": "transicion_invalida", "mensaje": "La orden T3-001 ya esta \"entregada\" y no admite mas cambios." }
```

### Orden inexistente o de otro food truck · 404

```json
{ "error": "orden_no_encontrada", "mensaje": "La orden 999 no existe para este food truck." }
```

Las dos situaciones responden lo mismo a propósito: pedir la orden de otro truck no debe servir
para averiguar que existe.

### Token ausente o vencido · 403

```json
{
  "error": "token_invalido",
  "mensaje": "El token de seguridad expiro o no es valido. Recargue el tablero."
}
```

A diferencia de la venta en CAJA, **este servicio no rota el token** tras cada cambio: el tablero
avanza varias órdenes seguidas y tener que releerlo entre una y otra lo volvería frágil. Puede
hacerlo porque no lo necesita para evitar duplicados —de eso ya se encarga la propia máquina de
estados: repetir el mismo envío encuentra la orden ya movida y responde 422 sin tocar nada.

Sin sesión y con rol equivocado se responde igual que en la consulta: 401 y 403.

## Consulta de turnos públicos para la ventanilla

```
GET /turnos/{slug}/ordenes
```

**Autenticación:** ninguna (servicio público).
**Parámetros:** `slug` en la dirección, correspondiente al food truck.
**Respuesta:** `Content-Type: application/json; charset=utf-8`.

Hermano público de `GET /svp/ordenes`, diseñado para alimentar la pantalla de turnos que cuelga en
la ventanilla del food truck. Incluye únicamente órdenes en preparación (`en_preparacion`) y listas
para entrega (`lista`).

**Aislamiento y privacidad:**
A diferencia del servicio privado de cocina, este servicio **no expone productos, cantidades,
totales, medios de pago ni notas de clientes**. Devuelve exclusivamente el número de turno y su
estado actual.

### Respuesta correcta · 200

```json
{
  "turno": 3,
  "ahora": "2026-09-12 10:30:00",
  "total": 2,
  "ordenes": [
    {
      "numero": "T3-002",
      "estado": "en_preparacion"
    },
    {
      "numero": "T3-001",
      "estado": "lista"
    }
  ]
}
```

| Campo | Significado |
|---|---|
| `turno` | Identificador del turno abierto, o `null` si no hay ninguno |
| `ahora` | Reloj del servidor en el instante de responder |
| `total` | Cantidad total de turnos en preparación y listos |
| `ordenes[].numero` | Consecutivo de la orden que el cliente tiene en su comprobante |
| `ordenes[].estado` | Estado actual: `"en_preparacion"` o `"lista"` |

### Turno abierto y sin órdenes en preparación ni listas · 200

```json
{ "turno": 3, "ahora": "2026-09-12 10:30:00", "total": 0, "ordenes": [] }
```

El turno está abierto, pero la cocina está al día y no hay pedidos pendientes de entrega.

### Sin turno abierto · 200

```json
{ "turno": null, "ahora": "2026-09-12 10:30:00", "total": 0, "ordenes": [] }
```

La ventanilla está cerrada. La pantalla muestra el aviso correspondiente.

### Food truck inexistente o inactivo · 404

```json
{
  "error": "food_truck_no_encontrado",
  "mensaje": "No hay un food truck activo con el slug \"no-existe\"."
}
```

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
