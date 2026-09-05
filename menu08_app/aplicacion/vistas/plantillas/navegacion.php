<?php

declare(strict_types=1);

use Menu08\Controladores\CajaControlador;
use Menu08\Controladores\PanelControlador;
use Menu08\Controladores\SvpControlador;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Vista;

/**
 * Navegacion del panel: los tres modulos, la seccion activa resaltada y el
 * colapso por debajo de 768 px.
 *
 * Los roles NO se escriben aqui: salen de la constante de cada controlador, que
 * es la misma que usa exigirRol(). Con una copia propia, cualquier cambio de
 * permisos dejaria enlaces que llevan a un 403, y el usuario no entiende por que
 * el menu le ofrece algo que no puede abrir.
 */

$rol = Sesion::rol();

/** Ruta pedida, ya sin el prefijo de url_base, para poder compararla. */
$base   = rtrim((string) parse_url(Vista::url('/'), PHP_URL_PATH), '/');
$actual = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($base !== '' && str_starts_with($actual, $base)) {
    $actual = substr($actual, strlen($base));
}

$actual = '/' . ltrim($actual, '/');

$modulos = [
    ['ruta' => '/panel', 'rotulo' => 'CARTA', 'descripcion' => 'Catalogo y paradas', 'roles' => PanelControlador::ROLES],
    ['ruta' => '/caja',  'rotulo' => 'CAJA',  'descripcion' => 'Turno y ventas',     'roles' => CajaControlador::ROLES],
    ['ruta' => '/svp',   'rotulo' => 'SVP',   'descripcion' => 'Tablero de produccion', 'roles' => SvpControlador::ROLES],
];

$visibles = array_values(array_filter(
    $modulos,
    static fn (array $m): bool => in_array($rol, $m['roles'], true)
));

if ($visibles === []) {
    return;
}
?>
<nav class="navegacion sin-impresion" id="navegacion-panel" aria-label="Modulos">
    <ul class="navegacion-lista contenedor-ancho">
        <?php foreach ($visibles as $m) : ?>
            <?php
            // Activa si la ruta pedida es la del modulo o cuelga de ella:
            // /panel/productos resalta CARTA igual que /panel.
            $activa = $actual === $m['ruta'] || str_starts_with($actual, $m['ruta'] . '/');
            ?>
            <li>
                <a class="navegacion-enlace<?= $activa ? ' navegacion-activa' : '' ?>"
                   href="<?= Vista::e(Vista::url($m['ruta'])) ?>"
                   <?= $activa ? 'aria-current="page"' : '' ?>>
                    <span class="navegacion-rotulo"><?= Vista::e($m['rotulo']) ?></span>
                    <span class="navegacion-descripcion"><?= Vista::e($m['descripcion']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
