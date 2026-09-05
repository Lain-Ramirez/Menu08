# Despliegue en adso.menu08.com

## La idea

El repositorio es un **espejo del servidor**. Sus dos carpetas de primer nivel se llaman igual
que en el hosting y se copian tal cual:

| En el repositorio | En el servidor | Alcance |
|---|---|---|
| `menu08_app/` | `/home/sfacturs2/menu08_app/` | 🔒 Privada. Ninguna raíz web la alcanza |
| `ADSO.menu08.com/` | `/home/sfacturs2/ADSO.menu08.com/` | 🌐 Pública. Es la raíz del sitio |

No hay nada que traducir ni recolocar: se sube cada carpeta a su homónima.

## Por qué está partido así

`ADSO.menu08.com` es la raíz del sitio: **todo lo que se ponga dentro es descargable desde
internet**. Ahí no puede vivir `configuracion/configuracion.php`, que lleva la contraseña de
MySQL, ni `basedatos/`, que lleva los scripts del esquema.

Por eso la aplicación entera vive en `menu08_app/`, una carpeta hermana **fuera de toda raíz
web**, y en la carpeta pública solo queda un puente de seis líneas. La protección es
estructural: no depende de que una regla de `.htaccess` siga activa.

```
/home/sfacturs2/
├── menu08_app/                    🔒 fuera de la web
│   ├── publico/index.php              front controller de verdad
│   ├── aplicacion/
│   ├── configuracion/                 credenciales
│   ├── basedatos/
│   └── almacenamiento/bitacora/
│
├── ADSO.menu08.com/               🌐 raíz del sitio
│   ├── index.php                      puente
│   ├── .htaccess
│   ├── recursos/                      css, js, imágenes
│   └── subidas/                       fotos y códigos QR, sin ejecución de PHP
│
├── lain.menu08.com                (de otro sitio, no se toca)
├── public_html                    (dominio principal, no se toca)
└── www.gerama.co                  (otro sitio, no se toca)
```

El puente busca `menu08_app/` hacia arriba en lugar de fijar una ruta absoluta, así que
funciona igual en el servidor y en el repositorio, donde las dos carpetas también son hermanas.

## Pasos

1. **Vaciar `ADSO.menu08.com`.** Conserva los archivos del WordPress que venía instalado.
   La base de datos ya se limpió y tiene el esquema de Menu08 cargado; faltan los archivos.
2. Subir los dos paquetes al directorio de la cuenta, `/home/sfacturs2/`, y extraerlos ahí
   con el Administrador de archivos de cPanel.
3. Comprobar que quedó `menu08_app/publico/index.php` y `ADSO.menu08.com/index.php`.
4. Permisos de escritura **775** en `menu08_app/almacenamiento/bitacora` y en
   `ADSO.menu08.com/subidas`.

## Comprobación

Estas tres deben responder:

```
https://adso.menu08.com/                              portada con las cartas publicadas
https://adso.menu08.com/comprobacion/festin-rodante   ruta con parámetro
https://adso.menu08.com/no-existe                     404 con la vista propia
```

Y estas tres **no deben devolver nada** (403 o 404). Si alguna entrega contenido, algo quedó
mal ubicado y hay que parar:

```
https://adso.menu08.com/configuracion/configuracion.php
https://adso.menu08.com/basedatos/esquema.sql
https://adso.menu08.com/aplicacion/nucleo/ConexionBD.php
```

## El entorno verificado

El hosting **no es Apache**: es **LiteSpeed**, y PHP no es 8.2 sino **8.3**. cPanel no lo
distingue porque LiteSpeed Enterprise lee la configuración y los `.htaccess` de Apache —por eso
el panel se ve igual de siempre y la reescritura de `ADSO.menu08.com/.htaccess` funciona sin
tocar nada—, pero el binario que atiende es `lshttpd`, no `httpd`. No hay VirtualHost que editar
ni servicio que instalar: el entorno viene dado y lo único que cabe es verificarlo.

Comprobado el 5 de septiembre de 2026 contra el sitio publicado:

```console
$ curl -sS -I https://adso.menu08.com/
HTTP/2 200
x-powered-by: PHP/8.3.33
set-cookie: menu08_sesion=…; path=/; secure; HttpOnly; SameSite=Lax
expires: Thu, 19 Nov 1981 08:52:00 GMT
cache-control: no-store, no-cache, must-revalidate
pragma: no-cache
content-type: text/html; charset=utf-8
date: Sat, 05 Sep 2026 11:04:07 GMT
server: LiteSpeed
vary: User-Agent
x-content-type-options: nosniff
x-frame-options: SAMEORIGIN
referrer-policy: same-origin
alt-svc: h3-29=":443"; ma=2592000
```

El identificador de sesión se elidió; el resto es la salida literal.

| Encabezado | Qué prueba |
|---|---|
| `server: LiteSpeed` | El servidor web es LiteSpeed, no Apache |
| `x-powered-by: PHP/8.3.33` | La versión de PHP es 8.3, no 8.2 |
| `alt-svc: h3-29` | HTTP/3 nativo: no hay un proxy delante disfrazando a otro servidor |
| `x-content-type-options`, `x-frame-options`, `referrer-policy` | LiteSpeed aplica el bloque `<IfModule mod_headers.c>` de `ADSO.menu08.com/.htaccess` |
| `secure; HttpOnly; SameSite=Lax` | La sesión endurecida de `Sesion` viaja como se configuró |

