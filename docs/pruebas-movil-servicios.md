# Pruebas de los servicios del módulo móvil

Ejecutadas contra **https://adso.menu08.com** el 8 de septiembre de 2026, con `curl`, sobre el
código recién desplegado. **31 peticiones a los dos servicios, 0 fallos.**

Cubren las dos rutas que consumirá el APK y que no existían hasta ahora:

| Operación | Ruta | Issue |
|---|---|---|
| Ingreso de la aplicación móvil | `POST /movil/ingresar` | #2 |
| Reporte del punto del GPS | `POST /movil/ubicacion` | #3 |

Todo corre sobre el **Truck de Pruebas** (`food_truck_id = 4`). Festín Rodante no se toca, y por un
motivo que conviene precisar: **en producción sí tiene agenda** —ocho paradas que su dueño cargó el
5 de septiembre—, aunque el bloque `ubicaciones` de `datos_iniciales.sql` siga comentado como
PENDIENTE. Escribir sobre ellas con reportes de prueba sería ensuciar datos reales.

El reloj del servidor durante la sesión marcaba **martes 8 de septiembre, 08:09 hora de Bogotá**
(`date: Tue, 08 Sep 2026 13:09:54 GMT`, y Bogotá es UTC−5). El martes **no hay ninguna parada
sembrada**, y ése es el escenario que ejercita la rama de alta.

---

## Un intento fallido antes de empezar: 404 en las dos rutas

El primer despliegue dejó la aplicación sana pero sin las rutas nuevas:

```
/                200
/ingresar        200
/componentes     200
/svp/ordenes     401        <- la infraestructura de servicios JSON, correcta
POST /movil/ingresar    404
POST /movil/ubicacion   404
```

**Causa:** los dos paquetes se generaron **sin la carpeta envoltorio**, como manda `CLAUDE.md`, y se
extrajeron en la raíz de la cuenta, como manda el paso 2 de [`despliegue.md`](despliegue.md). Las
dos instrucciones se contradicen: sin envoltorio hay que extraer **dentro** de la carpeta destino.
Se repitió la subida extrayendo en su sitio y las rutas respondieron.

> Queda pendiente corregir esa contradicción en [`despliegue.md`](despliegue.md).

---

## 1 · Ingreso · el camino correcto

```bash
curl -s -i -c jar.txt -X POST https://adso.menu08.com/movil/ingresar \
  -d 'correo=foodtruck@menu08.local' -d 'contrasena=Menu08*Demo2026'
```

```
HTTP/2 200
set-cookie: menu08_sesion=dv9qflbqh9ot7ge2kcvnnd0r0f; path=/; secure; HttpOnly; SameSite=Lax
content-type: application/json; charset=utf-8

{"usuario":{"id":2,"nombre":"Administrador del food truck","correo":"foodtruck@menu08.local",
"rol":"food_truck","food_truck_id":1},"token_csrf":"d0dc375a4f9ede4ec0b56425b5f761eb6e2c06ce9a25d0252a08c6a155f04183"}
```

Cuatro cosas comprobadas en esa única respuesta:

- El objeto `usuario` trae **exactamente cinco claves**. La `contrasena` cifrada que sí devuelve
  `Usuario::porCorreo()` **no aparece**: el controlador copia de `Sesion::usuario()`, no de la fila.
- `content-type: application/json; charset=utf-8`, nunca HTML.
- La cookie viaja con `secure`, `HttpOnly` y `SameSite=Lax`.
- `token_csrf` viene en el cuerpo, que es la única forma de que un cliente sin navegador lo obtenga.

Los cuatro roles entran, y `plataforma` devuelve `food_truck_id` nulo, como debe:

| Cuenta | Rol | `food_truck_id` |
|---|---|---|
| `plataforma@menu08.local` | `plataforma` | `null` |
| `foodtruck@menu08.local` | `food_truck` | 1 |
| `cajero@menu08.local` | `cajero` | 1 |
| `produccion@menu08.local` | `produccion` | 1 |
| `pruebas.foodtruck@menu08.local` | `food_truck` | 4 |

