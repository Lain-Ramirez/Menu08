<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Bloque de resumen del turno, de la pantalla de detalle.
 *
 * El cierre dejo de usarlo con el #19: alli el resumen se maqueta con las
 * clases de turno.css —tarjeta, cifras y tabla con iconos—, y esta version en
 * tablas sueltas se queda para caja/turno_detalle.php hasta que le toque su
 * remaquetado.
 *
 * @var array{total: string, ordenes: int, unidades: int, medios: list<array<string, mixed>>} $resumen
 * @var array<string, mixed> $turno
 */
$peso = static fn (mixed $n): string => '$ ' . number_format((float) $n, 0, ',', '.');
?>
<table>
    <tr><th>Base inicial</th><td><?= Vista::e($peso($turno['base_inicial'])) ?></td></tr>
    <tr><th>Ordenes</th><td><?= (int) $resumen['ordenes'] ?></td></tr>
    <tr><th>Unidades despachadas</th><td><?= (int) $resumen['unidades'] ?></td></tr>
    <tr><th>Total vendido</th><td><strong><?= Vista::e($peso($resumen['total'])) ?></strong></td></tr>
    <tr><th>Esperado en caja</th>
        <td><strong><?= Vista::e($peso((float) $turno['base_inicial'] + (float) $resumen['total'])) ?></strong></td></tr>
</table>

<h3>Por medio de pago</h3>

<?php if ($resumen['medios'] === []) : ?>
    <p>El turno todavia no tiene ordenes.</p>
<?php else : ?>
    <table>
        <thead><tr><th>Medio</th><th>Ordenes</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($resumen['medios'] as $m) : ?>
            <tr>
                <td><?= Vista::e(ucfirst((string) $m['medio_pago'])) ?></td>
                <td><?= (int) $m['ordenes'] ?></td>
                <td><?= Vista::e($peso($m['total'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