La última fila es la que cierra la duda de fondo: **LiteSpeed está interpretando el `.htaccess`
completo**, encabezados incluidos. Lo mismo se ve en la reescritura:

```console
$ curl -o /dev/null -w '%{http_code}\n' https://adso.menu08.com/carta/festin-rodante
200
$ curl -o /dev/null -w '%{http_code} -> %{redirect_url}\n' https://adso.menu08.com/svp
302 -> https://adso.menu08.com/ingresar
```

Las dos rutas se resuelven sin escribir `index.php` en la dirección. El 302 del SVP no es un
fallo: la ruta exige sesión y el controlador redirige al ingreso.

### Las extensiones, comprobadas por sus rutas

Los encabezados no listan extensiones, pero cada una tiene una ruta que la ejercita: si faltara,
PHP 8 lanzaría un `Error` por función indefinida y la respuesta sería un 500. Comprobado el 5 de
septiembre de 2026 con el usuario de demostración `foodtruck@menu08.local`:

| Extensión | Ruta que la ejercita | Resultado | Veredicto |
|---|---|---|---|
| `pdo_mysql` | `GET /` y `GET /panel` | 200 con datos leídos de la base | Presente |
| `zlib` | `GET /panel/qr/descargar` | 200 · `image/png` · 2780 bytes · PNG 410×410 válido | Presente |
| `mbstring` | `POST /panel/categorias` con un nombre de 100 caracteres `ñ` | 422 · «El nombre no puede pasar de 90 caracteres.» | Presente |

La sonda de `mbstring` está elegida para no escribir: 100 caracteres superan el límite de 90 que
comprueba `mb_strlen()` en `Validador::texto()`, así que la validación falla, la vista se
devuelve con 422 y no se crea ninguna fila. Se confirmó comparando el listado de categorías antes
y después: idéntico.

`zlib` aparece porque `GeneradorQr` arma el PNG a mano con `pack()` y `gzcompress()`.

### GD no hace falta

`docs` y los issues pedían `gd` por costumbre. **El proyecto no la usa:**

- `GeneradorQr` genera el PNG sin GD, por decisión explícita documentada en su cabecera.
- `GestorImagenes` solo llama a `getimagesize()`, que es de `ext/standard`, no de GD, y a
  `finfo_*` cuando está disponible.

`fileinfo` es opcional: `GestorImagenes::tipoReal()` la usa si existe y, si no, cae en
`getimagesize()`. No se puede distinguir cuál de las dos actuó sin subir un archivo, y una subida
de prueba dejaría basura en `ADSO.menu08.com/subidas/`, así que se deja sin comprobar.

### La versión de MySQL

**MySQL 8.0.46.** Consultado contra el servidor el 5 de septiembre de 2026 con `SELECT VERSION()`,
que responde `8.0.46-cll-lve` —la compilación de CloudLinux que acompaña a LiteSpeed en este
hosting—. Cumple el requisito de MySQL 8 que declara el `README.md`.

La base **no se llama `menu08`**: es `sfacturs2_ADSO_9d9wd`, con el prefijo de cuenta que impone
cPanel. Sus nueve tablas y las 25 columnas de texto están en `utf8mb4_unicode_ci`, como declara
`esquema.sql`, pero **el cotejamiento por omisión de la base es `utf8mb4_0900_ai_ci`**, el del
servidor. Mientras cada `CREATE TABLE` traiga su `COLLATE` explícito no pasa nada; una tabla nueva
que lo omita heredaría el del servidor y mezclaría cotejamientos en los `JOIN`.

Comparado columna por columna contra `menu08_app/basedatos/esquema.sql` el 5 de septiembre de 2026:
**nueve tablas de nueve, sin una sola diferencia de nombre ni de columna.**

## Configuración del servidor

`menu08_app/configuracion/configuracion.php` no se versiona nunca. En el servidor:

| Clave | Valor |
|---|---|
| `entorno` | `produccion`, para que ningún error muestre la traza al visitante |
| `url_base` | `https://adso.menu08.com`, la dirección que se codifica en los códigos QR |
| `base_datos.servidor` | `localhost`: desde el propio servidor la base es local |
| `sesion.solo_https` | `true`, porque el sitio se sirve por HTTPS |
| `subidas.ruta` | ruta relativa a `../../ADSO.menu08.com/subidas`, válida en el repositorio y en el servidor |

## Base de datos

El usuario del hosting **no puede crear bases**. Al importar hay que quitar de
`menu08_app/basedatos/esquema.sql` las sentencias `CREATE DATABASE` y `USE`, o importar desde
phpMyAdmin con la base ya seleccionada.

## Acceso al servidor

El cortafuegos del hosting **bloquea FTP, SFTP, SSH y cPanel** desde fuera de las direcciones
autorizadas, y los intentos fallidos hacen que se bloquee la dirección que insiste. El
despliegue se hace subiendo los paquetes por el Administrador de archivos. Lo único alcanzable
de forma remota es MySQL en el puerto 3306.