## 2 · Ingreso · lo que se rechaza

| Caso | Código | Cuerpo |
|---|---|---|
| Sin `correo` | **422** | `{"error":"datos_incompletos","mensaje":"Faltan el correo o la contraseña."}` |
| `correo` vacío | **422** | ídem |
| Sin `contrasena` | **422** | ídem |
| `contrasena` vacía | **422** | ídem |
| Cuerpo totalmente vacío | **422** | ídem |
| Correo inexistente | **401** | `{"error":"credenciales_invalidas","mensaje":"Correo o contraseña incorrectos."}` |
| Contraseña equivocada | **401** | ídem |
| **Cuenta con `activo = 0`**, contraseña correcta | **401** | ídem |

### Los tres motivos son indistinguibles

El tercero necesita una cuenta desactivada, y para no tumbar ninguna de las de demostración se creó
una **cuenta de prueba ya desactivada**, copiando el hash de una existente para que su contraseña
fuese válida —lo que se quiere ejercitar es el `activo = 0`, no un fallo de contraseña—:

```sql
INSERT INTO usuarios (food_truck_id, nombre, correo, contrasena, rol, activo)
SELECT food_truck_id, 'Cuenta desactivada de prueba', 'pruebas.desactivado@menu08.local',
       contrasena, rol, 0
  FROM usuarios WHERE correo = 'cajero@menu08.local';
```

Con esa cuenta y **la contraseña correcta**, el servicio responde 401. Los tres cuerpos se
compararon con `cmp`:

```
cuenta desactivada (clave BUENA)  http=401  {"error":"credenciales_invalidas","mensaje":"Correo o contraseña incorrectos."}
correo inexistente                http=401  {"error":"credenciales_invalidas","mensaje":"Correo o contraseña incorrectos."}
contrasena equivocada             http=401  {"error":"credenciales_invalidas","mensaje":"Correo o contraseña incorrectos."}

IDENTICOS. sha256 comun: fc1273f34d343075d58ecf3e2e6b3858
```

**Idénticos byte a byte.** Por la respuesta no se puede averiguar qué cuentas existen ni cuáles
están activas. El recorrido del navegador conserva la misma propiedad: esa cuenta contra
`POST /ingresar` devuelve 401 con la vista `auth/acceso` y el mismo texto.

La fila se borró al terminar; la tabla `usuarios` vuelve a tener sus siete cuentas, todas con
`activo = 1`.

Las cinco variantes de datos incompletos responden **antes de consultar la tabla**, porque la guarda
está escrita por encima de `Usuario::porCorreo()`.

## 3 · Ubicación · las tres puertas

```
sin sesión              401  {"error":"no_autenticado","mensaje":"Debe iniciar sesion para consultar este servicio."}
con sesión, sin _token  403  {"error":"token_invalido","mensaje":"El token de seguridad expiro o no es valido. Recargue el tablero."}
```

Y el rol, que es la misma puerta estrecha que exige `UbicacionControlador` en sus cuatro acciones:

| Rol | Código |
|---|---|
| `cajero` (Festín Rodante y Truck de Pruebas) | **403** `rol_no_autorizado` |
| `produccion` | **403** `rol_no_autorizado` |
| `plataforma` | **403** `rol_no_autorizado` |
| `food_truck` | pasa |

Por el teléfono no se entra más ancho que por el panel.

## 4 · Ubicación · sin parada vigente, se registra una nueva

Martes, sin ninguna parada sembrada en ese día:

```bash
curl -s -b jar.txt -X POST https://adso.menu08.com/movil/ubicacion \
  -d 'latitud=4.6767000' -d 'longitud=-74.0483000' -d "_token=$TOK"
```

```
HTTP 201
{
    "parada": {
        "id": 18,
        "nombre": "Punto reportado 2026-09-08 08:09",
        "referencia": "Registrado desde la aplicacion movil",
        "latitud": "4.6767000",
        "longitud": "-74.0483000",
        "dia_semana": 2,
        "hora_inicio": "08:09:00",
        "hora_fin": "08:09:00",
        "activa": 1
    },
    "creada": true
}
```

