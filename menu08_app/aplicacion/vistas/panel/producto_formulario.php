<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * @var array<string, mixed>|null  $producto
 * @var list<array<string, mixed>> $categorias
 * @var array<string, string>      $errores
 */
$e = static fn (string $c): string => isset($errores[$c])
    ? '<span class="error-campo">' . Vista::e($errores[$c]) . '</span>' : '';
$id = (int) ($producto['id'] ?? 0);
?>
<h1><?= $id > 0 ? 'Editar producto' : 'Nuevo producto' ?></h1>

<?php if ($categorias === []) : ?>
    <p class="aviso aviso-aviso">
        Antes de crear un producto hace falta al menos una categoria.
        <a href="<?= Vista::e(Vista::url('/panel/categorias')) ?>">Crear una</a>.
    </p>
<?php else : ?>
<form method="post" action="<?= Vista::e(Vista::url('/panel/productos')) ?>" enctype="multipart/form-data" novalidate>
    <?= Csrf::campo() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <p>
        <label for="categoria_id">Categoria</label><br>
        <select id="categoria_id" name="categoria_id" required>
            <option value="">Seleccione…</option>
            <?php foreach ($categorias as $c) : ?>
                <option value="<?= (int) $c['id'] ?>"
                    <?= (int) ($producto['categoria_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= Vista::e($c['nombre']) ?><?= (int) $c['activo'] === 1 ? '' : ' (inactiva)' ?>
                </option>
            <?php endforeach; ?>
        </select><br><?= $e('categoria_id') ?>
    </p>

    <p>
        <label for="nombre">Nombre</label><br>
        <input type="text" id="nombre" name="nombre" maxlength="120" required
               value="<?= Vista::e($producto['nombre'] ?? '') ?>"><br><?= $e('nombre') ?>
    </p>

    <p>
        <label for="descripcion">Descripcion</label><br>
        <textarea id="descripcion" name="descripcion" rows="3" maxlength="400"><?= Vista::e($producto['descripcion'] ?? '') ?></textarea>
        <br><?= $e('descripcion') ?>
    </p>

    <p>
        <label for="precio">Precio</label><br>
        <input type="text" id="precio" name="precio" inputmode="decimal" required
               value="<?= Vista::e($producto['precio'] ?? '') ?>"><br>
        <small>Numero positivo, hasta dos decimales.</small><br><?= $e('precio') ?>
    </p>

    <p>
        <label for="orden">Orden dentro de la categoria</label><br>
        <input type="number" id="orden" name="orden" min="0" max="999"
               value="<?= (int) ($producto['orden'] ?? 0) ?>"><br><?= $e('orden') ?>
    </p>

    <p>
        <label for="foto">Foto</label><br>
        <?php if (!empty($producto['foto'])) : ?>
            <img src="<?= Vista::e(Vista::url('/subidas/' . $producto['foto'])) ?>" alt="Foto actual" height="80"><br>
        <?php endif; ?>
        <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp"><br>
        <small>JPG, PNG o WEBP, hasta 2 MB.</small><br><?= $e('foto') ?>
    </p>

    <p>
        <label>
            <input type="checkbox" name="disponible" value="1"
                <?= ($producto === null || (int) ($producto['disponible'] ?? 1) === 1) ? 'checked' : '' ?>>
            Disponible en la carta
        </label>
    </p>

    <p>
        <button type="submit">Guardar</button>
        <a href="<?= Vista::e(Vista::url('/panel/productos')) ?>">Volver</a>
    </p>
</form>
<?php endif; ?>
