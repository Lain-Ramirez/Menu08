<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * @var array<string, mixed>      $usuario
 * @var array<string, mixed>|null $truck
 * @var array<string, int>|null   $resumen
 * @var array<string, int>|null   $paradas
 */
?>
<h1>Panel</h1>

<?php if ($truck === null) : ?>
    <p class="aviso aviso-aviso">
        Esta cuenta administra la plataforma y no esta asociada a ningun food truck,
        por lo que no tiene catalogo propio que administrar.
    </p>
<?php else : ?>
    <p>
        <strong><?= Vista::e($truck['nombre']) ?></strong> ·
        carta publica en
        <a href="<?= Vista::e(Vista::url('/carta/' . $truck['slug'])) ?>">/carta/<?= Vista::e($truck['slug']) ?></a>
    </p>

    <table>
        <tr><th>Categorias</th><td><?= (int) $resumen['categorias'] ?></td></tr>
        <tr><th>Productos</th><td><?= (int) $resumen['productos'] ?></td></tr>
        <tr><th>Disponibles ahora</th><td><?= (int) $resumen['disponibles'] ?></td></tr>
        <tr><th>Paradas</th><td><?= (int) $paradas['paradas'] ?> (<?= (int) $paradas['activas'] ?> activas)</td></tr>
    </table>

    <p>
        <a href="<?= Vista::e(Vista::url('/panel/food-truck')) ?>">Datos del food truck</a> ·
        <a href="<?= Vista::e(Vista::url('/panel/categorias')) ?>">Categorias</a> ·
        <a href="<?= Vista::e(Vista::url('/panel/productos')) ?>">Productos</a> ·
        <a href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">Paradas</a> ·
        <a href="<?= Vista::e(Vista::url('/panel/qr')) ?>">Codigo QR</a>
    </p>
<?php endif; ?>