`dia_semana: 2` es martes con la numeración de `Ubicacion::DIAS`, y la hora es la de Bogotá, no la
UTC de la cabecera: la zona horaria de la configuración está haciendo su trabajo.

## 5 · Ubicación · el reporte siguiente no siembra otra fila

`hora_fin` igual a `hora_inicio` deja esa parada vigente durante 24 horas por la segunda rama de
`vigente()`. El reporte siguiente cae en la rama de actualización:

```
2.º reporte  200  id 18  creada false  lat 4.7110000
3.er reporte 200  id 18  creada false  lat 4.7000000
```

**El mismo identificador las tres veces.** Y el tercero se envió con **el mismo token del ingreso,
sin releerlo**: el servicio no llama a `Csrf::rotar()`, así que la aplicación ingresa una vez y
reporta muchas.

## 6 · Ubicación · una parada del dueño solo pierde sus coordenadas

Creada desde `/panel/ubicaciones` con el recorrido del navegador, martes de 07:00 a 23:00 y
coordenadas de relleno `1.0000000, 1.0000000`. Después, un reporte del móvil:

```
HTTP 200
{
    "parada": {
        "id": 19,
        "nombre": "Parque programado por el dueno",
        "referencia": "costado sur, junto al kiosco",
        "latitud": "4.6512345",
        "longitud": "-74.0987654",
        "dia_semana": 2,
        "hora_inicio": "07:00:00",
        "hora_fin": "23:00:00",
        "activa": 1
    },
    "creada": false
}
```

**El nombre, la referencia, el día y las dos horas siguen exactamente como los dejó el dueño.** Solo
cambiaron `latitud` y `longitud`. Es la prueba de que el servicio no usa `Ubicacion::actualizar()`,
que reescribiría los siete campos del formulario del panel.

De paso queda comprobado el `ORDER BY hora_inicio, id`: había dos paradas vigentes a esa hora —la
del dueño desde las 07:00 y la reportada a las 08:09— y ganó la que abre antes.

## 7 · Ubicación · coordenadas que no valen

Con las dos paradas anteriores desactivadas y la sesión válida:

| Envío | Código | `mensaje` |
|---|---|---|
| `latitud` ausente | **422** | `La latitud es obligatoria.` |
| `longitud` vacía | **422** | `La longitud es obligatoria.` |
| `latitud=90.0000001` | **422** | `La latitud debe estar entre -90 y 90.` |
| `longitud=-180.0000001` | **422** | `La longitud debe estar entre -180 y 180.` |
| `latitud=4.71100005` | **422** | `La latitud debe ser un numero con hasta 7 decimales.` |
| `latitud=abc` | **422** | `La latitud debe ser un numero con hasta 7 decimales.` |
| `latitud=4,7110000` (coma) | **200** | se normaliza a `"4.7110000"` |

Los dos primeros son los que el formulario del panel **no** rechaza: allí la coordenada es opcional
y el vacío se guarda como `NULL`. Aquí un reporte del GPS sin punto no es un reporte, y el
controlador marca ese caso con `Validador::error()` para que la respuesta diga cuál faltó.

El último no es un fallo: el teclado en español escribe la coma decimal y `Validador::coordenada()`
la convierte antes de validar.

## 8 · Dos reportes a la vez dejan una sola parada

La prueba que justifica la transacción con `SELECT … FOR UPDATE`. Dos sesiones distintas, sin
ninguna parada vigente, y los dos reportes disparados en paralelo:

```
reporte 1  http=201  id 20  creada True
reporte 2  http=200  id 20  creada False
```

Uno creó y el otro actualizó **la misma fila**. Sin el bloqueo previo de la fila del food truck, los
dos habrían visto la tabla sin parada vigente y habrían insertado cada uno la suya.

## 9 · Comprobación contra las tablas

Las secciones anteriores leen la respuesta HTTP. Ésta lee la base de datos directamente, con el
cliente de MySQL y la conexión remota autorizada, para confirmar que lo que dijo el servicio es lo
que quedó escrito.

