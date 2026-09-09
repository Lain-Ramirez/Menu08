# Contrato de los servicios del módulo móvil

Dos operaciones: la aplicación **ingresa** con las cuentas que ya existen y **reporta** dónde está
parado el truck. Ambas responden JSON siempre, también al fallar.

| Operación | Ruta |
|---|---|
| Ingreso de la aplicación móvil | `POST /movil/ingresar` |
| Reporte del punto del GPS | `POST /movil/ubicacion` |

Los consume el APK del [módulo móvil](https://github.com/Lain-Ramirez/GA8-220501096-AA2-EV02), que
dentro de este repositorio es el submódulo `movil/`. Existen porque el recorrido del panel no le
sirve a un cliente sin navegador: `POST /ingresar` redirige con **302** al acertar y repinta la
vista `auth/acceso` con **401** al fallar, y el token viaja en un campo oculto que hay que raspar
del HTML.

## Dos reglas que valen para las dos rutas

**El cuerpo va como `application/x-www-form-urlencoded`.** No se acepta JSON de entrada, y no es un
descuido: `Csrf` lee el token únicamente de `$_POST['_token']`, sin mirar ninguna cabecera, y
`$_POST` solo se llena con ese tipo de cuerpo. Enviándolo así, el núcleo no necesita ni una línea
nueva.

**Ninguna de las dos rota el token.** Al contrario que `POST /caja/vender` y que las rutas del
panel, que llaman a `Csrf::rotar()` para que reenviar el formulario no repita la operación, aquí el
token del ingreso sirve para todos los reportes que vengan después. La aplicación entra una vez y
reporta muchas; obligarla a volver a ingresar entre un punto y el siguiente la volvería frágil sin
ganar nada, porque un reporte repetido no duplica ninguna fila: cae sobre la misma parada vigente.

---

## Ingreso

```
POST /movil/ingresar
```

**Autenticación:** ninguna. Es la puerta de entrada.
**Cuerpo:** `correo` y `contrasena`.

Es el único servicio `/movil/…` que **no** exige token, y no puede exigirlo: el token nace con la
sesión, y aquí todavía no hay ninguna.

### Respuesta correcta · 200

```json
{
  "usuario": {
    "id": 2,
    "nombre": "Administrador del food truck",
    "correo": "foodtruck@menu08.local",
    "rol": "food_truck",
    "food_truck_id": 1
  },
  "token_csrf": "d0dc375a4f9ede4ec0b56425b5f761eb6e2c06ce9a25d0252a08c6a155f04183"
}
```

Con ella viaja la cookie de sesión:

```
set-cookie: menu08_sesion=dv9qflbqh9ot7ge2kcvnnd0r0f; path=/; secure; HttpOnly; SameSite=Lax
```

| Campo | Significado |
|---|---|
| `usuario.rol` | `plataforma`, `food_truck`, `cajero` o `produccion`. La aplicación lo usa para saber si podrá reportar |
| `usuario.food_truck_id` | El truck de la cuenta. **`null` en el rol `plataforma`**, que no está asociado a ninguno |
| `token_csrf` | El que hay que mandar como `_token` en el reporte. Vive 120 minutos |

El objeto `usuario` lleva **esas cinco claves y ninguna más**. La contraseña cifrada que sí devuelve
`Usuario::porCorreo()` no aparece: el controlador copia de la sesión, no de la fila.

Una cuenta con rol `cajero`, `produccion` o `plataforma` **ingresa igual**. El rol no se filtra
aquí, sino en el reporte: así la aplicación puede decir «esta cuenta no administra la agenda» en vez
de «usuario o contraseña incorrectos», que sería mentira.

### Credenciales que no valen · 401

```json
{ "error": "credenciales_invalidas", "mensaje": "Correo o contraseña incorrectos." }
```

**Tres motivos distintos responden esto mismo, byte a byte:** el correo no existe, la contraseña no
coincide, o la cuenta está desactivada (`activo = 0`). Distinguirlos delataría qué cuentas hay
registradas y cuáles siguen activas. Está comprobado con `cmp` en
[`pruebas-movil-servicios.md`](pruebas-movil-servicios.md): los tres cuerpos tienen el mismo
`sha256`.

### Falta el correo o la contraseña · 422

```json
{ "error": "datos_incompletos", "mensaje": "Faltan el correo o la contraseña." }
```

Cubre los dos campos ausentes y los dos vacíos. La comprobación está escrita **antes** de consultar
`usuarios`, así que un cuerpo vacío no llega a tocar la tabla.

### Fallo del servidor · 500

```json
{ "error": "fallo_interno", "codigo": 500 }
```

En desarrollo se añade un campo `mensaje` con el detalle. En producción nunca.

---

## Reporte del punto

```
POST /movil/ubicacion
```

**Autenticación:** sesión con rol `food_truck`. La misma puerta estrecha que exige
`UbicacionControlador` en sus cuatro acciones: por el teléfono no se entra más ancho que por el
panel.
**Cuerpo:** `latitud`, `longitud` y `_token`.

Las dos coordenadas son **obligatorias**, al contrario que en el formulario del panel, donde son
opcionales y el vacío se guarda como `NULL`. Un reporte del GPS sin punto no es un reporte.

El `food_truck_id` sale **siempre de la sesión**. Un cuerpo que traiga `food_truck_id` no cambia a
qué truck se escribe; está comprobado enviando `food_truck_id=1` desde una sesión del truck 4.

### Qué hace con el punto

Ésta es la regla del servicio, y conviene leerla entera antes de integrarlo:

- **Si hay una parada vigente**, el punto es suyo: se le corrigen `latitud` y `longitud`, y **nada
  más**. El nombre, la referencia, el día y las dos horas los puso el dueño al programar la parada y
  el reporte no viene a reescribirlos. La respuesta es **200** con `"creada": false`.
- **Si no hay ninguna vigente** —el truck paró fuera de su horario programado— el reporte no se
  tira: se registra una **parada nueva**, y la respuesta es **201** con `"creada": true`.

Los campos de esa parada nueva los fija el servicio con el mismo instante que usó para buscar:

| Campo | Valor |
|---|---|
| `nombre` | `Punto reportado AAAA-MM-DD HH:MM` |
| `referencia` | `Registrado desde la aplicacion movil` |
| `dia_semana` | El día del reporte, 1 lunes … 7 domingo |
| `hora_inicio` | La hora del reporte, con los segundos en `00` |
| `hora_fin` | **Igual que `hora_inicio`** |
| `activa` | `1` |

**Que `hora_fin` sea igual a `hora_inicio` no es un descuido, es el mecanismo.** La condición de
parada vigente trata `hora_fin <= hora_inicio` como jornada que cierra al día siguiente, así que esa
parada queda vigente **desde el mismo segundo y durante 24 horas**. Consecuencia práctica: el
reporte siguiente ya encuentra parada vigente y cae en la rama de actualización. Pulsar el botón
diez veces deja **una fila**, no diez.

El dueño puede renombrar esa parada, ponerle referencia y horario reales, o desactivarla, desde
`/panel/ubicaciones` como cualquier otra.

Las dos ramas van dentro de **una sola transacción** que empieza bloqueando la fila del food truck
con `SELECT … FOR UPDATE`, igual que `Orden::registrar()` serializa la numeración. Dos teléfonos
reportando a la vez fuera de horario no crean una parada cada uno: el segundo ve la que insertó el
primero.

### Parada vigente actualizada · 200

```json
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

### Parada nueva registrada · 201

```json
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

| Campo | Significado |
|---|---|
| `parada` | **Las mismas nueve claves en las dos ramas.** Es el contrato del que depende la pantalla del APK |
| `parada.latitud`, `parada.longitud` | Cadenas, no números: se releen de la columna `DECIMAL(10,7)` dentro de la transacción, así que es lo que quedó escrito y no lo que se envió |
| `parada.dia_semana` | 1 lunes … 7 domingo, la numeración de `Ubicacion::DIAS` |
| `creada` | `false` si actualizó una parada existente, `true` si registró una nueva. Va acompañado del código: 200 o 201 |

El identificador `id` sirve para enlazar con `/panel/ubicaciones`, donde el dueño la edita.

### Coordenadas que no valen · 422

```json
{ "error": "coordenadas_invalidas", "mensaje": "La latitud debe estar entre -90 y 90." }
```

El campo `mensaje` lleva el texto del validador para la coordenada que falló, y los dos si fallan
las dos. Los mensajes salen de `Validador::coordenada()` y van **sin acentos**, como toda cadena del
código PHP:

| Envío | `mensaje` |
|---|---|
| `latitud` ausente o vacía | `La latitud es obligatoria.` |
| `longitud` ausente o vacía | `La longitud es obligatoria.` |
| Fuera de rango | `La latitud debe estar entre -90 y 90.` · `La longitud debe estar entre -180 y 180.` |
| Más de 7 decimales, o no numérica | `La latitud debe ser un numero con hasta 7 decimales.` |

**La tabla no se toca** en ninguno de esos casos: la validación corre antes de abrir la transacción.

La coma decimal **sí se acepta**: `4,7110000` se normaliza a `4.7110000` antes de validar, porque el
teclado en español la escribe así.

### Sin sesión · 401

```json
{ "error": "no_autenticado", "mensaje": "Debe iniciar sesion para consultar este servicio." }
```

### Rol sin permiso · 403

```json
{ "error": "rol_no_autorizado", "mensaje": "El rol \"cajero\" no tiene acceso a este servicio." }
```

Responden así `cajero`, `produccion` y `plataforma`. La aplicación puede distinguir este caso del
401 y decirle a quien atiende que su cuenta no administra la agenda, en vez de echarlo.

### Token ausente o vencido · 403

```json
{
  "error": "token_invalido",
  "mensaje": "El token de seguridad expiro o no es valido. Recargue el tablero."
}
```

El texto habla del tablero porque el mensaje se comparte con los servicios del SVP; el token es el
mismo mecanismo. Para la aplicación móvil significa: vuelve a llamar a `POST /movil/ingresar`.

### Fallo del servidor · 500

```json
{ "error": "fallo_interno", "codigo": 500 }
```

---

## Por qué los errores también son JSON

Un cliente que espera un objeto y recibe `<!doctype html>` falla con un error de sintaxis que no
dice nada del problema real. Es el mismo motivo que en [`api-svp.md`](api-svp.md), agravado en el
móvil: allí hay una consola de navegador donde mirar; aquí hay un teléfono ajeno en una ventanilla.

`ingresar()` activa el modo JSON del manejador de errores con `ManejadorErrores::responderEnJson()`
en su primera línea, porque `exigirRolApi()` —que es quien lo activa en los demás servicios— no
sirve cuando todavía no hay sesión cuyo rol exigir. `ubicacion()` sí lo hereda de `exigirRolApi()`.

Eso vale para las dos rutas exactas y con el método correcto. Un `GET /movil/ingresar` no coincide
con ninguna ruta registrada y responde **404 en HTML**, como cualquier dirección desconocida del
sitio.

## Cómo se prueba sin el APK

La carpeta **«8 - Modulo movil»** de
[`postman/Menu08.postman_collection.json`](../postman/Menu08.postman_collection.json) recorre las
dos rutas entera con **Run**, incluidos los rechazos. Guía en [`POSTMAN.md`](../POSTMAN.md).

Con `curl`, el recorrido mínimo son dos peticiones:

```bash
TOK=$(curl -s -c galleta.txt -X POST https://adso.menu08.com/movil/ingresar \
  -d 'correo=pruebas.foodtruck@menu08.local' -d 'contrasena=Menu08*Demo2026' \
  | python3 -c 'import sys,json;print(json.load(sys.stdin)["token_csrf"])')

curl -s -b galleta.txt -X POST https://adso.menu08.com/movil/ubicacion \
  -d 'latitud=4.6767000' -d 'longitud=-74.0483000' -d "_token=$TOK"
```

La evidencia de las pruebas contra el sitio publicado está en
[`pruebas-movil-servicios.md`](pruebas-movil-servicios.md), con lo que quedó sin cubrir.
