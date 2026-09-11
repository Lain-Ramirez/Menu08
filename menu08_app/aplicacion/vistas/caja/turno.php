<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Turno de CAJA: una sola pantalla para las dos caras del turno.
 *
 * Sin turno abierto se muestra el marcador con el que se abre; con turno abierto,
 * el cuadre con el que se cierra. Son dos pantallas distintas en todo menos en la
 * direccion, y por eso viven juntas: el cajero llega aqui sin saber cual de las
 * dos le toca, y quien lo decide es el estado del turno, no el enlace por el que
 * entro.
 *
 * LOS DOS CAMPOS SON <input> DE VERDAD. El teclado de la apertura y la diferencia
 * viva del cierre los pone turno.js encima, no debajo: sin JavaScript se teclea
 * con el teclado del dispositivo y las dos caras se envian igual. Lo que vale es
 * siempre lo que valida el servidor —Validador::precio, en CajaControlador—, y
 * ninguna cifra de esta pantalla decide nada por su cuenta.
 *
 * @var array<string, mixed>|null $turno     turno abierto, o null si no hay
 * @var array{total: string, ordenes: int, unidades: int, medios: list<array<string, mixed>>}|null $resumen
 * @var array<string, string>     $errores   errores de campo del ultimo envio
 * @var string                    $cajero    nombre de quien tiene la sesion
 * @var string                    $base      base marcada en el intento anterior
 * @var string                    $declarado conteo marcado en el intento anterior
 * @var string|null               $aviso     rechazo que no pertenece a ningun campo
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

$trazoReloj    = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
$trazoCandado  = '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0"/>';
$trazoCerrado  = '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>';
$trazoBorrar   = '<path d="M20 5H9l-6 7 6 7h11a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1Z"/>'
               . '<path d="m17 9-5 6"/><path d="m12 9 5 6"/>';
$trazoInfo     = '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>';
$trazoCheck    = '<path d="m5 12.5 4.5 4.5L19 7"/>';
$trazoArriba   = '<path d="M12 19V6"/><path d="m6 12 6-6 6 6"/>';
$trazoAbajo    = '<path d="M12 5v13"/><path d="m18 12-6 6-6-6"/>';
$trazoAlerta   = '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/>'
               . '<path d="M12 10v3.5"/><path d="M12 17h.01"/>';

/**
 * Medios de pago. Los mismos tres que admite Orden::MEDIOS y que declara la
 * columna medio_pago del esquema; si alli cambian, aqui tambien.
 */
$trazosMedio = [
    'efectivo'      => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
    'tarjeta'       => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'transferencia' => '<path d="M4 8h13"/><path d="m14 5 3 3-3 3"/><path d="M20 16H7"/><path d="m10 13-3 3 3 3"/>',
];
?>
<?php if ($aviso !== null) : ?>
    <div class="aviso aviso-error" role="alert">
        <?= $icono($trazoAlerta, 'aviso-icono') ?>
        <p><?= Vista::e($aviso) ?></p>
    </div>
<?php endif; ?>

