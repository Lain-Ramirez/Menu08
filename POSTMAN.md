# Probar Menu08 con Postman

Guía para probar **todas** las rutas del prototipo desde Postman, sin tener que leer el código PHP.
Está escrita para quien nunca ha probado esta aplicación: se explica cada paso y qué se debe
recibir en cada uno.

**Servidor:** `https://adso.menu08.com`

---

## Atajo: importa la colección y ya está

En `postman/Menu08.postman_collection.json` está **todo hecho**: las 35 rutas, las credenciales,
los cuerpos de ejemplo y las comprobaciones automáticas.

1. Abre Postman.
2. Botón **Import**, arriba a la izquierda.
3. Arrastra el archivo `postman/Menu08.postman_collection.json`.
4. Abre la carpeta **«1 - Recorrido completo»** y pulsa **Run** (el botón ▶ de la carpeta).

Eso ejecuta una venta de principio a fin —abre turno, vende, la orden aparece en el tablero de
producción, avanza hasta entregada y cierra el turno— y va marcando en verde cada comprobación.

El resto del documento explica **qué está pasando** en cada petición, por si algo sale en rojo o
quieres armar las tuyas.

---

## Antes de nada: cómo entra uno a esta aplicación

No hay «API key» ni «Bearer token». Son **dos cosas a la vez**, y si falta una, nada funciona.

### 1. La cookie de sesión

Al ingresar, el servidor manda una cookie llamada `menu08_sesion`. **Postman la guarda y la
reenvía sola**, igual que un navegador: basta con hacer el ingreso una vez y todas las peticiones
siguientes ya van identificadas.

Si en algún momento todo empieza a dar 401 o a redirigir al ingreso, la sesión caducó: se vuelve a
hacer el ingreso. Caduca a las **2 horas** sin actividad.

### 2. El campo `_token`

Es una defensa contra falsificación de peticiones. **Todos los POST lo exigen** y no se puede
inventar: el servidor lo genera y lo esconde dentro del formulario de la página.

Entonces el patrón es siempre el mismo, **dos peticiones**:

```
GET  la página que trae el formulario   ->  de ahí se saca el _token
POST la acción, mandando ese _token     ->  se ejecuta
```

Y hay una vuelta de tuerca que conviene saber desde el principio:

> **Cada POST de formulario que sale bien invalida el token.** Está hecho a propósito, para que
> reenviar un formulario no repita la venta. Es decir: **antes de cada POST hay que volver a hacer
> el GET.** No sirve reutilizar el de hace un rato.

La única excepción es `POST /svp/orden/{id}/estado`, que **no** invalida el token, porque el
tablero de producción avanza varias órdenes seguidas.

### Tres errores que le pasan a todo el mundo la primera vez

| Lo que ves | Lo que pasó |
|---|---|
| **403** en un POST que parecía bien | Falta el `_token`, o es el de un POST anterior y ya se gastó. Repite el GET |
| El POST devuelve el HTML de una página en vez del código | Postman siguió la redirección. Hay que desactivar **Automatically follow redirects** |
| **403** aunque copiaste bien el token | Mandaste el cuerpo como **raw / JSON**. Esta aplicación solo lee `x-www-form-urlencoded` |

---

## Preparar Postman a mano

Si prefieres no importar la colección:

**1. Crea una colección** y, en su pestaña **Variables**, añade estas. La columna que Postman usa
al ejecutar es **Current value**:

| Variable | Valor |
|---|---|
| `baseUrl` | `https://adso.menu08.com` |
| `clave` | `Menu08*Demo2026` |
| `token` | *(vacío, se rellena solo)* |
| `ordenId` | *(vacío, se rellena solo)* |

**2. Desactiva las redirecciones automáticas.** En la colección: `…` → **Edit** → pestaña
**Settings** → **Automatically follow redirects** → **OFF**. Sin esto no verás los **302**, que
son la respuesta normal de casi todos los POST.

**3. En cada `GET` que traiga un formulario**, pega esto en la pestaña **Scripts → Post-response**
(en versiones antiguas se llama **Tests**). Guarda el token en la variable:

