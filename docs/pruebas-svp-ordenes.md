# Pruebas del servicio JSON de órdenes en curso

Ejecutadas contra **https://adso.menu08.com** y contra la base de datos del prototipo.
**37 comprobaciones, 0 fallos**, más un defecto de contrato encontrado, corregido y verificado
en el servidor.

## Control de acceso

Cuatro roles y dos sesiones inválidas contra `GET /svp/ordenes`. Ninguna respuesta trajo una sola
etiqueta HTML:

| Quién pide | Código | Cuerpo |
|---|---|---|
| Sin sesión | **401** | `{"error":"no_autenticado","mensaje":"Debe iniciar sesion para consultar este servicio."}` |
| Cookie de sesión inventada | **401** | el mismo objeto |
| Rol `cajero` | **403** | `{"error":"rol_no_autorizado","mensaje":"El rol \"cajero\" no tiene acceso a este servicio."}` |
| Rol `plataforma` | **403** | el mismo objeto con `"plataforma"` |
| Rol `produccion` | **200** | el tablero |
| Rol `food_truck` | **200** | el tablero |

Las seis con `Content-Type: application/json; charset=utf-8`.

El rol `plataforma` merece una nota: no está atado a ningún food truck (`food_truck_id` en `NULL`),
así que si llegara a pasar el control de rol, la consulta no sabría de qué truck traer las órdenes.
Queda cortado antes, en el **403**, que es lo correcto: la plataforma administra trucks, no cocina
en ninguno.

## Cabeceras de la respuesta

```
cache-control: no-store, no-cache, must-revalidate
pragma: no-cache
content-type: application/json; charset=utf-8
x-content-type-options: nosniff
```

`no-store` importa más de lo que parece en este servicio: el tablero sondea sobre datos móviles y
un proxy intermedio que cacheara la respuesta dejaría la pantalla congelada mostrando órdenes ya
despachadas.

## Método y ruta equivocados

| Petición | Resultado |
|---|---|
| `POST /svp/ordenes` | **404** en HTML |
| `GET /svp/ordenes/9` | **404** en HTML |

No coinciden con ninguna ruta registrada y caen en el 404 general del sitio. Queda anotado en el
contrato: la promesa de responder siempre JSON vale para `GET` sobre la ruta exacta, que es lo
único que hace el tablero.

## Carga real: veinte órdenes

Se registraron **20 órdenes** desde CAJA en el turno **#3** —`T3-001` a `T3-020`, 50 unidades en
total—, con cantidades entre 1 y 4, los tres medios de pago alternados y nota para producción en
6 de ellas.

Un detalle del camino: la primera tanda falló con **403** en 19 de 20 envíos. La causa no era un
defecto sino lo contrario, `Csrf::rotar()` haciendo su trabajo —el token se descarta tras cada
venta para que reenviar el formulario no duplique la orden—, así que hubo que pedir uno fresco
antes de cada venta. Es exactamente lo que hace un navegador.

## Estructura de la respuesta

Validada con `json.tool` sobre las 20 órdenes:

| Comprobación | Resultado |
|---|---|
| `ordenes` es una lista, no un objeto con claves numéricas | sí |
| `total` coincide con la cantidad de elementos | 20 = 20 |
| `turno` trae el identificador del turno abierto | 3 |
| Campos por orden | `id, numero, estado, estado_nombre, minutos, demorada, nota, items` en las 20 |
| Tipos | `int, str, str, str, int, bool, null\|str, list` |
| `nota` | `null` en 14, texto en 6 |
| `items[]` | todos con `nombre` y `cantidad` |
| Estados presentes | solo `pendiente` |

Ejemplo literal de la primera orden:

```json
{"id": 3, "numero": "T3-001", "estado": "pendiente", "estado_nombre": "Pendiente",
 "minutos": 0, "demorada": false, "nota": null,
 "items": [{"nombre": "Maduritos", "cantidad": 2}]}
```

## Tiempo de respuesta

Cinco peticiones seguidas con las 20 órdenes activas, medidas con `curl -w "%{time_total}"` desde
fuera del hosting, así que **incluyen la latencia de internet**:

| | 1 | 2 | 3 | 4 | 5 | Mediana |
|---|---|---|---|---|---|---|
| Segundos | 0,458 | 0,792 | 0,582 | 0,516 | 0,486 | **0,516** |

Las cinco por debajo del segundo que pide el criterio. El cuerpo pesa **3.408 bytes**, un tamaño
sano para un sondeo continuo sobre datos móviles.