<?php if ($turno === null) : ?>

    <?php // =============================================== ABRIR EL TURNO ==
          // Una tarjeta centrada con dos zonas: la cifra a la izquierda y el
          // teclado a la derecha. El turno se abre de pie y con la mano ocupada,
          // asi que la base se marca con teclas de 64 px en vez de con el teclado
          // del sistema, que en una tableta tapa media pantalla. ?>

    <noscript>
        <div class="aviso aviso-aviso">
            <?= $icono($trazoAlerta, 'aviso-icono') ?>
            <p>
                El teclado en pantalla y los valores frecuentes necesitan JavaScript.
                Escriba la base con el teclado del dispositivo: el campo y la apertura
                funcionan igual sin él.
            </p>
        </div>
    </noscript>

    <div class="turno-centrado">
        <form class="turno-apertura tarjeta tarjeta-elevada pila" method="post"
              action="<?= Vista::e(Vista::url('/caja/turno/abrir')) ?>" novalidate data-turno-apertura>
            <?= Csrf::campo() ?>

            <div class="fila fila-entre fila-arriba">
                <div class="pila pila-1 turno-encabezado-texto">
                    <h1>Abrir turno</h1>
                    <p class="tarjeta-texto">Cuente el efectivo del cajón y márquelo aquí.</p>
                </div>

                <span class="turno-corona"><?= $icono($trazoCandado) ?></span>
            </div>

            <div class="turno-meta">
                <div class="turno-meta-dato">
                    <span class="turno-meta-rotulo">Cajero</span>
                    <span class="turno-meta-valor" title="<?= Vista::e($cajero) ?>"><?= Vista::e($cajero) ?></span>
                </div>

                <div class="turno-meta-dato">
                    <span class="turno-meta-rotulo">Apertura</span>
                    <span class="turno-meta-valor numerica"><?= Vista::e(date('d/m/Y · H:i')) ?></span>
                </div>
            </div>

            <div class="turno-marcador">
                <div class="pila pila-3">
                    <div class="pila pila-1">
                        <label class="turno-meta-rotulo" for="base_inicial">Base inicial en caja</label>

                        <?php // La cifra a la derecha y el signo a la izquierda: la base se
                              // cuenta en billetes, no lleva decimales, y asi las dos ultimas
                              // cifras no bailan al escribir. ?>
                        <div class="turno-lectura<?= isset($errores['base_inicial']) ? ' turno-lectura-error' : '' ?>"
                             data-turno-lectura>
                            <span class="turno-lectura-signo" aria-hidden="true">$</span>

                            <input class="turno-lectura-valor" type="text" id="base_inicial" name="base_inicial"
                                   inputmode="numeric" autocomplete="off" required placeholder="0"
                                   value="<?= Vista::e($base) ?>" data-turno-base
                                   aria-describedby="base-apoyo<?= isset($errores['base_inicial']) ? ' base-error' : '' ?>">
                        </div>
                    </div>

                    <?php if (isset($errores['base_inicial'])) : ?>
                        <span class="error-campo" id="base-error" role="alert" data-turno-base-error>
                            <?= Vista::e($errores['base_inicial']) ?>
                        </span>
                    <?php endif; ?>

                    <p class="campo-apoyo turno-apoyo" id="base-apoyo">
                        El efectivo con el que arranca la jornada. Por ejemplo 50.000.
                    </p>

                    <?php // En la ventanilla la base es casi siempre una cifra redonda: un
                          // toque resuelve el caso normal y el teclado queda para la excepcion. ?>
                    <div class="turno-sugerencias sin-impresion" role="group"
                         aria-label="Valores frecuentes de la base">
                        <?php foreach ([50000, 100000, 150000, 200000] as $valor) : ?>
                            <button type="button" class="boton boton-contorno turno-sugerencia"
                                    data-turno-valor="<?= (int) $valor ?>" disabled>
                                <?= Vista::e($peso($valor)) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php // Las teclas salen DESHABILITADAS y las habilita turno.js: sin el no
                      // pueden escribir en el campo, y un boton que se anuncia como boton y
                      // no responde es peor que uno que se anuncia apagado. ?>
                <div class="turno-teclado sin-impresion" role="group" aria-label="Teclado numérico">
                    <?php foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '000', '0', 'borrar'] as $tecla) : ?>
                        <?php $secundaria = $tecla === '000' || $tecla === 'borrar'; ?>
                        <button type="button" class="turno-tecla<?= $secundaria ? ' turno-tecla-secundaria' : '' ?>"
                                data-turno-tecla="<?= Vista::e($tecla) ?>"
                                aria-label="<?= $tecla === 'borrar' ? 'Borrar' : Vista::e($tecla) ?>" disabled>
                            <?= $tecla === 'borrar' ? $icono($trazoBorrar) : '<span>' . Vista::e($tecla) . '</span>' ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="boton boton-relleno boton-bloque turno-enviar" data-turno-enviar>
                <?= $icono($trazoCandado, 'boton-icono') ?>Abrir turno
            </button>
        </form>
    </div>

    <p class="turno-enlace-centro">
        <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/caja/turnos')) ?>">Turnos anteriores</a>
    </p>

