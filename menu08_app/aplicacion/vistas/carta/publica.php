<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Carta publica. Se consulta desde el telefono, haciendo fila en la ventanilla,
 * asi que el movil manda.
 *
 * No lleva el marco del panel. La renderiza plantillas/publica.php, que abre el
 * documento sin barra de usuario ni navegacion.
 *
 * EL ORDEN DE LA PANTALLA ES EL ORDEN DE LAS PREGUNTAS del que la abre:
 *
 *   1. ¿Es este el truck?           La portada: logotipo, nombre y una linea.
 *   2. ¿Esta abierto y donde?       El estado va en la portada misma, y el punto
 *                                   justo debajo, con el enlace para llegar.
 *   3. ¿Que hay y cuanto vale?      La carta: buscador, categorias y productos.
 *
 * La semana completa va plegada: es la respuesta a una cuarta pregunta —¿y el
 * sabado?— que casi nadie hace, y desplegada empujaba los productos dos
 * pantallas hacia abajo.
 *
 * TODO FUNCIONA SIN JAVASCRIPT. Las categorias son anclas, la foto es un enlace
 * a la imagen y la semana es un <details>. carta.js pone encima el buscador, el
 * visor de fotos y el «leer mas» de la descripcion, y por eso esas tres piezas
 * salen con hidden o sin recortar: sin el guion no prometen nada que no hagan.
 *
 * @var array<string, mixed>                          $truck
 * @var array<int, array{nombre: string, productos: list<array<string, mixed>>}> $porCategoria
 * @var list<array<string, mixed>>                    $agenda
 * @var array<string, mixed>|null                     $vigente  parada abierta ahora
 * @var array<string, mixed>|null                     $proxima  la siguiente que abre, si no hay vigente
 * @var array<int, string>                            $dias
 */

/** La base devuelve TIME como 18:00:00 y en la carta solo interesa 18:00. */
$hm = static fn (mixed $hora): string => substr((string) $hora, 0, 5);

$peso = static fn (mixed $n): string => '$ ' . number_format((float) $n, 0, ',', '.');

/**
 * Cuando abre la proxima parada, dicho como lo diria alguien: hoy, manana o el
 * dia por su nombre. La fecha la calcula el modelo en `abre_en`; aqui solo se
 * traduce a palabras, que es lo unico que el cliente en la fila necesita.
 */
$cuando = static function (string $abreEn) use ($dias): string {
    $marca = strtotime($abreEn);

    if ($marca === false) {
        return '';
    }

    $faltan = (int) ((strtotime(date('Y-m-d', $marca)) - strtotime(date('Y-m-d'))) / 86400);

    if ($faltan <= 0) {
        return 'hoy';
    }

    if ($faltan === 1) {
        return 'mañana';
    }

    return 'el ' . mb_strtolower($dias[(int) date('N', $marca)] ?? '');
};

/**
 * Icono de trazo sobre reticula de 24, como el resto de la aplicacion. El unico
 * emoji de la pantalla es el de la marca.
 */
$icono = static fn (string $trazos, string $clase = ''): string => sprintf(
    '<svg%s viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
    . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
    $clase === '' ? '' : ' class="' . Vista::e($clase) . '"',
    $trazos
);

$trazoPunto    = '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>';
$trazoReloj    = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
$trazoChat     = '<path d="M21 11.5a8.5 8.5 0 0 1-12.7 7.4L3 20l1.2-4.8A8.5 8.5 0 1 1 21 11.5Z"/>';
$trazoTelefono = '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>';
$trazoCamara   = '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>';
$trazoRuta     = '<path d="M3 11 21 3l-8 18-2-8-8-2Z"/>';
$trazoLupa     = '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>';
$trazoAbajo    = '<path d="m6 9 6 6 6-6"/>';
$trazoCerrar   = '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>';
$trazoImagen   = '<path d="M4 19.5V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v13.5"/><path d="M4 17l4.5-4.5a2 2 0 0 1 2.8 0L16 17"/>'
               . '<path d="M14 15l1.5-1.5a2 2 0 0 1 2.8 0L20 15"/><circle cx="9" cy="9" r="1.2"/>';

/**
 * Reemplazo para el producto sin foto. Es un SVG de trazo y no un archivo de
 * imagen: pesa unos bytes, se recolorea con el tema porque usa currentColor y
 * no hay que mantener un JPG mas en subidas.
 */
$sinFoto = static fn (): string =>
    '<span class="carta-foto carta-foto-vacia" aria-hidden="true">' . $icono($trazoImagen) . '</span>';

/* ------------------------------------------------------------- contacto --
   Cada dato de contacto es una accion, no un texto: el telefono marca, el
   WhatsApp abre el chat y el Instagram abre el perfil. Lo que no se pueda
   convertir en enlace con seguridad se queda como texto. */
