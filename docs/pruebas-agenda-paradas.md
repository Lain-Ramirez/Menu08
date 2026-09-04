# Pruebas de la agenda de paradas

Ejecutadas contra **https://adso.menu08.com** el 4 de septiembre de 2026, con `curl` y la sesión
real del panel. **55 comprobaciones, 0 fallos.**

Todo corre sobre el **Truck de Pruebas** (`food_truck_id = 4`), que es el que tiene agenda sembrada.
Festín Rodante no tiene ninguna parada: su agenda es un bloque PENDIENTE en `datos_iniciales.sql`,
igual que su carta, y no se inventa.

El banco de partida son las cuatro filas de `datos_pruebas.sql`, que en el servidor tienen los
identificadores **2, 3, 4 y 5**:

| id | Día | Punto | Horario | Estado |
|---|---|---|---|---|
| 5 | Lunes | Parada desactivada | 09:00 → 13:00 | **inactiva** |
| 2 | Miércoles | Parque de Pruebas | 11:00 → 15:00 | activa |
| 3 | Viernes | Plaza de Pruebas | 12:00 → 20:00 | activa |
| 4 | Sábado | Zona Rosa de Pruebas | **18:00 → 01:00** | activa |

## Cómo se entra

```bash
TOK=$(curl -s -c jar.txt https://adso.menu08.com/ingresar \
  | grep -oE 'name="_token" value="[a-f0-9]+"' | grep -oE '[a-f0-9]{32,}')

curl -s -b jar.txt -c jar.txt -d "correo=pruebas.foodtruck@menu08.local" \
  -d "contrasena=Menu08*Demo2026" -d "_token=$TOK" https://adso.menu08.com/ingresar
# -> 302
```

Los POST del panel rotan el token, así que **entre uno y el siguiente hay que releerlo** con un GET
a `/panel/ubicaciones`. Todos los envíos de abajo lo hacen.

---

## 1 · El reloj, que es de lo que depende todo lo demás

El primer `GET /panel/ubicaciones` de la sesión salió con esta cabecera y esta respuesta:

```
date: Fri, 04 Sep 2026 12:00:31 GMT
-> "No hay ninguna parada vigente ahora mismo."
```

Las 12:00:31 GMT son las **07:00:31 en Bogotá**, y la parada del viernes abre a las 12:00. Que no
haya vigente es la respuesta correcta, y además **discrimina**: si la sesión de MySQL hubiera
quedado en UTC, `NOW()` habría dicho viernes 12:00:31 y «Plaza de Pruebas» habría salido vigente.

| Comprobación | Resultado |
|---|---|
| A las 07:00 de Bogotá no hay parada abierta | **correcto**, ninguna vigente |
| El `SET time_zone` de `ConexionBD` está surtiendo efecto | **confirmado** por el caso anterior |

## 2 · La jornada que cruza la medianoche

Es el criterio que define el issue. `2026-09-05` es sábado y `2026-09-06` domingo, así que la fila
en negrita es literalmente el enunciado: *una parada declarada de 18:00 a 01:00, consultada a las
00:30, se devuelve como vigente*.

```bash
curl -s -b jar.txt "https://adso.menu08.com/panel/ubicaciones?momento=2026-09-06T00:30"
```

| Momento consultado | Respuesta real | |
|---|---|---|
| viernes 13:00 | Plaza de Pruebas · Viernes de 12:00 a 20:00 | ✔ |
| sábado 17:59 | ninguna vigente | ✔ |
| sábado 18:00 | Zona Rosa de Pruebas · de 18:00 a 01:00 (cierra al dia siguiente) | ✔ |
| sábado 23:30 | Zona Rosa de Pruebas | ✔ |
| **domingo 00:30** | **Zona Rosa de Pruebas** | ✔ **el criterio** |
| domingo 00:59 | Zona Rosa de Pruebas | ✔ |
| domingo 01:00 | ninguna vigente | ✔ cierre exclusivo |
| domingo 02:00 | ninguna vigente | ✔ |
| miércoles 14:59 | Parque de Pruebas · Miercoles de 11:00 a 15:00 | ✔ |
| miércoles 15:00 | ninguna vigente | ✔ cierre exclusivo |
| jueves 00:30 | ninguna vigente | ✔ una jornada diurna no se prolonga |
| lunes 10:00 | ninguna vigente | ✔ esa parada está desactivada |