```js
const m = pm.response.text().match(/name="_token" value="([a-f0-9]+)"/);
if (m) { pm.collectionVariables.set("token", m[1]); }
```

**4. En el `GET /svp`** usa este otro, porque el tablero no tiene formularios y el token viaja en
una etiqueta `<meta>`:

```js
const m = pm.response.text().match(/name="csrf-token" content="([a-f0-9]+)"/);
if (m) { pm.collectionVariables.set("token", m[1]); }
```

**5. En los POST**, el cuerpo va en **Body → x-www-form-urlencoded**, y el campo se llama `_token`
con valor `{{token}}`.

---

## Usuarios

Este repositorio es privado, así que las contraseñas están aquí mismo. **Todas son
`Menu08*Demo2026`.**

### Festín Rodante — el food truck de demostración del prototipo

| Correo | Contraseña | Rol | Qué puede hacer |
|---|---|---|---|
| `plataforma@menu08.local` | `Menu08*Demo2026` | `plataforma` | Ver `/panel`. No está atado a ningún truck |
| `foodtruck@menu08.local` | `Menu08*Demo2026` | `food_truck` | Panel de CARTA: catálogo, paradas y QR |
| `cajero@menu08.local` | `Menu08*Demo2026` | `cajero` | CAJA: turnos y ventas |
| `produccion@menu08.local` | `Menu08*Demo2026` | `produccion` | SVP: el tablero de producción |

### Truck de Pruebas — el banco de pruebas

**Usa estos para practicar.** Así no se ensucian los datos de Festín Rodante, que es el food truck
de demostración del prototipo.

| Correo | Contraseña | Rol |
|---|---|---|
| `pruebas.foodtruck@menu08.local` | `Menu08*Demo2026` | `food_truck` |
| `pruebas.cajero@menu08.local` | `Menu08*Demo2026` | `cajero` |
| `pruebas.produccion@menu08.local` | `Menu08*Demo2026` | `produccion` |

Carta pública: **https://adso.menu08.com/carta/truck-de-pruebas** · `food_truck_id = 4`

> Estas credenciales valen para el prototipo del SENA. **Hay que cambiarlas antes de publicar
> cualquier cosa de verdad.**

### El catálogo del Truck de Pruebas

Los identificadores hacen falta para vender: cada casilla de cantidad se llama
`cantidad[<id del producto>]`.

| id | Categoría | Producto | Precio | Para qué está puesto |
|---|---|---|---|---|
| 4 | Entradas | Papas de prueba | 6.500,00 | normal |
| 5 | Entradas | Empanada de prueba | 3.200,00 | normal |
| 6 | Entradas | Entrada agotada | 5.000,00 | **`disponible = 0`**: no se puede vender |
| 7 | Fuertes | Hamburguesa de prueba | 24.900,00 | normal |
| 8 | Fuertes | Perro de prueba | 18.750,50 | decimales que en coma flotante fallan |
| 9 | Fuertes | Salchipapa de prueba | 21.000,00 | normal |
| 10 | Bebidas | Gaseosa de prueba | 4.500,00 | normal |
| 11 | Bebidas | Limonada de prueba | 7.000,00 | normal |
| 12 | Bebidas | Vaso de agua | 0,00 | precio cero |
| 13 | Postres | Brownie de prueba | 9.900,00 | normal |
| 14 | Postres | Producto con un nombre deliberadamente largo… | 12.345,67 | corte en pantalla |

Categorías: **3** Entradas · **4** Fuertes · **5** Bebidas · **6** Postres · **7** *Categoría
desactivada*.

La agenda de paradas tiene cuatro filas, y cada una está para algo:

| id | Día | Punto | Horario | Estado | Para qué |
|---|---|---|---|---|---|
| 2 | Miércoles | Parque de Pruebas | 11:00 → 15:00 | activa | jornada normal |
| 3 | Viernes | Plaza de Pruebas | 12:00 → 20:00 | activa | jornada larga |
| 4 | **Sábado** | **Zona Rosa de Pruebas** | **18:00 → 01:00** | activa | **cruza la medianoche** |
| 5 | Lunes | Parada desactivada | 09:00 → 13:00 | **inactiva** | no debe salir en la carta |

