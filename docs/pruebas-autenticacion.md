# Pruebas de autenticación, roles y CSRF

Ejecutadas contra **https://adso.menu08.com** (LiteSpeed, PHP 8.3.33, MySQL 8.0.46), no en local.
**19 comprobaciones, 0 fallos.**

> **Corrección del 4 de septiembre de 2026.** Este encabezado decía «PHP 8.2», que era el valor
> declarado del proyecto y no una medida. La versión real del servidor se leyó ese día en la
> cabecera `x-powered-by: PHP/8.3.33` de una respuesta de producción. Los resultados de abajo no
> cambian; lo que se corrige es el dato del entorno.

## Ingreso

| Caso | Esperado | Obtenido |
|---|---|---|
| `GET /ingresar` | 200 con token CSRF | 200, token de 64 hexadecimales |
| Contraseña incorrecta | 401 | 401 |
| Usuario inexistente | 401 | 401 |
| Credenciales correctas | 302 al inicio del rol | 302 a `/caja` |

El mensaje de error es **idéntico** en los dos casos fallidos: «Correo o contraseña incorrectos».
Decir cuál de los dos falló revelaría qué correos están registrados. Además, cuando el correo no
existe se compara igualmente contra un hash señuelo, para que la respuesta tarde lo mismo: si no,
el tiempo delataría las cuentas existentes.

## Sesión

| Caso | Resultado |
|---|---|
| Identificador de sesión tras ingresar | **cambia** (defensa contra fijación de sesión) |
| `GET /salir` | 302, sesión destruida y cookie expirada |
| Zona privada tras salir | 302 a `/ingresar` |

## Control de acceso por rol

Probado con la cuenta de rol `cajero`:

| Ruta | Esperado | Obtenido |
|---|---|---|
| `/caja` | 200 | 200 |
| `/panel` | 403 | 403 |
| `/svp` | 403 | 403 |

La página 403 muestra únicamente «No tiene permiso para ver esta pagina». No filtra nada del
panel: ni listados, ni datos del food truck, ni rastro de la ruta protegida.

Sin sesión, las tres rutas privadas responden 302 hacia `/ingresar`. Se redirige en lugar de
responder 403 porque el visitante no ha fallado, simplemente no ha entrado. Con sesión y rol
equivocado sí es 403: si ahí se redirigiera, el usuario quedaría dando vueltas entre su inicio
y la página que no le corresponde.

## Token contra falsificación de peticiones

| Caso | Esperado | Obtenido |
|---|---|---|
| POST sin token | 403 | 403 |
| POST con token alterado | 403 | 403 |

**Ningún POST rechazado tocó la base de datos.** Comprobado con la columna `ultimo_ingreso`:

| Usuario | Antes | Después |
|---|---|---|
| `cajero@menu08.local` | nunca | 2026-09-03 05:25:02 ← único ingreso correcto |
| `plataforma@menu08.local` | nunca | nunca |
| `produccion@menu08.local` | nunca | nunca |

Los intentos con contraseña incorrecta, usuario inexistente y token inválido no dejaron marca.

## Almacenamiento de contraseñas

Consulta directa a la tabla `usuarios`: las cuatro cuentas devuelven cadenas que empiezan por
`$2y$`, el prefijo de bcrypt que produce `password_hash`. Ninguna en texto plano.

## Lo que no cubre esta prueba

No hay límite de intentos de ingreso. Un atacante puede probar contraseñas sin freno más allá de
lo que imponga el propio servidor. Para el prototipo se acepta; en un despliegue real haría falta
un contador de intentos por cuenta y por dirección de origen.
