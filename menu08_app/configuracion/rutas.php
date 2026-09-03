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
use Menu08\Controladores\CartaControlador;
use Menu08\Controladores\CategoriaControlador;
use Menu08\Controladores\InicioControlador;
use Menu08\Controladores\PanelControlador;
use Menu08\Controladores\ProductoControlador;
use Menu08\Controladores\QrControlador;
use Menu08\Controladores\SvpControlador;

// --- Publicas --------------------------------------------------------------
$enrutador->get('/', [InicioControlador::class, 'comprobacion']);
$enrutador->get('/comprobacion/{slug}', [InicioControlador::class, 'porSlug']);

// La carta que abre el cliente al leer el QR de la ventanilla. Sin sesion.
$enrutador->get('/carta/{slug}', [CartaControlador::class, 'publica']);

// --- Autenticacion ---------------------------------------------------------
$enrutador->get('/ingresar',  [AutenticacionControlador::class, 'formulario']);
$enrutador->post('/ingresar', [AutenticacionControlador::class, 'ingresar']);
$enrutador->get('/salir',     [AutenticacionControlador::class, 'salir']);

// --- Zonas privadas --------------------------------------------------------
$enrutador->get('/panel', [PanelControlador::class, 'inicio']);   // plataforma, food_truck

// --- Panel de CARTA: solo el rol food_truck, y todo POST con token ---------
$enrutador->get('/panel/food-truck',  [PanelControlador::class, 'formulario']);
$enrutador->post('/panel/food-truck', [PanelControlador::class, 'guardar']);

$enrutador->get('/panel/categorias',            [CategoriaControlador::class, 'listado']);
$enrutador->get('/panel/categorias/{id:\\d+}',   [CategoriaControlador::class, 'editar']);
$enrutador->post('/panel/categorias',           [CategoriaControlador::class, 'guardar']);
$enrutador->post('/panel/categorias/estado',    [CategoriaControlador::class, 'cambiarEstado']);

$enrutador->get('/panel/productos',                 [ProductoControlador::class, 'listado']);
$enrutador->get('/panel/productos/nuevo',           [ProductoControlador::class, 'nuevo']);
$enrutador->get('/panel/productos/{id:\\d+}',        [ProductoControlador::class, 'editar']);
$enrutador->post('/panel/productos',                [ProductoControlador::class, 'guardar']);
$enrutador->post('/panel/productos/disponibilidad', [ProductoControlador::class, 'cambiarDisponibilidad']);

$enrutador->get('/panel/qr',           [QrControlador::class, 'mostrar']);
$enrutador->get('/panel/qr/descargar', [QrControlador::class, 'descargar']);
$enrutador->get('/caja',              [CajaControlador::class, 'inicio']);      // food_truck, cajero
$enrutador->get('/caja/turno',        [CajaControlador::class, 'turno']);
$enrutador->post('/caja/turno/abrir', [CajaControlador::class, 'abrir']);
$enrutador->post('/caja/turno/cerrar',[CajaControlador::class, 'cerrar']);
$enrutador->post('/caja/vender',      [CajaControlador::class, 'vender']);
$enrutador->get('/caja/comprobante/{id:\\d+}', [CajaControlador::class, 'comprobante']);
$enrutador->get('/caja/turnos',       [CajaControlador::class, 'historial']);
$enrutador->get('/caja/turnos/{id:\\d+}', [CajaControlador::class, 'detalle']);
$enrutador->get('/svp',         [SvpControlador::class, 'inicio']);    // food_truck, produccion
$enrutador->get('/svp/ordenes', [SvpControlador::class, 'ordenes']);   // servicio JSON del tablero