$soloDigitos = static fn (mixed $v): string => (string) preg_replace('/\D/', '', (string) $v);

$whatsapp = $soloDigitos($truck['whatsapp'] ?? '');
$telefono = $soloDigitos($truck['telefono'] ?? '');

/** El usuario de Instagram, sin la arroba ni la direccion si la escribieron
    entera. Solo letras, cifras, punto y guion bajo: lo que admite Instagram. */
$instagramTexto = trim((string) ($truck['instagram'] ?? ''));
$instagram      = (string) preg_replace(
    '/[^A-Za-z0-9._]/',
    '',
    (string) preg_replace('#^(https?://)?(www\.)?instagram\.com/#i', '', ltrim($instagramTexto, '@'))
);

/* ---------------------------------------------------------------- agenda -- */
$destacada = $vigente ?? $proxima;
$abierto   = $vigente !== null;

/** Enlace para llegar, solo si la parada trae coordenadas: las rellena la
    aplicacion movil cuando el truck reporta su punto. */
$comoLlegar = '';

if ($destacada !== null && ($destacada['latitud'] ?? null) !== null && ($destacada['longitud'] ?? null) !== null) {
    $comoLlegar = 'https://www.google.com/maps/search/?api=1&query='
        . rawurlencode((string) $destacada['latitud'] . ',' . (string) $destacada['longitud']);
}

/** El resto de la semana, sin la parada que ya va arriba: se repetiria dos veces
    en la misma pantalla. */
$resto = $destacada === null ? [] : array_values(array_filter(
    $agenda,
    static fn (array $u): bool => (int) $u['id'] !== (int) $destacada['id']
));

$hoy = (int) date('N');

$totalProductos = 0;

