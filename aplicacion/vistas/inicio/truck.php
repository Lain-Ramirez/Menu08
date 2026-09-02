<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * @var array<string, mixed> $truck
 */
?>
<h1><?= Vista::e($truck['nombre']) ?></h1>

<p>
    Resuelto por el parametro <code><?= Vista::e($truck['slug']) ?></code> de la direccion,
    con una sentencia preparada.
</p>

<?php if (!empty($truck['ciudad'])) : ?>
    <p>Opera en <?= Vista::e($truck['ciudad']) ?>.</p>
<?php endif; ?>

<?php if (!empty($truck['descripcion'])) : ?>
    <p><?= Vista::e($truck['descripcion']) ?></p>
<?php endif; ?>

<p><a href="<?= Vista::e(Vista::url('/')) ?>">Volver</a></p>
