<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Tablero del Sistema de Visualizacion de Produccion.
 *
 * Es la pantalla de la cocina: las ordenes que CAJA registra aparecen aqui como
 * tarjetas, avanzan de estado con una pulsacion y el tablero se refresca solo
 * por sondeo. Se lee de pie y a metro y medio, asi que el numero de orden va a
 * 40 px y el boton de avance a 56 de alto.
 *
 * EL PRIMER PINTADO LO HACE EL SERVIDOR. El tablero llega con las ordenes ya
 * puestas y svp.js toma el relevo desde ahi: si el sondeo tarda, o si nunca
 * llega a arrancar, en la pared hay un tablero de verdad y no un hueco. Sin
 * JavaScript se ve lo mismo, pero congelado —lo dice el aviso <noscript>— y los
 * botones de avance salen deshabilitados, porque sin fetch no pueden mover
 * nada.
 *
 * UNA SOLA FUENTE DEL MARCADO. La tarjeta se escribe una vez, aqui, en $tarjeta.
 * Con datos sirve para el primer pintado y vacia se emite dentro de un
 * <template> por estado, que es lo que svp.js clona cuando entra una orden
 * nueva. Asi el guion no repite ni una etiqueta: solo rellena texto. Si cambia
 * la tarjeta, cambia en un unico sitio.
 *
 * @var int|null                   $turno    turno abierto, o null si no hay
 * @var list<array<string, mixed>> $ordenes  ordenes en curso, ya ordenadas
 * @var string                     $ahora    reloj del servidor al pintar
 * @var int                        $demora   minutos a partir de los cuales se realza
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

$trazoReloj   = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
$trazoAlerta  = '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/>'
              . '<path d="M12 10v3.5"/><path d="M12 17h.01"/>';
$trazoCheck   = '<path d="m5 12.5 4.5 4.5L19 7"/>';
$trazoPersiana = '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M3 14h18"/>';

/**
 * Los tres estados en curso, en el orden en que viaja una orden.
 *
 * La tabla de transiciones de verdad vive en Orden::TRANSICIONES; esto es lo que
 * hace falta para pintarla: como se llama la columna, que color le toca, a que
 * estado pasa y con que palabra se pulsa.
 */
$estados = [
    'pendiente' => [
        'titulo'   => 'Pendientes',
        'nombre'   => 'Pendiente',
        'columna'  => 'svp-columna-pendiente',
        'tarjeta'  => 'svp-tarjeta-pendiente',
        'etiqueta' => 'etiqueta-pendiente',
        'destino'  => 'en_preparacion',
        'accion'   => 'Empezar',
        'vacia'    => 'Nada esperando.',
    ],
    'en_preparacion' => [
        'titulo'   => 'En preparación',
        'nombre'   => 'En preparacion',
        'columna'  => 'svp-columna-preparacion',
        'tarjeta'  => 'svp-tarjeta-preparacion',
        'etiqueta' => 'etiqueta-preparacion',
        'destino'  => 'lista',
        'accion'   => 'Marcar lista',
        'vacia'    => 'Nada en la plancha.',
    ],
    'lista' => [
        'titulo'   => 'Listas',
        'nombre'   => 'Lista',
        'columna'  => 'svp-columna-lista',
        'tarjeta'  => 'svp-tarjeta-lista',
        'etiqueta' => 'etiqueta-lista',
        'destino'  => 'entregada',
        'accion'   => 'Entregar',
        'vacia'    => 'Nada esperando en la ventanilla.',
    ],
];

/** Hora de una marca de tiempo de la base, sin la fecha: en el tablero solo hay
    ordenes de hoy y la fecha entera roba sitio a lo que importa. */
$horaDe = static function (?string $marca): string {
    $t = $marca === null ? false : strtotime($marca);

    return $t === false ? '--:--' : date('H:i', $t);
};

/** Cronometro en MM:SS, y en H:MM:SS cuando pasa de la hora. Es el mismo formato
    que escribe svp.js cada segundo: si cambia uno, cambia el otro. */
$cronometro = static function (int $segundos): string {
    $segundos = max(0, $segundos);
    $horas    = intdiv($segundos, 3600);
    $minutos  = intdiv($segundos % 3600, 60);
    $resto    = $segundos % 60;

    return $horas > 0
        ? sprintf('%d:%02d:%02d', $horas, $minutos, $resto)
        : sprintf('%02d:%02d', $minutos, $resto);
};

/**
 * La tarjeta de una orden.
 *
 * Con $o a null emite el esqueleto vacio para el <template>: mismas etiquetas,
 * mismas clases y los huecos sin rellenar.
 *
 * @param array<string, mixed>|null $o
 * @param array<string, string>     $e estado al que pertenece la tarjeta
 */
