<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Agenda de paradas del food truck.
 *
 * Una sola pantalla para las tres cosas que se hacen con una parada: crearla,
 * editarla y darla de baja. El formulario es el mismo para lo primero y lo
 * segundo —cambia el titulo y el valor del campo oculto `id`—, y la baja es
 * logica: la parada se queda en la agenda, deja de salir en la carta publica y
 * se puede volver a activar. Un food truck cambia de sitio por temporadas, y
 * una parada borrada de verdad habria que volver a escribirla entera.
 *
 * LAS PARADAS SE AGRUPAN POR DIA. Es como se piensa una semana de truck —«los
 * miercoles estamos en el parque»— y no como una tabla ordenada por fecha de
 * creacion. Los dias sin ninguna parada se dicen al final, en una linea: un
 * hueco en la agenda es informacion, no ausencia de informacion.
 *
 * La comprobacion del navegador la pone validacion.js a traves de los atributos
 * data-validar-*. Los mensajes se escriben aqui, al lado del campo y con las
 * mismas palabras que usa Validador en el servidor, que es quien manda.
 *
 * @var list<array<string, mixed>> $ubicaciones todas, activas e inactivas
 * @var array<string, mixed>|null  $edita       parada en edicion, o null
 * @var array<string, string>      $errores     errores del ultimo envio
 * @var array<int, string>         $dias        1 lunes ... 7 domingo
 * @var array<string, mixed>|null  $vigente     parada abierta en el momento consultado
 * @var string|null                $momento     momento consultado, o null para ahora
 */

/** Mensaje de rechazo del servidor, en el mismo nodo que usa validacion.js. */
$e = static function (string $campo) use ($errores): string {
    return '<span class="error-campo" data-validar-error="' . Vista::e($campo) . '"'
        . (isset($errores[$campo]) ? '' : ' hidden') . '>'
        . Vista::e($errores[$campo] ?? '')
        . '</span>';
};

/** La base devuelve TIME como 18:00:00 y <input type="time"> quiere 18:00. */
$hm = static fn (mixed $hora): string => substr((string) $hora, 0, 5);

/** Una jornada que cierra a la misma hora a la que abre, o antes, termina al
    dia siguiente. Es el caso normal de un truck nocturno y conviene decirlo. */
$cruza = static fn (array $u): bool => (string) $u['hora_fin'] <= (string) $u['hora_inicio'];

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
$trazoReloj  = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
$trazoLapiz  = '<path d="M4 20h4l10-10-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>';

/** Las paradas, repartidas por dia. El modelo ya las devuelve ordenadas por
    dia y hora, asi que aqui solo se reparten sin volver a ordenar nada. */
$porDia = [];

foreach ($ubicaciones as $u) {
    $porDia[(int) $u['dia_semana']][] = $u;
}

/** Los dias en los que el truck no para en ningun sitio. */
$sinParadas = [];

foreach ($dias as $numero => $nombreDia) {
    if (!isset($porDia[$numero])) {
        $sinParadas[] = mb_strtolower($nombreDia);
    }
}

$editando = $edita !== null && (int) ($edita['id'] ?? 0) > 0;
?>
<div class="pila pila-1">
    <h1>Paradas</h1>

    <p class="texto-apagado">
        Un food truck no tiene dirección: para en puntos distintos según el día. Cada parada es un
        punto con su día y su franja horaria, y con ellas la carta pública responde dónde está el
        truck ahora mismo.
    </p>
</div>

<?php // ------------------------------------------------------ donde estamos --
      // Lo mismo que ve el cliente en la carta, aqui arriba: es la comprobacion
      // de que la agenda dice lo que el dueno cree que dice. ?>
