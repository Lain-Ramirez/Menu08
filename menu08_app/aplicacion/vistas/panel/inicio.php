<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Portada del panel.
 *
 * Es la pantalla que se abre al entrar, y por eso no es un menu: un menu ya lo
 * hay arriba, en la navegacion del marco. Lo que aporta esta es RESPONDER, de
 * un vistazo, las tres cosas que el dueno de un truck quiere saber antes de
 * tocar nada:
 *
 *   1. Que esta viendo ahora mismo el cliente que abre el codigo QR.
 *   2. Donde dice la agenda que estan parados en este momento.
 *   3. Que le falta a la carta para estar completa.
 *
 * Lo tercero es lo que la separa de un tablero de cifras decorativo: los avisos
 * salen SOLO cuando hay algo que arreglar, y cada uno lleva el enlace a la
 * pantalla donde se arregla. Con la carta completa no hay ningun aviso, que es
 * la forma de que se lean cuando aparecen.
 *
 * La cuenta de plataforma no administra ningun catalogo, asi que ve otra cosa:
 * decirle que no tiene productos seria un aviso que no puede atender.
 *
 * @var array<string, mixed>                                          $usuario
 * @var array<string, mixed>|null                                     $truck
 * @var array{categorias: int, productos: int, disponibles: int}|null $resumen
 * @var array{paradas: int, activas: int}|null                        $paradas
 * @var array<string, mixed>|null                                     $vigente
 * @var array<string, mixed>|null                                     $proxima
 * @var array<int, string>                                            $dias
 */

/**
 * Icono de trazo sobre reticula de 24, como el resto de la aplicacion. Nunca
 * emoji: no se recolorean con el tema y cada sistema los dibuja distinto.
 */
$icono = static fn (string $trazos, string $clase = ''): string => sprintf(
    '<svg%s viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
    . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
    $clase === '' ? '' : ' class="' . Vista::e($clase) . '"',
    $trazos
);

$trazoPunto  = '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>';
$trazoCarta  = '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6"/><path d="M9 12h6"/>';
$trazoQr     = '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>'
             . '<rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v7h-3"/>';
$trazoTienda = '<path d="M4 9h16v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9Z"/><path d="M3 9l1.5-5h15L21 9"/><path d="M9 21v-6h6v6"/>';
$trazoEtiqueta = '<path d="M3 12V5a2 2 0 0 1 2-2h7l9 9-9 9-9-9Z"/><circle cx="8" cy="8" r="1.5"/>';
$trazoPlato  = '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>';
$trazoAlerta = '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/>'
             . '<path d="M12 10v3.5"/><path d="M12 17h.01"/>';
$trazoIr     = '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>';

/** La base devuelve TIME como 18:00:00 y aqui solo interesa 18:00. */
$hm = static fn (mixed $hora): string => substr((string) $hora, 0, 5);
?>
<?php if ($truck === null) : ?>

    <?php // ------------------------------------------------- cuenta de plataforma ?>
    <div class="pila pila-1">
        <h1>Panel</h1>
        <p class="texto-apagado">
            Entró como <strong><?= Vista::e($usuario['nombre']) ?></strong>, con el rol
            <code><?= Vista::e($usuario['rol']) ?></code>.
        </p>
    </div>

    <div class="tarjeta tarjeta-contorno pila pila-2">
        <p class="tarjeta-titulo">Esta cuenta administra la plataforma</p>
        <p class="tarjeta-texto">
            No está asociada a ningún food truck, así que no tiene carta, paradas ni caja propias
            que administrar. Las cuentas de cada negocio sí las tienen.
        </p>
        <p class="tarjeta-pie">
            <a class="boton boton-contorno" href="<?= Vista::e(Vista::url('/')) ?>">
                <?= $icono($trazoCarta, 'boton-icono') ?>Ver las cartas publicadas
            </a>
        </p>
    </div>

