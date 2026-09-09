<?php

declare(strict_types=1);

/**
 * Validador::coordenada() - la puerta por la que entra el punto del GPS.
 *
 * Sin espacio de nombres, como las vistas: este archivo lo incluye ejecutar.php.
 *
 * Cada caso estrena su propio Validador porque los errores se acumulan en la
 * instancia: reutilizarlo haria que un caso arrastrase el fallo del anterior.
 */

use Menu08\Nucleo\Validador;

// --- El campo vacio: null, y SIN mensaje ------------------------------------
//
// Es el detalle del que depende el servicio movil. La coordenada es opcional en
// el formulario del panel, asi que el vacio se guarda como nulo y no deja error.
// Por eso POST /movil/ubicacion, donde las dos coordenadas SI son obligatorias,
// tiene que marcar ese caso a mano con error(). Si alguien "arreglara" esto
// haciendo que el vacio deje mensaje, el servicio movil seguiria funcionando y
// el panel empezaria a rechazar paradas sin coordenadas.
$v = new Validador();
afirmarIgual(null, $v->coordenada('latitud', '', -90.0, 90.0, 'La latitud'), 'coordenada vacia devuelve null');
afirmar($v->correcto(), 'coordenada vacia deja el validador correcto');
afirmarIgual([], $v->errores(), 'coordenada vacia no deja ningun mensaje');
afirmarIgual(null, $v->valor('latitud'), 'coordenada vacia se guarda como null, no como cadena vacia');

// --- La coma decimal del teclado en espanol ---------------------------------
$v = new Validador();
afirmarIgual(
    '4.7110000',
    $v->coordenada('latitud', '4,7110000', -90.0, 90.0, 'La latitud'),
    'la coma decimal se normaliza a punto'
);
afirmar($v->correcto(), 'la coma decimal no deja error');

// --- Devuelve CADENA, no float ----------------------------------------------
//
// Pasar por float perderia precision justo en los decimales que dan el metro de
// exactitud, y la columna es DECIMAL(10,7).
$v = new Validador();
$lat = $v->coordenada('latitud', '4.7110000', -90.0, 90.0, 'La latitud');
afirmarIgual('4.7110000', $lat, 'siete decimales se aceptan tal cual');
afirmar(is_string($lat), 'la coordenada vuelve como cadena, no como float');

// --- Ocho decimales se rechazan ---------------------------------------------
$v = new Validador();
afirmarIgual(null, $v->coordenada('latitud', '4.71100005', -90.0, 90.0, 'La latitud'), 'ocho decimales se rechazan');
afirmar(!$v->correcto(), 'ocho decimales dejan el validador incorrecto');
afirmarIgual(
    'La latitud debe ser un numero con hasta 7 decimales.',
    $v->errores()['latitud'] ?? null,
    'mensaje del limite de decimales'
);

// --- Los extremos del rango ENTRAN ------------------------------------------
//
// La comprobacion es < minimo o > maximo, asi que -90 y 90 son validos.
$v = new Validador();
afirmarIgual('-90', $v->coordenada('latitud', '-90', -90.0, 90.0, 'La latitud'), 'latitud -90 se acepta');
$v = new Validador();
afirmarIgual('90', $v->coordenada('latitud', '90', -90.0, 90.0, 'La latitud'), 'latitud 90 se acepta');
$v = new Validador();
afirmarIgual('-180', $v->coordenada('longitud', '-180', -180.0, 180.0, 'La longitud'), 'longitud -180 se acepta');
$v = new Validador();
afirmarIgual('180', $v->coordenada('longitud', '180', -180.0, 180.0, 'La longitud'), 'longitud 180 se acepta');

// --- Un pelo por fuera se rechaza -------------------------------------------
//
// Estos dos valores PASAN el formato —tienen siete decimales justos— y fallan
// la comprobacion siguiente, que es justo la que se quiere ejercitar.
$v = new Validador();
afirmarIgual(null, $v->coordenada('latitud', '90.0000001', -90.0, 90.0, 'La latitud'), 'latitud 90.0000001 se rechaza');
afirmarIgual(
    'La latitud debe estar entre -90 y 90.',
    $v->errores()['latitud'] ?? null,
    'mensaje del rango de latitud, con los limites sin decimales'
);

$v = new Validador();
afirmarIgual(
    null,
    $v->coordenada('longitud', '180.0000001', -180.0, 180.0, 'La longitud'),
    'longitud 180.0000001 se rechaza'
);
afirmarIgual(
    'La longitud debe estar entre -180 y 180.',
    $v->errores()['longitud'] ?? null,
    'mensaje del rango de longitud, bajo la clave del campo'
);
