<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * @var list<array<string, mixed>> $productos
 */
?>
<h1>Productos</h1>

<p><a href="<?= Vista::e(Vista::url('/panel/productos/nuevo')) ?>">Nuevo producto</a></p>

<?php if ($productos === []) : ?>
    <p>Todavia no hay productos.</p>
<?php else : ?>
    <table>
        <thead>
            <tr><th>Foto</th><th>Producto</th><th>Categoria</th><th>Precio</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($productos as $p) : ?>
            <tr>
                <td>
                    <?php if (!empty($p['foto'])) : ?>
                        <img src="<?= Vista::e(Vista::url('/subidas/' . $p['foto'])) ?>"
                             alt="<?= Vista::e($p['nombre']) ?>" height="48">
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?= Vista::e($p['nombre']) ?></td>
                <td><?= Vista::e($p['categoria']) ?></td>
                <td>$ <?= Vista::e(number_format((float) $p['precio'], 0, ',', '.')) ?></td>
                <td><?= ((int) $p['disponible'] === 1) ? 'disponible' : 'no disponible' ?></td>
                <td>
                    <a href="<?= Vista::e(Vista::url('/panel/productos/' . $p['id'])) ?>">Editar</a>
                    <form method="post" action="<?= Vista::e(Vista::url('/panel/productos/disponibilidad')) ?>"
                          style="display:inline">
                        <?= Csrf::campo() ?>
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button type="submit"><?= ((int) $p['disponible'] === 1) ? 'Agotar' : 'Reponer' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
