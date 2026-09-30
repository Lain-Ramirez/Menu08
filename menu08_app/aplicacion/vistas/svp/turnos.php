<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Pantalla publica de turnos para la ventanilla del food truck.
 *
 * Se proyecta o cuelga en una pantalla exterior mirando hacia la fila de clientes.
 * Muestra UNICAMENTE el numero de turno repartido en dos columnas:
 *   - «En preparacion»: pedidos que la cocina ya empezo a preparar.
 *   - «Listos»: pedidos listos para entrega en la ventanilla.
 *
 * No expone ningun dato privado: ni productos, ni importes, ni medios de pago,
 * ni notas de clientes. Solo el nombre y logo del food truck y los numeros de turno.
 *
 * Las cifras estan dimensionadas para leerse comodamente a tres metros de distancia
 * en una pantalla de 24 pulgadas, con contraste reforzado (WCAG AAA).
 *
 * @var array<string, mixed>       $foodTruck datos del truck (nombre, slug, logo)
 * @var int|null                   $turno     turno abierto, o null si esta cerrado
 * @var list<array<string, mixed>> $ordenes   ordenes publicas (numero, estado)
 * @var string                     $ahora     reloj del servidor al pintar
 */

$icono = static fn (string $trazos, string $clase = ''): string => sprintf(
    '<svg%s viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
    . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
    $clase === '' ? '' : ' class="' . Vista::e($clase) . '"',
    $trazos
);

$trazoReloj      = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
$trazoAlerta     = '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/><path d="M12 10v3.5"/><path d="M12 17h.01"/>';
$trazoCheck      = '<path d="m5 12.5 4.5 4.5L19 7"/>';
$trazoFuego      = '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 3.5z"/>';
$trazoPantalla   = '<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>';

$preparacion = [];
$listas      = [];

foreach ($ordenes as $o) {
    if ($o['estado'] === 'en_preparacion') {
        $preparacion[] = $o;
    } elseif ($o['estado'] === 'lista') {
        $listas[] = $o;
    }
}

$hayTurno = $turno !== null;
?>
<div class="turnos-pantalla"
     data-turnos
     data-turnos-slug="<?= Vista::e((string) $foodTruck['slug']) ?>"
     data-turnos-servicio="<?= Vista::e(Vista::url('/turnos/' . $foodTruck['slug'] . '/ordenes')) ?>">

    <header class="turnos-cabecera">
        <div class="turnos-identidad">
            <?php if (!empty($foodTruck['logo'])) : ?>
                <img src="<?= Vista::e(Vista::url('/subidas/' . $foodTruck['logo'])) ?>"
                     alt="<?= Vista::e((string) $foodTruck['nombre']) ?>"
                     class="turnos-logo">
            <?php endif; ?>
            <div class="turnos-titulos">
                <h1 class="turnos-negocio"><?= Vista::e((string) $foodTruck['nombre']) ?></h1>
                <span class="turnos-subtitulo">Turnos de atención</span>
            </div>
        </div>

        <div class="turnos-controles">
            <span class="turnos-latido" data-turnos-latido>
                <span class="turnos-latido-punto" aria-hidden="true"></span>
                <span data-turnos-latido-texto>Actualizado ahora</span>
            </span>

            <button type="button" class="boton boton-tonal turnos-btn-pantalla" data-turnos-btn-pantalla title="Pantalla completa">
                <?= $icono($trazoPantalla, 'turnos-icono-btn') ?>
                <span class="turnos-texto-btn" data-turnos-btn-texto>Pantalla completa</span>
            </button>
        </div>
    </header>

    <div class="aviso aviso-error turnos-aviso-red" role="alert" data-turnos-aviso hidden>
        <?= $icono($trazoAlerta, 'aviso-icono') ?>
        <p data-turnos-aviso-texto></p>
    </div>

    <div class="turnos-vacio-cerrado" data-turnos-cerrado<?= $hayTurno ? ' hidden' : '' ?>>
        <div class="turnos-vacio-icono">
            <?= $icono($trazoReloj) ?>
        </div>
        <h2 class="turnos-vacio-titulo">Ventanilla cerrada</h2>
        <p class="turnos-vacio-texto">
            En este momento el food truck no tiene un turno de atención abierto.
            Los turnos aparecerán automáticamente al abrir la caja.
        </p>
    </div>

    <div class="turnos-columnas" data-turnos-columnas<?= $hayTurno ? '' : ' hidden' ?>>
        <!-- Columna 1: En preparación -->
        <section class="turnos-columna turnos-columna-preparacion"
                 data-turnos-columna="en_preparacion"
                 aria-labelledby="titulo-preparacion">
            <header class="turnos-columna-cabecera">
                <div class="turnos-columna-titulo-grupo">
                    <span class="turnos-columna-icono"><?= $icono($trazoFuego) ?></span>
                    <h2 class="turnos-columna-titulo" id="titulo-preparacion">En preparación</h2>
                </div>
                <span class="turnos-columna-cuenta" data-turnos-cuenta="en_preparacion">
                    <?= count($preparacion) ?>
                </span>
            </header>

            <ul class="turnos-lista" data-turnos-lista="en_preparacion">
                <?php foreach ($preparacion as $o) : ?>
                    <li class="turno-tarjeta turno-tarjeta-preparacion" data-turno-numero="<?= Vista::e((string) $o['numero']) ?>">
                        <span class="turno-numero"><?= Vista::e((string) $o['numero']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="turnos-columna-vacia" data-turnos-vacia="en_preparacion"<?= $preparacion === [] ? '' : ' hidden' ?>>
                Sin órdenes en preparación
            </p>
        </section>

        <!-- Columna 2: Listos -->
        <section class="turnos-columna turnos-columna-lista"
                 data-turnos-columna="lista"
                 aria-labelledby="titulo-lista">
            <header class="turnos-columna-cabecera">
                <div class="turnos-columna-titulo-grupo">
                    <span class="turnos-columna-icono"><?= $icono($trazoCheck) ?></span>
                    <h2 class="turnos-columna-titulo" id="titulo-lista">Listos para entrega</h2>
                </div>
                <span class="turnos-columna-cuenta" data-turnos-cuenta="lista">
                    <?= count($listas) ?>
                </span>
            </header>

            <ul class="turnos-lista" data-turnos-lista="lista">
                <?php foreach ($listas as $o) : ?>
                    <li class="turno-tarjeta turno-tarjeta-lista" data-turno-numero="<?= Vista::e((string) $o['numero']) ?>">
                        <span class="turno-numero"><?= Vista::e((string) $o['numero']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="turnos-columna-vacia" data-turnos-vacia="lista"<?= $listas === [] ? '' : ' hidden' ?>>
                Esperando pedidos listos
            </p>
        </section>
    </div>

    <!-- Plantillas para clonar desde JavaScript sin escribir marcado -->
    <template data-turnos-plantilla="en_preparacion">
        <li class="turno-tarjeta turno-tarjeta-preparacion" data-turno-numero="">
            <span class="turno-numero"></span>
        </li>
    </template>

    <template data-turnos-plantilla="lista">
        <li class="turno-tarjeta turno-tarjeta-lista" data-turno-numero="">
            <span class="turno-numero"></span>
        </li>
    </template>
</div>
