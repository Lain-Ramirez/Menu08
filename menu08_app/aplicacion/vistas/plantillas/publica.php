<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Marco de las pantallas publicas: la carta que se abre desde el codigo QR.
 *
 * Es el hermano de plantillas/base.php, pero para quien llega sin sesion y sin
 * intencion de tenerla. Por eso no encadena cabecera.php ni navegacion.php ni
 * pie.php: esas tres son el marco del panel. Aqui no hay barra de usuario, ni
 * enlaces a los modulos, ni pie del proyecto formativo.
 *
 * Tampoco publica el testigo CSRF. La cabecera del panel solo lo imprime con
 * sesion iniciada, y en una pantalla publica no hay ningun formulario que
 * proteger: dejarlo seria regalar un dato sin motivo.
 *
 * Abre y cierra el documento entero, igual que hacen cabecera.php y pie.php
 * entre las dos para el panel.
 *
 * @var string       $titulo
 * @var string       $contenido ya renderizado y escapado por su vista
 * @var list<string> $hojas     hojas propias de la pantalla, tras las tres base
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Vista::e($titulo) ?></title>

    <?php // Mismo orden que en el panel: md3.css declara los tokens de color,
          // base.css los de tipografia, espaciado, radios y foco, y
          // componentes.css los consume. Las hojas propias van al final, para
          // que puedan afinar lo anterior sin pelear con el orden de carga. ?>
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/md3.css')) ?>">
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/componentes.css')) ?>">

    <?php foreach (($hojas ?? []) as $hoja) : ?>
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/' . $hoja)) ?>">
    <?php endforeach; ?>

    <script src="<?= Vista::e(Vista::url('/recursos/js/interfaz.js')) ?>" defer></script>
</head>
<body class="publico">
    <main class="contenido-publico">
        <?= $contenido ?>
    </main>
</body>
</html>
