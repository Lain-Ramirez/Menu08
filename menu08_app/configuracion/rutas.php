<?php

declare(strict_types=1);

/**
 * Tabla de rutas del prototipo.
 *
 * Este archivo se incluye desde publico/index.php, que ya creo $enrutador.
 *
 * @var \Menu08\Nucleo\Enrutador $enrutador
 */

use Menu08\Controladores\InicioControlador;

// Comprobacion del nucleo: renderiza una vista y muestra datos leidos de la base.
$enrutador->get('/', [InicioControlador::class, 'comprobacion']);

// Ruta con parametro, para demostrar la extraccion de la direccion.
$enrutador->get('/comprobacion/{slug}', [InicioControlador::class, 'porSlug']);
