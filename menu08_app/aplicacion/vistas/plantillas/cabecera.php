<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Vista;

/**
 * Cabecera del marco: apertura del documento, carga de estilos, titulo dinamico
 * y la barra superior con el negocio, el usuario y la salida.
 *
 * El bloque de scripts va aqui con defer, no al final del cuerpo: defer ya
 * garantiza que no bloquea el pintado y que corre con el DOM construido, y asi
 * la apertura y el cierre del documento quedan cada una en su archivo.
 *
 * @var string $titulo
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

    <?php // El orden importa: md3.css declara los tokens de color, base.css los de
          // tipografia, espaciado, radios y foco, y componentes.css los consume.
          // Todo local: ni un marco CSS ni un recurso remoto. ?>
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/md3.css')) ?>">
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/componentes.css')) ?>">

    <script src="<?= Vista::e(Vista::url('/recursos/js/interfaz.js')) ?>" defer></script>
</head>
<body>
    <header class="cabecera sin-impresion">
        <div class="cabecera-interior contenedor-ancho">
            <a class="cabecera-marca" href="<?= Vista::e(Vista::url('/')) ?>">
                <span class="cabecera-nombre">Menu08</span>
                <span class="cabecera-lema">carta, caja y produccion para food trucks</span>
            </a>

            <?php if (Sesion::autenticado()) : ?>
                <div class="cabecera-sesion">
                    <span class="cabecera-usuario">
                        <span class="cabecera-nombre-usuario"><?= Vista::e(Sesion::usuario()['nombre']) ?></span>
                        <span class="etiqueta etiqueta-pendiente"><?= Vista::e(Sesion::rol()) ?></span>
                    </span>

                    <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/salir')) ?>">Salir</a>

                    <?php // Solo se ve por debajo de 768 px; lo alterna Interfaz.menu(). ?>
                    <button type="button" class="boton-simbolo cabecera-alterna"
                            data-alterna="navegacion-panel" data-alterna-desde="(max-width: 767.98px)"
                            aria-label="Alternar navegacion">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </header>