La tercera es la que demuestra el caso difícil: consultada un domingo a las 00:30 sigue vigente,
aunque esté declarada en el día anterior. La cuarta demuestra lo contrario: desactivada, no aparece
ni como vigente ni en la agenda de `/carta/truck-de-pruebas`. Sus identificadores **cambian al
resembrar**, igual que los de los productos; se leen del listado del panel.

Si hace falta dejarlo todo como estaba, se vuelve a ejecutar
[`menu08_app/basedatos/datos_pruebas.sql`](menu08_app/basedatos/datos_pruebas.sql): borra su propio
truck y lo rehace, sin tocar ninguna fila de los demás. **Al hacerlo cambian los identificadores**
de la tabla de arriba; los nuevos se ven en la pantalla de venta.

---

## El recorrido completo, paso a paso

Es lo que hace la carpeta «1 - Recorrido completo» de la colección. Aquí está explicado.

### 1 · `GET {{baseUrl}}/ingresar`

**Esperas:** `200` y una página HTML. En el cuerpo hay algo así:

```html
<input type="hidden" name="_token" value="f33a18ab40d8701e0265fd115b1775a7…">
```

El script guarda ese valor en `{{token}}`.

### 2 · `POST {{baseUrl}}/ingresar`

**Mandas** (Body → x-www-form-urlencoded):

| Campo | Valor |
|---|---|
| `correo` | `pruebas.cajero@menu08.local` |
| `contrasena` | `Menu08*Demo2026` |
| `_token` | `{{token}}` |

**Esperas:** `302`, y en las cabeceras `Location: https://adso.menu08.com/caja`.

Si recibes **401**, la contraseña está mal. Si recibes **403**, el `_token` está mal o falta.

Cada rol aterriza en un sitio distinto: `plataforma` y `food_truck` en `/panel`, `cajero` en
`/caja`, `produccion` en `/svp`.

### 3 · `GET {{baseUrl}}/caja`

**Esperas:** `302` hacia `/caja/turno`. Sin turno abierto, CAJA no deja vender. Si te responde
`200`, es que ya hay un turno abierto y puedes saltarte los pasos 4 y 5.

### 4 · `GET {{baseUrl}}/caja/turno` → captura el token

**Esperas:** `200` con el formulario de apertura.

### 5 · `POST {{baseUrl}}/caja/turno/abrir`

| Campo | Valor |
|---|---|
| `base_inicial` | `100000` |
| `_token` | `{{token}}` |

**Esperas:** `302` hacia `/caja`.

Si recibes **409**, ya había un turno abierto y **no se creó otro**. Eso es correcto: dos cajeros
pulsando «Abrir» a la vez repartirían las ventas entre dos turnos y los dos cuadres saldrían mal.

### 6 · `GET {{baseUrl}}/caja` → captura el token

**Esperas:** `200` con la pantalla de venta. Aquí se ven los identificadores reales del catálogo,
en las casillas `name="cantidad[7]"`, `name="cantidad[10]"`…

### 7 · `POST {{baseUrl}}/caja/vender`

| Campo | Valor | Qué es |
|---|---|---|
| `cantidad[7]` | `2` | dos hamburguesas |
| `cantidad[10]` | `1` | una gaseosa |
| `medio_pago` | `efectivo` | o `tarjeta`, o `transferencia` |
| `nota` | `Sin cebolla, para llevar` | va al tablero de producción |
| `_token` | `{{token}}` | |

**Esperas:** `302` hacia `/caja/comprobante/{id}`.

**Prueba interesante:** añade al cuerpo un campo `total` con valor `1`. La venta se registra igual
por **54.300,00**, no por 1. El servidor lee los precios de la base de datos dentro de la misma
transacción que escribe la venta: lo que diga el navegador sobre el importe se descarta.

### 8 · `GET {{baseUrl}}/caja/comprobante/{{comprobanteId}}`

