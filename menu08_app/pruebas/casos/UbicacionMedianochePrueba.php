<?php

declare(strict_types=1);

/**
 * Ubicacion::cruzaMedianoche() - la regla de la jornada nocturna.
 *
 * Un food truck que abre a las 18:00 y cierra a la 01:00 trabaja una sola
 * jornada que cae en dos dias. La regla que lo resuelve es que `hora_fin` sea
 * menor o igual que `hora_inicio`, y esa misma comparacion la aplican en SQL la
 * segunda y la tercera rama de Ubicacion::vigente(), y la copia bloqueante que
 * usa Ubicacion::asentarPunto() para el reporte del movil.
 *
 * El metodo es estatico y puro: recibe dos cadenas y devuelve un booleano. No
 * consulta nada, asi que se prueba en frio. Autocargar el modelo tampoco abre
 * nada: los alias de la cabecera son solo alias, y lo que consulta vive dentro
 * de los metodos que no se llaman aqui.
 *
 * Hoy este metodo NO tiene ningun llamador en el codigo —las vistas que lo
 * usaran son del issue de Frontend, todavia abierto—, asi que esta prueba es lo
 * unico que sostiene su regla: si alguien invierte la comparacion, nada mas se
 * quejaria.
 */

use Menu08\Modelos\Ubicacion;
use Menu08\Nucleo\Validador;

// --- Los tres casos ---------------------------------------------------------
afirmarIgual(
    true,
    Ubicacion::cruzaMedianoche('18:00:00', '01:00:00'),
    'de 18:00 a 01:00 cruza la medianoche'
);

afirmarIgual(
    false,
    Ubicacion::cruzaMedianoche('11:00:00', '15:00:00'),
    'de 11:00 a 15:00 es jornada normal'
);

// Horas iguales: la jornada dura 24 horas. Es lo que hace que la parada que
// registra el reporte del movil quede vigente desde el mismo segundo, y por
// tanto que el reporte siguiente la actualice en vez de crear otra.
afirmarIgual(
    true,
    Ubicacion::cruzaMedianoche('12:00:00', '12:00:00'),
    'con hora_fin igual a hora_inicio la jornada dura 24 horas'
);

// --- Encadenado con hora(), que es como llega de verdad ---------------------
//
// El formulario manda HH:MM y la columna guarda HH:MM:SS. Que las dos formas
// respondan lo mismo fija que la comparacion es TEXTUAL: sirve porque el
// formato HH:MM:SS ordena igual como cadena que como hora.
$v      = new Validador();
$inicio = $v->hora('hora_inicio', '18:00');
$fin    = $v->hora('hora_fin', '01:00');

afirmarIgual('18:00:00', $inicio, 'la hora de inicio se normaliza antes de comparar');
afirmarIgual('01:00:00', $fin, 'la hora de fin se normaliza antes de comparar');
afirmarIgual(
    true,
    Ubicacion::cruzaMedianoche((string) $inicio, (string) $fin),
    'el HH:MM del formulario da el mismo resultado que el HH:MM:SS de la columna'
);

// --- El limite justo --------------------------------------------------------
//
// Un minuto mas y ya no cruza: es la frontera de la regla.
afirmarIgual(
    false,
    Ubicacion::cruzaMedianoche('18:00:00', '18:00:01'),
    'un segundo despues de abrir todavia es jornada normal'
);

afirmarIgual(
    true,
    Ubicacion::cruzaMedianoche('18:00:00', '17:59:59'),
    'un segundo antes de abrir ya es jornada que cierra al dia siguiente'
);
