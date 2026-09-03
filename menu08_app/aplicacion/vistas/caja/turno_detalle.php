<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * @var array<string, mixed> $turno
 * @var array<string, mixed> $resumen
 */
$peso = static fn (mixed $n): string => $n === null ? '—' : '$ ' . number_format((float) $n, 0, ',', '.');
?>
<h1>Turno #<?= (int) $turno['id'] ?></h1>

<p>
    <?= Vista::e($turno['cajero']) ?> ·
    abierto el <?= Vista::e((string) $turno['abierto_en']) ?>
    <?php if ($turno['cerrado_en'] !== null) : ?>
        · cerrado el <?= Vista::e((string) $turno['cerrado_en']) ?>
    <?php endif; ?>
    · <?= Vista::e((string) $turno['estado']) ?>
</p>

<?= Vista::renderizar('caja/_resumen', ['turno' => $turno, 'resumen' => $resumen]) ?>

<?php if ((string) $turno['estado'] === 'cerrado') : ?>
    <h3>Cuadre</h3>
    <table>
        <tr><th>Esperado en caja</th>
            <td><?= Vista::e($peso((float) $turno['base_inicial'] + (float) $turno['total_ventas'])) ?></td></tr>
        <tr><th>Conteo fisico</th><td><?= Vista::e($peso($turno['total_declarado'])) ?></td></tr>
        <tr><th>Diferencia</th>
            <td><strong><?= Vista::e($peso($turno['diferencia'])) ?></strong>
                <?php $d = (float) $turno['diferencia']; ?>
                <?= $d > 0 ? '(sobrante)' : ($d < 0 ? '(faltante)' : '(cuadra)') ?>
            </td></tr>
    </table>
<?php endif; ?>

<p><a href="<?= Vista::e(Vista::url('/caja/turnos')) ?>">Volver a los turnos</a></p>