## Defecto encontrado y corregido

**Con el turno #3 abierto y todavía sin órdenes**, el servicio respondía:

```json
{"turno": null, "minutos_demora": 10, "total": 0, "ordenes": []}
```

Es decir, exactamente lo mismo que cuando no hay ningún turno abierto, que es como el propio
contrato define ese `null`. El tablero no podía distinguir **«ventanilla abierta, producción al
día»** de **«el truck no está vendiendo»**, que son dos pantallas distintas.

La causa: la consulta arrancaba en `ordenes`, y el identificador del turno se sacaba de la primera
fila devuelta. Sin órdenes no había filas, y sin filas no había de dónde sacar el turno, así que
el método salía por un atajo devolviendo `null`.

**Corregido:** la consulta arranca ahora en `turnos_caja` y baja a `ordenes` con un `LEFT JOIN`.
Un turno abierto devuelve una fila aunque esté vacío, y el turno se informa igual. Sigue
resolviéndose con dos sentencias preparadas como máximo; de hecho, con el turno vacío la segunda
ni se prepara.

**Comprobado ya desplegado.** Con el turno #3 abierto, se marcaron sus 20 órdenes como
`entregada` para provocar el caso, y el servicio respondió:

```json
{"turno":3,"minutos_demora":10,"total":0,"ordenes":[]}
```

El `turno` viaja informado con la lista vacía, que es justo lo que antes no ocurría. Las 20
órdenes se devolvieron a su estado original en el mismo guion, dentro de un `finally`, y el
tablero volvió a mostrar `turno 3, 20 órdenes`. De paso queda confirmado sobre el servicio en
vivo que **una orden entregada no aparece en el tablero**.

## El SQL, ejecutado contra la base

Las pruebas por HTTPS no alcanzan los dos casos que dependen del estado de la base: el turno
abierto y vacío, y el turno cerrado. Provocarlos desde CAJA obligaría a entregar veinte órdenes y
a cuadrar un turno, y dejaría la base sucia. Se comprobaron conectando directamente a MySQL.

El guion **no retipea las consultas**: las extrae del propio `Orden.php` —el `$turnoVigente`, el
`$enCurso` y los dos `prepare()`— y las interpola igual que PHP, para probar exactamente lo que se
despliega. Los dos escenarios que cambian datos corren dentro de una transacción que termina en
`ROLLBACK`, y después se vuelve a consultar para comprobar que la base quedó como estaba.

| Escenario | Comprobación | Resultado |
|---|---|---|
| Turno abierto con órdenes | 20 filas, todas con `orden_id`, un solo `turno_id` (3) | pasa |
| | Solo estados en curso; `minutos` entero ≥ 0 | pasa |
| | La consulta de ítems resuelve y todo ítem pertenece a una orden devuelta | pasa |
| Sin turno abierto | Cero filas | pasa |
| **Turno abierto y vacío** | **Devuelve una fila, con `orden_id` en `NULL`** | **pasa** |
| | **Esa fila trae el `turno_id`, que es lo que corrige el defecto** | **pasa** |
| Turno cerrado | Cero filas | pasa |
| Reversión | Los dos `ROLLBACK` dejaron las 20 órdenes y el turno como estaban | pasa |

Las quince comprobaciones pasaron. El caso del turno vacío se provocó marcando las veinte órdenes
como `entregada` dentro de la transacción, así que de paso confirma que **una orden entregada
queda fuera del tablero**, que era el único punto del filtro de estados sin verificar sobre datos
reales.

## Lo que estas pruebas no cubren todavía

- **Los estados `en_preparacion` y `lista`** y **el umbral de demora** quedaron fuera de estas
  pruebas porque todavía no existía la ruta que avanza el estado de una orden. Los cubre
  [`pruebas-svp-estado.md`](pruebas-svp-estado.md), donde una orden recorre los cuatro estados y
  otra aparece en el tablero con `minutos: 110` y `demorada: true`.
- **Varios turnos abiertos a la vez.** La consulta se queda con el más reciente
  (`ORDER BY id DESC LIMIT 1`). CAJA lo impide con un `SELECT … FOR UPDATE` al abrir, así que el
  caso no se pudo provocar sin forzar la base a un estado que la aplicación no permite.
- **Qué debe ver producción cuando el cajero cierra el turno.** Hoy las órdenes en curso
  desaparecen del tablero en el acto, porque la consulta las ata al turno abierto. Con comida aún
  en la plancha, es una decisión de producto a confirmar antes de construir el tablero.