$tarjeta = static function (?array $o, array $e) use ($icono, $trazoAlerta, $horaDe, $cronometro, $ahora): string {
    $segundos = 0;

    if ($o !== null) {
        $inicio   = strtotime((string) $o['creado_en']);
        $fin      = strtotime($ahora);
        $segundos = $inicio === false || $fin === false ? 0 : $fin - $inicio;
    }

    $demorada = $o !== null && (bool) $o['demorada'];

    return '<li class="svp-tarjeta ' . Vista::e($e['tarjeta']) . ($demorada ? ' svp-tarjeta-demorada' : '') . '"'
        . ' data-svp-tarjeta'
        . ' data-svp-orden="' . ($o === null ? '' : (int) $o['id']) . '"'
        . ' data-svp-estado="' . Vista::e($o === null ? '' : (string) $o['estado']) . '"'
        . ' data-svp-creado="' . Vista::e($o === null ? '' : (string) $o['creado_en']) . '">'

        . '<div class="svp-tarjeta-cabeza">'
        . '<span class="svp-numero" data-svp-numero>' . Vista::e($o === null ? '' : (string) $o['numero']) . '</span>'
        . '<span class="etiqueta ' . Vista::e($e['etiqueta']) . '">' . Vista::e($e['nombre']) . '</span>'
        . '</div>'

        . '<p class="svp-tiempos">'
        . '<span>Recibida a las <span class="numerica" data-svp-recibida>'
        . Vista::e($o === null ? '--:--' : $horaDe((string) $o['creado_en'])) . '</span></span>'
        . '<span class="svp-reloj numerica" data-svp-reloj>' . Vista::e($cronometro($segundos)) . '</span>'
        . '</p>'

        // El realce nunca es solo color: la etiqueta pone la palabra al lado.
        . '<span class="etiqueta etiqueta-demorada" data-svp-demora' . ($demorada ? '' : ' hidden') . '>'
        . $icono($trazoAlerta, 'etiqueta-icono') . 'Demorada</span>'

        . '<ul class="svp-lineas" data-svp-lineas>'
        . implode('', array_map(
            static fn (array $i): string => '<li class="svp-linea">'
                . '<span class="svp-cantidad">' . (int) $i['cantidad'] . '</span>'
                . '<span class="svp-producto">' . Vista::e($i['nombre']) . '</span>'
                . '</li>',
            $o === null ? [] : (array) $o['items']
        ))
        . '</ul>'

        . '<p class="svp-nota" data-svp-nota' . ($o === null || ($o['nota'] ?? null) === null ? ' hidden' : '') . '>'
        . '<span class="svp-nota-rotulo">Nota</span>'
        . '<span data-svp-nota-texto>' . Vista::e($o === null ? '' : (string) ($o['nota'] ?? '')) . '</span>'
        . '</p>'

        // Sale deshabilitado y lo habilita svp.js: sin fetch no puede mover nada.
        . '<button type="button" class="boton boton-relleno svp-avance"'
        . ' data-svp-avance="' . Vista::e($e['destino']) . '" disabled>'
        . Vista::e($e['accion']) . '</button>'

        . '</li>';
};

/** Las ordenes ya llegan ordenadas por estado y antiguedad; aqui solo se
    reparten por columna sin tocar ese orden. */
$porEstado = array_fill_keys(array_keys($estados), []);

foreach ($ordenes as $o) {
    $codigo = (string) $o['estado'];

    if (isset($porEstado[$codigo])) {
        $porEstado[$codigo][] = $o;
    }
}

$hayTurno = $turno !== null;
?>
<?php // Las direcciones las escribe el servidor, no el guion: url_base puede
      // llevar subcarpeta —http://localhost/Menu08/publico— y una ruta absoluta
      // escrita a mano en JavaScript solo acierta en produccion. ?>