<?php else : ?>

    <?php // =============================================== CERRAR EL TURNO =
          // Dos zonas: el resumen a la izquierda y el cierre en una columna que
          // viaja con la pagina. Se puede revisar el desglose entero sin perder
          // de vista el conteo ni el boton. ?>

    <?php
    // El resumen viaja siempre junto al turno abierto. Si alguna vez llegara sin
    // el, la pantalla dice cero y deja cerrar igual: el cuadre definitivo lo
    // calcula el servidor sobre la base, no sobre lo que se vea aqui.
    $resumen ??= ['total' => '0.00', 'ordenes' => 0, 'unidades' => 0, 'medios' => []];

    $esperado = (float) $turno['base_inicial'] + (float) $resumen['total'];
    $hora     = substr((string) $turno['abierto_en'], 11, 5);
    ?>

    <div class="turno-barra">
        <div class="turno-barra-datos">
            <span class="etiqueta etiqueta-turno">
                <?= $icono($trazoReloj, 'etiqueta-icono') ?>
                Turno #<?= (int) $turno['id'] ?> abierto
            </span>

            <span class="turno-barra-cifras">
                <?= Vista::e($turno['cajero']) ?> · abierto a las <strong><?= Vista::e($hora) ?></strong> ·
                <strong><?= (int) $resumen['ordenes'] ?></strong>
                <?= ((int) $resumen['ordenes']) === 1 ? 'orden' : 'órdenes' ?> ·
                <strong><?= Vista::e($peso($resumen['total'])) ?></strong> vendido
            </span>
        </div>

        <a class="boton boton-contorno sin-impresion" href="<?= Vista::e(Vista::url('/caja')) ?>">Seguir vendiendo</a>
    </div>

    <h1>Cerrar turno</h1>

    <div class="turno-cierre">
        <div class="pila pila-5">
            <div class="tarjeta tarjeta-elevada pila turno-resumen">
                <div class="pila pila-1">
                    <span class="turno-meta-rotulo">Total vendido en el turno</span>
                    <span class="turno-cifra"><?= Vista::e($peso($resumen['total'])) ?></span>
                </div>

                <div class="turno-regla"></div>

                <div class="rejilla rejilla-3 turno-datos">
                    <div class="turno-meta-dato">
                        <span class="turno-meta-rotulo">Órdenes</span>
                        <span class="turno-cifra-m"><?= (int) $resumen['ordenes'] ?></span>
                    </div>

                    <div class="turno-meta-dato">
                        <span class="turno-meta-rotulo">Unidades</span>
                        <span class="turno-cifra-m"><?= (int) $resumen['unidades'] ?></span>
                    </div>

                    <div class="turno-meta-dato">
                        <span class="turno-meta-rotulo">Apertura</span>
                        <span class="turno-cifra-m"><?= Vista::e($hora) ?></span>
                    </div>
                </div>
            </div>

            <div class="pila pila-3">
                <h2 class="turno-subtitulo">Por medio de pago</h2>

                <?php if ($resumen['medios'] === []) : ?>
                    <p class="texto-apagado">El turno todavía no tiene órdenes.</p>
                <?php else : ?>
                    <div class="tabla-envoltura">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th scope="col">Medio</th>
                                    <th scope="col" class="cifra">Órdenes</th>
                                    <th scope="col" class="cifra">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumen['medios'] as $m) : ?>
                                    <?php $clave = (string) $m['medio_pago']; ?>
                                    <tr>
                                        <td>
                                            <span class="turno-medio">
                                                <?= $icono($trazosMedio[$clave] ?? $trazoInfo) ?>
                                                <?= Vista::e(ucfirst($clave)) ?>
                                            </span>
                                        </td>
                                        <td class="cifra"><?= (int) $m['ordenes'] ?></td>
                                        <td class="cifra"><?= Vista::e($peso($m['total'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th scope="row">Total del turno</th>
                                    <th class="cifra"><?= (int) $resumen['ordenes'] ?></th>
                                    <th class="cifra"><?= Vista::e($peso($resumen['total'])) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php // El cierre no se deshace: pregunta antes de enviarse con el dialogo de
              // interfaz.js —data-confirmar, sin una linea de JavaScript en la vista— y
              // sin el, el formulario se envia normal y el servidor sigue mandando. ?>
        <form class="tarjeta tarjeta-elevada pila turno-cierre-panel" method="post"
              action="<?= Vista::e(Vista::url('/caja/turno/cerrar')) ?>" novalidate
              data-turno-cierre data-turno-esperado="<?= (int) round($esperado * 100) ?>"
              data-confirmar="¿Cerrar el turno #<?= (int) $turno['id'] ?>? Después de cerrarlo no se pueden registrar más ventas hasta abrir uno nuevo."
              data-confirmar-titulo="Cerrar turno" data-confirmar-aceptar="Cerrar turno"
              data-confirmar-cancelar="Seguir vendiendo" data-confirmar-peligro>
            <?= Csrf::campo() ?>

            <h2 class="tarjeta-titulo">Cierre de caja</h2>

            <?php // La cuenta se escribe como una cuenta y no como una tabla porque es
                  // exactamente eso: base + ventas = lo que deberia haber en el cajon. ?>
            <div class="turno-cuenta">
                <div class="turno-cuenta-linea">
                    <span class="turno-cuenta-rotulo">
                        <span class="turno-cuenta-signo" aria-hidden="true"></span>Base inicial
                    </span>
                    <span class="turno-cuenta-valor"><?= Vista::e($peso($turno['base_inicial'])) ?></span>
                </div>

                <div class="turno-cuenta-linea">
                    <span class="turno-cuenta-rotulo">
                        <span class="turno-cuenta-signo" aria-hidden="true">+</span>Vendido en el turno
                    </span>
                    <span class="turno-cuenta-valor"><?= Vista::e($peso($resumen['total'])) ?></span>
                </div>

                <div class="turno-cuenta-regla"></div>

                <div class="turno-cuenta-linea turno-cuenta-total">
                    <span class="turno-cuenta-rotulo">
                        <span class="turno-cuenta-signo" aria-hidden="true">=</span>Esperado en caja
                    </span>
                    <span class="turno-cifra-m"><?= Vista::e($peso($esperado)) ?></span>
                </div>
            </div>

            <div>
                <div class="campo campo-con-prefijo campo-sobre-contenedor<?= isset($errores['total_declarado']) ? ' campo-error' : '' ?>">
                    <input class="campo-control cifra" type="text" id="total_declarado" name="total_declarado"
                           inputmode="numeric" autocomplete="off" required placeholder=" "
                           value="<?= Vista::e($declarado) ?>" data-turno-conteo
                           aria-describedby="conteo-apoyo<?= isset($errores['total_declarado']) ? ' conteo-error' : '' ?>">
                    <label class="campo-etiqueta" for="total_declarado">Conteo físico de la caja</label>
                    <span class="campo-prefijo" aria-hidden="true">$</span>
                </div>

                <?php if (isset($errores['total_declarado'])) : ?>
                    <span class="error-campo" id="conteo-error" role="alert">
                        <?= Vista::e($errores['total_declarado']) ?>
                    </span>
                <?php endif; ?>

                <p class="campo-apoyo turno-apoyo" id="conteo-apoyo">
                    Lo que hay realmente en la caja al cerrar. La diferencia se calcula sola.
                </p>
            </div>

            <?php // La diferencia nace en blanco y la escribe turno.js mientras se cuenta:
                  // es un adelanto, no un dato. El que queda guardado lo calcula
                  // TurnoCaja::cerrar() en el servidor —total_declarado menos base mas
                  // ventas— y se ve en el detalle del turno recien cerrado.
                  //
                  // El estado nunca se distingue solo por el color: fondo, icono Y palabra,
                  // que es lo que md3.css exige en la banda calida de esta paleta. ?>
            <div class="turno-diferencia" role="status" data-turno-cuadre>
                <span class="turno-diferencia-rotulo">
                    <span data-turno-cuadre-icono="sin-contar"><?= $icono($trazoInfo) ?></span>
                    <span data-turno-cuadre-icono="cuadra" hidden><?= $icono($trazoCheck) ?></span>
                    <span data-turno-cuadre-icono="sobrante" hidden><?= $icono($trazoArriba) ?></span>
                    <span data-turno-cuadre-icono="faltante" hidden><?= $icono($trazoAbajo) ?></span>
                    <span data-turno-cuadre-rotulo>Diferencia</span>
                </span>

                <span class="turno-cifra-m" data-turno-cuadre-valor>—</span>
            </div>

            <button type="submit" class="boton boton-peligro boton-bloque turno-enviar" data-turno-enviar>
                <?= $icono($trazoCerrado, 'boton-icono') ?>Cerrar turno
            </button>

            <p class="turno-enlace-centro sin-impresion">
                <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/caja')) ?>">Seguir vendiendo</a>
            </p>
        </form>
    </div>

<?php endif; ?>