<section class="panel-vigente" aria-labelledby="panel-vigente-titulo">
    <h2 class="panel-vigente-titulo" id="panel-vigente-titulo">Dónde estamos</h2>

    <?php if ($vigente === null) : ?>
        <p class="panel-vigente-texto">
            No hay ninguna parada vigente<?= $momento === null ? ' ahora mismo' : ' en ese momento' ?>.
        </p>
    <?php else : ?>
        <p class="panel-vigente-punto">
            <?= $icono($trazoPunto, 'panel-vigente-icono') ?>
            <?= Vista::e($vigente['nombre']) ?>
        </p>

        <?php if (!empty($vigente['referencia'])) : ?>
            <p class="panel-vigente-texto"><?= Vista::e($vigente['referencia']) ?></p>
        <?php endif; ?>

        <p class="panel-vigente-texto numerica">
            <?= Vista::e($dias[(int) $vigente['dia_semana']] ?? '') ?>
            de <?= Vista::e($hm($vigente['hora_inicio'])) ?>
            a <?= Vista::e($hm($vigente['hora_fin'])) ?>
            <?= $cruza($vigente) ? ' · cierra al día siguiente' : '' ?>
        </p>
    <?php endif; ?>

    <?php // La agenda nocturna no se puede comprobar a las once de la manana sin
          // esperar a la noche ni tocar el reloj del servidor: se pregunta por
          // otra hora. Es de solo lectura y sigue filtrando por el food truck de
          // la sesion. ?>
    <form class="panel-momento" method="get" action="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">
        <div class="campo campo-sobre-contenedor">
            <input class="campo-control" type="datetime-local" id="momento" name="momento"
                   placeholder=" "
                   value="<?= Vista::e($momento === null ? '' : str_replace(' ', 'T', substr($momento, 0, 16))) ?>">
            <label class="campo-etiqueta" for="momento">Consultar otro momento</label>
        </div>

        <button type="submit" class="boton boton-contorno">
            <?= $icono($trazoReloj, 'boton-icono') ?>Consultar
        </button>

        <?php if ($momento !== null) : ?>
            <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">Volver a ahora</a>
        <?php endif; ?>
    </form>
</section>