**Esperas:** `200` con el comprobante y el número de turno que se le canta al cliente.

### 9 y 10 · Cambiar de usuario a producción

`GET /ingresar` para capturar el token, y `POST /ingresar` con
`pruebas.produccion@menu08.local`. **Esperas** `302` hacia `/svp`.

### 11 · `GET {{baseUrl}}/svp` → captura el token del `<meta>`

**Esperas:** `200`. En el `<head>` está:

```html
<meta name="csrf-token" content="58f83188…">
```

### 12 · `GET {{baseUrl}}/svp/ordenes`

**Esperas:** `200` con `Content-Type: application/json`, y la orden que acabas de registrar:

```json
{
  "turno": 5,
  "minutos_demora": 10,
  "total": 1,
  "ordenes": [
    {
      "id": 24,
      "numero": "T5-001",
      "estado": "pendiente",
      "estado_nombre": "Pendiente",
      "minutos": 0,
      "demorada": false,
      "nota": "Sin cebolla, para llevar",
      "items": [
        { "nombre": "Hamburguesa de prueba", "cantidad": 2 },
        { "nombre": "Gaseosa de prueba", "cantidad": 1 }
      ]
    }
  ]
}
```

Ésta es exactamente la petición que el tablero repite cada pocos segundos. Por eso la orden
«aparece sola»: no hay magia, hay sondeo.

### 13, 14 y 15 · `POST {{baseUrl}}/svp/orden/{{ordenId}}/estado`

Tres veces, cambiando el campo `estado`: primero `en_preparacion`, luego `lista`, luego
`entregada`.

| Campo | Valor |
|---|---|
| `estado` | `en_preparacion` |
| `_token` | `{{token}}` |

**Esperas** en cada una `200` con:

```json
{
  "orden": {
    "id": 24,
    "numero": "T5-001",
    "estado_anterior": "pendiente",
    "estado": "en_preparacion",
    "estado_nombre": "En preparacion",
    "siguiente": "lista",
    "creado_en": "2026-09-03 13:10:22",
    "en_preparacion_en": "2026-09-03 13:11:05",
    "lista_en": null,
    "entregada_en": null,
    "minutos_hasta_lista": null
  }
}
```

Fíjate en `siguiente`: te dice a dónde puede ir después. Cuando llega a `entregada`, vale `null`.
Y `minutos_hasta_lista` sigue en `null` hasta que la orden llega a `lista`.

### 16 · `GET {{baseUrl}}/svp/ordenes` otra vez

**Esperas:** `200`, y la orden **ya no está**. Una orden entregada salió por la ventanilla y no se
muestra en el tablero.

### 17 a 20 · Cerrar el turno

Vuelves a ingresar como cajero, `GET /caja/turno` para capturar el token —ahora esa página muestra
el formulario de **cierre**— y `POST /caja/turno/cerrar` con `total_declarado`.

**Esperas:** `302` hacia `/caja/turnos/{id}`. Si declaras solo la base, saldrá un faltante por el
valor de lo vendido: es lo correcto, el servidor recalcula el total desde las órdenes.

---

# Referencia de todas las rutas

35 rutas. Leyenda de roles: **pública** · `plataforma` · `food_truck` · `cajero` · `produccion`.

Sin sesión, las rutas privadas redirigen a `/ingresar` con **302**. Las excepciones son los dos
servicios JSON del SVP, que responden **401** con un objeto de error.

## Públicas

| Método | Ruta | Cuerpo | Qué esperas |
|---|---|---|---|
| `GET` | `/` | — | `200` · comprobación del núcleo |
| `GET` | `/comprobacion/{slug}` | — | `200` · datos del truck. `404` si el slug no existe |
| `GET` | `/carta/{slug}` | — | `200` · la carta que abre el cliente al leer el QR |

En `/carta/truck-de-pruebas` **no** deben aparecer «Categoria desactivada» ni «Entrada agotada»:
la carta pública oculta lo que está dado de baja.

## Autenticación

