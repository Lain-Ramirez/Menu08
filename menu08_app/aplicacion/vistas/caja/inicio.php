<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Pantalla de venta. Se arma la orden marcando cantidades sobre el catalogo.
 *
 * @var array<string, mixed>       $usuario
 * @var array<string, mixed>       $turno
 * @var array<string, mixed>       $resumen
 * @var list<array<string, mixed>> $catalogo
 * @var list<array<string, mixed>> $ordenes
 * @var string|null                $error
 */
$peso = static fn (mixed $n): string => '$ ' . number_format((float) $n, 0, ',', '.');

$porCategoria = [];

foreach ($catalogo as $p) {
    $porCategoria[$p['categoria']][] = $p;
}
?>
<h1>Caja</h1>

<p>
    Turno #<?= (int) $turno['id'] ?> · <?= Vista::e($turno['cajero']) ?> ·
    <?= (int) $resumen['ordenes'] ?> ordenes · vendido <?= Vista::e($peso($resumen['total'])) ?>
</p>

<?php if ($error !== null) : ?>
    <p class="aviso aviso-error" role="alert"><?= Vista::e($error) ?></p>
<?php endif; ?>

<?php if ($catalogo === []) : ?>
    <p class="aviso aviso-aviso">
        No hay productos disponibles para vender. Revise el catalogo en el panel.
    </p>
<?php else : ?>
<form method="post" action="<?= Vista::e(Vista::url('/caja/vender')) ?>" novalidate>
    <?= Csrf::campo() ?>

    <?php foreach ($porCategoria as $categoria => $productos) : ?>
        <h2><?= Vista::e($categoria) ?></h2>

        <table>
            <thead><tr><th>Producto</th><th>Precio</th><th>Cantidad</th></tr></thead>
            <tbody>
            <?php foreach ($productos as $p) : ?>
                <tr>
                    <td><?= Vista::e($p['nombre']) ?></td>
                    <td><?= Vista::e($peso($p['precio'])) ?></td>
                    <td>
                        <input type="number" min="0" max="99" step="1" value="0"
                               style="width:5rem"
                               name="cantidad[<?= (int) $p['id'] ?>]"
                               aria-label="Cantidad de <?= Vista::e($p['nombre']) ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <p>
        <label for="medio_pago">Medio de pago</label><br>
        <select id="medio_pago" name="medio_pago" required>
            <option value="efectivo">Efectivo</option>
            <option value="tarjeta">Tarjeta</option>
            <option value="transferencia">Transferencia</option>
        </select>
    </p>

    <p>
        <label for="nota">Nota para produccion</label><br>
        <input type="text" id="nota" name="nota" maxlength="300"
               placeholder="Sin cebolla, para llevar…">
    </p>

    <p>
        <button type="submit">Registrar orden</button>
        <a href="<?= Vista::e(Vista::url('/caja/turno')) ?>">Cerrar turno</a>
    </p>
</form>
<?php endif; ?>

<h2>Ordenes de este turno</h2>

<?php if ($ordenes === []) : ?>
    <p>Todavia no hay ordenes en el turno.</p>
<?php else : ?>
    <table>
        <thead><tr><th>Numero</th><th>Total</th><th>Medio</th><th>Estado</th><th>Hora</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($ordenes as $o) : ?>
            <tr>
                <td><?= Vista::e($o['numero']) ?></td>
                <td><?= Vista::e($peso($o['total'])) ?></td>
                <td><?= Vista::e(ucfirst((string) $o['medio_pago'])) ?></td>
                <td><?= Vista::e($o['estado']) ?></td>
                <td><?= Vista::e(substr((string) $o['creado_en'], 11, 5)) ?></td>
                <td><a href="<?= Vista::e(Vista::url('/caja/comprobante/' . $o['id'])) ?>">Comprobante</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
