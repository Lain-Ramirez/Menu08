<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Pagina de error. El detalle tecnico solo llega con valor en entorno de
 * desarrollo: en produccion el visitante nunca ve la traza ni la consulta.
 *
 * Tres cosas, en este orden: que paso, dicho sin codigo; que puede hacer ahora;
 * y el codigo, pequeno, para quien tenga que reportarlo. Una pagina de error
 * que solo dice «Error 404» deja al visitante sin salida.
 *
 * @var int         $codigo
 * @var string|null $detalle
 */

$titulos = [
    403 => 'Acceso restringido',
    404 => 'Página no encontrada',
    500 => 'Algo salió mal',
];

$mensajes = [
    403 => 'No tiene permiso para ver esta pagina.',
    404 => 'No encontramos la pagina que busca.',
    500 => 'Ocurrio un problema al procesar la solicitud.',
];
?>
<section class="estado-vacio" aria-labelledby="error-titulo">
    <span class="estado-vacio-icono" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <?php if ((int) $codigo === 403) : ?>
                <rect x="4" y="10" width="16" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
            <?php elseif ((int) $codigo === 404) : ?>
                <circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path><path d="M8.5 11h5"></path>
            <?php else : ?>
                <path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"></path>
                <path d="M12 10v3.5"></path><path d="M12 17h.01"></path>
            <?php endif; ?>
        </svg>
    </span>

    <p class="estado-vacio-codigo">Error <?= Vista::e($codigo) ?></p>

    <h1 class="estado-vacio-titulo" id="error-titulo"><?= Vista::e($titulos[$codigo] ?? 'Algo salió mal') ?></h1>

    <p class="estado-vacio-texto"><?= Vista::e($mensajes[$codigo] ?? 'Ocurrio un problema inesperado.') ?></p>

    <p class="estado-vacio-acciones">
        <a class="boton boton-relleno" href="<?= Vista::e(Vista::url('/')) ?>">Volver al inicio</a>
    </p>
</section>

<?php if ($detalle !== null) : ?>
    <hr>
    <p><strong>Detalle tecnico</strong> (visible solo en entorno de desarrollo):</p>
    <pre style="white-space: pre-wrap; overflow-x: auto;"><?= Vista::e($detalle) ?></pre>
<?php endif; ?>
