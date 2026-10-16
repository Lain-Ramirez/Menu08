# Pruebas de Menu08

Las pruebas unitarias de funciones puras están en `menu08_app/pruebas/`. La colección Postman está en `postman/Menu08_GA9_VIDEO.postman_collection.json`; Newman la ejecuta desde Node.js. El recorrido E2E usa Playwright para Node.js y Chromium. Los registros anteriores se conservan en `evidencias/inicial/` y `evidencias/actual/`.

## Cloud Shell Editor

Abre la rama `Lain`, entra a `~/Menu08` y abre la terminal integrada. La instalación de este entorno ya cuenta con Node.js, Playwright, Newman, Chromium headless, MySQL local y PHP. Si necesitas inicializar o recuperar los servicios, ejecuta una vez:

```bash
./tests/preparar_cloudshell.sh
```

Para repetir las pruebas, copia uno de estos comandos desde la raíz del repositorio:

```bash
php menu08_app/pruebas/ejecutar.php
./tests/video.sh postman
./tests/video.sh e2e
```

El comando unitario no requiere base de datos ni iniciar sesión. Postman comprueba 22 solicitudes y 40 aserciones. El E2E abre Chromium, inicia un turno de caja, registra una venta, sigue la orden en producción y en la pantalla pública, y cierra el turno; comprueba la base local y guarda capturas nuevas en `tests/evidencias/actual/`.

## Base de datos de demostración

El preparador puede crear o iniciar el contenedor aislado `menu08-ga9-mysql`, puerto local `13306`, y reinicializa la base `menu08` con los datos de demostración de `menu08_app/basedatos/`. No uses esta preparación contra una base personal ni contra el hosting. La aplicación se sirve en `http://127.0.0.1:8088`. El preparador solo crea servicios locales; no publica la aplicación.

Para instalar dependencias desde un checkout nuevo con Node.js y npm disponibles:

```bash
npm ci --prefix tests
```

Se requiere PHP 8.3 con `pdo_mysql`, cliente MySQL y Docker con acceso a su daemon local. Las carpetas `.runtime/`, `node_modules/`, la configuración local y las sesiones quedan fuera del control de versiones.

## Alcance registrado

El informe consolidado de la ejecución anterior conserva 101 casos: 97 aprobados, ninguno fallido y cuatro seguimientos que requieren revisión física, una decisión funcional o despliegue. La ejecución reciente de los comandos de video se conserva aparte en la salida de la terminal y en las capturas E2E.