La última fila vale doble: la parada del lunes existe y su franja contiene las 10:00, pero está
`activa = 0` y por eso no sale. Es la mitad del criterio 4, medida sobre `vigente()`.

### El envolvimiento del domingo al lunes

No lo pide el issue y es el caso más difícil, porque el día anterior al 1 es el 7. Se creó para
comprobarlo una parada del **domingo de 20:00 a 02:00** (id 6):

| Momento consultado | Respuesta real | |
|---|---|---|
| domingo 19:59 | ninguna vigente | ✔ |
| domingo 20:00 | Parada nocturna del domingo · de 20:00 a 02:00 | ✔ |
| domingo 23:30 | Parada nocturna del domingo | ✔ |
| **lunes 01:00** | **Parada nocturna del domingo** | ✔ **7 → 1 resuelto** |
| lunes 01:59 | Parada nocturna del domingo | ✔ |
| lunes 02:00 | ninguna vigente | ✔ |

Sale de `WEEKDAY(ahora.m - INTERVAL 1 DAY) + 1`: el día anterior lo calcula la aritmética de fechas
de MySQL, no una resta sobre el número del día, y por eso el paso de 1 a 7 no necesita ningún caso
especial.

## 3 · El formulario rechaza lo que no vale

Ocho envíos, cada uno con token recién leído. Los mensajes son los que salen del `<span
class="error-campo">` junto al control, tal cual:

| Envío | Código | Mensaje |
|---|---|---|
| `dia_semana=0` | **422** | El dia debe ir de 1 (lunes) a 7 (domingo). |
| `dia_semana=8` | **422** | El dia debe ir de 1 (lunes) a 7 (domingo). |
| `dia_semana=lunes` | **422** | El dia de la semana es obligatorio. |
| `hora_inicio=25:99` | **422** | La hora de inicio debe tener el formato HH:MM, de 00:00 a 23:59. |
| `hora_inicio=18` | **422** | La hora de inicio debe tener el formato HH:MM, de 00:00 a 23:59. |
| `hora_fin=` vacía | **422** | La hora de fin es obligatoria. |
| `latitud=200` | **422** | La latitud debe estar entre -90 y 90. |
| `nombre=` vacío | **422** | El punto es obligatorio. |

`hora_inicio=18:00` con `hora_fin=01:00` **no** se rechaza: responde 302 y guarda. Es el horario
normal de un truck nocturno, no un error de captura, y es la comprobación de la sección 5.

## 4 · El token, y que la base no se toca sin él

| Comprobación | Resultado |
|---|---|
| Paradas antes de empezar | **4** |
| `POST /panel/ubicaciones` sin `_token` | **403** |
| `POST /panel/ubicaciones` con un `_token` inventado de 64 ceros | **403** |
| `POST /panel/ubicaciones/estado` sin `_token` | **403** |
| Paradas después de los tres intentos | **4 — la base no cambió** |

El `AccesoDenegado` se lanza en `verificarCsrf()`, antes de la primera sentencia: el conteo idéntico
antes y después es la evidencia de que no llegó a escribirse nada.

## 5 · Una parada de otro food truck no existe

Se hace desde la sesión de **Festín Rodante** pidiendo paradas del Truck de Pruebas, porque es el
único sentido posible: Festín Rodante no tiene ninguna.

| Petición | Código |
|---|---|
| `GET /panel/ubicaciones/99999` (no existe) | **404** |
| `GET /panel/ubicaciones/2` (ajena) | **404** |
| `GET /panel/ubicaciones/3` (ajena) | **404** |
| `GET /panel/ubicaciones/4` (ajena) | **404** |
| `GET /panel/ubicaciones/5` (ajena) | **404** |
| `POST /panel/ubicaciones/estado` con `id=5` y token válido de esa sesión | **404** |