| Método | Ruta | Cuerpo | Qué esperas |
|---|---|---|---|
| `GET` | `/ingresar` | — | `200` con el `_token`. Con sesión abierta, `302` al inicio del rol |
| `POST` | `/ingresar` | `correo`, `contrasena`, `_token` | `302` al inicio del rol · `401` si las credenciales fallan · `403` si falta el token |
| `GET` | `/salir` | — | `302` a `/ingresar`. Borra la cookie |

El mensaje de error del ingreso es el mismo para correo inexistente, contraseña equivocada y cuenta
desactivada. Es deliberado: decir cuál de los tres fue delataría qué cuentas existen.

## Panel de CARTA · rol `food_truck`

Todos los POST necesitan `_token` **y lo invalidan**: hay que repetir el GET antes de cada uno.

| Método | Ruta | Cuerpo | Qué esperas |
|---|---|---|---|
| `GET` | `/panel` | — | `200`. También lo ve `plataforma` |
| `GET` | `/panel/food-truck` | — | `200` con el formulario |
| `POST` | `/panel/food-truck` | `nombre`, `slug`, `descripcion`, `telefono`, `whatsapp`, `instagram`, `ciudad`, `_token`, y `logo` como archivo | `302` a `/panel` |
| `GET` | `/panel/categorias` | — | `200` con el listado |
| `GET` | `/panel/categorias/{id}` | — | `200` con esa categoría en edición · `404` si es de otro truck |
| `POST` | `/panel/categorias` | `id` (**`0` para crear**), `nombre`, `orden`, `_token` | `302` |
| `POST` | `/panel/categorias/estado` | `id`, `_token` | `302`. **Alterna** activa/inactiva: no lleva el valor nuevo, lo invierte |
| `GET` | `/panel/productos` | — | `200` |
| `GET` | `/panel/productos/nuevo` | — | `200` con el formulario vacío |
| `GET` | `/panel/productos/{id}` | — | `200` · `404` si es de otro truck |
| `POST` | `/panel/productos` | `id` (**`0` para crear**), `categoria_id`, `nombre`, `descripcion`, `precio`, `orden`, `disponible`, `_token`, y `foto` como archivo | `302` |
| `POST` | `/panel/productos/disponibilidad` | `id`, `_token` | `302`. **Alterna** disponible/agotado |
| `GET` | `/panel/ubicaciones` | — | `200` con la agenda y la parada vigente. Admite `?momento=AAAA-MM-DDTHH:MM` |
| `GET` | `/panel/ubicaciones/{id}` | — | `200` con esa parada en edición · `404` si es de otro truck |
| `POST` | `/panel/ubicaciones` | `id` (**`0` para crear**), `nombre`, `referencia`, `dia_semana`, `hora_inicio`, `hora_fin`, `latitud`, `longitud`, `_token` | `302` · `422` si el día está fuera de 1-7 o la hora está mal formada |
| `POST` | `/panel/ubicaciones/estado` | `id`, `_token` | `302`. **Alterna** activa/inactiva |
| `GET` | `/panel/qr` | — | `200` con el QR de la carta |
| `GET` | `/panel/qr/descargar` | — | `200` con `Content-Type: image/png`. Postman lo muestra como imagen |

Para mandar el `logo` o la `foto`, el cuerpo tiene que ser **form-data** en vez de
`x-www-form-urlencoded`, con ese campo de tipo **File**. Los demás campos van en el mismo form-data.

Los tres POST que **alternan** no reciben el valor nuevo: leen el actual y lo invierten. Si lo
ejecutas dos veces, queda como estaba.

En las paradas, `dia_semana` va de **1 (lunes) a 7 (domingo)**; `hora_inicio` y `hora_fin`, en
`HH:MM` o `HH:MM:SS`. Y lo que más sorprende la primera vez: **una `hora_fin` menor o igual que
`hora_inicio` es correcta y significa que la jornada cierra al día siguiente** — de 18:00 a 01:00
es el horario normal de un truck nocturno, no un error de captura. `referencia`, `latitud` y
`longitud` son opcionales; vacías se guardan como `NULL`.

