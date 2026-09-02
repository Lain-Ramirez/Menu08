<?php
/**
 * Menu08 - Plantilla de configuracion.
 *
 * Copiar este archivo como configuracion/configuracion.php y completar los
 * valores reales. El archivo configuracion.php esta en .gitignore y NUNCA
 * debe subirse al repositorio.
 *
 *   cp configuracion/configuracion.ejemplo.php configuracion/configuracion.php
 */

return [

    // Entorno: 'desarrollo' muestra los errores en pantalla,
    // 'produccion' los escribe unicamente en la bitacora.
    'entorno' => 'desarrollo',

    // Direccion publica desde la que se sirve la aplicacion, sin barra final.
    // Es la que se codifica dentro del codigo QR de la carta.
    //   Prototipo:  https://adso.menu08.com
    //   Local:      http://localhost/Menu08/publico
    'url_base' => 'http://localhost/Menu08/publico',

    'base_datos' => [
        'servidor'    => 'localhost',
        'puerto'      => 3306,
        'nombre'      => 'menu08',
        'usuario'     => '',
        'contrasena'  => '',
        'cotejamiento'=> 'utf8mb4_unicode_ci',
    ],

    'sesion' => [
        'nombre'          => 'menu08_sesion',
        'vida_minutos'    => 120,
        // Poner en true cuando el sitio se sirva por HTTPS.
        'solo_https'      => false,
    ],

    'subidas' => [
        'ruta'            => __DIR__ . '/../almacenamiento/subidas',
        'tamano_maximo'   => 2 * 1024 * 1024,   // 2 MB
        'tipos_permitidos'=> ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'bitacora' => [
        'ruta' => __DIR__ . '/../almacenamiento/bitacora',
    ],
];
