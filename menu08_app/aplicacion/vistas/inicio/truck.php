<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Comprobacion de la ruta con parametro: /comprobacion/{slug}.
 *
 * @var array<string, mixed> $truck
 */
?>
<div class="pila pila-5 contenedor-lectura">
    <header class="pila pila-2">
        <p class="migas">
            <a href="<?= Vista::e(Vista::url('/')) ?>">Inicio</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
            <span>Comprobación</span>
        </p>

        <h1><?= Vista::e($truck['nombre']) ?></h1>
    </header>

    <section class="tarjeta tarjeta-contorno panel-grupo">
        <p class="tarjeta-texto">
            Resuelto por el parametro <code><?= Vista::e($truck['slug']) ?></code> de la direccion,
            con una sentencia preparada.
        </p>

        <?php if (!empty($truck['ciudad'])) : ?>
            <p>Opera en <?= Vista::e($truck['ciudad']) ?>.</p>
        <?php endif; ?>

        <?php if (!empty($truck['descripcion'])) : ?>
            <p><?= Vista::e($truck['descripcion']) ?></p>
        <?php endif; ?>

        <div class="tarjeta-pie">
            <a class="boton boton-contorno" href="<?= Vista::e(Vista::url('/')) ?>">Volver</a>
        </div>
    </section>
</div>
