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
        <header class="acceso-encabezado">
            <span class="cabecera-sello acceso-sello" aria-hidden="true">🍴</span>
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

            <div class="campo campo-sobre-contenedor campo-con-accion">
                <input class="campo-control" type="password" id="contrasena" name="contrasena"
                       placeholder=" " required autocomplete="current-password">
                <label class="campo-etiqueta" for="contrasena">Contrasena</label>

                <?php // Ver lo que se escribio antes de enviar: en el telefono, con el
                      // teclado tapando media pantalla, es la diferencia entre entrar a
                      // la primera y fallar tres veces. Sale oculto y lo muestra
                      // interfaz.js: sin JavaScript no puede hacer nada. ?>
                <button type="button" class="boton-simbolo campo-accion" data-ver-clave="contrasena"
                        aria-label="Mostrar la contrasena" aria-pressed="false" hidden>
                    <svg class="campo-accion-muestra" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="campo-accion-oculta" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 3l18 18"></path>
                        <path d="M10.6 5.1A10.9 10.9 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.1"></path>
                        <path d="M6.6 6.6A17.3 17.3 0 0 0 2 12s3.6 7 10 7a10.7 10.7 0 0 0 5.4-1.5"></path>
                        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                    </svg>
                </button>
            </div>

            <button class="boton boton-relleno boton-bloque acceso-entrar" type="submit">
                <svg class="boton-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"></path>
                    <path d="M10 8l4 4-4 4"></path><path d="M14 12H4"></path>
                </svg>
                Entrar
            </button>
        </form>

        <p class="acceso-pie texto-m">
            <a href="<?= Vista::e(Vista::url('/')) ?>">Ver las cartas publicadas</a>
        </p>
    </div>
</section>
