# Comprobación de la agenda de paradas

> **Estado: guion preparado, sin ejecutar todavía.** El código está en el árbol de trabajo pero
> aún no se ha publicado en `adso.menu08.com`. Este archivo se convierte en evidencia cuando el
> desarrollador suba el ZIP y se rellenen las columnas de resultado con la **salida real**. No se
> anota ningún resultado antes de obtenerlo.

Todo se ejecuta contra **https://adso.menu08.com** y sobre el **Truck de Pruebas**, que es el que
tiene agenda sembrada. Festín Rodante no tiene paradas a propósito: su agenda la entrega su dueño,
igual que su carta.

## El banco de pruebas

`menu08_app/basedatos/datos_pruebas.sql` deja cuatro paradas, cada una para algo:

| Día | Punto | Horario | Estado | Qué demuestra |
|---|---|---|---|---|
| Miércoles (3) | Parque de Pruebas | 11:00 → 15:00 | activa | jornada normal |
| Viernes (5) | Plaza de Pruebas | 12:00 → 20:00 | activa | jornada larga |
| **Sábado (6)** | **Zona Rosa de Pruebas** | **18:00 → 01:00** | activa | **cruza la medianoche** |
| Lunes (1) | Parada desactivada | 09:00 → 13:00 | **inactiva** | baja lógica |

## Cómo entrar

El formulario lleva el token en un campo oculto, así que hay que pedirlo antes de cada POST:

```bash
TOK=$(curl -s -c j.txt https://adso.menu08.com/ingresar \
  | grep -oE 'name="_token" value="[a-f0-9]+"' | grep -oE '[a-f0-9]{32,}')

curl -s -b j.txt -c j.txt \
  -d "correo=pruebas.foodtruck@menu08.local" \
  -d "contrasena=…" -d "_token=$TOK" \
  https://adso.menu08.com/ingresar
```

Los POST del panel rotan el token (`Csrf::rotar()`), de modo que **entre una alta y la siguiente
hay que releerlo**. Es lo mismo que ya documenta `POSTMAN.md` para categorías y productos.

---

## 1 · La jornada que cruza la medianoche

Es el criterio que define este issue: *una parada declarada de 18:00 a 01:00, consultada a las
00:30, se devuelve como vigente*.

La consulta de `Ubicacion::vigente()` tiene tres ramas, y la tercera es la que resuelve el caso:

```sql
-- Jornada normal: 11:00 -> 15:00 del mismo dia.
(u.hora_fin > u.hora_inicio
 AND u.dia_semana = WEEKDAY(ahora.m) + 1
 AND TIME(ahora.m) >= u.hora_inicio AND TIME(ahora.m) < u.hora_fin)

-- Nocturna, antes de medianoche: 18:00 -> 01:00 consultada a las 23:00.
OR (u.hora_fin <= u.hora_inicio
    AND u.dia_semana = WEEKDAY(ahora.m) + 1
    AND TIME(ahora.m) >= u.hora_inicio)

-- La misma jornada ya pasada la medianoche: a las 00:30 sigue abierta,
-- pero la parada esta declarada en el dia ANTERIOR.
OR (u.hora_fin <= u.hora_inicio
    AND u.dia_semana = WEEKDAY(ahora.m - INTERVAL 1 DAY) + 1
    AND TIME(ahora.m) < u.hora_fin)
```

El día anterior sale de `- INTERVAL 1 DAY`, no de restarle uno al número del día: así el paso del
domingo al lunes lo resuelve la aritmética de fechas de MySQL, sin ningún caso especial.

### 1a · Con el instante inyectado

`GET /panel/ubicaciones` admite `?momento=`, que es de solo lectura y sigue filtrando por el food
truck de la sesión. `2026-09-06` es domingo, así que estas dos peticiones son literalmente el caso
del criterio:

```bash
curl -s -b j.txt "https://adso.menu08.com/panel/ubicaciones?momento=2026-09-06T00:30" | grep -A4 "Donde estamos"
curl -s -b j.txt "https://adso.menu08.com/panel/ubicaciones?momento=2026-09-06T01:30" | grep -A4 "Donde estamos"
```

| Instante | `dia_semana` de hoy / de ayer | Esperado | Resultado |
|---|---|---|---|
| sábado 17:59 | 6 / 5 | no vigente | *pendiente* |
| sábado 18:00 | 6 / 5 | **vigente** | *pendiente* |
| sábado 23:30 | 6 / 5 | **vigente** | *pendiente* |
| **domingo 00:30** | 7 / **6** | **vigente** ← el criterio | *pendiente* |
| domingo 01:00 | 7 / 6 | no vigente (cierre exclusivo) | *pendiente* |
| domingo 02:00 | 7 / 6 | no vigente | *pendiente* |

### 1b · Con el reloj real, sin fiarse del parámetro

