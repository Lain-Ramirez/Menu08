<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * @var list<array<string, mixed>> $turnos
 */
$peso = static fn (mixed $n): string => $n === null ? '—' : '$ ' . number_format((float) $n, 0, ',', '.');
?>
<h1>Turnos de caja</h1>

<?php if ($turnos === []) : ?>
    <p>Todavia no se ha abierto ningun turno.</p>
<?php else : ?>
    <table>
        <thead>
            <tr><th>#</th><th>Cajero</th><th>Abierto</th><th>Cerrado</th>
                <th>Ordenes</th><th>Vendido</th><th>Diferencia</th><th>Estado</th></tr>
        </thead>
        <tbody>
        <?php foreach ($turnos as $t) : ?>
            <tr>
                <td><a href="<?= Vista::e(Vista::url('/caja/turnos/' . $t['id'])) ?>"><?= (int) $t['id'] ?></a></td>
                <td><?= Vista::e($t['cajero']) ?></td>
                <td><?= Vista::e((string) $t['abierto_en']) ?></td>
                <td><?= Vista::e((string) ($t['cerrado_en'] ?? '—')) ?></td>
                <td><?= (int) $t['ordenes'] ?></td>
                <td><?= Vista::e($peso($t['total_ventas'])) ?></td>
                <td><?= Vista::e($peso($t['diferencia'])) ?></td>
                <td><?= Vista::e((string) $t['estado']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="<?= Vista::e(Vista::url('/caja')) ?>">Volver a caja</a></p>
