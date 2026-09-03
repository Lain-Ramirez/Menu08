<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * @var array<string, mixed> $usuario
 * @var array<string, mixed> $turno
 * @var array<string, mixed> $resumen
 */
?>
<h1>Caja</h1>

<p>
    Turno #<?= (int) $turno['id'] ?> abierto por <?= Vista::e($turno['cajero']) ?>
    el <?= Vista::e((string) $turno['abierto_en']) ?>.
</p>

<?= Vista::renderizar('caja/_resumen', ['turno' => $turno, 'resumen' => $resumen]) ?>

<p class="aviso aviso-aviso">
    El armado de la orden se construye en su propio issue. Por ahora el turno ya
    esta abierto y listo para recibir ventas.
</p>

<p>
    <a href="<?= Vista::e(Vista::url('/caja/turno')) ?>">Cerrar turno</a> ·
    <a href="<?= Vista::e(Vista::url('/caja/turnos')) ?>">Turnos anteriores</a>
</p>