<div class="panel-paradas">
    <?php // ------------------------------------------------------ formulario --
          // El mismo para crear y para editar: lo que decide es el campo oculto
          // id, y el enlace «Editar» de cada parada trae el ancla para que en el
          // telefono la pantalla caiga justo aqui. ?>
    <form class="tarjeta tarjeta-elevada pila panel-grupo panel-paradas-formulario"
          id="parada-formulario"
          method="post" action="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>"
          novalidate data-validar>
        <?= Csrf::campo() ?>
        <input type="hidden" name="id" value="<?= (int) ($edita['id'] ?? 0) ?>">

        <h2 class="tarjeta-titulo"><?= $editando ? 'Editar parada' : 'Nueva parada' ?></h2>

        <div>
            <div class="campo<?= isset($errores['nombre']) ? ' campo-error' : '' ?>">
                <input class="campo-control" type="text" id="nombre" name="nombre" maxlength="120"
                       required autocomplete="off" placeholder=" "
                       value="<?= Vista::e($edita['nombre'] ?? '') ?>"
                       aria-describedby="nombre-apoyo"
                       data-validar-requerido="El punto es obligatorio."
                       data-validar-largo="El punto no puede pasar de 120 caracteres.">
                <label class="campo-etiqueta" for="nombre">Punto</label>
            </div>
            <?= $e('nombre') ?>
            <p class="campo-apoyo" id="nombre-apoyo">Como lo conoce la gente: Parque de la 93.</p>
        </div>

        <div>
            <div class="campo<?= isset($errores['referencia']) ? ' campo-error' : '' ?>">
                <input class="campo-control" type="text" id="referencia" name="referencia" maxlength="200"
                       autocomplete="off" placeholder=" "
                       value="<?= Vista::e($edita['referencia'] ?? '') ?>"
                       aria-describedby="referencia-apoyo"
                       data-validar-largo="La referencia no puede pasar de 200 caracteres.">
                <label class="campo-etiqueta" for="referencia">Referencia</label>
            </div>
            <?= $e('referencia') ?>
            <p class="campo-apoyo" id="referencia-apoyo">Opcional: costado norte, frente al centro comercial.</p>
        </div>

        <div>
            <div class="campo campo-con-valor<?= isset($errores['dia_semana']) ? ' campo-error' : '' ?>">
                <select class="campo-control" id="dia_semana" name="dia_semana" required
                        data-validar-requerido="El dia de la semana es obligatorio.">
                    <option value="">Elija un día</option>
                    <?php foreach ($dias as $numero => $nombreDia) : ?>
                        <option value="<?= (int) $numero ?>"
                            <?= (int) ($edita['dia_semana'] ?? 0) === (int) $numero ? ' selected' : '' ?>>
                            <?= Vista::e($nombreDia) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="campo-etiqueta" for="dia_semana">Día</label>
            </div>
            <?= $e('dia_semana') ?>
        </div>

        <div class="panel-formulario-linea panel-formulario-linea-2">
            <div>
                <div class="campo<?= isset($errores['hora_inicio']) ? ' campo-error' : '' ?>">
                    <input class="campo-control" type="time" id="hora_inicio" name="hora_inicio" required
                           placeholder=" " value="<?= Vista::e($hm($edita['hora_inicio'] ?? '')) ?>"
                           data-validar-requerido="La hora de inicio es obligatoria."
                           data-validar-patron="^([01][0-9]|2[0-3]):[0-5][0-9]$"
                           data-validar-formato="La hora de inicio debe tener el formato HH:MM, de 00:00 a 23:59.">
                    <label class="campo-etiqueta" for="hora_inicio">Abre</label>
                </div>
                <?= $e('hora_inicio') ?>
            </div>

            <div>
                <div class="campo<?= isset($errores['hora_fin']) ? ' campo-error' : '' ?>">
                    <input class="campo-control" type="time" id="hora_fin" name="hora_fin" required
                           placeholder=" " value="<?= Vista::e($hm($edita['hora_fin'] ?? '')) ?>"
                           aria-describedby="hora-apoyo"
                           data-validar-requerido="La hora de fin es obligatoria."
                           data-validar-patron="^([01][0-9]|2[0-3]):[0-5][0-9]$"
                           data-validar-formato="La hora de fin debe tener el formato HH:MM, de 00:00 a 23:59.">
                    <label class="campo-etiqueta" for="hora_fin">Cierra</label>
                </div>
                <?= $e('hora_fin') ?>
            </div>
        </div>

        <p class="campo-apoyo" id="hora-apoyo">
            Una hora de cierre igual o anterior a la de apertura significa que la jornada cierra al
            día siguiente: de 18:00 a 01:00 es correcto.
        </p>

        <div class="panel-formulario-linea panel-formulario-linea-2">
            <div>
                <div class="campo<?= isset($errores['latitud']) ? ' campo-error' : '' ?>">
                    <input class="campo-control" type="text" id="latitud" name="latitud"
                           inputmode="decimal" maxlength="12" autocomplete="off" placeholder=" "
                           value="<?= Vista::e($edita['latitud'] ?? '') ?>"
                           data-validar-patron="^-?\d{1,3}([.,]\d{1,7})?$"
                           data-validar-formato="La latitud debe ser un numero con hasta 7 decimales.">
                    <label class="campo-etiqueta" for="latitud">Latitud</label>
                </div>
                <?= $e('latitud') ?>
            </div>

            <div>
                <div class="campo<?= isset($errores['longitud']) ? ' campo-error' : '' ?>">
                    <input class="campo-control" type="text" id="longitud" name="longitud"
                           inputmode="decimal" maxlength="12" autocomplete="off" placeholder=" "
                           value="<?= Vista::e($edita['longitud'] ?? '') ?>"
                           aria-describedby="coordenadas-apoyo"
                           data-validar-patron="^-?\d{1,3}([.,]\d{1,7})?$"
                           data-validar-formato="La longitud debe ser un numero con hasta 7 decimales.">
                    <label class="campo-etiqueta" for="longitud">Longitud</label>
                </div>
                <?= $e('longitud') ?>
            </div>
        </div>

        <p class="campo-apoyo" id="coordenadas-apoyo">
            Opcionales. Las rellena sola la aplicación móvil cuando el truck reporta su punto.
        </p>

        <div class="tarjeta-pie">
            <button type="submit" class="boton boton-relleno"><?= $editando ? 'Guardar' : 'Crear parada' ?></button>

            <?php if ($editando) : ?>
                <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php // --------------------------------------------------------- la agenda -- ?>
    <section class="pila pila-5 panel-paradas-agenda" aria-labelledby="panel-agenda-titulo">
        <h2 id="panel-agenda-titulo">La semana</h2>

        <?php if ($ubicaciones === []) : ?>
            <div class="tarjeta tarjeta-contorno pila pila-2">
                <p class="tarjeta-titulo">Todavía no hay paradas</p>
                <p class="tarjeta-texto">
                    Sin ninguna parada, la carta pública no puede decir dónde está el truck. Cree la
                    primera con el formulario.
                </p>
            </div>
        <?php else : ?>
            <?php foreach ($dias as $numero => $nombreDia) : ?>
                <?php if (!isset($porDia[$numero])) : ?>
                    <?php continue; ?>
                <?php endif; ?>

                <div class="pila pila-2">
                    <h3 class="panel-dia"><?= Vista::e($nombreDia) ?></h3>

                    <ul class="panel-paradas-lista">
                        <?php foreach ($porDia[$numero] as $u) : ?>
                            <?php $activa = (int) $u['activa'] === 1; ?>
                            <li class="panel-parada<?= $activa ? '' : ' panel-parada-inactiva' ?>">
                                <div class="panel-parada-texto">
                                    <p class="panel-parada-punto"><?= Vista::e($u['nombre']) ?></p>

                                    <?php if (!empty($u['referencia'])) : ?>
                                        <p class="panel-parada-referencia"><?= Vista::e($u['referencia']) ?></p>
                                    <?php endif; ?>

                                    <p class="panel-parada-horario numerica">
                                        <?= Vista::e($hm($u['hora_inicio'])) ?> a <?= Vista::e($hm($u['hora_fin'])) ?>
                                        <?= $cruza($u) ? ' · cierra al día siguiente' : '' ?>
                                        <?php if ($u['latitud'] !== null && $u['longitud'] !== null) : ?>
                                            · <?= Vista::e($u['latitud']) ?>, <?= Vista::e($u['longitud']) ?>
                                        <?php endif; ?>
                                    </p>

                                    <?php if (!$activa) : ?>
                                        <span class="etiqueta etiqueta-entregada">No aparece en la carta</span>
                                    <?php endif; ?>
                                </div>

                                <div class="panel-parada-acciones">
                                    <a class="boton boton-texto"
                                       href="<?= Vista::e(Vista::url('/panel/ubicaciones/' . $u['id'])) ?>#parada-formulario">
                                        <?= $icono($trazoLapiz, 'boton-icono') ?>Editar
                                    </a>

                                    <?php // Desactivar saca la parada de la carta publica, asi que
                                          // pregunta antes. Activar no: no esconde nada. ?>
                                    <form method="post"
                                          action="<?= Vista::e(Vista::url('/panel/ubicaciones/estado')) ?>"
                                          <?= $activa ? 'data-confirmar="¿Desactivar «' . Vista::e($u['nombre'])
                                              . '»? Dejará de aparecer en la carta pública."'
                                              . ' data-confirmar-titulo="Desactivar parada"'
                                              . ' data-confirmar-aceptar="Desactivar" data-confirmar-peligro' : '' ?>>
                                        <?= Csrf::campo() ?>
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">

                                        <button type="submit" class="boton boton-contorno">
                                            <?= $activa ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <?php if ($sinParadas !== []) : ?>
                <p class="texto-apagado texto-m">
                    Sin paradas: <?= Vista::e(implode(', ', $sinParadas)) ?>.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
