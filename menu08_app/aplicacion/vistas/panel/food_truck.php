<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * @var array<string, mixed>  $truck
 * @var array<string, string> $errores
 */
$e = static fn (string $c): string => isset($errores[$c])
    ? '<span class="error-campo">' . Vista::e($errores[$c]) . '</span>' : '';
?>
<h1>Datos del food truck</h1>

<form method="post" action="<?= Vista::e(Vista::url('/panel/food-truck')) ?>" enctype="multipart/form-data" novalidate>
    <?= Csrf::campo() ?>

    <p>
        <label for="nombre">Nombre</label><br>
        <input type="text" id="nombre" name="nombre" maxlength="120" required
               value="<?= Vista::e($truck['nombre'] ?? '') ?>"><br><?= $e('nombre') ?>
    </p>

    <p>
        <label for="slug">Slug de la carta publica</label><br>
        <input type="text" id="slug" name="slug" maxlength="80"
               value="<?= Vista::e($truck['slug'] ?? '') ?>"><br>
        <small>Minusculas y guiones. Si se deja vacio se genera desde el nombre.</small><br><?= $e('slug') ?>
    </p>

    <p>
        <label for="ciudad">Ciudad</label><br>
        <input type="text" id="ciudad" name="ciudad" maxlength="80"
               value="<?= Vista::e($truck['ciudad'] ?? '') ?>"><br><?= $e('ciudad') ?>
    </p>

    <p>
        <label for="descripcion">Descripcion</label><br>
        <textarea id="descripcion" name="descripcion" rows="3" maxlength="500"><?= Vista::e($truck['descripcion'] ?? '') ?></textarea>
        <br><?= $e('descripcion') ?>
    </p>

    <p>
        <label for="telefono">Telefono</label><br>
        <input type="text" id="telefono" name="telefono" maxlength="40"
               value="<?= Vista::e($truck['telefono'] ?? '') ?>"><br><?= $e('telefono') ?>
    </p>

    <p>
        <label for="whatsapp">WhatsApp</label><br>
        <input type="text" id="whatsapp" name="whatsapp" maxlength="40"
               value="<?= Vista::e($truck['whatsapp'] ?? '') ?>"><br><?= $e('whatsapp') ?>
    </p>

    <p>
        <label for="instagram">Instagram</label><br>
        <input type="text" id="instagram" name="instagram" maxlength="80"
               value="<?= Vista::e($truck['instagram'] ?? '') ?>"><br><?= $e('instagram') ?>
    </p>

    <p>
        <label for="logo">Logotipo</label><br>
        <?php if (!empty($truck['logo'])) : ?>
            <img src="<?= Vista::e(Vista::url('/subidas/' . $truck['logo'])) ?>" alt="Logotipo actual" height="80"><br>
        <?php endif; ?>
        <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp"><br>
        <small>JPG, PNG o WEBP, hasta 2 MB. Se deja vacio para conservar el actual.</small><br><?= $e('logo') ?>
    </p>

    <p>
        <button type="submit">Guardar</button>
        <a href="<?= Vista::e(Vista::url('/panel')) ?>">Volver</a>
    </p>
</form>