El parámetro `?momento=` del listado sirve para preguntar «¿qué parada está abierta a esta otra
hora?» sin esperar a que llegue. Es de solo lectura y sigue filtrando por el food truck de la
sesión. Con `?momento=2026-09-06T00:30` —un domingo a las 00:30— la parada del sábado de 18:00 a
01:00 aparece como vigente; con `?momento=2026-09-06T01:30`, ya no.

## CAJA · roles `cajero` y `food_truck`

Todos los POST necesitan `_token` **y lo invalidan**.

| Método | Ruta | Cuerpo | Qué esperas |
|---|---|---|---|
| `GET` | `/caja` | — | `200` con la venta · **`302` a `/caja/turno` si no hay turno abierto** |
| `GET` | `/caja/turno` | — | `200`. Muestra apertura o cierre según haya turno |
| `POST` | `/caja/turno/abrir` | `base_inicial`, `_token` | `302` a `/caja` · **`409`** si ya había turno, y no crea la fila |
| `POST` | `/caja/turno/cerrar` | `total_declarado`, `_token` | `302` a `/caja/turnos/{id}` |
| `POST` | `/caja/vender` | `cantidad[<id>]` una por producto, `medio_pago`, `nota`, `_token` | `302` al comprobante · **`422`** si la orden va vacía, con cantidad cero, o con un producto inexistente o agotado |
| `GET` | `/caja/comprobante/{id}` | — | `200` · `404` si es de otro truck |
| `GET` | `/caja/turnos` | — | `200` con el historial |
| `GET` | `/caja/turnos/{id}` | — | `200` con el cuadre del turno |

`medio_pago` admite `efectivo`, `tarjeta` o `transferencia`. Máximo **99** unidades por producto.

Si el turno se cierra mientras tenías la pantalla de venta abierta, el POST responde `302` hacia
`/caja/turno` con el aviso «No hay un turno abierto», y no escribe nada.

## SVP · roles `produccion` y `food_truck`

Los dos servicios JSON. **Responden JSON siempre, también al fallar**, porque el tablero los
consume desde JavaScript y una página HTML de error rompería el consumidor. Contrato completo en
[`docs/api-svp.md`](docs/api-svp.md).

| Método | Ruta | Cuerpo | Qué esperas |
|---|---|---|---|
| `GET` | `/svp` | — | `200` HTML. De aquí sale el token, en `<meta name="csrf-token">` |
| `GET` | `/svp/ordenes` | — | `200` JSON con las órdenes en curso |
| `POST` | `/svp/orden/{id}/estado` | `estado`, `_token` | `200` JSON con la orden ya avanzada |

`estado` admite `en_preparacion`, `lista` o `entregada`. **El ciclo solo avanza**, nunca retrocede:

```
pendiente  ->  en_preparacion  ->  lista  ->  entregada
```

Sobre `GET /svp/ordenes`:

- Trae los estados `pendiente`, `en_preparacion` y `lista`. Las `entregada` quedan fuera.
- El arreglo **ya viene ordenado** por estado y, dentro de cada estado, por antigüedad.
- `demorada` **ya viene calculado** contra `minutos_demora`: no hay que compararlo a mano.
- `turno` vale `null` **solo** si no hay ningún turno abierto. Con turno abierto y sin órdenes
  devuelve el identificador y la lista vacía, para poder distinguir «producción al día» de
  «ventanilla cerrada».

## Errores de los servicios JSON

| Código | `error` | Cuándo |
|---|---|---|
| `401` | `no_autenticado` | Sin sesión, o con la cookie caducada |
| `403` | `rol_no_autorizado` | Sesión con rol `cajero` o `plataforma` |
| `403` | `token_invalido` | `_token` ausente, vencido o alterado |
| `404` | `orden_no_encontrada` | La orden no existe **o es de otro food truck** |
| `422` | `transicion_invalida` | Salto de estado, estado inexistente, u orden ya entregada. **No modifica la orden** |
| `500` | `fallo_interno` | Fallo no previsto. El campo `mensaje` solo aparece fuera de producción |

Que «no existe» y «es de otro food truck» respondan **lo mismo** es deliberado: un `403` confirmaría
que esa orden existe, y eso ya es información que no debe salir.