foreach ($porCategoria as $grupo) {
    $totalProductos += count($grupo['productos']);
}
?>
<article class="carta" data-carta>

    <?php // ------------------------------------------------------ la marca --
          // Una linea fina arriba: dice de quien es la plataforma y lleva a las
          // demas cartas, sin quitarle la pantalla al truck. ?>
    <p class="carta-marca">
        <a href="<?= Vista::e(Vista::url('/')) ?>">
            <span aria-hidden="true">🍴</span> Menu08
        </a>
    </p>

    <?php // ----------------------------------------------------- la portada ?>
    <header class="carta-portada">
        <?php if (!empty($truck['logo'])) : ?>
            <img src="<?= Vista::e(Vista::url('/subidas/' . $truck['logo'])) ?>"
                 alt="Logotipo de <?= Vista::e($truck['nombre']) ?>" class="carta-logo"
                 width="96" height="96" decoding="async">
        <?php else : ?>
            <span class="carta-logo carta-logo-vacio" aria-hidden="true">
                <?= Vista::e(mb_substr((string) $truck['nombre'], 0, 1)) ?>
            </span>
        <?php endif; ?>

        <div class="carta-portada-texto">
            <h1 class="carta-nombre"><?= Vista::e($truck['nombre']) ?></h1>

            <?php // El estado, en la portada: es lo primero que se quiere saber, y asi
                  // se lee sin desplazar. Nunca solo por el color: lleva la palabra. ?>
            <p class="carta-estado">
                <?php if ($abierto) : ?>
                    <span class="carta-estado-chip carta-estado-abierto">Abierto ahora</span>
                    <span class="numerica">hasta las <?= Vista::e($hm($vigente['hora_fin'])) ?></span>
                <?php elseif ($proxima !== null) : ?>
                    <span class="carta-estado-chip carta-estado-cerrado">Cerrado ahora</span>
                    <span class="numerica">
                        abre <?= Vista::e($cuando((string) $proxima['abre_en'])) ?>
                        a las <?= Vista::e($hm($proxima['hora_inicio'])) ?>
                    </span>
                <?php endif; ?>

                <?php if (!empty($truck['ciudad'])) : ?>
                    <span class="carta-ciudad"><?= Vista::e($truck['ciudad']) ?></span>
                <?php endif; ?>
            </p>
        </div>

        <?php if (!empty($truck['descripcion'])) : ?>
            <?php // Entera en el marcado. carta.js la recorta a tres renglones y
                  // pone el boton solo si de verdad no cabe. ?>
            <div class="carta-acerca">
                <p class="carta-descripcion" id="carta-descripcion" data-carta-descripcion><?= Vista::e($truck['descripcion']) ?></p>
                <button type="button" class="carta-leer-mas" data-carta-leer-mas
                        aria-expanded="false" aria-controls="carta-descripcion" hidden>Leer más</button>
            </div>
        <?php endif; ?>

        <?php if ($whatsapp !== '' || $telefono !== '' || $instagramTexto !== '') : ?>
            <ul class="carta-acciones" aria-label="Contacto">
                <?php if ($whatsapp !== '') : ?>
                    <li>
                        <a class="boton boton-relleno" href="https://wa.me/<?= Vista::e($whatsapp) ?>"
                           target="_blank" rel="noopener noreferrer">
                            <?= $icono($trazoChat, 'boton-icono') ?>WhatsApp
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($telefono !== '') : ?>
                    <li>
                        <a class="boton boton-contorno" href="tel:<?= Vista::e($telefono) ?>">
                            <?= $icono($trazoTelefono, 'boton-icono') ?>Llamar
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($instagram !== '') : ?>
                    <li>
                        <a class="boton boton-contorno" href="https://www.instagram.com/<?= Vista::e($instagram) ?>/"
                           target="_blank" rel="noopener noreferrer">
                            <?= $icono($trazoCamara, 'boton-icono') ?>Instagram
                        </a>
                    </li>
                <?php elseif ($instagramTexto !== '') : ?>
                    <li class="carta-accion-texto"><?= Vista::e($instagramTexto) ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </header>

    <?php // ------------------------------------------ donde estamos hoy --
          // Es la pregunta que trae al cliente a esta pantalla: la abre haciendo
          // fila, desde el telefono, para saber si el truck esta donde cree.
          // Por eso va antes de los productos, y por eso nunca se queda vacia:
          // sin parada abierta, dice cuando vuelve. ?>
    <?php if ($destacada !== null) : ?>
        <section class="carta-agenda" aria-labelledby="carta-donde">
            <h2 id="carta-donde" class="carta-agenda-rotulo">
                <?= $abierto ? 'Estamos en' : 'Próxima parada' ?>
            </h2>

            <div class="carta-ahora<?= $abierto ? '' : ' carta-ahora-cerrado' ?>">
                <span class="carta-ahora-icono" aria-hidden="true"><?= $icono($trazoPunto) ?></span>

                <div class="carta-ahora-texto">
                    <p class="carta-ahora-punto"><?= Vista::e($destacada['nombre']) ?></p>

                    <?php if (!empty($destacada['referencia'])) : ?>
                        <p class="carta-ahora-referencia"><?= Vista::e($destacada['referencia']) ?></p>
                    <?php endif; ?>

                    <?php // No repite el dia cuando esta abierto: una jornada nocturna
                          // empezo AYER, y decir «hoy de 18:00 a 01:00» a las 00:30 seria
                          // mentira. Lo que importa a esa hora es hasta cuando. ?>
                    <p class="carta-ahora-franja numerica">
                        <?= $icono($trazoReloj) ?>
                        <span>
                            <?= Vista::e($dias[(int) $destacada['dia_semana']] ?? '') ?>
                            de <?= Vista::e($hm($destacada['hora_inicio'])) ?>
                            a <?= Vista::e($hm($destacada['hora_fin'])) ?>
                            <?php if ($hm($destacada['hora_fin']) <= $hm($destacada['hora_inicio'])) : ?>
                                · cierra al día siguiente
                            <?php endif; ?>
                        </span>
                    </p>
                </div>

                <?php if ($comoLlegar !== '') : ?>
                    <a class="boton boton-tonal carta-llegar" href="<?= Vista::e($comoLlegar) ?>"
                       target="_blank" rel="noopener noreferrer">
                        <?= $icono($trazoRuta, 'boton-icono') ?>Cómo llegar
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($resto !== []) : ?>
                <?php // Plegada: es la respuesta a «¿y el sabado?», que casi nadie
                      // pregunta. <details> no necesita JavaScript. ?>
                <details class="carta-semana">
                    <summary class="carta-semana-resumen">
                        <span>Ver toda la semana</span>
                        <span class="carta-semana-cuenta numerica"><?= count($resto) ?></span>
                        <?= $icono($trazoAbajo, 'carta-semana-flecha') ?>
                    </summary>

                    <ul class="carta-paradas">
                        <?php foreach ($resto as $u) : ?>
                            <?php $esHoy = (int) $u['dia_semana'] === $hoy; ?>
                            <li class="carta-parada<?= $esHoy ? ' carta-parada-hoy' : '' ?>">
                                <span class="carta-parada-dia">
                                    <?= $esHoy ? 'Hoy' : Vista::e(mb_substr($dias[(int) $u['dia_semana']] ?? '', 0, 3)) ?>
                                </span>
                                <span class="carta-parada-punto"><?= Vista::e($u['nombre']) ?></span>
                                <span class="carta-parada-horario numerica">
                                    <?= Vista::e($hm($u['hora_inicio'])) ?>–<?= Vista::e($hm($u['hora_fin'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($porCategoria === []) : ?>
        <section class="estado-vacio">
            <span class="estado-vacio-icono" aria-hidden="true"><?= $icono($trazoReloj) ?></span>
            <h2 class="estado-vacio-titulo">La carta se está preparando</h2>
            <p class="estado-vacio-texto">Vuelva en un momento.</p>
        </section>
    <?php else : ?>
        <?php // ------------------------------------------------- la carta --
              // El buscador sale oculto y lo muestra carta.js: filtra las fichas
              // ya pintadas, sin ninguna consulta, y sin el guion no puede. ?>
        <div class="carta-herramientas">
            <div class="carta-buscador" data-carta-buscador hidden>
                <?= $icono($trazoLupa, 'carta-buscador-icono') ?>
                <input class="carta-buscador-campo" type="search" id="carta-busqueda"
                       placeholder="Buscar en la carta" autocomplete="off"
                       aria-label="Buscar en la carta (<?= (int) $totalProductos ?> productos)"
                       data-carta-busqueda>
            </div>

            <?php // Barra de categorias. Son anclas de verdad: sin JavaScript el
                  // salto al bloque funciona igual, porque cada seccion lleva su id. ?>
            <nav class="carta-barra" aria-label="Categorias de la carta" data-carta-barra>
                <ul>
                    <?php foreach ($porCategoria as $id => $grupo) : ?>
                        <li>
                            <a href="#categoria-<?= Vista::e((string) $id) ?>">
                                <?= Vista::e($grupo['nombre']) ?>
                                <span class="carta-barra-cuenta numerica"><?= count($grupo['productos']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>

        <p class="carta-sin-resultados" data-carta-sin-resultados role="status" hidden>
            Nada en la carta coincide con la búsqueda.
        </p>

        <?php foreach ($porCategoria as $id => $grupo) : ?>
            <section class="carta-categoria" id="categoria-<?= Vista::e((string) $id) ?>" data-carta-categoria>
                <h2><?= Vista::e($grupo['nombre']) ?></h2>

                <ul class="carta-lista">
                    <?php foreach ($grupo['productos'] as $p) : ?>
                        <?php
                        $hay     = (int) $p['disponible'] === 1;
                        $urlFoto = empty($p['foto']) ? '' : Vista::url('/subidas/' . $p['foto']);
                        ?>
                        <li class="carta-item<?= $hay ? '' : ' carta-item-agotado' ?>" data-carta-producto
                            data-nombre="<?= Vista::e($p['nombre']) ?>"
                            data-descripcion="<?= Vista::e($p['descripcion'] ?? '') ?>"
                            data-precio="<?= Vista::e($peso($p['precio'])) ?>">
                            <div class="carta-texto">
                                <h3><?= Vista::e($p['nombre']) ?></h3>

                                <?php if (!empty($p['descripcion'])) : ?>
                                    <p><?= Vista::e($p['descripcion']) ?></p>
                                <?php endif; ?>

                                <div class="carta-pie">
                                    <span class="carta-precio numerica"><?= Vista::e($peso($p['precio'])) ?></span>

                                    <?php // El color no decide solo: el agotado se
                                          // distingue tambien por la palabra. ?>
                                    <?php if (!$hay) : ?>
                                        <span class="etiqueta etiqueta-pendiente">No disponible</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($urlFoto !== '') : ?>
                                <?php // La foto es un enlace a la imagen: sin JavaScript se
                                      // abre sola, y con el, carta.js la muestra en grande
                                      // sin salir de la carta. ?>
                                <a class="carta-foto-enlace" href="<?= Vista::e($urlFoto) ?>" data-carta-ver
                                   aria-label="Ver la foto de <?= Vista::e($p['nombre']) ?>">
                                    <img src="<?= Vista::e($urlFoto) ?>" alt="" class="carta-foto"
                                         width="104" height="104" loading="lazy" decoding="async">
                                </a>
                            <?php else : ?>
                                <?= $sinFoto() ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer class="carta-cierre">
        <a href="<?= Vista::e(Vista::url('/')) ?>">
            <span aria-hidden="true">🍴</span> Ver más food trucks en Menu08
        </a>
    </footer>

    <?php // ------------------------------------------------- visor de foto --
          // <dialog> nativo: encierra el foco, atiende Escape y lo devuelve al
          // cerrar. Lo rellena y lo abre carta.js. ?>
    <dialog class="carta-visor" data-carta-visor aria-labelledby="carta-visor-nombre">
        <form method="dialog" class="carta-visor-cerrar">
            <button type="submit" class="boton-simbolo" aria-label="Cerrar"><?= $icono($trazoCerrar) ?></button>
        </form>

        <img class="carta-visor-foto" alt="" data-carta-visor-foto>

        <div class="carta-visor-texto">
            <div class="carta-visor-fila">
                <h2 class="carta-visor-nombre" id="carta-visor-nombre" data-carta-visor-nombre></h2>
                <span class="carta-precio numerica" data-carta-visor-precio></span>
            </div>
            <p class="carta-visor-descripcion" data-carta-visor-descripcion></p>
        </div>
    </dialog>
</article>