Una parada ajena y un identificador inexistente responden **exactamente lo mismo**. Es deliberado:
un 403 confirmaría que ese identificador existe.

## 6 · Alta, edición y baja lógica

| Acción | Resultado |
|---|---|
| Crear la parada del domingo 20:00 → 02:00 | **302**, queda con id **6** |
| Editar su referencia | **302** |
| La edición se ve en el listado | **sí** |
| Desactivarla (`/estado`) | **302** |
| Volver a activarla (el mismo POST alterna) | **302** |
| Desactivada, deja de ser vigente el lunes a la 01:00 | **confirmado** |

## 7 · La baja lógica en la carta pública

Sin sesión, `GET /carta/truck-de-pruebas` → **200**, con el bloque «Donde estamos» una vez.

| Parada | Estado | Apariciones en la carta |
|---|---|---|
| Parque de Pruebas | activa | **1** |
| Plaza de Pruebas | activa | **1** |
| Zona Rosa de Pruebas | activa | **1** |
| Parada desactivada | inactiva | **0** |
| Parada nocturna del domingo | activa → se desactiva | **1 → 0** |

La quinta fila es la prueba en movimiento: la misma parada aparece mientras está activa y desaparece
de la carta en cuanto se desactiva desde el panel, sin tocar nada más.

## 8 · Nada de lo que llega por la petición se interpola

| Intento | Resultado |
|---|---|
| `?momento=' OR 1=1 --` | **200**, la página normal con la parada de ahora |
| `?momento=2026-09-06T00:30' UNION SELECT 1--` | **200**, sin filas de más |
| `nombre=Parada'); DROP TABLE ubicaciones;--` | **302**: se guarda como **texto** |
| La tabla sigue viva después | **sí**, las cuatro paradas siguen ahí |
| Ese nombre se ve escapado en el listado | **sí**, impreso, no ejecutado |

Y en el código, `grep -nE '\$_(GET\|POST\|REQUEST\|COOKIE)' menu08_app/aplicacion/modelos/Ubicacion.php`
no devuelve **ninguna coincidencia**: el modelo no lee la petición, recibe valores ya validados y
cada uno viaja como marcador de una sentencia preparada.

---

## Lo que estas pruebas no cubren

- **Paradas solapadas.** Nada impide declarar dos paradas del mismo día con franjas que se pisan.
  `vigente()` devuelve una sola y de forma determinista (`ORDER BY hora_inicio, id`), pero no se
  probó el caso ni hay criterio de negocio sobre cuál debería ganar.
- **El horario de verano.** El desplazamiento se calcula en cada conexión, así que una zona con
  cambio de hora saldría bien, pero no se ha probado: Colombia no tiene.
- **Franjas de más de 24 horas.** La columna es `TIME` y admite hasta 838 horas. El validador acota
  a 00:00–23:59, pero una fila cargada a mano por SQL con `60:00:00` no la contemplaría la consulta,
  que solo mira el día de hoy y el de ayer.
- **La agenda vacía.** Festín Rodante no tiene paradas, así que su carta no muestra el bloque. Es
  correcto, pero no se ha probado con paradas reales suyas.
- **El aspecto.** Este issue deja las dos pantallas funcionando, sin hoja de estilos propia. El
  maquetado —la agrupación por día, la validación en el navegador y los 320 px sin desplazamiento
  horizontal— es el issue de Frontend de CARTA.

## Lo que estas pruebas dejaron en el banco

Dos paradas creadas aquí, **ambas desactivadas** al terminar, para que no ensucien la carta pública:

| id | Punto | Por qué se creó |
|---|---|---|
| 6 | Parada nocturna del domingo | el envolvimiento 7 → 1 |
| 7 | `Parada'); DROP TABLE ubicaciones;--` | la prueba de inyección |

Las cuatro originales quedaron intactas. Para dejarlo todo como estaba se vuelve a ejecutar
[`menu08_app/basedatos/datos_pruebas.sql`](../menu08_app/basedatos/datos_pruebas.sql), que borra su
propio truck y lo rehace sin tocar a los demás; **al hacerlo cambian los identificadores**.