---

## Comprobaciones que merece la pena hacer

Son las de la carpeta «7 - Errores que DEBEN fallar». **Que den error es lo correcto**; si alguna
respondiera `200`, habría un problema de verdad.

| Prueba | Cómo | Qué esperas |
|---|---|---|
| Sin sesión no se ve el tablero | `GET /salir`, luego `GET /svp/ordenes` | `401` `no_autenticado`, **en JSON, sin nada de HTML** |
| El cajero no entra al tablero | Ingresa como `pruebas.cajero`, `GET /svp/ordenes` | `403` `rol_no_autorizado` |
| Producción no entra a CAJA | Ingresa como `pruebas.produccion`, `GET /caja` | `403` |
| El cajero no entra al panel | Ingresa como `pruebas.cajero`, `GET /panel` | `403` |
| Sin token no se mueve una orden | `POST …/estado` sin `_token` | `403` `token_invalido`, **y la orden sigue igual** |
| No se puede saltar un estado | `POST …/estado` con `estado=entregada` sobre una pendiente | `422`, y el mensaje dice a cuál sí puede pasar |
| No existe un estado inventado | `estado=volando` | `422` |
| No se toca la orden de otro truck | Como `pruebas.produccion`, avanza una orden de Festín Rodante | `404`, idéntico a una inexistente |
| El total no lo pone el cliente | `POST /caja/vender` añadiendo `total=1` | La orden se guarda con el total real |
| No se vende lo agotado | `POST /caja/vender` con `cantidad[6]=1` | `422` |
| No se abren dos turnos | `POST /caja/turno/abrir` con uno ya abierto | `409`, y no se crea la fila |
| No se toca la parada de otro truck | Ingresa como `foodtruck` (Festín Rodante) y pide `GET /panel/ubicaciones/{id del Truck de Pruebas}` | `404`, idéntico a una inexistente |
| No se acepta un día inventado | `POST /panel/ubicaciones` con `dia_semana=9` | `422`, con el mensaje junto al campo |
| No se acepta una hora imposible | `POST /panel/ubicaciones` con `hora_inicio=25:99` | `422` |
| La parada desactivada no se publica | `GET /carta/truck-de-pruebas` | El cuerpo **no** contiene «Parada desactivada» |

---

## Si algo sale mal

| Síntoma | Causa y arreglo |
|---|---|
| `403` en todos los POST | Falta `_token`, o se gastó en el POST anterior. **Repite el GET** de la página del formulario |
| `403` con el token recién capturado | El cuerpo va como **raw/JSON**. Cámbialo a **x-www-form-urlencoded** |
| Veo HTML donde esperaba un código | Postman siguió el `302`. Desactiva **Automatically follow redirects** |
| Todo redirige a `/ingresar` | La sesión caducó (2 horas). Vuelve a ingresar |
| `GET /caja` responde `302` | No hay turno abierto. Abre uno primero |
| `GET /svp/ordenes` da `{"turno":null,…}` | No hay turno abierto en ese food truck. Ábrelo desde CAJA |
| `POST /caja/vender` da `422` | Todas las cantidades van en cero, o el producto no existe o está agotado |
| `{{ordenId}}` sale vacío | Ejecuta antes `GET /svp/ordenes`: su script es el que rellena esa variable |
| Los identificadores de producto no coinciden | Se volvió a sembrar el banco de pruebas. Míralos en la pantalla de venta, en `name="cantidad[<id>]"` |

---

## Mantener este archivo

Cada vez que se añada, se cambie o se quite una ruta en
`menu08_app/configuracion/rutas.php`, **este documento y la colección se actualizan en el mismo
cambio**: método, ruta, rol, campos exactos del cuerpo y códigos de respuesta. Los nombres de los
campos se comprueban leyendo el controlador, nunca deduciéndolos del formulario.

Si cambia el banco de pruebas, se actualizan también
[`menu08_app/basedatos/datos_pruebas.sql`](menu08_app/basedatos/datos_pruebas.sql) y la tabla de
identificadores de este documento.
