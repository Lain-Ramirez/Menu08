<?php

declare(strict_types=1);

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
    <title><?= Vista::e($titulo) ?> · Menu08</title>
    <?php // Estilos minimos provisionales. La hoja definitiva llega con el issue #14. ?>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        header, main, footer { max-width: 52rem; margin: 0 auto; padding: 1rem; }
        header { border-bottom: 1px solid #8884; }
        footer { border-top: 1px solid #8884; font-size: .875rem; opacity: .75; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #8884; }
        code { background: #8882; padding: .1rem .3rem; border-radius: .2rem; }
    </style>
</head>
<body>
    <header>
        <strong>Menu08</strong> · carta, caja y produccion para food trucks
    </header>

    <main>
        <?= $contenido ?>
    </main>

    <footer>
        Prototipo del proyecto formativo · SENA ADSO ficha 3235887
    </footer>
</body>
</html>
