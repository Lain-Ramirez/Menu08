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
 * @var string       $titulo
 * @var list<string> $hojas   hojas propias de la pantalla, tras las tres base
 * @var list<string> $guiones guiones propios de la pantalla, tras interfaz.js
 */

/** Nombre y rol de quien entro. El rol llega como lo guarda la base
    —food_truck— y se escribe con espacio: es un dato que se lee, no un codigo. */
$usuarioSesion = Sesion::autenticado() ? (Sesion::usuario() ?? []) : [];
$nombreSesion  = (string) ($usuarioSesion['nombre'] ?? '');
$rolSesion     = str_replace('_', ' ', (string) Sesion::rol());

/** La pantalla de acceso no ofrece el boton «Ingresar»: ya se esta en ella. */
$rutaPedida = rtrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$enAcceso   = str_ends_with($rutaPedida, '/ingresar');
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
    <meta name="description" content="Menu08: carta, caja y produccion para food trucks.">
    <meta name="theme-color" content="#fff8f4">
    <?php // El tema se aplica ANTES de pintar: si la clase llegara con interfaz.js,
          // que va con defer, quien eligio el oscuro veria un fogonazo claro en
          // cada pagina. Solo lee la preferencia guardada; el boton que la cambia
          // lo atiende interfaz.js. ?>
    <script>try{if(localStorage.getItem('menu08-tema')==='o'){document.documentElement.className='o';}}catch(e){}</script>
    <?php // El icono de la pestana es el mismo emoji de la marca, dibujado en un
          // SVG en linea: no hay archivo que subir ni que cachear. ?>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8D%B4%3C/text%3E%3C/svg%3E">

    <?php // El orden importa: md3.css declara los tokens de color, base.css los de
          // tipografia, espaciado, radios y foco, y componentes.css los consume.
          // Todo local: ni un marco CSS ni un recurso remoto. ?>
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/md3.css')) ?>">
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/componentes.css')) ?>">

    <?php // Lo propio de la pantalla va al final, para que pueda afinar lo
          // anterior sin pelear con el orden de carga. Es el mismo reparto que
          // hace plantillas/publica.php con la carta. ?>
    <?php foreach (($hojas ?? []) as $hoja) : ?>
    <link rel="stylesheet" href="<?= Vista::e(Vista::url('/recursos/css/' . $hoja)) ?>">
    <?php endforeach; ?>

    <script src="<?= Vista::e(Vista::url('/recursos/js/interfaz.js')) ?>" defer></script>

    <?php foreach (($guiones ?? []) as $guion) : ?>
    <script src="<?= Vista::e(Vista::url('/recursos/js/' . $guion)) ?>" defer></script>
    <?php endforeach; ?>
</head>
<body>
    <?php // Lo primero que encuentra el tabulador: quien navega con teclado salta
          // la cabecera y la navegacion sin recorrerlas en cada pagina. ?>
    <a class="salto" href="#contenido">Saltar al contenido</a>

    <header class="cabecera sin-impresion">
        <div class="cabecera-interior contenedor-ancho">
            <a class="cabecera-marca" href="<?= Vista::e(Vista::url('/')) ?>">
                <?php // El sello es el emoji de la marca. Es decorativo: el nombre
                      // que va al lado ya dice a donde lleva el enlace. ?>
                <span class="cabecera-sello" aria-hidden="true">🍴</span>

                <span class="cabecera-textos">
                    <span class="cabecera-nombre">Menu08</span>
                    <span class="cabecera-lema">carta, caja y produccion para food trucks</span>
                </span>
            </a>

            <div class="cabecera-sesion">
                <?php // Tema claro u oscuro. Sale oculto y lo muestra interfaz.js:
                      // sin JavaScript no puede cambiar nada. ?>
                <button type="button" class="boton-simbolo boton-tema" data-tema
                        aria-label="Cambiar a tema oscuro" aria-pressed="false" hidden>
                    <svg class="boton-tema-luna" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"></path>
                    </svg>
                    <svg class="boton-tema-sol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2"></path><path d="M12 20v2"></path><path d="M4.9 4.9l1.4 1.4"></path>
                        <path d="M17.7 17.7l1.4 1.4"></path><path d="M2 12h2"></path><path d="M20 12h2"></path>
                        <path d="M4.9 19.1l1.4-1.4"></path><path d="M17.7 6.3l1.4-1.4"></path>
                    </svg>
                </button>

                <?php if (Sesion::autenticado()) : ?>
                    <span class="cabecera-usuario">
                        <?php if ($nombreSesion !== '') : ?>
                            <span class="cabecera-avatar" aria-hidden="true"><?= Vista::e(mb_substr($nombreSesion, 0, 1)) ?></span>
                        <?php endif; ?>
                        <span class="cabecera-nombre-usuario"><?= Vista::e($nombreSesion) ?></span>
                        <span class="etiqueta etiqueta-pendiente cabecera-rol"><?= Vista::e($rolSesion) ?></span>
                    </span>

                    <a class="boton boton-texto cabecera-salir" href="<?= Vista::e(Vista::url('/salir')) ?>">
                        <svg class="boton-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3"></path>
                            <path d="M15 8l4 4-4 4"></path><path d="M19 12H9"></path>
                        </svg>
                        <span class="cabecera-salir-texto">Salir</span>
                    </a>

                    <?php // Solo se ve por debajo de 768 px; lo alterna Interfaz.menu(). ?>
                    <button type="button" class="boton-simbolo cabecera-alterna"
                            data-alterna="navegacion-panel" data-alterna-desde="(max-width: 767.98px)"
                            aria-label="Alternar navegacion">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path>
                        </svg>
                    </button>
                <?php elseif (!$enAcceso) : ?>
                    <a class="boton boton-contorno cabecera-ingresar" href="<?= Vista::e(Vista::url('/ingresar')) ?>">Ingresar</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
