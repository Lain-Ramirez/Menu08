<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Historial de turnos de CAJA.
 *
 * Una fila por turno, del mas reciente al mas antiguo, con lo que se busca al
 * volver sobre uno: quien lo llevo, cuanto vendio y si cuadro. La diferencia no
 * se lee solo por el signo ni solo por el color: lleva la palabra —cuadra,
 * sobrante, faltante— y su icono, igual que en la pantalla de cierre.
 *
 * @var list<array<string, mixed>> $turnos
 */
$peso = static fn (mixed $n): string => $n === null ? '—' : '$ ' . number_format((float) $n, 0, ',', '.');

/** La base devuelve 2026-09-12 10:11:57; en la tabla se lee 12/09/2026 · 10:11.
    Lo que no sea una fecha se imprime tal cual: mejor un dato crudo que un hueco. */
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
?>
<div class="pila pila-5">

    <header class="fila fila-entre">
        <h1>Turnos de caja</h1>
        <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/caja')) ?>">
            <?= $icono($trazoAtras, 'boton-icono') ?>Volver a caja
        </a>
    </header>

    <?php if ($turnos === []) : ?>
        <section class="estado-vacio">
            <span class="estado-vacio-icono" aria-hidden="true"><?= $icono($trazoReloj) ?></span>
            <h2 class="estado-vacio-titulo">Todavia no se ha abierto ningun turno</h2>
            <p class="estado-vacio-texto">
                Cuando se abra y se cierre el primero, aquí queda su resumen: órdenes, ventas y cuadre.
            </p>
            <p class="estado-vacio-acciones">
                <a class="boton boton-relleno" href="<?= Vista::e(Vista::url('/caja/turno')) ?>">Abrir turno</a>
            </p>
        </section>
    <?php else : ?>
        <div class="tabla-envoltura">
            <table class="tabla tabla-apilable">
                <caption class="solo-lectores">Turnos de caja, del más reciente al más antiguo</caption>
                <thead>
                    <tr>
                        <th scope="col" class="columna-minima">Turno</th>
                        <th scope="col">Cajero</th>
                        <th scope="col">Abierto</th>
                        <th scope="col">Cerrado</th>
                        <th scope="col" class="cifra">Ordenes</th>
                        <th scope="col" class="cifra">Vendido</th>
                        <th scope="col" class="cifra">Diferencia</th>
                        <th scope="col" class="columna-minima">Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($turnos as $t) : ?>
                    <?php
                    $abierto = (string) $t['estado'] === 'abierto';
                    $d       = $t['diferencia'] === null ? null : (float) $t['diferencia'];
                    ?>
                    <tr>
                        <td data-etiqueta="Turno" class="columna-minima">
                            <a class="tabla-enlace" href="<?= Vista::e(Vista::url('/caja/turnos/' . $t['id'])) ?>">
                                #<?= (int) $t['id'] ?>
                            </a>
                        </td>
                        <td data-etiqueta="Cajero"><?= Vista::e($t['cajero']) ?></td>
                        <td data-etiqueta="Abierto" class="numerica"><?= Vista::e($cuando($t['abierto_en'])) ?></td>
                        <td data-etiqueta="Cerrado" class="numerica"><?= Vista::e($cuando($t['cerrado_en'] ?? null)) ?></td>
                        <td data-etiqueta="Ordenes" class="cifra"><?= (int) $t['ordenes'] ?></td>
                        <td data-etiqueta="Vendido" class="cifra"><?= Vista::e($peso($t['total_ventas'])) ?></td>
                        <td data-etiqueta="Diferencia" class="cifra"><?= Vista::e($peso($t['diferencia'])) ?></td>
                        <td data-etiqueta="Estado" class="columna-minima">
                            <?php if ($abierto) : ?>
                                <span class="etiqueta etiqueta-turno"><?= $icono($trazoReloj, 'etiqueta-icono') ?>Abierto</span>
                            <?php elseif ($d === null) : ?>
                                <span class="etiqueta etiqueta-entregada"><?= $icono($trazoCheck, 'etiqueta-icono') ?>Cerrado</span>
                            <?php elseif ($d > 0) : ?>
                                <span class="etiqueta etiqueta-sobrante"><?= $icono($trazoArriba, 'etiqueta-icono') ?>Sobrante</span>
                            <?php elseif ($d < 0) : ?>
                                <span class="etiqueta etiqueta-faltante"><?= $icono($trazoAbajo, 'etiqueta-icono') ?>Faltante</span>
                            <?php else : ?>
                                <span class="etiqueta etiqueta-cuadra"><?= $icono($trazoCheck, 'etiqueta-icono') ?>Cuadra</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
