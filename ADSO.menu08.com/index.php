<?php

declare(strict_types=1);

/**
 * Menu08 - Punto de entrada publico de adso.menu08.com
 *
 * Esta es la unica carpeta que el servidor web publica. La aplicacion vive
 * FUERA de ella, en /home/<cuenta>/menu08_app, para que ni la configuracion
 * con las credenciales ni los scripts de la base de datos sean descargables.
 *
 * La busqueda hacia arriba evita fijar una ruta absoluta: funciona igual si la
 * carpeta publica cuelga del directorio de la cuenta o de public_html.
 */

$aplicacion = null;

for ($carpeta = __DIR__, $intento = 0; $intento < 4; $intento++, $carpeta = dirname($carpeta)) {
    if (is_file($carpeta . '/menu08_app/publico/index.php')) {
        $aplicacion = $carpeta . '/menu08_app';
        break;
    }
}

if ($aplicacion === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');

    exit('No se encontro la aplicacion. Revise docs/despliegue.md.');
}

require $aplicacion . '/publico/index.php';