<div class="svp" data-svp
     data-svp-demora="<?= (int) $demora ?>"
     data-svp-ahora="<?= Vista::e($ahora) ?>"
     data-svp-ordenes="<?= Vista::e(Vista::url('/svp/ordenes')) ?>"
     data-svp-avance-base="<?= Vista::e(Vista::url('/svp/orden/')) ?>">

    <noscript>
        <div class="aviso aviso-aviso">
            <?= $icono($trazoAlerta, 'aviso-icono') ?>
            <p>
                Sin JavaScript el tablero no se refresca solo ni avanza las órdenes: lo que se ve es
                la foto del momento en que se cargó la página. Actívelo en el navegador, o recargue
                para ver los cambios.
            </p>
        </div>
    </noscript>

    <div class="svp-barra">
        <div class="svp-barra-datos">
            <h1 class="solo-lectores">Sistema de Visualización de Producción</h1>

            <?php if ($hayTurno) : ?>
                <span class="etiqueta etiqueta-turno">
                    <?= $icono($trazoReloj, 'etiqueta-icono') ?>
                    Turno #<?= (int) $turno ?>
                </span>
            <?php else : ?>
                <span class="etiqueta etiqueta-entregada">
                    <?= $icono($trazoReloj, 'etiqueta-icono') ?>
                    Sin turno abierto
                </span>
            <?php endif; ?>

            <span class="svp-barra-cifras">
                <strong data-svp-total><?= count($ordenes) ?></strong>
                <span data-svp-total-rotulo><?= count($ordenes) === 1 ? 'orden en curso' : 'órdenes en curso' ?></span>
                · se realzan a partir de <strong data-svp-demora-rotulo><?= (int) $demora ?></strong> min
            </span>
        </div>

        <?php // Un tablero que se pinta solo tiene que decir que sigue vivo: sin
              // esto, una pantalla congelada y una cocina al dia se ven igual. ?>
        <span class="svp-latido" data-svp-latido>
            <span class="svp-latido-punto" aria-hidden="true"></span>
            <span data-svp-latido-texto>Actualizado ahora</span>
        </span>
    </div>

    <?php // El fallo del sondeo no borra el tablero: se avisa aqui, las ordenes
          // se quedan en pantalla y el ciclo siguiente vuelve a intentarlo. ?>
    <div class="aviso aviso-error" role="alert" data-svp-aviso hidden>
        <?= $icono($trazoAlerta, 'aviso-icono') ?>
        <p data-svp-aviso-texto></p>
    </div>

    <div class="svp-columnas" data-svp-columnas<?= $hayTurno && $ordenes !== [] ? '' : ' hidden' ?>>
        <?php foreach ($estados as $codigo => $e) : ?>
            <section class="svp-columna <?= Vista::e($e['columna']) ?>"
                     data-svp-columna="<?= Vista::e($codigo) ?>"
                     aria-labelledby="svp-columna-<?= Vista::e($codigo) ?>">
                <div class="svp-columna-cabeza">
                    <h2 class="svp-columna-titulo" id="svp-columna-<?= Vista::e($codigo) ?>">
                        <?= Vista::e($e['titulo']) ?>
                    </h2>
                    <span class="svp-columna-cuenta" data-svp-cuenta><?= count($porEstado[$codigo]) ?></span>
                </div>

                <ul class="svp-lista" data-svp-lista>
                    <?php foreach ($porEstado[$codigo] as $o) : ?>
                        <?= $tarjeta($o, $e) ?>
                    <?php endforeach; ?>
                </ul>

                <p class="svp-columna-vacia" data-svp-columna-vacia<?= $porEstado[$codigo] === [] ? '' : ' hidden' ?>>
                    <?= Vista::e($e['vacia']) ?>
                </p>
            </section>
        <?php endforeach; ?>
    </div>

    <?php // Dos pantallas que se parecen y no significan lo mismo: sin turno la
          // ventanilla esta cerrada; con turno y sin ordenes, la cocina va al dia. ?>
    <div class="svp-vacio" data-svp-vacio="cerrado"<?= $hayTurno ? ' hidden' : '' ?>>
        <span class="svp-vacio-icono"><?= $icono($trazoPersiana) ?></span>
        <h2 class="svp-vacio-titulo">La ventanilla está cerrada</h2>
        <p class="svp-vacio-texto">
            No hay ningún turno abierto, así que no entran órdenes. El tablero se enciende solo
            en cuanto CAJA abra el turno.
        </p>
    </div>

    <div class="svp-vacio" data-svp-vacio="al-dia"<?= $hayTurno && $ordenes === [] ? '' : ' hidden' ?>>
        <span class="svp-vacio-icono"><?= $icono($trazoCheck) ?></span>
        <h2 class="svp-vacio-titulo">Producción al día</h2>
        <p class="svp-vacio-texto">
            El turno está abierto y no queda nada en la plancha. Las órdenes nuevas aparecen aquí
            solas, sin recargar.
        </p>
    </div>

    <?php // Las plantillas de svp.js: la misma tarjeta de arriba, vacia y una por
          // estado, para que el guion clone en lugar de escribir marcado. ?>
    <?php foreach ($estados as $codigo => $e) : ?>
        <template data-svp-plantilla="<?= Vista::e($codigo) ?>"><?= $tarjeta(null, $e) ?></template>
    <?php endforeach; ?>

    <template data-svp-plantilla-linea><li class="svp-linea"><span class="svp-cantidad"></span><span class="svp-producto"></span></li></template>
</div>
