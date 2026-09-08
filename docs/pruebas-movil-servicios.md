# Pruebas de los servicios del módulo móvil

Ejecutadas contra **https://adso.menu08.com** el 8 de septiembre de 2026, con `curl`, sobre el
código recién desplegado. **31 peticiones a los dos servicios, 0 fallos.**

Cubren las dos rutas que consumirá el APK y que no existían hasta ahora:

| Operación | Ruta | Issue |
|---|---|---|
| Ingreso de la aplicación móvil | `POST /movil/ingresar` | #2 |
| Reporte del punto del GPS | `POST /movil/ubicacion` | #3 |

Todo corre sobre el **Truck de Pruebas** (`food_truck_id = 4`), que es el único con agenda
sembrada. Festín Rodante no se toca: su agenda es un bloque PENDIENTE en `datos_iniciales.sql` y no
se inventa.

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

Los dos motivos de fallo que se pueden provocar desde fuera devuelven **el mismo cuerpo**: por la
respuesta no se puede averiguar qué cuentas existen. Es la propiedad que ya tenía el recorrido del
navegador y que había que conservar.

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

---

## Lo que estas pruebas no cubren

- **La cuenta desactivada.** El tercer motivo que el servicio iguala a propósito —`activo = 0`—
  **no se probó**: exige un `UPDATE` sobre `usuarios` y no hay acceso a la base de datos desde
  fuera. Comparte rama con los otros dos motivos, pero la rama no se ejecutó con ese valor.
- **Las dos ramas nocturnas de `vigenteBloqueada()`.** La sesión fue un martes por la mañana y la
  única parada que cruza la medianoche —Zona Rosa de Pruebas, sábado de 18:00 a 01:00— no estaba en
  su franja. Las tres ramas están copiadas de `Ubicacion::vigente()`, que sí tiene su comprobación
  en [`pruebas-agenda-paradas.md`](pruebas-agenda-paradas.md), pero **la copia bloqueante no se ha
  ejercitado de noche**. Es lo primero que hay que probar en la próxima sesión nocturna.
- **El token vencido.** Se probó el token ausente, no uno caducado por los 120 minutos de vida.
- **El 500 `fallo_interno`.** No se provocó ningún fallo no previsto, así que la salida en JSON del
  manejador de errores para estos dos servicios queda sin ejercitar.
- **El error `food_truck_invalido`.** Inalcanzable desde fuera: el `food_truck_id` sale de la sesión
  y la clave foránea garantiza que existe. Está en el código como red, no como caso probable.
- **`php -l`.** Los tres archivos nunca pasaron por el analizador de sintaxis: no hay PHP en la
  máquina donde se escribieron. Que el servicio responda es la única evidencia de que compilan.
- **Festín Rodante.** No se usó en ninguna prueba. Sin agenda sembrada no tiene parada vigente que
  actualizar, y su agenda la define su dueño.

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
