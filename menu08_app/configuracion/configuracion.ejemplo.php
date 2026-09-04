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

    // Zona horaria de la aplicacion. Fija el reloj de PHP y el de la sesion de
    // MySQL, de la que sale NOW(). De ella depende cual es la parada vigente.
    'zona_horaria' => 'America/Bogota',

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
        'ruta'            => __DIR__ . '/../../ADSO.menu08.com/subidas',
        'tamano_maximo'   => 2 * 1024 * 1024,   // 2 MB
        'tipos_permitidos'=> ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'bitacora' => [
        'ruta' => __DIR__ . '/../almacenamiento/bitacora',
    ],
];
