<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Comprobante imprimible. Estrecho a proposito: sale en una impresora termica
 * de rollo, que es lo que lleva un food truck.
 *
 * @var array<string, mixed>       $orden
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>|null  $truck
 */
$peso = static fn (mixed $n): string => '$ ' . number_format((float) $n, 0, ',', '.');
?>
<article class="comprobante">
    <header>
        <h1><?= Vista::e($truck['nombre'] ?? 'Menu08') ?></h1>
        <?php if (!empty($truck['ciudad'])) : ?>
            <p><?= Vista::e($truck['ciudad']) ?></p>
        <?php endif; ?>
        <p class="comprobante-numero">Orden <?= Vista::e($orden['numero']) ?></p>
        <p><?= Vista::e((string) $orden['creado_en']) ?></p>
    </header>

    <table>
        <thead><tr><th>Producto</th><th>Cant.</th><th>Valor</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i) : ?>
            <tr>
                <td>
                    <?= Vista::e($i['nombre_producto']) ?><br>
                    <small><?= Vista::e($peso($i['precio_unitario'])) ?> c/u</small>
                </td>
                <td><?= (int) $i['cantidad'] ?></td>
                <td><?= Vista::e($peso($i['subtotal'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">Total</th>
                <th class="comprobante-total"><?= Vista::e($peso($orden['total'])) ?></th>
            </tr>
        </tfoot>
    </table>

    <p>
        Medio de pago: <?= Vista::e(ucfirst((string) $orden['medio_pago'])) ?><br>
        Estado: <?= Vista::e($orden['estado']) ?>
    </p>

    <?php if (!empty($orden['nota'])) : ?>
        <p><strong>Nota:</strong> <?= Vista::e($orden['nota']) ?></p>
    <?php endif; ?>

    <p class="comprobante-gracias">Gracias por su compra</p>
</article>

<p class="sin-impresion">
    <a href="<?= Vista::e(Vista::url('/caja')) ?>">Volver a caja</a> ·
    <a href="#" onclick="window.print();return false;">Imprimir</a>
</p>
