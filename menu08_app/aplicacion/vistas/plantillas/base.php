<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Vista;

/**
 * Plantilla comun. $contenido ya viene renderizado y escapado por su vista,
 * por eso es lo unico que se imprime sin volver a escapar.
 *
 * El marco del panel —cabecera, navegacion y pie— lo maqueta el issue #15.
 *
 * @var string $titulo
 * @var string $contenido
 */

/**
 * Icono de cada tipo de mensaje. md3.css advierte que la banda calida queda
 * densa, asi que un aviso nunca se distingue solo por el color: lleva tambien
 * icono y palabra. Los tres unicos tipos son los que emite Sesion::mensaje().
 */
$iconoAviso = static function (string $tipo): string {
    $trazos = [
        'exito' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'aviso' => '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/><path d="M12 10v3.5"/><path d="M12 17h.01"/>',
        'error' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5"/><path d="M12 16h.01"/>',
    ];

    return sprintf(
        '<svg class="aviso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        $trazos[$tipo]
    );
};
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

    <?php // El orden importa: md3.css declara los tokens de color, base.css los de
          // tipografia, espaciado, radios y foco, y componentes.css los consume.
          // Todo local: ni un marco CSS ni un recurso remoto. ?>
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/md3.css')) ?>">
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/componentes.css')) ?>">

    <?php // defer: no bloquea el pintado y corre con el DOM ya construido. Nada de
          // lo que hay dentro es requisito para operar; todo es mejora progresiva. ?>
    <script src="<?= Vista::e(Vista::url('/recursos/js/interfaz.js')) ?>" defer></script>
</head>
<body>
    <header class="contenedor barra sin-impresion">
        <span><strong>Menu08</strong> · carta, caja y produccion para food trucks</span>
        <?php if (Sesion::autenticado()) : ?>
            <span>
                <?= Vista::e(Sesion::usuario()['nombre']) ?>
                (<?= Vista::e(Sesion::rol()) ?>) ·
                <a href="<?= Vista::e(Vista::url('/salir')) ?>">Salir</a>
            </span>
        <?php endif; ?>
    </header>

    <?php // Un solo <main> por documento: los mensajes van dentro, no en uno aparte. ?>
    <main class="contenedor pila pila-5">
        <?php $mensajes = Sesion::sacarMensajes(); ?>

        <?php if ($mensajes !== []) : ?>
            <div class="pila pila-3 sin-impresion">
                <?php foreach ($mensajes as $mensaje) : ?>
                    <?php
                    // Un tipo desconocido caeria en una clase sin estilo: se trata
                    // como aviso, que es el valor por omision de Sesion::mensaje().
                    $tipo = in_array($mensaje['tipo'], ['exito', 'aviso', 'error'], true)
                        ? $mensaje['tipo']
                        : 'aviso';
                    ?>
                    <div class="aviso aviso-<?= Vista::e($tipo) ?>"
                         role="<?= $tipo === 'error' ? 'alert' : 'status' ?>">
                        <?= $iconoAviso($tipo) ?>
                        <p><?= Vista::e($mensaje['texto']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $contenido ?>
    </main>

    <footer class="contenedor texto-apagado texto-m sin-impresion">
        Prototipo del proyecto formativo · SENA ADSO ficha 3235887
    </footer>
</body>
</html>
