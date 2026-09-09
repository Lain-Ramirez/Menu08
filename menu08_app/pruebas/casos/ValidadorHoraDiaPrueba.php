<?php

declare(strict_types=1);

/**
 * Validador::hora() y Validador::diaSemana() - las dos reglas que comparten el
 * panel de paradas y el servicio movil.
 *
 * Se llaman con la etiqueta por omision, que es como las usa el codigo cuando
 * no pasa una propia: de ahi "La hora" y "El dia" en los mensajes.
 */

use Menu08\Nucleo\Validador;

// --- hora(): normaliza a HH:MM:SS -------------------------------------------
//
// El control <input type="time"> manda HH:MM y la columna es TIME. Los segundos
// los pone el validador, no la base.
$v = new Validador();
afirmarIgual('18:00:00', $v->hora('hora_inicio', '18:00'), 'HH:MM se completa con los segundos en 00');

$v = new Validador();
afirmarIgual('20:30:45', $v->hora('hora_fin', '20:30:45'), 'HH:MM:SS se conserva entero');

$v = new Validador();
afirmarIgual('00:00:00', $v->hora('hora_inicio', '00:00'), 'la medianoche es una hora valida');

$v = new Validador();
afirmarIgual('23:59:00', $v->hora('hora_fin', '23:59'), 'el ultimo minuto del dia es valido');

// --- hora(): el vacio tiene su propio mensaje -------------------------------
//
// Las dos horas de una parada son NOT NULL, asi que aqui vacio SI es error, al
// contrario que en coordenada().
$v = new Validador();
afirmarIgual(null, $v->hora('hora_inicio', ''), 'la hora vacia se rechaza');
afirmarIgual('La hora es obligatoria.', $v->errores()['hora_inicio'] ?? null, 'mensaje de hora obligatoria');

// --- hora(): formatos que no valen ------------------------------------------
//
// El tipo TIME de MySQL admite hasta 838 horas, asi que no protege de un 25:99:
// la acotacion es de esta funcion.
foreach (['9:00' => 'sin el cero delante', '24:00' => 'la hora 24 no existe', '23:60' => 'el minuto 60 no existe'] as $malo => $porque) {
    $v = new Validador();
    afirmarIgual(null, $v->hora('hora_inicio', $malo), sprintf('hora "%s" se rechaza: %s', $malo, $porque));
    afirmarIgual(
        'La hora debe tener el formato HH:MM, de 00:00 a 23:59.',
        $v->errores()['hora_inicio'] ?? null,
        sprintf('mensaje de formato para "%s"', $malo)
    );
}

// --- diaSemana(): devuelve ENTERO -------------------------------------------
//
// El dia viaja como cadena desde el formulario y como entero a la columna
// TINYINT. Que la conversion ocurra aqui es lo que fija esta comprobacion.
$v = new Validador();
$dia = $v->diaSemana('dia_semana', '7');
afirmarIgual(7, $dia, 'el domingo es el 7');
afirmar($dia !== '7', 'diaSemana devuelve un entero, no la cadena que llego');
afirmar(is_int($dia), 'el tipo devuelto es int');

$v = new Validador();
afirmarIgual(1, $v->diaSemana('dia_semana', '1'), 'el lunes es el 1');

// --- diaSemana(): fuera del rango 1..7 --------------------------------------
foreach (['0', '8'] as $malo) {
    $v = new Validador();
    afirmarIgual(null, $v->diaSemana('dia_semana', $malo), sprintf('el dia %s se rechaza', $malo));
    afirmarIgual(
        'El dia debe ir de 1 (lunes) a 7 (domingo).',
        $v->errores()['dia_semana'] ?? null,
        sprintf('mensaje de rango para el dia %s', $malo)
    );
}

// --- diaSemana(): lo que no es un numero ------------------------------------
//
// Cae en la primera rama, la del campo obligatorio, no en la del rango. El
// mensaje es distinto y conviene dejarlo fijado: es la conducta real.
$v = new Validador();
afirmarIgual(null, $v->diaSemana('dia_semana', 'lunes'), 'un dia escrito con letras se rechaza');
afirmarIgual(
    'El dia de la semana es obligatorio.',
    $v->errores()['dia_semana'] ?? null,
    'un valor no numerico da el mensaje de obligatorio, no el de rango'
);
