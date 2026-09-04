<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Vista;

/**
 * Plantilla comun. $contenido ya viene renderizado y escapado por su vista,
 * por eso es lo unico que se imprime sin volver a escapar.
 *
 * @var string $titulo
 * @var string $contenido
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php // Los formularios llevan el token en un campo oculto, pero el tablero del
          // SVP lo necesita desde JavaScript para POST /svp/orden/{id}/estado, y no
          // tiene ningun formulario de donde sacarlo. Solo en zona privada. ?>
    <?php if (Sesion::autenticado()) : ?>
    <meta name="csrf-token" content="<?= Vista::e(Csrf::token()) ?>">
    <?php endif; ?>
    <title><?= Vista::e($titulo) ?> · Menu08</title>
    <?php // Estilos minimos provisionales. La hoja definitiva llega con el issue #14. ?>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        header, main, footer { max-width: 52rem; margin: 0 auto; padding: 1rem; }
        header { border-bottom: 1px solid #8884; }
        footer { border-top: 1px solid #8884; font-size: .875rem; opacity: .75; }
        table { border-collapse: collapse; width: 100%; }
        .barra { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .aviso { padding: .6rem .9rem; border-radius: .3rem; border: 1px solid; }
        .aviso-exito { border-color: #2e7d32; }
        .aviso-aviso { border-color: #f9a825; }
        .aviso-error { border-color: #c62828; }
        label { font-weight: 600; }
        .error-campo { color: #c62828; font-size: .875rem; }
        textarea, select { font: inherit; width: min(28rem, 100%); padding: .45rem .6rem; }
        img { vertical-align: middle; border-radius: .25rem; }
        .carta { max-width: 34rem; margin: 0 auto; }
        .carta-cabecera { text-align: center; margin-bottom: 1.5rem; }
        .carta-logo { max-height: 6rem; }
        .carta-contacto { font-size: .9rem; opacity: .8; }
        .carta-categoria h2 { border-bottom: 2px solid #8884; padding-bottom: .3rem; }
        .carta-lista { list-style: none; padding: 0; margin: 0; }
        .carta-item { display: flex; gap: .8rem; align-items: flex-start;
                      padding: .8rem 0; border-bottom: 1px solid #8883; }
        .carta-foto { width: 4.5rem; height: 4.5rem; object-fit: cover; flex: none; }
        .carta-texto { flex: 1; min-width: 0; }
        .carta-texto h3 { margin: 0 0 .2rem; font-size: 1.05rem; }
        .carta-texto p { margin: 0; font-size: .9rem; opacity: .85; }
        .carta-precio { font-weight: 700; white-space: nowrap; }
        .comprobante { max-width: 22rem; margin: 0 auto; }
        .comprobante header { text-align: center; }
        .comprobante-numero { font-size: 1.6rem; font-weight: 700; margin: .4rem 0; }
        .comprobante-total { font-size: 1.2rem; }
        .comprobante-gracias { text-align: center; opacity: .8; }
        @media print {
            header, footer, .sin-impresion, .aviso { display: none !important; }
            .comprobante { max-width: none; }
        }
        input, button { font: inherit; padding: .45rem .6rem; }
        input[type="email"], input[type="password"] { width: min(22rem, 100%); }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #8884; }
        code { background: #8882; padding: .1rem .3rem; border-radius: .2rem; }
    </style>
</head>
<body>
    <header class="barra">
        <span><strong>Menu08</strong> · carta, caja y produccion para food trucks</span>
        <?php if (Sesion::autenticado()) : ?>
            <span>
                <?= Vista::e(Sesion::usuario()['nombre']) ?>
                (<?= Vista::e(Sesion::rol()) ?>) ·
                <a href="<?= Vista::e(Vista::url('/salir')) ?>">Salir</a>
            </span>
        <?php endif; ?>
    </header>

    <?php foreach (Sesion::sacarMensajes() as $mensaje) : ?>
        <main>
            <p class="aviso aviso-<?= Vista::e($mensaje['tipo']) ?>"><?= Vista::e($mensaje['texto']) ?></p>
        </main>
    <?php endforeach; ?>

    <main>
        <?= $contenido ?>
    </main>

    <footer>
        Prototipo del proyecto formativo · SENA ADSO ficha 3235887
    </footer>
</body>
</html>
