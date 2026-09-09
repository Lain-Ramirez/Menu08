<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Pantalla de venta de CAJA: catalogo, orden en construccion y cobro.
 *
 * Es la pantalla que mas se usa durante el turno, asi que esta pensada para
 * cerrar una orden con el minimo de pulsaciones: se toca el producto y entra en
 * la orden, se ajusta la cantidad en el mismo renglon y se cobra sin recargar.
 *
 * TODO EL DOCUMENTO ES UN SOLO FORMULARIO. Cada ficha del catalogo lleva su
 * campo oculto cantidad[ID], que es lo que viaja en el envio; caja.js lo
 * mantiene al dia mientras se arma la orden. Asi la pantalla no inyecta nada al
 * enviar, y una orden rechazada por el servidor vuelve con las cantidades ya
 * escritas y se recompone sola.
 *
 * Los precios que se ven aqui son informativos. El que vale es el que
 * Orden::registrar lee de la base dentro de la transaccion que escribe la
 * venta: lo que diga el navegador sobre importes se descarta.
 *
 * @var array<string, mixed>|null  $turno     turno vigente, o null si se cerro
 * @var array<string, mixed>|null  $resumen   totales del turno vigente
 * @var list<array<string, mixed>> $catalogo  productos disponibles
 * @var list<array<string, mixed>> $ordenes   ordenes ya registradas del turno
 * @var string|null                $error     rechazo del ultimo intento de venta
 * @var array<int, int>            $seleccion cantidades del intento anterior
 * @var string                     $medioPago medio elegido en el intento anterior
 * @var string                     $nota      nota del intento anterior
 */

$peso = static fn (mixed $n): string => '$ ' . number_format((float) $n, 0, ',', '.');

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

$trazoReloj  = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
$trazoCheck  = '<path d="m5 12.5 4.5 4.5L19 7"/>';
$trazoCarro  = '<path d="M3 5h2l2.2 10.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.55L20.5 9H6"/>'
             . '<circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>';
$trazoImagen = '<path d="M4 19.5V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v13.5"/>'
             . '<path d="M4 17l4.5-4.5a2 2 0 0 1 2.8 0L16 17"/>'
             . '<path d="M14 15l1.5-1.5a2 2 0 0 1 2.8 0L20 15"/><circle cx="9" cy="9" r="1.2"/>';

/**
 * Medios de pago. Son los mismos tres que admite Orden::MEDIOS y que declara la
 * columna medio_pago del esquema; si alli cambian, aqui tambien.
 */
