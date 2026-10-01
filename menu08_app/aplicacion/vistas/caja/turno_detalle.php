<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Detalle de un turno de CAJA: quien lo llevo, cuando, cuanto vendio y, si ya
 * se cerro, como cuadro.
 *
 * Es donde cae el cajero justo despues de cerrar el turno, asi que lo primero
 * que se lee es el resultado del cuadre. Nunca solo por el color: fondo, icono
 * y palabra, igual que en la pantalla de cierre.
 *
 * @var array<string, mixed> $turno
 * @var array<string, mixed> $resumen
 */
$peso = static fn (mixed $n): string => $n === null ? '—' : '$ ' . number_format((float) $n, 0, ',', '.');

/** La base devuelve 2026-09-12 10:11:57; aqui se lee 12/09/2026 · 10:11. */
$cuando = static function (mixed $marca): string {
    if ($marca === null || $marca === '') {
        return '—';
    }

    $t = strtotime((string) $marca);

    return $t === false ? (string) $marca : date('d/m/Y · H:i', $t);
};

$icono = static fn (string $trazos, string $clase = ''): string => sprintf(
    '<svg%s viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
    . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
    $clase === '' ? '' : ' class="' . Vista::e($clase) . '"',
    $trazos
);

$trazoReloj  = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
$trazoCheck  = '<path d="m5 12.5 4.5 4.5L19 7"/>';
$trazoArriba = '<path d="M12 19V6"/><path d="m6 12 6-6 6 6"/>';
$trazoAbajo  = '<path d="M12 5v13"/><path d="m18 12-6 6-6-6"/>';
$trazoAtras  = '<path d="M19 12H5"/><path d="m11 6-6 6 6 6"/>';
$trazoSigue  = '<path d="m9 6 6 6-6 6"/>';

$cerrado = (string) $turno['estado'] === 'cerrado';
$d       = (float) ($turno['diferencia'] ?? 0);

/** Como cuadro: la clase de la etiqueta, la palabra y el icono. */
$cuadre = $d > 0
    ? ['etiqueta-sobrante', 'Sobrante', $trazoArriba]
    : ($d < 0
        ? ['etiqueta-faltante', 'Faltante', $trazoAbajo]
        : ['etiqueta-cuadra', 'Cuadra', $trazoCheck]);
?>
<div class="pila pila-5">

    <header class="pila pila-2">
        <p class="migas">
            <a href="<?= Vista::e(Vista::url('/caja')) ?>">Caja</a>
            <?= $icono($trazoSigue) ?>
            <a href="<?= Vista::e(Vista::url('/caja/turnos')) ?>">Turnos</a>
            <?= $icono($trazoSigue) ?>
            <span>Turno #<?= (int) $turno['id'] ?></span>
        </p>

        <div class="fila fila-entre">
            <div class="fila">
                <h1>Turno #<?= (int) $turno['id'] ?></h1>

                <?php if ($cerrado) : ?>
                    <span class="etiqueta etiqueta-entregada"><?= $icono($trazoCheck, 'etiqueta-icono') ?>cerrado</span>
                <?php else : ?>
                    <span class="etiqueta etiqueta-turno"><?= $icono($trazoReloj, 'etiqueta-icono') ?><?= Vista::e((string) $turno['estado']) ?></span>
                <?php endif; ?>
            </div>

            <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/caja/turnos')) ?>">
                <?= $icono($trazoAtras, 'boton-icono') ?>Volver a los turnos
            </a>
        </div>

        <p class="texto-apagado numerica">
            <?= Vista::e($turno['cajero']) ?> ·
            abierto el <?= Vista::e($cuando($turno['abierto_en'])) ?>
            <?php if ($turno['cerrado_en'] !== null) : ?>
                · cerrado el <?= Vista::e($cuando($turno['cerrado_en'])) ?>
            <?php endif; ?>
        </p>
    </header>

    <?php if ($cerrado) : ?>
        <?php // El cuadre va primero: es lo que viene a leer quien acaba de cerrar. ?>
        <section class="tarjeta tarjeta-contorno panel-grupo" aria-labelledby="cuadre-titulo">
            <div class="fila fila-entre">
                <h2 class="tarjeta-titulo" id="cuadre-titulo">Cuadre</h2>
                <span class="etiqueta <?= Vista::e($cuadre[0]) ?>">
                    <?= $icono($cuadre[2], 'etiqueta-icono') ?><?= Vista::e($cuadre[1]) ?>
                </span>
            </div>

            <dl class="datos">
                <div>
                    <dt>Diferencia</dt>
                    <dd class="datos-destacado">
                        <?= Vista::e($peso($turno['diferencia'])) ?>
                        <span class="solo-lectores">
                            <?= $d > 0 ? '(sobrante)' : ($d < 0 ? '(faltante)' : '(cuadra)') ?>
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Esperado en caja</dt>
                    <dd><?= Vista::e($peso((float) $turno['base_inicial'] + (float) $turno['total_ventas'])) ?></dd>
                </div>
                <div>
                    <dt>Conteo físico</dt>
                    <dd><?= Vista::e($peso($turno['total_declarado'])) ?></dd>
                </div>
            </dl>
        </section>
    <?php endif; ?>

    <?= Vista::renderizar('caja/_resumen', ['turno' => $turno, 'resumen' => $resumen]) ?>
</div>