Las cuatro filas que tocaron las pruebas:

```
+----+----+----------------------------------+-----------+-------------+-----+-------------+----------+--------+
| id | ft | nombre                           | latitud   | longitud    | dia | hora_inicio | hora_fin | activa |
+----+----+----------------------------------+-----------+-------------+-----+-------------+----------+--------+
| 18 |  4 | Punto reportado 2026-09-08 08:09 | 4.7110000 | -74.0721000 |   2 | 08:09:00    | 08:09:00 |      0 |
| 19 |  4 | Parque programado por el dueno   | 4.6512345 | -74.0987654 |   2 | 07:00:00    | 23:00:00 |      0 |
| 20 |  4 | Punto reportado 2026-09-08 08:12 | 4.6000001 | -74.0100001 |   2 | 08:12:00    | 08:12:00 |      0 |
| 21 |  4 | Punto reportado 2026-09-08 08:28 | 4.6000000 | -74.0200000 |   2 | 08:28:00    | 08:28:00 |      0 |
+----+----+----------------------------------+-----------+-------------+-----+-------------+----------+--------+
```

Cinco cosas que la tabla confirma y que la respuesta HTTP solo insinuaba:

- **La parada 19 conserva lo del dueño.** `nombre`, `referencia`, `dia_semana` y las dos horas
  —07:00 a 23:00— siguen como se crearon desde el panel. Solo cambiaron las coordenadas: el
  `UPDATE` del servicio toca dos columnas y ninguna más.
- **La 18 acumuló cuatro reportes en una sola fila.** Su latitud final, `4.7110000`, es la del
  último envío, el de la coma decimal ya normalizada. No hay ninguna fila hermana.
- **La 20 salió de los dos reportes simultáneos** y su latitud es `4.6000001`, la del segundo: el
  primero creó la fila y el segundo la actualizó. **Una sola fila**, que es lo que el `FOR UPDATE`
  tenía que garantizar.
- **Las cuatro quedaron en `activa = 0`.** La limpieza fue completa.
- **Las cuatro son del `food_truck_id = 4`.** Ninguna del 1.

Ese último punto, comprobado al revés —buscando en toda la tabla las filas que escribió el móvil:

```sql
SELECT id, food_truck_id, nombre, creado_en FROM ubicaciones
 WHERE referencia = 'Registrado desde la aplicacion movil' OR nombre LIKE 'Punto reportado%';
```

```
| 18 |  4 | Punto reportado 2026-09-08 08:09 | 2026-09-08 08:09:56 |
| 20 |  4 | Punto reportado 2026-09-08 08:12 | 2026-09-08 08:12:15 |
| 21 |  4 | Punto reportado 2026-09-08 08:28 | 2026-09-08 08:28:26 |
```

**Las tres en el truck de la sesión.** La 21 se envió con `food_truck_id=1` en el cuerpo, intentando
escribir en Festín Rodante, y aun así quedó en el 4: el identificador sale de la sesión y lo que
diga la petición se descarta. Es el invariante de aislamiento entre food trucks, comprobado sobre la
tabla y no sobre la respuesta.

Estado final del banco:

```
| ft | truck            | paradas | activas |
|  1 | Festin Rodante   |       8 |       8 |   <- intactas
|  4 | Truck de Pruebas |      10 |       3 |   <- las tres sembradas
```

## 10 · La bitácora del servidor

Bajada de `menu08_app/almacenamiento/bitacora/` al terminar la sesión. **Se queda fuera del
repositorio**: lleva rutas internas del hosting y los correos de quien intentó entrar, y `.gitignore`
cubre `*.log`.

```
[2026-09-08 08:05:07] AVISO: Menu08\Nucleo\RutaNoEncontrada: No hay ruta registrada para POST /movil/ingresar en …/nucleo/Enrutador.php:81
[2026-09-08 08:05:27] AVISO: Menu08\Nucleo\RutaNoEncontrada: No hay ruta registrada para POST /movil/ubicacion en …/nucleo/Enrutador.php:81
[2026-09-08 08:09:24] AVISO: Ingreso movil fallido para el correo "nadie@menu08.local"
[2026-09-08 08:09:24] AVISO: Ingreso movil fallido para el correo "foodtruck@menu08.local"
[2026-09-08 08:09:55] AVISO: Token CSRF invalido en POST /movil/ubicacion
[2026-09-08 09:36:41] AVISO: Ingreso fallido para el correo "foodtruck@menu08.local"
```

