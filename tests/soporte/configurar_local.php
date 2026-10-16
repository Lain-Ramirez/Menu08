<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = $root . '/menu08_app/configuracion/configuracion.ejemplo.php';
$target = $root . '/menu08_app/configuracion/configuracion.php';
$config = file_get_contents($source);
if ($config === false) {
    fwrite(STDERR, "No se pudo leer la plantilla de configuración.\n");
    exit(1);
}
$config = str_replace(
    ["'entorno' => 'desarrollo'", "'http://localhost/Menu08/publico'", "'servidor'    => 'localhost'", "'puerto'      => 3306", "'usuario'     => ''", "'contrasena'  => ''"],
    ["'entorno' => 'produccion'", "'http://127.0.0.1:8088'", "'servidor'    => '127.0.0.1'", "'puerto'      => 13306", "'usuario'     => 'root'", "'contrasena'  => 'Menu08LocalQA2026'"],
    $config
);
if (file_put_contents($target, $config) === false) {
    fwrite(STDERR, "No se pudo crear la configuración local.\n");
    exit(1);
}
@mkdir(__DIR__ . '/../soporte/sesiones', 0775, true);
echo "Configuración local lista (PHP 8088, MySQL 13306).\n";
