<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * @var list<array<string, mixed>> $categorias
 * @var array<string, mixed>|null  $edita
 * @var array<string, string>      $errores
 */
$e = static fn (string $c): string => isset($errores[$c])
    ? '<span class="error-campo">' . Vista::e($errores[$c]) . '</span>' : '';
?>
<h1>Categorias</h1>

<form method="post" action="<?= Vista::e(Vista::url('/panel/categorias')) ?>" novalidate>
    <?= Csrf::campo() ?>
    <input type="hidden" name="id" value="<?= (int) ($edita['id'] ?? 0) ?>">

    <p>
        <label for="nombre"><?= $edita === null ? 'Nueva categoria' : 'Editar categoria' ?></label><br>
        <input type="text" id="nombre" name="nombre" maxlength="90" required
               value="<?= Vista::e($edita['nombre'] ?? '') ?>"><br><?= $e('nombre') ?>
    </p>

    <p>
        <label for="orden">Orden en la carta</label><br>
        <input type="number" id="orden" name="orden" min="0" max="999"
               value="<?= (int) ($edita['orden'] ?? 0) ?>"><br><?= $e('orden') ?>
    </p>

    <p>
        <button type="submit"><?= $edita === null ? 'Crear' : 'Guardar' ?></button>
        <?php if ($edita !== null) : ?>
            <a href="<?= Vista::e(Vista::url('/panel/categorias')) ?>">Cancelar</a>
        <?php endif; ?>
    </p>
</form>

<?php if ($categorias === []) : ?>
    <p>Todavia no hay categorias. La carta publica se vera vacia hasta que cree la primera.</p>
<?php else : ?>
    <table>
        <thead>
            <tr><th>Orden</th><th>Nombre</th><th>Productos</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($categorias as $c) : ?>
            <tr>
                <td><?= (int) $c['orden'] ?></td>
                <td><?= Vista::e($c['nombre']) ?></td>
                <td><?= (int) $c['productos'] ?></td>
                <td><?= ((int) $c['activo'] === 1) ? 'activa' : 'inactiva' ?></td>
                <td>
                    <a href="<?= Vista::e(Vista::url('/panel/categorias/' . $c['id'])) ?>">Editar</a>
                    <form method="post" action="<?= Vista::e(Vista::url('/panel/categorias/estado')) ?>"
                          style="display:inline">
                        <?= Csrf::campo() ?>
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button type="submit"><?= ((int) $c['activo'] === 1) ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
