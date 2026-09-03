<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * @var array<string, mixed>|null $turno
 * @var array<string, mixed>|null $resumen
 * @var array<string, string>     $errores
 */
$e = static fn (string $c): string => isset($errores[$c])
    ? '<span class="error-campo">' . Vista::e($errores[$c]) . '</span>' : '';
?>
<?php if ($turno === null) : ?>

    <h1>Abrir turno</h1>

    <p>El turno agrupa las ventas de la jornada. Sin turno abierto no se puede vender.</p>

    <form method="post" action="<?= Vista::e(Vista::url('/caja/turno/abrir')) ?>" novalidate>
        <?= Csrf::campo() ?>

        <p>
            <label for="base_inicial">Base inicial en caja</label><br>
            <input type="text" id="base_inicial" name="base_inicial" inputmode="decimal" required
                   value="<?= Vista::e($_POST['base_inicial'] ?? '') ?>"><br>
            <small>El efectivo con el que arranca la jornada. Por ejemplo 50.000.</small><br><?= $e('base_inicial') ?>
        </p>

        <p><button type="submit">Abrir turno</button></p>
    </form>

<?php else : ?>

    <h1>Cerrar turno</h1>

    <p>
        Turno #<?= (int) $turno['id'] ?>, abierto por <?= Vista::e($turno['cajero']) ?>
        el <?= Vista::e((string) $turno['abierto_en']) ?>.
    </p>

    <?php if ($resumen !== null) : ?>
        <?= Vista::renderizar('caja/_resumen', ['turno' => $turno, 'resumen' => $resumen]) ?>
    <?php endif; ?>

    <form method="post" action="<?= Vista::e(Vista::url('/caja/turno/cerrar')) ?>" novalidate>
        <?= Csrf::campo() ?>

        <p>
            <label for="total_declarado">Conteo fisico de la caja</label><br>
            <input type="text" id="total_declarado" name="total_declarado" inputmode="decimal" required
                   value="<?= Vista::e($_POST['total_declarado'] ?? '') ?>"><br>
            <small>Lo que hay realmente en la caja al cerrar. La diferencia se calcula sola.</small>
            <br><?= $e('total_declarado') ?>
        </p>

        <p>
            <button type="submit">Cerrar turno</button>
            <a href="<?= Vista::e(Vista::url('/caja')) ?>">Seguir vendiendo</a>
        </p>
    </form>

<?php endif; ?>

<p><a href="<?= Vista::e(Vista::url('/caja/turnos')) ?>">Turnos anteriores</a></p>
