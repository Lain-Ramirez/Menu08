<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Pagina de error. El detalle tecnico solo llega con valor en entorno de
 * desarrollo: en produccion el visitante nunca ve la traza ni la consulta.
 *
 * @var int         $codigo
 * @var string|null $detalle
 */

$mensajes = [
    403 => 'No tiene permiso para ver esta pagina.',
    404 => 'No encontramos la pagina que busca.',
    500 => 'Ocurrio un problema al procesar la solicitud.',
];
?>
<h1>Error <?= Vista::e($codigo) ?></h1>

<p><?= Vista::e($mensajes[$codigo] ?? 'Ocurrio un problema inesperado.') ?></p>

<p><a href="<?= Vista::e(Vista::url('/')) ?>">Volver al inicio</a></p>

<?php if ($detalle !== null) : ?>
    <hr>
    <p><strong>Detalle tecnico</strong> (visible solo en entorno de desarrollo):</p>
    <pre style="white-space: pre-wrap; overflow-x: auto;"><?= Vista::e($detalle) ?></pre>
<?php endif; ?>