$medios = [
    'efectivo'      => ['Efectivo', '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>'],
    'tarjeta'       => ['Tarjeta', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'],
    'transferencia' => ['Transferencia', '<path d="M4 8h13"/><path d="m14 5 3 3-3 3"/><path d="M20 16H7"/><path d="m10 13-3 3 3 3"/>'],
];

if (!array_key_exists($medioPago, $medios)) {
    $medioPago = 'efectivo';
}

/** Categorias presentes en el catalogo, en el orden en que las trae el modelo. */
$categorias = [];

foreach ($catalogo as $p) {
    $categorias[(int) $p['categoria_id']] = (string) $p['categoria'];
}

$abierto = $turno !== null;
?>
<form class="venta" method="post" action="<?= Vista::e(Vista::url('/caja/vender')) ?>" novalidate
      data-venta data-turno="<?= $abierto ? 'abierto' : 'cerrado' ?>">
    <?= Csrf::campo() ?>

    <h1 class="solo-lectores">Venta</h1>

    <?php // Lo que cambia al armar la orden se anuncia una sola vez y en corto:
          // repetir la lista entera en cada pulsacion la vuelve inservible. ?>
    <p class="solo-lectores" aria-live="polite" data-venta-anuncio></p>

    <noscript>
        <div class="aviso aviso-aviso venta-noscript">
            <?= $icono('<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/><path d="M12 10v3.5"/><path d="M12 17h.01"/>', 'aviso-icono') ?>
            <p>
                Esta pantalla necesita JavaScript para armar la orden y cobrar. Actívelo en el
                navegador y vuelva a cargar. El turno se abre y se cierra en
                <a href="<?= Vista::e(Vista::url('/caja/turno')) ?>">Turno de caja</a>, que sí funciona sin él.
            </p>
        </div>
    </noscript>

    <?php if ($error !== null) : ?>
        <div class="aviso aviso-error" role="alert">
            <?= $icono('<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5"/><path d="M12 16h.01"/>', 'aviso-icono') ?>
            <p><?= Vista::e($error) ?></p>
        </div>
    <?php endif; ?>

    <?php // ---------------------------------------------- barra del turno --
          // Encabeza la pantalla porque el turno manda: sin turno abierto no
          // hay a que pertenecer la orden ni cuadre en el que entre. ?>
    <div class="venta-turno">
        <div class="venta-turno-datos">
            <?php if ($abierto) : ?>
                <span class="etiqueta etiqueta-turno">
                    <?= $icono($trazoReloj, 'etiqueta-icono') ?>
                    Turno #<?= (int) $turno['id'] ?> abierto
                </span>

                <span class="venta-turno-cifras">
                    <?= Vista::e($turno['cajero']) ?> ·
                    <strong><?= (int) ($resumen['ordenes'] ?? 0) ?></strong>
                    <?= ((int) ($resumen['ordenes'] ?? 0)) === 1 ? 'orden' : 'ordenes' ?> ·
                    <strong><?= Vista::e($peso($resumen['total'] ?? 0)) ?></strong> vendido
                </span>
            <?php else : ?>
                <span class="etiqueta etiqueta-pendiente">
                    <?= $icono($trazoReloj, 'etiqueta-icono') ?>
                    Sin turno abierto
                </span>

                <span class="venta-turno-cifras">Abra el turno para poder vender.</span>
            <?php endif; ?>
        </div>

        <a class="boton boton-contorno" href="<?= Vista::e(Vista::url('/caja/turno')) ?>">
            <?= $abierto ? 'Cerrar turno' : 'Abrir turno' ?>
        </a>
    </div>

    <div class="venta-zonas" data-venta-zonas>
        <?php // ------------------------------------------------- catalogo --
              // El filtro y la busqueda actuan sobre estas mismas fichas, ya
              // renderizadas: no hay ninguna consulta de por medio. ?>
        <section class="venta-catalogo" aria-labelledby="venta-catalogo-titulo">
            <h2 class="solo-lectores" id="venta-catalogo-titulo">Catalogo</h2>

            <div class="campo venta-buscador">
                <input class="campo-control" type="search" id="venta-busqueda" placeholder=" "
                       autocomplete="off" data-venta-busqueda>
                <label class="campo-etiqueta" for="venta-busqueda">Buscar producto</label>
            </div>

            <?php if ($categorias !== []) : ?>
                <div class="venta-chips" role="group" aria-label="Filtrar por categoria">
                    <?php // El chip activo no se distingue solo por el fondo: lleva
                          // la marca de verificado y su estado en aria-pressed. ?>
                    <button type="button" class="venta-chip" data-venta-categoria="" aria-pressed="true">
                        <?= $icono($trazoCheck, 'venta-chip-marca') ?>Todo
                    </button>

                    <?php foreach ($categorias as $id => $nombre) : ?>
                        <button type="button" class="venta-chip"
                                data-venta-categoria="<?= (int) $id ?>" aria-pressed="false">
                            <?= $icono($trazoCheck, 'venta-chip-marca') ?><?= Vista::e($nombre) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($catalogo === []) : ?>
                <p class="aviso aviso-aviso">
                    <?= $icono('<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>', 'aviso-icono') ?>
                    No hay productos disponibles para vender. Revise el catálogo en el panel de CARTA.
                </p>
            <?php else : ?>
                <ul class="venta-rejilla" data-venta-rejilla>
                    <?php foreach ($catalogo as $p) : ?>
                        <?php
                        $id = (int) $p['id'];
                        // En centavos y con enteros, igual que Orden::registrar: es lo
                        // que impide que la suma del navegador se separe de la del
                        // servidor por redondeo.
                        $centavos = (int) round((float) $p['precio'] * 100);
                        $cantidad = (int) ($seleccion[$id] ?? 0);
                        ?>
                        <li class="venta-ficha" data-venta-producto
                            data-categoria="<?= (int) $p['categoria_id'] ?>">
                            <?php // Deshabilitada de origen: sin JavaScript no puede
                                  // hacer nada, y asi se anuncia como no disponible en
                                  // vez de como un boton que no responde. ?>
                            <button type="button" class="venta-ficha-boton" disabled
                                    data-venta-agregar="<?= $id ?>"
                                    data-precio="<?= $centavos ?>"
                                    data-nombre="<?= Vista::e($p['nombre']) ?>">
                                <?php if (!empty($p['foto'])) : ?>
                                    <img class="venta-ficha-foto"
                                         src="<?= Vista::e(Vista::url('/subidas/' . $p['foto'])) ?>"
                                         alt="" loading="lazy">
                                <?php else : ?>
                                    <?php // Mismo hueco que la foto, para que la
                                          // reticula no baile. ?>
                                    <span class="venta-ficha-foto venta-ficha-vacia">
                                        <?= $icono($trazoImagen) ?>
                                    </span>
                                <?php endif; ?>

                                <span class="venta-ficha-nombre"><?= Vista::e($p['nombre']) ?></span>
                                <span class="venta-ficha-precio"><?= Vista::e($peso($p['precio'])) ?></span>
                            </button>

                            <?php // La cantidad que viaja en el envio. caja.js la
                                  // mantiene al dia; el servidor la vuelve a emitir
                                  // cuando rechaza una venta, y asi la orden se
                                  // recompone sin que el cajero la rearme. ?>
                            <input type="hidden" name="cantidad[<?= $id ?>]"
                                   value="<?= $cantidad ?>" data-venta-cantidad>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p class="venta-vacio" data-venta-vacio hidden>
                    Ningún producto coincide con la búsqueda.
                </p>
            <?php endif; ?>
        </section>

        <?php // -------------------------------------- orden en construccion ?>
        <aside class="venta-orden" aria-labelledby="venta-orden-titulo">
            <div class="venta-orden-cabeza">
                <h2 class="venta-orden-titulo" id="venta-orden-titulo">Orden</h2>

                <button type="button" class="boton boton-texto" data-venta-vaciar disabled>Vaciar</button>
            </div>

            <ul class="venta-lineas" data-venta-lineas></ul>

            <p class="venta-orden-vacia" data-venta-orden-vacia>
                Toque un producto del catálogo para empezar la orden.
            </p>

            <div class="venta-resumen">
                <div class="venta-resumen-fila texto-m texto-apagado">
                    <span>Artículos</span>
                    <span class="cifra" data-venta-unidades>0</span>
                </div>

                <div class="venta-resumen-fila">
                    <span class="venta-resumen-rotulo">Total</span>
                    <span class="venta-resumen-total" data-venta-total><?= Vista::e($peso(0)) ?></span>
                </div>
            </div>

            <button type="button" class="boton boton-relleno venta-cobrar" data-venta-cobrar disabled>
                <?= $icono($trazoCarro, 'boton-icono') ?>Cobrar
            </button>
        </aside>
    </div>

    <?php // ------------------------------------------------ dialogo de cobro
          // <dialog> nativo y no el velo que dibuja interfaz.js: aquel se
          // construye desde JavaScript para preguntar si o no, y este lleva
          // dentro campos que viajan en el envio. Ademas encierra el foco, lo
          // devuelve al cerrar y atiende Escape sin reimplementarlo. ?>
    <dialog class="venta-dialogo" data-venta-dialogo aria-labelledby="venta-dialogo-titulo">
        <div class="venta-dialogo-cuerpo">
            <h2 class="venta-dialogo-titulo" id="venta-dialogo-titulo">Cobrar la orden</h2>

            <div class="venta-dialogo-total">
                <?php // La frase entera la escribe caja.js: con el numero por un
                      // lado y "articulos" fijo al lado salia "1 articulos". ?>
                <span class="texto-m texto-apagado" data-venta-dialogo-unidades>0 artículos</span>
                <span class="venta-dialogo-total-valor" data-venta-dialogo-total><?= Vista::e($peso(0)) ?></span>
            </div>

            <fieldset class="venta-grupo">
                <legend class="venta-grupo-rotulo">Medio de pago</legend>

                <div class="venta-medios">
                    <?php foreach ($medios as $valor => [$rotulo, $trazos]) : ?>
                        <label class="venta-medio">
                            <input class="solo-lectores" type="radio" name="medio_pago"
                                   value="<?= Vista::e($valor) ?>"
                                   <?= $valor === $medioPago ? 'checked' : '' ?> data-venta-medio>
                            <?= $icono($trazos) ?>
                            <span><?= Vista::e($rotulo) ?></span>
                            <?= $icono($trazoCheck, 'venta-medio-marca') ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <?php // Solo con efectivo hay cambio que calcular. Con tarjeta el
                  // campo sobrando invita a rellenarlo y no significa nada. ?>
            <div class="venta-efectivo" data-venta-efectivo>
                <?php // En la ventanilla se paga casi siempre con un billete
                      // redondo: un toque resuelve el caso normal y el teclado
                      // queda para la excepcion. Los valores los calcula
                      // caja.js sobre el total de cada orden. ?>
                <div class="venta-sugerencias" role="group"
                     aria-label="Valores recibidos frecuentes" data-venta-sugerencias></div>

                <div class="campo" data-venta-recibido-campo>
                    <input class="campo-control cifra" type="text" id="venta-recibido"
                           inputmode="numeric" autocomplete="off" placeholder=" "
                           aria-describedby="venta-recibido-error" data-venta-recibido>
                    <label class="campo-etiqueta" for="venta-recibido">Recibido</label>
                    <span class="error-campo" id="venta-recibido-error"
                          data-venta-recibido-error hidden></span>
                </div>

                <?php // El apagado va en el bloque entero y no solo en la cifra:
                      // sin valor suficiente no es que el cambio sea cero, es
                      // que todavia no se puede calcular. ?>
                <div class="venta-cambio venta-cambio-sin" data-venta-cambio-bloque>
                    <span class="venta-cambio-rotulo">Cambio</span>
                    <span class="venta-cambio-valor" data-venta-cambio>—</span>
                </div>
            </div>

            <?php // Se usa poco —"sin cebolla", "para llevar"— y ocupaba un campo
                  // entero en un dialogo que ya va justo de alto. Plegada con
                  // <details>, que no necesita ni una linea de JavaScript: el
                  // campo de dentro se envia igual con la nota cerrada. Vuelve
                  // abierta si el intento anterior traia nota. ?>
            <details class="venta-nota"<?= $nota === '' ? '' : ' open' ?>>
                <summary class="venta-nota-resumen">Nota para producción</summary>

                <div class="campo venta-nota-campo">
                    <input class="campo-control" type="text" id="venta-nota" name="nota"
                           maxlength="300" autocomplete="off" placeholder=" "
                           value="<?= Vista::e($nota) ?>">
                    <label class="campo-etiqueta" for="venta-nota">Sin cebolla, para llevar…</label>
                </div>
            </details>

            <div class="venta-dialogo-acciones">
                <button type="button" class="boton boton-texto" data-venta-cancelar>Cancelar</button>

                <button type="submit" class="boton boton-relleno" data-venta-confirmar disabled>
                    <?= $icono($trazoCheck, 'boton-icono') ?>Confirmar cobro
                </button>
            </div>
        </div>
    </dialog>
</form>

<?php // --------------------------------------------- ordenes de este turno --
      // Fuera de la pantalla de venta y debajo: la venta ocupa el alto de la
      // ventana y esto se consulta de vez en cuando, no en cada orden. ?>
<?php if ($abierto) : ?>
    <section class="venta-historial" aria-labelledby="venta-historial-titulo">
        <h2 id="venta-historial-titulo">Órdenes de este turno</h2>

        <?php if ($ordenes === []) : ?>
            <p class="texto-apagado">Todavía no hay órdenes en el turno.</p>
        <?php else : ?>
            <div class="tabla-envoltura">
                <table class="tabla tabla-apilable">
                    <thead>
                        <tr>
                            <th scope="col">Número</th>
                            <th scope="col" class="cifra">Total</th>
                            <th scope="col">Medio</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Hora</th>
                            <th scope="col"><span class="solo-lectores">Comprobante</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordenes as $o) : ?>
                            <tr>
                                <td data-etiqueta="Número"><?= Vista::e($o['numero']) ?></td>
                                <td data-etiqueta="Total" class="cifra"><?= Vista::e($peso($o['total'])) ?></td>
                                <td data-etiqueta="Medio"><?= Vista::e(ucfirst((string) $o['medio_pago'])) ?></td>
                                <td data-etiqueta="Estado"><?= Vista::e($o['estado']) ?></td>
                                <td data-etiqueta="Hora" class="numerica">
                                    <?= Vista::e(substr((string) $o['creado_en'], 11, 5)) ?>
                                </td>
                                <td class="tabla-acciones">
                                    <a class="boton boton-texto"
                                       href="<?= Vista::e(Vista::url('/caja/comprobante/' . $o['id'])) ?>">
                                        Comprobante
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
