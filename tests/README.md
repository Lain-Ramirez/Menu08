<!-- Menu08.com:header -->
[![Menu08.com · Ejecutar y conservar las pruebas de AA3. Preparación local, grupos de automatización y registros del ciclo inicial y corregido. Preparar el entorno local](../assets/brand/tests-automatizacion-menu08-com-header.png)](#preparación-local)
<!-- /Menu08.com:header -->

# Pruebas de Menu08

Las pruebas unitarias de funciones puras están en `menu08_app/pruebas/`. La colección Postman está en `postman/Menu08_GA9_VIDEO.postman_collection.json`; Newman la ejecuta desde Node.js. El recorrido E2E usa Playwright para Node.js y Chromium. Los registros anteriores se conservan en `evidencias/inicial/` y `evidencias/actual/`.

## Cloud Shell Editor

Abre la rama `Lain`, entra a `~/Menu08` y abre la terminal integrada. Para ejecutar el recorrido completo basta con:

```bash
./tests/video.sh e2e
```

El comando prepara automáticamente Node.js, Playwright, Newman, Chromium y MySQL si faltan, inicia los servicios locales y ejecuta las pruebas. Para preparar el entorno por separado puedes usar `./tests/preparar_cloudshell.sh`.

Los demás comandos disponibles desde la raíz del repositorio son:

```bash
php menu08_app/pruebas/ejecutar.php
./tests/video.sh postman
```

El comando unitario no requiere base de datos ni iniciar sesión. Postman comprueba 22 solicitudes y 40 aserciones. El E2E abre Chromium, inicia un turno de caja, registra una venta, sigue la orden en producción y en la pantalla pública, y cierra el turno; comprueba la base local y guarda capturas nuevas en `tests/evidencias/actual/`. Las capturas `CP-AUTO-E2E-01-node-*.png` se regeneran en cada ejecución, quedan ignoradas por Git y pueden eliminarse después de revisarlas.

## Videos del E2E

`./tests/video.sh e2e` graba tres videos WebM a 1280 × 720 y muestra sus rutas al terminar:

- `01-caja-y-cierre.webm`: ingreso del cajero, apertura, venta y cierre del turno.
- `02-produccion.webm`: ingreso y avance de la orden hasta entregarla.
- `03-pantalla-publica.webm`: aparición y retirada del número de la orden.

Se guardan en `tests/evidencias/actual/videos/<fecha-hora-UTC>/`. Cada ejecución crea su propia carpeta, conserva las anteriores y finaliza las grabaciones también cuando falla la prueba. Son videos del navegador, sin audio ni terminal. Quedan ignorados por Git; puedes descargarlos desde el explorador de Cloud Shell Editor. FFmpeg se instala automáticamente si falta.

## Base de datos de demostración

El preparador puede crear o iniciar el contenedor aislado `menu08-ga9-mysql`, puerto local `13306`, y reinicializa la base `menu08` con los datos de demostración de `menu08_app/basedatos/`. No uses esta preparación contra una base personal ni contra el hosting. La aplicación se sirve en `http://127.0.0.1:8088`. El preparador solo crea servicios locales; no publica la aplicación.

La primera inicialización espera hasta 60 intentos, separados por dos segundos, a que una consulta `SELECT 1` funcione por el puerto `13306` antes de importar los datos. El servidor temporal de MySQL puede responder dentro del contenedor sin estar disponible todavía para PHP. Si una preparación anterior terminó con `ERROR 2013`, vuelve a ejecutar `./tests/video.sh e2e`.

El ejecutor comprueba que queden al menos 64 MiB libres para guardar sesiones y capturas. Si falla el inicio de sesión, el E2E guarda una captura `fallo-ingreso-*.png` en `tests/evidencias/actual/` e informa la URL y el texto de la pantalla para facilitar el diagnóstico; revisa también el espacio libre con `df -h ~`.

Para instalar dependencias desde un checkout nuevo con Node.js y npm disponibles:

```bash
npm ci --prefix tests
```

Se requiere PHP 8.3 con `pdo_mysql`, cliente MySQL y Docker con acceso a su daemon local. Las carpetas `.runtime/`, `node_modules/`, la configuración local y las sesiones quedan fuera del control de versiones.

## Alcance registrado

