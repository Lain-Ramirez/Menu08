# Pruebas del método HEAD en el enrutador

Ejecutadas contra **https://adso.menu08.com** el 4 de septiembre de 2026, con `curl` y con
peticiones HTTP crudas por `openssl s_client`. **30 comprobaciones, 0 fallos.**

Corresponden al issue de corrección del enrutador: antes de este cambio, **toda** ruta del sitio
respondía 404 a `HEAD`, porque `despachar()` buscaba la tabla del método exacto y solo existían
`GET` y `POST`.

## Antes

```
HEAD /                  -> 404
HEAD /ingresar          -> 404
HEAD /panel/categorias  -> 404
HEAD /svp/ordenes       -> 404
```

Y una línea de aviso en la bitácora por cada intento:

```
[2026-09-04 07:00:31] AVISO: Menu08\Nucleo\RutaNoEncontrada: No hay ruta registrada para HEAD /panel/ubicaciones
```

## Después · `HEAD` responde el mismo código que `GET`

| Ruta | `GET` | `HEAD` | |
|---|---|---|---|
| `/` | 200 | **200** | ✔ |
| `/ingresar` | 200 | **200** | ✔ |
| `/panel/categorias` | 302 | **302** | ✔ redirige igual sin sesión |
| `/svp/ordenes` | 401 | **401** | ✔ |
| `/carta/truck-de-pruebas` | 200 | **200** | ✔ ruta con parámetro |
| `/comprobacion/truck-de-pruebas` | 200 | **200** | ✔ |
| `/carta/no-existe` | 404 | **404** | ✔ sigue fallando lo que debe fallar |
| `/ruta-que-no-existe` | 404 | **404** | ✔ |

## El cuerpo va vacío, medido byte a byte

No basta con `curl -I`, que nunca leería el cuerpo. Se pidió en crudo:

```bash
printf 'HEAD / HTTP/1.1\r\nHost: adso.menu08.com\r\nConnection: close\r\n\r\n' \
  | openssl s_client -quiet -connect adso.menu08.com:443 -servername adso.menu08.com
```

```
respuesta completa: 509 bytes
  cabeceras: 505 bytes
  cuerpo   :   0 bytes
```

| Petición | Código | `Content-Type` | Cuerpo |
|---|---|---|---|
| `GET /` | 200 | text/html; charset=utf-8 | 4607 bytes |
| `HEAD /` | 200 | text/html; charset=utf-8 | **0 bytes** |
| `HEAD /carta/truck-de-pruebas` | 200 | text/html; charset=utf-8 | **0 bytes** |
| `HEAD /carta/no-existe` | 404 | text/html; charset=utf-8 | **0 bytes** |
| `HEAD /panel/ubicaciones` | 302 | text/html; charset=UTF-8 | **0 bytes** |
| `HEAD /panel/qr/descargar` | 302 | text/html; charset=UTF-8 | **0 bytes** |

Mismo código y mismo `Content-Type` que el `GET`; lo único que cambia es el cuerpo.

## Los servicios JSON del SVP siguen dando JSON

Es el caso que podía romperse en silencio: un tablero que sondea espera un objeto, y una página de
error HTML le da un fallo de sintaxis que no dice nada.

| Petición, sin sesión | Código | `Content-Type` | Cuerpo |
|---|---|---|---|
| `GET /svp/ordenes` | 401 | **application/json**; charset=utf-8 | 88 bytes |
| `HEAD /svp/ordenes` | 401 | **application/json**; charset=utf-8 | **0 bytes** |

## Lo que no debía cambiar, no cambió

| Comprobación | Resultado |
|---|---|
| `HEAD /caja/vender` (existe solo como POST) | **404**, sin cuerpo |
| `HEAD /panel/categorias/estado` (solo POST) | **404**, sin cuerpo |
| La parada nocturna sigue vigente el domingo a las 00:30 | Zona Rosa de Pruebas |
| `POST /panel/ubicaciones` sin token | **403** |
| `POST /panel/ubicaciones` con `dia_semana=9` | **422** |
| `GET /carta/truck-de-pruebas` | **200** |
| `GET /panel/qr/descargar` | **200** |
| Paradas en el listado antes y después de **tres** `HEAD` | **6 → 6**, ningún `HEAD` escribe |

