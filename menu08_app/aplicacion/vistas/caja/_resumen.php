<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Bloque de resumen del turno, de la pantalla de detalle.
 *
 * El cierre dejo de usarlo con el #19: alli el resumen se maqueta con las
 * clases de turno.css. Esta version es la de caja/turno_detalle.php, y se
 * escribe con las piezas del catalogo —tarjeta, ficha de datos y tabla—, asi
 * que no necesita ninguna hoja propia.
 *
 * @var array{total: string, ordenes: int, unidades: int, medios: list<array<string, mixed>>} $resumen
 * @var array<string, mixed> $turno
 */
$peso = static fn (mixed $n): string => '$ ' . number_format((float) $n, 0, ',', '.');

/**
 * Medios de pago. Los mismos tres que admite Orden::MEDIOS; el icono acompana
 * al nombre igual que en la pantalla de cierre.
 */
$trazosMedio = [
    'efectivo'      => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
    'tarjeta'       => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'transferencia' => '<path d="M4 8h13"/><path d="m14 5 3 3-3 3"/><path d="M20 16H7"/><path d="m10 13-3 3 3 3"/>',
];
?>
<section class="tarjeta tarjeta-contorno panel-grupo" aria-labelledby="resumen-titulo">
    <h2 class="tarjeta-titulo" id="resumen-titulo">Resumen</h2>

    <dl class="datos">
        <div>
            <dt>Total vendido</dt>
            <dd class="datos-destacado"><?= Vista::e($peso($resumen['total'])) ?></dd>
        </div>
        <div>
            <dt>Base inicial</dt>
            <dd><?= Vista::e($peso($turno['base_inicial'])) ?></dd>
        </div>
        <div>
            <dt>Esperado en caja</dt>
            <dd><?= Vista::e($peso((float) $turno['base_inicial'] + (float) $resumen['total'])) ?></dd>
        </div>
        <div>
            <dt>Órdenes</dt>
            <dd><?= (int) $resumen['ordenes'] ?></dd>
        </div>
        <div>
            <dt>Unidades despachadas</dt>
            <dd><?= (int) $resumen['unidades'] ?></dd>
        </div>
    </dl>
</section>

<section class="pila pila-3" aria-labelledby="medios-titulo">
    <h2 id="medios-titulo">Por medio de pago</h2>

    <?php if ($resumen['medios'] === []) : ?>
        <p class="texto-apagado">El turno todavía no tiene órdenes.</p>
    <?php else : ?>
        <div class="tabla-envoltura">
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Medio</th>
                        <th scope="col" class="cifra">Órdenes</th>
                        <th scope="col" class="cifra">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resumen['medios'] as $m) : ?>
                    <?php $clave = (string) $m['medio_pago']; ?>
                    <tr>
                        <td>
                            <span class="fila fila-2">
                                <?php if (isset($trazosMedio[$clave])) : ?>
                                    <svg class="etiqueta-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                         aria-hidden="true"><?= $trazosMedio[$clave] ?></svg>
                                <?php endif; ?>
                                <?= Vista::e(ucfirst($clave)) ?>
                            </span>
                        </td>
                        <td class="cifra"><?= (int) $m['ordenes'] ?></td>
                        <td class="cifra"><?= Vista::e($peso($m['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">Total del turno</th>
                        <th class="cifra"><?= (int) $resumen['ordenes'] ?></th>
                        <th class="cifra"><?= Vista::e($peso($resumen['total'])) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>
