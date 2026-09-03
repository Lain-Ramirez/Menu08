<?php

declare(strict_types=1);

/**
 * Tabla de rutas del prototipo.
 *
 * Este archivo se incluye desde publico/index.php, que ya creo $enrutador.
 * El control de acceso NO vive aqui: cada controlador declara con exigirRol()
 * quien puede entrar, para que la regla viaje junto a la accion que protege.
 *
 * @var \Menu08\Nucleo\Enrutador $enrutador
 */

use Menu08\Controladores\AutenticacionControlador;
use Menu08\Controladores\CajaControlador;
use Menu08\Controladores\InicioControlador;
use Menu08\Controladores\PanelControlador;
use Menu08\Controladores\SvpControlador;

// --- Publicas --------------------------------------------------------------
$enrutador->get('/', [InicioControlador::class, 'comprobacion']);
$enrutador->get('/comprobacion/{slug}', [InicioControlador::class, 'porSlug']);

// --- Autenticacion ---------------------------------------------------------
$enrutador->get('/ingresar',  [AutenticacionControlador::class, 'formulario']);
$enrutador->post('/ingresar', [AutenticacionControlador::class, 'ingresar']);
$enrutador->get('/salir',     [AutenticacionControlador::class, 'salir']);

// --- Zonas privadas --------------------------------------------------------
$enrutador->get('/panel', [PanelControlador::class, 'inicio']);   // plataforma, food_truck
$enrutador->get('/caja',  [CajaControlador::class,  'inicio']);   // food_truck, cajero
$enrutador->get('/svp',   [SvpControlador::class,   'inicio']);   // food_truck, produccion
