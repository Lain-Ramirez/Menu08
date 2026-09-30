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

/**
 * Cada modulo lleva su icono: la carta, la caja y la plancha. Son trazos sobre
 * reticula de 24, como el resto de la aplicacion, y son decorativos —el rotulo
 * de al lado ya nombra el modulo—, asi que van con aria-hidden.
 */
$modulos = [
    [
        'ruta' => '/panel', 'rotulo' => 'CARTA', 'descripcion' => 'Catalogo y paradas',
        'roles' => PanelControlador::ROLES,
        'icono' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6"/><path d="M9 12h6"/>',
    ],
    [
        'ruta' => '/caja', 'rotulo' => 'CAJA', 'descripcion' => 'Turno y ventas',
        'roles' => CajaControlador::ROLES,
        'icono' => '<rect x="3" y="11" width="18" height="9" rx="2"/><path d="M7 11V5h8v6"/>'
                 . '<path d="M10 8h2"/><path d="M8 15.5h.01"/><path d="M12 15.5h.01"/><path d="M16 15.5h.01"/>',
    ],
    [
        'ruta' => '/svp', 'rotulo' => 'SVP', 'descripcion' => 'Tablero de produccion',
        'roles' => SvpControlador::ROLES,
        'icono' => '<rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="9.5" y="4" width="5" height="11" rx="1.5"/>'
                 . '<rect x="16" y="4" width="5" height="7" rx="1.5"/>',
    ],
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
                    <span class="navegacion-icono" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round"><?= $m['icono'] ?></svg>
                    </span>
                    <span class="navegacion-textos">
                        <span class="navegacion-rotulo"><?= Vista::e($m['rotulo']) ?></span>
                        <span class="navegacion-descripcion"><?= Vista::e($m['descripcion']) ?></span>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
