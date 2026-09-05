<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Pantalla de acceso. La ruta sigue siendo /ingresar; lo que cambia es el
 * nombre del archivo.
 *
 * Sin novalidate: el criterio pide que el formulario no se envie con el correo
 * o la contrasena vacios, y eso lo da el required del navegador. Con novalidate
 * —como estaba— el required queda desactivado y el formulario viaja igualmente,
 * asi que la comprobacion recaia entera en el servidor.
 *
 * El correo escrito vuelve al campo tras un intento fallido; la contrasena
 * nunca, que es lo correcto.
 *
 * @var string      $correo
 * @var string|null $error
 */
$error = $error ?? null;
?>
<section class="acceso">
    <div class="tarjeta tarjeta-elevada acceso-tarjeta">
        <header class="pila pila-1">
            <h1 class="acceso-titulo">Ingresar</h1>
            <p class="texto-apagado texto-m">Zona privada de Menu08.</p>
        </header>

        <?php if ($error !== null) : ?>
            <div class="aviso aviso-error" role="alert">
                <svg class="aviso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 7v5.5"></path>
                    <path d="M12 16h.01"></path>
                </svg>
                <p><strong>Error.</strong> <?= Vista::e($error) ?></p>
            </div>
        <?php endif; ?>

        <form class="pila pila-4" method="post" action="<?= Vista::e(Vista::url('/ingresar')) ?>">
            <?= Csrf::campo() ?>

            <div class="campo campo-sobre-contenedor">
                <input class="campo-control" type="email" id="correo" name="correo"
                       value="<?= Vista::e($correo) ?>" placeholder=" "
                       required autocomplete="username" autofocus>
                <label class="campo-etiqueta" for="correo">Correo</label>
            </div>

            <div class="campo campo-sobre-contenedor">
                <input class="campo-control" type="password" id="contrasena" name="contrasena"
                       placeholder=" " required autocomplete="current-password">
                <label class="campo-etiqueta" for="contrasena">Contrasena</label>
            </div>

            <button class="boton boton-relleno boton-bloque" type="submit">Entrar</button>
        </form>
    </div>
</section>