La prueba anterior demuestra la consulta, pero pasa por un parámetro. Para ejercitar la misma rama
con el reloj del servidor se crea una parada declarada **ayer**, cuya `hora_fin` sea posterior a la
hora actual y su `hora_inicio` también:

> Si hoy es viernes a las 14:20 → parada del **jueves**, de **20:00 a 15:00**.
> `hora_fin <= hora_inicio`, luego cruza la medianoche; y 14:20 < 15:00, luego sigue abierta.

Debe salir vigente ahora mismo, sin `?momento=`. Y una parada gemela de **20:00 a 13:00** no, por
haber cerrado ya.

**Cuidado con la receta relativa**: la fórmula «inicio = ahora + 2 h, fin = ahora + 1 h» falla a
partir de las 22:00, porque el `+2 h` envuelve la medianoche y la relación entre las dos horas se
invierte. Los valores de arriba son absolutos justamente por eso.

## 2 · El rechazo del formulario

Con token fresco en `$TOK`:

| Envío | Esperado | Resultado |
|---|---|---|
| `dia_semana=0` | `422` y «El dia debe ir de 1 (lunes) a 7 (domingo).» junto al campo | *pendiente* |
| `dia_semana=8` | `422`, mismo mensaje | *pendiente* |
| `dia_semana=lunes` | `422` y «El dia de la semana es obligatorio.» | *pendiente* |
| `hora_inicio=25:99` | `422` y «La hora de inicio debe tener el formato HH:MM, de 00:00 a 23:59.» | *pendiente* |
| `hora_inicio=18` | `422`, mismo mensaje | *pendiente* |
| `hora_fin=` vacía | `422` y «La hora de fin es obligatoria.» | *pendiente* |
| `latitud=200` | `422` y «La latitud debe estar entre -90 y 90.» | *pendiente* |
| `hora_inicio=18:00`, `hora_fin=01:00` | **`302`**: cruza la medianoche y es correcto | *pendiente* |

## 3 · El token y el aislamiento entre food trucks

| Comprobación | Cómo | Esperado | Resultado |
|---|---|---|---|
| Sin token no se crea nada | `POST /panel/ubicaciones` sin `_token`, y volver a `GET /panel/ubicaciones` | `403` **y el listado con las mismas filas que antes** | *pendiente* |
| Sin token no se desactiva nada | `POST /panel/ubicaciones/estado` sin `_token` | `403`, y la parada sigue activa | *pendiente* |
| Una parada ajena no existe | `GET /panel/ubicaciones/{id de Festín Rodante}` con la sesión de pruebas | **`404`**, idéntico a un id inexistente | *pendiente* |
| Tampoco al guardarla | `POST /panel/ubicaciones` con ese `id` y token válido | `404`, **antes** de escribir | *pendiente* |

El 404 y no el 403 es deliberado: un 403 confirmaría que ese identificador existe.

## 4 · La baja lógica en la carta pública

```bash
curl -s https://adso.menu08.com/carta/truck-de-pruebas | grep -c "Parada desactivada"
```

| Comprobación | Esperado | Resultado |
|---|---|---|
| La parada inactiva no sale en la agenda de la carta | `0` coincidencias | *pendiente* |
| La parada inactiva nunca es la vigente | ausente con cualquier `?momento=` del lunes 09:00–13:00 | *pendiente* |
| Las tres activas sí salen | las tres, agrupadas por día | *pendiente* |

## 5 · Las sentencias van preparadas

```bash
grep -nE '\$_(GET|POST)' menu08_app/aplicacion/modelos/Ubicacion.php
```

Esperado: **sin coincidencias**. El modelo no lee la petición; recibe valores ya validados desde el
controlador, y cada uno viaja como marcador de una sentencia preparada.

---

## Lo que estas pruebas no cubren

- **Paradas solapadas.** Nada impide declarar dos paradas del mismo día con franjas que se pisan.
  `vigente()` devuelve una sola, de forma determinista (`ORDER BY hora_inicio, id`), pero no avisa
  del solapamiento ni hay criterio de negocio sobre cuál debería ganar.
- **El reloj del servidor.** `ConexionBD` fija ahora la zona horaria de la sesión de MySQL a partir
  de la clave `zona_horaria`, pero eso no se ha verificado contra el hosting: hay que comprobar en
  producción que `SELECT NOW()` responde la hora de Bogotá y no la del sistema.
- **La franja máxima.** La columna es `TIME` y admite hasta 838 horas. El validador acota a
  00:00–23:59, pero una fila cargada a mano por SQL con `60:00:00` no la contempla la consulta, que
  solo mira el día de hoy y el de ayer.
- **La agenda vacía.** Festín Rodante no tiene ninguna parada, así que su carta no muestra el
  bloque. Es correcto, pero no se ha probado con paradas reales suyas.
- **El aspecto.** Este issue deja las dos pantallas funcionando, sin hoja de estilos propia. El
  maquetado —la agrupación por día, la validación en el navegador y los 320 px sin desplazamiento
  horizontal— es el issue de Frontend de CARTA.
