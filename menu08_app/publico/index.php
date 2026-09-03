<?php

declare(strict_types=1);

/**
 * Menu08 - Front controller.
 *
 * Unica puerta de entrada de la aplicacion. Las reglas de publico/.htaccess
 * mandan aqui toda peticion que no corresponda a un archivo existente.
 */

use Menu08\Nucleo\Configuracion;
use Menu08\Nucleo\Enrutador;
use Menu08\Nucleo\ManejadorErrores;
use Menu08\Nucleo\Sesion;

$raiz = dirname(__DIR__);

/**
 * Servidor integrado de PHP (php -S localhost:8000 -t publico publico/index.php):
 * los archivos que existen los sirve el propio servidor, no el front controller.
 */
if (PHP_SAPI === 'cli-server') {
    $solicitado = __DIR__ . (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');

    if (is_file($solicitado)) {
        return false;
    }
}

/**
 * Autocarga por convencion, sin gestor de dependencias:
 *   Menu08\Nucleo\Enrutador               -> aplicacion/nucleo/Enrutador.php
 *   Menu08\Controladores\CartaControlador -> aplicacion/controladores/CartaControlador.php
 *   Menu08\Modelos\FoodTruck              -> aplicacion/modelos/FoodTruck.php
 */
spl_autoload_register(static function (string $clase) use ($raiz): void {
    $prefijo = 'Menu08\\';

    if (!str_starts_with($clase, $prefijo)) {
        return;
    }

    $carpetas = [
        'Nucleo'        => 'nucleo',
        'Controladores' => 'controladores',
        'Modelos'       => 'modelos',
    ];

    $partes  = explode('\\', substr($clase, strlen($prefijo)));
    $espacio = array_shift($partes);

    if (!isset($carpetas[$espacio]) || $partes === []) {
        return;
    }

    $archivo = $raiz . '/aplicacion/' . $carpetas[$espacio] . '/' . implode('/', $partes) . '.php';

    if (is_file($archivo)) {
        require $archivo;
    }
});

// Se registra antes que nada: a partir de aqui cualquier fallo queda en la bitacora.
ManejadorErrores::registrar();

Configuracion::cargar($raiz . '/configuracion/configuracion.php');

// La sesion se abre antes de despachar: las vistas y los controladores
// consultan el usuario autenticado desde el primer momento.
Sesion::iniciar();

$enrutador = new Enrutador();

require $raiz . '/configuracion/rutas.php';

/**
 * Ruta solicitada. Se descuenta la subcarpeta de instalacion para que la
 * aplicacion funcione igual en la raiz del dominio que en http://host/Menu08/publico.
 */
$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$base   = str_ends_with($script, '.php')
    ? rtrim(str_replace('\\', '/', dirname($script)), '/')
    : '';
$ruta = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');

if ($base !== '' && str_starts_with($ruta, $base)) {
    $ruta = substr($ruta, strlen($base));
}

$ruta = '/' . trim(rawurldecode($ruta), '/');

$enrutador->despachar((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), $ruta);
