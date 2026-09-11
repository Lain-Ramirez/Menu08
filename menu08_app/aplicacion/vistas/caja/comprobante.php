<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Comprobante imprimible de una orden ya registrada.
 *
 * Estrecho a proposito: sale de una impresora termica de rollo, que es lo que
 * lleva un food truck. En pantalla se dibuja al ancho REAL del papel —302 px son
 * los 80 mm del rollo a 96 ppp— porque una vista previa que no mide lo que sale
 * del rollo no sirve de vista previa: el corte de los nombres largos se
 * descubriria al entregar el tiquete.
 *
 * FILETES EN LUGAR DE FONDOS. Una termica quema el papel para ennegrecerlo: los
 * bloques se separan con reglas —de puntos, continua y doble—, que en una
 * termica cuestan una linea, y no con recuadros rellenos, que gastan consumible
 * y se entregan grises.
 *
 * El dialogo de impresion lo abre comprobante.js al cargar, y el boton de la
 * barra lo vuelve a abrir. Sin JavaScript el comprobante se ve igual y se
 * imprime desde el menu del navegador: lo dice el aviso <noscript>.
 *
 * @var array<string, mixed>       $orden
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>|null  $truck
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

$trazoAtras     = '<path d="M19 12H5"/><path d="m11 6-6 6 6 6"/>';
$trazoImpresora = '<path d="M7 9V4h10v5"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M7 14h10v6H7z"/>';
$trazoAlerta    = '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/>'
                . '<path d="M12 10v3.5"/><path d="M12 17h.01"/>';

/** Fecha y hora de la venta. Si la columna trajera algo que no es una fecha se
    imprime tal cual: en el papel vale mas un dato crudo que un hueco. */
$marca = strtotime((string) $orden['creado_en']);
$fecha = $marca === false ? (string) $orden['creado_en'] : date('d/m/Y · H:i', $marca);

/** Articulos de la orden: unidades, no renglones. Es lo que se cuenta contra la
    bolsa al entregar. */
$articulos = 0;

foreach ($items as $i) {
    $articulos += (int) $i['cantidad'];
}

/** Ciudad y telefono del negocio. datos_iniciales.sql los deja vacios a
    proposito —se completan desde el panel—, asi que el renglon solo sale cuando
    hay algo que poner. */
$contacto = array_values(array_filter([
    trim((string) ($truck['ciudad'] ?? '')),
    ($truck['telefono'] ?? '') === '' || $truck['telefono'] === null
        ? '' : 'Tel. ' . trim((string) $truck['telefono']),
], static fn (string $dato): bool => $dato !== ''));

/** Direccion de la carta, para que el cliente vuelva a pedir sin el QR delante.
    Solo si url_base trae servidor: en el papel "/carta/festin-rodante" no se
    puede teclear. */
$carta = '';

if (($truck['slug'] ?? '') !== '') {
    $direccion = Vista::url('/carta/' . $truck['slug']);
    $carta     = preg_match('#^https?://#', $direccion) === 1
        ? (string) preg_replace('#^https?://#', '', $direccion)
        : '';
}
?>
<noscript>
    <div class="aviso aviso-aviso sin-impresion">
        <?= $icono($trazoAlerta, 'aviso-icono') ?>
        <p>
            El diálogo de impresión se abre con JavaScript. Sin él, imprima el comprobante
            desde el menú del navegador o con Ctrl+P: lo que sale al papel es el mismo.
        </p>
    </div>
</noscript>

<div class="fila fila-entre sin-impresion">
    <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/caja')) ?>">
        <?= $icono($trazoAtras, 'boton-icono') ?>Volver a caja
    </a>

    <div class="fila fila-2">
        <span class="texto-apagado texto-p">El diálogo de impresión se abre solo al cargar la página.</span>

        <?php // Sale DESHABILITADO y lo habilita comprobante.js: sin el no puede
              // abrir nada, y un boton que se anuncia como boton y no responde es
              // peor que uno que se anuncia apagado. ?>
        <button type="button" class="boton boton-relleno" data-comprobante-imprimir disabled>
            <?= $icono($trazoImpresora, 'boton-icono') ?>Imprimir de nuevo
        </button>
    </div>
</div>

<div class="comprobante-mesa">
    <div>
        <p class="comprobante-medida">
            <span class="comprobante-medida-linea"></span>
            80 mm · 302 px a 96 ppp
            <span class="comprobante-medida-linea"></span>
        </p>

        <div class="comprobante-hoja">
            <article class="comprobante">
                <header>
                    <p class="comprobante-negocio"><?= Vista::e($truck['nombre'] ?? 'Menu08') ?></p>

                    <?php if ($contacto !== []) : ?>
                        <p class="comprobante-contacto"><?= Vista::e(implode(' · ', $contacto)) ?></p>
                    <?php endif; ?>
                </header>

                <hr class="comprobante-regla">

                <div>
                    <p class="comprobante-rotulo">Orden</p>
                    <p class="comprobante-numero"><?= Vista::e($orden['numero']) ?></p>
                    <p class="comprobante-fecha"><?= Vista::e($fecha) ?></p>
                </div>

                <hr class="comprobante-regla comprobante-regla-solida">

                <div>
                    <?php // La cantidad va delante y en negrita porque es lo que se coteja
                          // con la bolsa; el unitario va debajo del nombre y no en su propia
                          // columna, que a 80 mm no cabe sin partir los nombres. ?>
                    <?php foreach ($items as $i) : ?>
                        <div class="comprobante-linea">
                            <span class="comprobante-cantidad"><?= (int) $i['cantidad'] ?></span>

                            <span class="comprobante-detalle">
                                <span class="comprobante-nombre"><?= Vista::e($i['nombre_producto']) ?></span>
                                <span class="comprobante-unitario">
                                    <?= Vista::e($peso($i['precio_unitario'])) ?> c/u
                                </span>
                            </span>

                            <span class="comprobante-subtotal"><?= Vista::e($peso($i['subtotal'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr class="comprobante-regla comprobante-regla-solida">

                <div class="comprobante-total">
                    <span class="comprobante-total-rotulo">TOTAL</span>
                    <span class="comprobante-total-valor"><?= Vista::e($peso($orden['total'])) ?></span>
                </div>

                <hr class="comprobante-regla comprobante-regla-doble">

                <div>
                    <div class="comprobante-dato">
                        <span class="comprobante-dato-rotulo">Medio de pago</span>
                        <span class="comprobante-dato-valor"><?= Vista::e(ucfirst((string) $orden['medio_pago'])) ?></span>
                    </div>

                    <div class="comprobante-dato">
                        <span class="comprobante-dato-rotulo">Artículos</span>
                        <span class="comprobante-dato-valor numerica"><?= (int) $articulos ?></span>
                    </div>
                </div>

                <?php if (($orden['nota'] ?? '') !== '') : ?>
                    <hr class="comprobante-regla">

                    <p class="comprobante-nota">
                        <span class="comprobante-nota-rotulo">Nota</span>
                        <?= Vista::e($orden['nota']) ?>
                    </p>
                <?php endif; ?>

                <hr class="comprobante-regla">

                <p class="comprobante-gracias">Gracias por su compra</p>

                <?php if ($carta !== '') : ?>
                    <p class="comprobante-pie"><?= Vista::e($carta) ?></p>
                <?php endif; ?>
            </article>
        </div>
    </div>
</div>