<?php else : ?>

    <?php
    $sinCarta   = (int) $resumen['disponibles'] === 0;
    $sinParadas = (int) $paradas['activas'] === 0;
    $destacada  = $vigente ?? $proxima;
    ?>

    <?php // ---------------------------------------------------- el negocio --
          // Con el enlace a la carta publica tal como la ve el cliente: es la
          // unica forma honesta de comprobar lo que se publico. ?>
    <section class="panel-inicio-cabecera">
        <?php if (!empty($truck['logo'])) : ?>
            <img class="panel-logo" src="<?= Vista::e(Vista::url('/subidas/' . $truck['logo'])) ?>"
                 alt="Logotipo de <?= Vista::e($truck['nombre']) ?>" width="96" height="96">
        <?php else : ?>
            <span class="panel-logo panel-miniatura-vacia" aria-hidden="true"><?= $icono($trazoTienda) ?></span>
        <?php endif; ?>

        <div class="panel-inicio-datos">
            <h1><?= Vista::e($truck['nombre']) ?></h1>

            <p class="panel-inicio-slug">
                <?php if (!empty($truck['ciudad'])) : ?>
                    <?= Vista::e($truck['ciudad']) ?> ·
                <?php endif; ?>
                /carta/<?= Vista::e($truck['slug']) ?>
            </p>
        </div>

        <div class="panel-inicio-acciones">
            <a class="boton boton-relleno" href="<?= Vista::e(Vista::url('/carta/' . $truck['slug'])) ?>">
                <?= $icono($trazoCarta, 'boton-icono') ?>Ver la carta
            </a>
            <a class="boton boton-contorno" href="<?= Vista::e(Vista::url('/panel/qr')) ?>">
                <?= $icono($trazoQr, 'boton-icono') ?>Código QR
            </a>
        </div>
    </section>

    <?php // ------------------------------------------------- donde estamos --
          // La misma respuesta que da la carta publica, en el mismo sitio donde
          // se administra la agenda. Si aqui dice algo raro, el cliente lo esta
          // leyendo igual de raro. ?>
    <section class="panel-vigente" aria-labelledby="panel-inicio-donde">
        <h2 class="panel-vigente-titulo" id="panel-inicio-donde">Dónde estamos</h2>

        <?php if ($destacada === null) : ?>
            <p class="panel-vigente-texto">
                La agenda no tiene ninguna parada activa, así que la carta no puede decir dónde
                está el truck.
            </p>
        <?php else : ?>
            <p class="panel-vigente-punto">
                <?= $icono($trazoPunto, 'panel-vigente-icono') ?>
                <?= Vista::e($destacada['nombre']) ?>
            </p>

            <?php if (!empty($destacada['referencia'])) : ?>
                <p class="panel-vigente-texto"><?= Vista::e($destacada['referencia']) ?></p>
            <?php endif; ?>

            <p class="panel-vigente-texto numerica">
                <?php if ($vigente !== null) : ?>
                    Abierto hasta las <strong><?= Vista::e($hm($vigente['hora_fin'])) ?></strong>
                <?php else : ?>
                    Cerrado ahora · abre el
                    <?= Vista::e(mb_strtolower($dias[(int) $proxima['dia_semana']] ?? '')) ?>
                    a las <strong><?= Vista::e($hm($proxima['hora_inicio'])) ?></strong>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <p class="tarjeta-pie">
            <a class="boton boton-contorno" href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">
                <?= $icono($trazoPunto, 'boton-icono') ?>Agenda de paradas
            </a>
        </p>
    </section>

    <?php // ---------------------------------------------------- las cifras --
          // Cuatro numeros y ninguno de adorno: los tres primeros son el tamano
          // de la carta y el ultimo, si el truck tiene sitio donde estar. ?>
    <section class="panel-cifras" aria-label="Resumen del catálogo">
        <a class="panel-cifra" href="<?= Vista::e(Vista::url('/panel/categorias')) ?>">
            <span class="panel-cifra-valor"><?= (int) $resumen['categorias'] ?></span>
            <span class="panel-cifra-rotulo">Categorías</span>
        </a>

        <a class="panel-cifra" href="<?= Vista::e(Vista::url('/panel/productos')) ?>">
            <span class="panel-cifra-valor"><?= (int) $resumen['productos'] ?></span>
            <span class="panel-cifra-rotulo">Productos</span>
        </a>

        <a class="panel-cifra<?= $sinCarta ? ' panel-cifra-aviso' : '' ?>"
           href="<?= Vista::e(Vista::url('/panel/productos')) ?>">
            <span class="panel-cifra-valor"><?= (int) $resumen['disponibles'] ?></span>
            <span class="panel-cifra-rotulo">Disponibles ahora</span>
        </a>

        <a class="panel-cifra<?= $sinParadas ? ' panel-cifra-aviso' : '' ?>"
           href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">
            <span class="panel-cifra-valor"><?= (int) $paradas['activas'] ?></span>
            <span class="panel-cifra-rotulo">Paradas activas</span>
        </a>
    </section>

    <?php // ------------------------------------------------------- avisos --
          // Solo cuando hay algo que arreglar, y cada uno con el enlace a donde
          // se arregla. Un aviso que sale siempre deja de leerse. ?>
    <?php if ($sinCarta || $sinParadas) : ?>
        <div class="pila pila-3">
            <?php if ($sinCarta) : ?>
                <div class="aviso aviso-aviso" role="status">
                    <?= $icono($trazoAlerta, 'aviso-icono') ?>
                    <p>
                        <strong>La carta se ve vacía.</strong>
                        <?php if ((int) $resumen['productos'] === 0) : ?>
                            Todavía no hay productos: quien abra el código QR no verá nada que pedir.
                        <?php else : ?>
                            Hay <?= (int) $resumen['productos'] ?> productos, pero ninguno está
                            disponible, así que la carta no muestra ninguno.
                        <?php endif; ?>
                        <a href="<?= Vista::e(Vista::url('/panel/productos')) ?>">Ir a productos</a>.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($sinParadas) : ?>
                <div class="aviso aviso-aviso" role="status">
                    <?= $icono($trazoAlerta, 'aviso-icono') ?>
                    <p>
                        <strong>Sin paradas activas.</strong>
                        <?php if ((int) $paradas['paradas'] === 0) : ?>
                            La agenda está vacía, así que la carta no puede decir dónde para el truck.
                        <?php else : ?>
                            Las <?= (int) $paradas['paradas'] ?> paradas de la agenda están
                            desactivadas y ninguna aparece en la carta.
                        <?php endif; ?>
                        <a href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">Ir a la agenda</a>.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php // ------------------------------------------------------- atajos --
          // La navegacion de arriba lleva a los tres modulos; esto lleva a las
          // pantallas de dentro de CARTA, que es lo que se administra aqui. ?>
    <section class="pila pila-3" aria-labelledby="panel-inicio-atajos">
        <h2 id="panel-inicio-atajos">Administrar la carta</h2>

        <div class="rejilla rejilla-2">
            <a class="tarjeta tarjeta-contorno tarjeta-enlace panel-atajo"
               href="<?= Vista::e(Vista::url('/panel/food-truck')) ?>">
                <span class="panel-atajo-icono"><?= $icono($trazoTienda) ?></span>
                <span class="panel-atajo-texto">
                    <span class="tarjeta-titulo">Datos del negocio</span>
                    <span class="tarjeta-texto">Nombre, logotipo, ciudad y contacto: lo que encabeza la carta.</span>
                </span>
                <?= $icono($trazoIr, 'panel-atajo-ir') ?>
            </a>

            <a class="tarjeta tarjeta-contorno tarjeta-enlace panel-atajo"
               href="<?= Vista::e(Vista::url('/panel/categorias')) ?>">
                <span class="panel-atajo-icono"><?= $icono($trazoEtiqueta) ?></span>
                <span class="panel-atajo-texto">
                    <span class="tarjeta-titulo">Categorías</span>
                    <span class="tarjeta-texto">Los bloques en los que se reparte la carta, y en qué orden salen.</span>
                </span>
                <?= $icono($trazoIr, 'panel-atajo-ir') ?>
            </a>

            <a class="tarjeta tarjeta-contorno tarjeta-enlace panel-atajo"
               href="<?= Vista::e(Vista::url('/panel/productos')) ?>">
                <span class="panel-atajo-icono"><?= $icono($trazoPlato) ?></span>
                <span class="panel-atajo-texto">
                    <span class="tarjeta-titulo">Productos</span>
                    <span class="tarjeta-texto">Precios, fotos y qué hay disponible hoy.</span>
                </span>
                <?= $icono($trazoIr, 'panel-atajo-ir') ?>
            </a>

            <a class="tarjeta tarjeta-contorno tarjeta-enlace panel-atajo"
               href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">
                <span class="panel-atajo-icono"><?= $icono($trazoPunto) ?></span>
                <span class="panel-atajo-texto">
                    <span class="tarjeta-titulo">Paradas</span>
                    <span class="tarjeta-texto">Dónde para el truck cada día y en qué horario.</span>
                </span>
                <?= $icono($trazoIr, 'panel-atajo-ir') ?>
            </a>
        </div>
    </section>

<?php endif; ?>