Tres cosas quedan probadas:

- **Ninguna contraseña se escribe.** Buscadas explícitamente en el archivo la clave de demostración
  y las cadenas equivocadas que se enviaron: **cero coincidencias**. El apunte guarda el correo,
  que es lo que sirve para investigar, y nada más.
- **Los dos caminos se distinguen.** `Ingreso movil fallido` sale del servicio nuevo y
  `Ingreso fallido` de `AutenticacionControlador::ingresar()`, el del navegador. Dos entradas del
  primero —el correo inexistente y la contraseña equivocada— y una del segundo, la comprobación de
  que el recorrido del panel sigue intacto. Ante un incidente se sabe por dónde entró el intento.
- **El rechazo por token también deja rastro**, sin cuerpo de la petición.

Las dos primeras líneas son del despliegue fallido que abre este documento: el enrutador no conocía
las rutas y las registró como `RutaNoEncontrada`. Confirma la causa que se dio allí.

---

## Lo que estas pruebas no cubren

- **El 500 `fallo_interno`.** No se provocó ningún fallo no previsto, así que la salida en JSON del
  manejador de errores para estos dos servicios queda sin ejercitar. Se decidió no forzarlo: romper
  algo a propósito en producción no compensa, y el mecanismo es el mismo que ya usa el SVP.
- **Las dos ramas nocturnas de `vigenteBloqueada()`.** La sesión fue un martes por la mañana y
  ninguna parada que cruce la medianoche estaba en su franja: ni Zona Rosa de Pruebas —sábado de
  18:00 a 01:00— ni las dos de Festín Rodante que cierran a las 02:00. Las tres ramas están
  copiadas de `Ubicacion::vigente()`, que sí tiene su comprobación
  en [`pruebas-agenda-paradas.md`](pruebas-agenda-paradas.md), pero **la copia bloqueante no se ha
  ejercitado de noche**. Es lo primero que hay que probar en la próxima sesión nocturna.
- **El token vencido.** Se probó el token ausente, no uno caducado por los 120 minutos de vida.
- **El error `food_truck_invalido`.** Inalcanzable desde fuera: el `food_truck_id` sale de la sesión
  y la clave foránea garantiza que existe. Está en el código como red, no como caso probable.
- **`php -l`.** Los tres archivos nunca pasaron por el analizador de sintaxis: no hay PHP en la
  máquina donde se escribieron. Que el servicio responda es la única evidencia de que compilan.
- **Festín Rodante.** No se usó en ninguna prueba, a propósito: sus ocho paradas de producción son
  datos reales de su dueño. Queda sin ejercitar el servicio sobre el food truck que de verdad lo va
  a usar; lo que sí quedó comprobado es que **ningún** reporte de estas pruebas llegó a su agenda.

## Lo que estas pruebas dejaron en el banco

Tres paradas creadas aquí, **las tres desactivadas** al terminar:

| id | Punto | Por qué se creó |
|---|---|---|
| 18 | Punto reportado 2026-09-08 08:09 | la rama de alta sin parada vigente |
| 19 | Parque programado por el dueno | la rama de actualización sobre una parada del dueño |
| 20 | Punto reportado 2026-09-08 08:2x | los dos reportes simultáneos |

Comprobado al cerrar: las únicas paradas **activas** del Truck de Pruebas vuelven a ser las tres
sembradas —Parque de Pruebas el miércoles, Plaza de Pruebas el viernes y Zona Rosa de Pruebas el
sábado—. Para dejarlo todo como estaba se vuelve a ejecutar
[`menu08_app/basedatos/datos_pruebas.sql`](../menu08_app/basedatos/datos_pruebas.sql); **al hacerlo
cambian los identificadores**.