## Cómo se arregló

Dos sitios, porque uno solo no bastaba.

**`Enrutador::despachar()`** resuelve `HEAD` con la tabla de `GET`, de modo que registrar una ruta
sigue siendo una sola línea en `rutas.php`:

```php
$tabla = $metodo === 'HEAD' && !isset($this->rutas['HEAD']) ? 'GET' : $metodo;
```

y descarta el cuerpo con un búfer cuyo callback devuelve cadena vacía:

```php
ob_start(static fn (string $cuerpo): string => '');
```

El callback es lo que lo hace fiable: `redirigir()` y `jsonError()` terminan en `exit`, así que un
`ob_end_clean()` al final del método nunca llegaría a ejecutarse; en cambio el vaciado implícito del
final del script sí pasa por el callback y sale vacío.

**`ManejadorErrores::responder()`** lo comprueba otra vez. Hacía falta: ese método cierra todos los
búferes antes de escribir —para que la página de error no quede incrustada dentro de una vista
rota—, incluido el del enrutador. Sin esta segunda comprobación, los `HEAD` que acaban en 404, 403 o
422 sí habrían llevado cuerpo, que es justo lo que vería un monitor de disponibilidad.

## La bitácora del servidor lo confirma

Es el criterio que no se puede medir desde fuera. Se descargó `almacenamiento/bitacora/2026-09-04.log`
del servidor y se revisó (queda fuera del repositorio: lleva rutas internas del hosting).

**37 líneas, las 37 de nivel `AVISO`. Ningún `ERROR`, ningún 500**, y ninguna traza de `TypeError`,
`PDOException` ni `Undefined`: el búfer de salida que se añadió no rompió nada.

El corte está en las **07:49:27**, que es la hora que trajo la cabecera `Date` de la primera petición
`HEAD` que respondió 200.

| Comprobación sobre la bitácora | Resultado |
|---|---|
| Avisos de `HEAD` **antes** del arreglo | 6, uno por cada ruta sondeada, incluidas `/`, `/ingresar`, `/panel/categorias` y `/svp/ordenes` |
| Avisos de `HEAD` sobre rutas que **existen** como `GET`, después del arreglo | **ninguno** |
| Avisos de `HEAD` después del arreglo | 4, todos de `/caja/vender` y `/panel/categorias/estado` |
| Esas dos rutas están registradas como `GET` | **no**, solo como `POST`: el 404 es correcto |

Y la comprobación más fina, que el propio registro deja ver: `HEAD /carta/no-existe` **no** aparece
como «No hay ruta registrada», sino como

```
[2026-09-04 07:49:52] AVISO: Menu08\Nucleo\RutaNoEncontrada: No hay un food truck activo con el slug "no-existe".
```

es decir, el `HEAD` se enrutó, llegó hasta `CartaControlador::publica()` y el 404 lo produjo el
dominio, no el enrutador. Que es exactamente lo que se buscaba.

Del mismo archivo sale también la equivalencia entre los dos métodos, registrada en el mismo segundo:

```
[2026-09-04 07:49:14] AVISO: ... No hay ruta registrada para GET  /ruta-que-no-existe
[2026-09-04 07:49:14] AVISO: ... No hay ruta registrada para HEAD /ruta-que-no-existe
```

## Lo que estas pruebas no cubren

- **`Content-Length`.** La norma admite omitirlo en `HEAD`, y aquí se omite porque el cuerpo se
  descarta. No se comprobó que coincida con el del `GET`, que sería lo ideal pero obligaría a
  generar y medir la página entera.
- **`OPTIONS` y los demás métodos.** Siguen respondiendo 404. No lo pide ningún criterio y no hay
  cliente que los use, pero el enrutador tampoco los contempla.
- **El coste.** En `HEAD` la página se genera entera y se tira. Es correcto —así el código y las
  cabeceras son de verdad los del `GET`— pero no se midió el tiempo, y en `/panel/qr/descargar` eso
  significa construir el PNG para descartarlo.
- **Otros clientes.** Se probó con `curl` y con peticiones crudas por `openssl`. No se ha puesto un
  monitor de disponibilidad real apuntando al sitio, que es el caso de uso que motivó el arreglo.
