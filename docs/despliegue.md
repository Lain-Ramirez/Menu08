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
https://adso.menu08.com/                              comprobación del núcleo
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
