-- Menu08 - Operacion completa de Festin Rodante
--
-- Parrilla colombiana sobre ruedas. El registro del texto es el de una carta de
-- verdad: coloquial, con picardia y sin adornos de laboratorio. Precios en pesos.
--
-- La carta es de comida callejera colombiana —empanadas, arepa de huevo, bandeja
-- paisa, lechona, perro caliente, salchipapa, lulo, salpicon— y, a diferencia
-- del resto del sembrado, los nombres y descripciones de los productos van con
-- tildes y enes: son lo que lee el cliente en la carta publica, y «Bunuelos» o
-- «Patacon» se leen mal. El archivo esta en UTF-8 y la base en utf8mb4, asi que
-- phpMyAdmin los importa tal cual.
--
-- BORRA Y REEMPLAZA todos los datos de operacion del food truck 1. No toca la
-- fila de food_trucks ni sus usuarios: sin ellos no se podria ni entrar.
--
-- Las horas son relativas a NOW(), asi que la jornada se ve viva a cualquier
-- hora en que se importe: un turno cerrado ayer y otro abierto hace un rato.
--
-- Se importa desde phpMyAdmin con la base ya seleccionada.

-- ---------------------------------------------------------------------------
-- 0. Limpieza. El orden lo manda la clave foranea: los items cuelgan de las
--    ordenes, las ordenes del turno, y los productos de las categorias.
-- ---------------------------------------------------------------------------
DELETE FROM orden_items WHERE orden_id IN (SELECT id FROM ordenes WHERE food_truck_id = 1);
DELETE FROM ordenes     WHERE food_truck_id = 1;
DELETE FROM turnos_caja WHERE food_truck_id = 1;
DELETE FROM productos   WHERE food_truck_id = 1;
DELETE FROM categorias  WHERE food_truck_id = 1;
DELETE FROM ubicaciones WHERE food_truck_id = 1;

-- ---------------------------------------------------------------------------
-- 1. Categorias
-- ---------------------------------------------------------------------------
INSERT INTO categorias (food_truck_id, nombre, orden, activo) VALUES
  (1, 'Para picar',     1, 1),
  (1, 'De la parrilla', 2, 1),
  (1, 'Con la mano',    3, 1),
  (1, 'Para bajarlo',   4, 1),
  (1, 'El dulce',       5, 1);

-- ---------------------------------------------------------------------------
-- 2. Productos
--    La lechona queda no disponible: se acabo a media jornada, que es lo que
--    pasa en un truck y lo que la carta publica tiene que saber decir.
-- ---------------------------------------------------------------------------
INSERT INTO productos (food_truck_id, categoria_id, nombre, descripcion, precio, disponible, orden) VALUES
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Para picar'),
      'Empanadas con ají', 'Tres empanadas de papa y carne, crocanticas, con ají de la casa.', 9900, 1, 1),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Para picar'),
      'Arepa de huevo', 'Frita en la ventanilla, con el huevo entero adentro, como en la Costa.', 8900, 1, 2),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Para picar'),
      'Patacón con hogao', 'Aplastado dos veces, como debe ser, y con hogao de tomate y cebolla larga.', 12900, 1, 3),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Para picar'),
      'Chorizo santarrosano con arepa', 'El de Santa Rosa de Cabal, a la brasa, con arepa y tajada de queso.', 15900, 1, 4),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Para picar'),
      'Buñuelos', 'Cuatro, de queso costeño y recién salidos del aceite.', 8900, 1, 5),

  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='De la parrilla'),
      'Bandeja paisa', 'Frijoles, arroz, chicharrón, carne molida, chorizo, huevo, maduro, aguacate y arepa.', 38900, 1, 1),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='De la parrilla'),
      'Picada parrillera', 'Carne asada, chorizo, morcilla, papa salada y yuca. Alcanza para dos.', 46900, 1, 2),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='De la parrilla'),
      'Lechona tolimense', 'Con arroz, arveja y cuero tostado. Se acaba temprano.', 24900, 0, 3),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='De la parrilla'),
      'Punta de anca 250 g', 'El término lo elige usted, pero el parrillero recomienda tres cuartos.', 42900, 1, 4),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='De la parrilla'),
      'Costilla de cerdo BBQ', 'Se suelta del hueso sola: el cuchillo sobra.', 34900, 1, 5),

  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Con la mano'),
      'Perro caliente colombiano', 'Salchicha, papita triturada, queso, salsa de piña y todas las salsas.', 15900, 1, 1),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Con la mano'),
      'Hamburguesa del truck', 'Carne de res a la parrilla, queso, tocineta, lechuga y tomate.', 26900, 1, 2),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Con la mano'),
      'Salchipapa', 'Papa a la francesa y salchicha ranchera, con salsa rosada.', 17900, 1, 3),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Con la mano'),
      'Arepa de chócolo con queso', 'Dulcecita, como en la carretera a Villeta.', 15900, 1, 4),

  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Para bajarlo'),
      'Limonada de coco', 'La que apaga el ají y la sed.', 12900, 1, 1),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Para bajarlo'),
      'Jugo de lulo', 'En agua o en leche, bien frío.', 8900, 1, 2),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Para bajarlo'),
      'Aguapanela con queso', 'Calientica, con queso campesino para echarle adentro y almojábana.', 7900, 1, 3),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='Para bajarlo'),
      'Cerveza nacional', 'Bien helada, no hay de otra.', 9900, 1, 4),

  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='El dulce'),
      'Salpicón de frutas', 'Fruta picada en jugo de sandía, y con helado si quiere.', 10900, 1, 1),
  (1, (SELECT id FROM categorias WHERE food_truck_id=1 AND nombre='El dulce'),
      'Arroz con leche', 'Cremosito y con canela, como lo hace la abuela.', 8900, 1, 2);

-- ---------------------------------------------------------------------------
-- 3. Agenda semanal
--    El lunes no aparece: el truck descansa y el equipo hace mercado. Las dos
--    paradas nocturnas cierran pasada la medianoche, el caso que el esquema
--    contempla con hora_fin <= hora_inicio.
-- ---------------------------------------------------------------------------
INSERT INTO ubicaciones (food_truck_id, nombre, referencia, latitud, longitud, dia_semana, hora_inicio, hora_fin, activa) VALUES
  (1, 'Parque de la 93',       'Costado occidental, frente a las oficinas',      4.6767000, -74.0483000, 2, '12:00:00', '16:00:00', 1),
  (1, 'Zona G',                'Calle 69 con carrera 5, junto al parque',        4.6533000, -74.0621000, 3, '12:00:00', '16:00:00', 1),
  (1, 'Usaquen',               'Carrera 6 con calle 119, al lado de la plaza',   4.6950000, -74.0308000, 4, '17:00:00', '23:00:00', 1),
  (1, 'Zona T',                'Peatonal de la 82, frente al centro comercial',  4.6667000, -74.0530000, 5, '18:00:00', '02:00:00', 1),
  (1, 'Parque El Virrey',      'Costado sur, sobre la carrera 15',               4.6742000, -74.0546000, 6, '12:00:00', '17:00:00', 1),
  (1, 'Zona T',                'Peatonal de la 82, frente al centro comercial',  4.6667000, -74.0530000, 6, '18:00:00', '02:00:00', 1),
  (1, 'Ciclovia de la Septima','A la altura de la calle 60, costado oriental',   4.6480000, -74.0620000, 7, '08:00:00', '13:00:00', 1),
  (1, 'Parque de la 93',       'Costado norte, junto a los restaurantes',        4.6767000, -74.0483000, 7, '17:00:00', '22:00:00', 1);

-- ---------------------------------------------------------------------------
-- 4. Turno de ayer, ya cerrado
--    Jornada de la Zona T: abre a las seis de la tarde y cierra pasada la
--    medianoche, con la ultima orden a las 00:40. Al cuadrar la caja faltaron
--    400 pesos de cambio, que es lo que pasa de verdad y lo que el cierre debe
--    saber mostrar.
-- ---------------------------------------------------------------------------
SET @cajero := (SELECT id FROM usuarios WHERE food_truck_id = 1 AND rol = 'cajero' LIMIT 1);

INSERT INTO turnos_caja (food_truck_id, usuario_id, base_inicial, total_ventas, total_declarado, diferencia, estado, abierto_en, cerrado_en)
VALUES (1, @cajero, 200000, 280300, 479900, -400, 'cerrado',
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '18:00:00'),
        TIMESTAMP(CURDATE(), '02:30:00'));
SET @t1 := LAST_INSERT_ID();

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, lista_en, entregada_en, estado_actualizado_en)
VALUES (1, @t1, 4, CONCAT('T', @t1, '-001'), 58700, 'tarjeta', NULL,
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '19:12:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '19:15:00'),
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '19:26:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '19:29:00'),
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '19:29:00'));
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Bandeja paisa'), 'Bandeja paisa', 38900, 1, 38900),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Cerveza nacional'),       'Cerveza nacional',       9900, 2, 19800);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, lista_en, entregada_en, estado_actualizado_en)
VALUES (1, @t1, 4, CONCAT('T', @t1, '-002'), 49600, 'efectivo', NULL,
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '20:35:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '20:38:00'),
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '20:49:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '20:52:00'),
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '20:52:00'));
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Perro caliente colombiano'), 'Perro caliente colombiano', 15900, 2, 31800),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Jugo de lulo'), 'Jugo de lulo', 8900, 2, 17800);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, lista_en, entregada_en, estado_actualizado_en)
VALUES (1, @t1, 4, CONCAT('T', @t1, '-003'), 55800, 'transferencia', NULL,
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '21:48:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '21:51:00'),
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '22:05:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '22:07:00'),
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '22:07:00'));
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Punta de anca 250 g'), 'Punta de anca 250 g', 42900, 1, 42900),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Limonada de coco'), 'Limonada de coco', 12900, 1, 12900);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, lista_en, entregada_en, estado_actualizado_en)
VALUES (1, @t1, 4, CONCAT('T', @t1, '-004'), 55600, 'efectivo', NULL,
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '23:10:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '23:13:00'),
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '23:24:00'), TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '23:26:00'),
        TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '23:26:00'));
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Salchipapa'), 'Salchipapa', 17900, 2, 35800),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Cerveza nacional'), 'Cerveza nacional', 9900, 2, 19800);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, lista_en, entregada_en, estado_actualizado_en)
VALUES (1, @t1, 4, CONCAT('T', @t1, '-005'), 60600, 'efectivo', 'La ultima de la noche',
        TIMESTAMP(CURDATE(), '00:40:00'), TIMESTAMP(CURDATE(), '00:43:00'),
        TIMESTAMP(CURDATE(), '00:58:00'), TIMESTAMP(CURDATE(), '01:01:00'),
        TIMESTAMP(CURDATE(), '01:01:00'));
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Costilla de cerdo BBQ'), 'Costilla de cerdo BBQ', 34900, 1, 34900),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Arroz con leche'), 'Arroz con leche', 8900, 2, 17800),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Aguapanela con queso'), 'Aguapanela con queso', 7900, 1, 7900);

-- ---------------------------------------------------------------------------
-- 5. Turno de hoy, abierto
--    Abrio hace hora y tres cuartos. total_ventas queda en cero porque la
--    aplicacion lo calcula al cerrar, igual que en los turnos ya existentes.
--
--    Las ocho ordenes reparten los cuatro estados para que el tablero del SVP
--    tenga algo real que mostrar: tres entregadas, una lista en la ventanilla,
--    dos en preparacion --una de ellas pasada del umbral de diez minutos, que
--    es la que sale realzada-- y dos recien tomadas.
-- ---------------------------------------------------------------------------
INSERT INTO turnos_caja (food_truck_id, usuario_id, base_inicial, total_ventas, estado, abierto_en)
VALUES (1, @cajero, 200000, 0, 'abierto', NOW() - INTERVAL 105 MINUTE);
SET @t2 := LAST_INSERT_ID();

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, lista_en, entregada_en, estado_actualizado_en)
VALUES (1, @t2, 4, CONCAT('T', @t2, '-001'), 79600, 'efectivo', NULL,
        NOW() - INTERVAL 95 MINUTE, NOW() - INTERVAL 92 MINUTE,
        NOW() - INTERVAL 85 MINUTE, NOW() - INTERVAL 83 MINUTE, NOW() - INTERVAL 83 MINUTE);
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Hamburguesa del truck'), 'Hamburguesa del truck', 26900, 2, 53800),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Limonada de coco'), 'Limonada de coco', 12900, 2, 25800);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, lista_en, entregada_en, estado_actualizado_en)
VALUES (1, @t2, 4, CONCAT('T', @t2, '-002'), 17800, 'efectivo', NULL,
        NOW() - INTERVAL 78 MINUTE, NOW() - INTERVAL 76 MINUTE,
        NOW() - INTERVAL 70 MINUTE, NOW() - INTERVAL 68 MINUTE, NOW() - INTERVAL 68 MINUTE);
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Empanadas con ají'), 'Empanadas con ají', 9900, 1, 9900),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Aguapanela con queso'), 'Aguapanela con queso', 7900, 1, 7900);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, lista_en, entregada_en, estado_actualizado_en)
VALUES (1, @t2, 4, CONCAT('T', @t2, '-003'), 62700, 'tarjeta', NULL,
        NOW() - INTERVAL 52 MINUTE, NOW() - INTERVAL 49 MINUTE,
        NOW() - INTERVAL 41 MINUTE, NOW() - INTERVAL 38 MINUTE, NOW() - INTERVAL 38 MINUTE);
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Punta de anca 250 g'), 'Punta de anca 250 g', 42900, 1, 42900),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Cerveza nacional'), 'Cerveza nacional', 9900, 2, 19800);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, lista_en, estado_actualizado_en)
VALUES (1, @t2, 3, CONCAT('T', @t2, '-004'), 59800, 'efectivo', 'Sin cebolla, por favor',
        NOW() - INTERVAL 22 MINUTE, NOW() - INTERVAL 19 MINUTE,
        NOW() - INTERVAL 11 MINUTE, NOW() - INTERVAL 11 MINUTE);
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Picada parrillera'), 'Picada parrillera', 46900, 1, 46900),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Patacón con hogao'), 'Patacón con hogao', 12900, 1, 12900);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, estado_actualizado_en)
VALUES (1, @t2, 2, CONCAT('T', @t2, '-005'), 99500, 'transferencia', 'Mesa larga, van seis',
        NOW() - INTERVAL 16 MINUTE, NOW() - INTERVAL 13 MINUTE, NOW() - INTERVAL 13 MINUTE);
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Costilla de cerdo BBQ'), 'Costilla de cerdo BBQ', 34900, 2, 69800),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Cerveza nacional'), 'Cerveza nacional', 9900, 3, 29700);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, en_preparacion_en, estado_actualizado_en)
VALUES (1, @t2, 2, CONCAT('T', @t2, '-006'), 33800, 'efectivo', NULL,
        NOW() - INTERVAL 7 MINUTE, NOW() - INTERVAL 5 MINUTE, NOW() - INTERVAL 5 MINUTE);
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Chorizo santarrosano con arepa'), 'Chorizo santarrosano con arepa', 15900, 1, 15900),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Salchipapa'), 'Salchipapa', 17900, 1, 17900);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, estado_actualizado_en)
VALUES (1, @t2, 1, CONCAT('T', @t2, '-007'), 21800, 'tarjeta', 'Para llevar',
        NOW() - INTERVAL 3 MINUTE, NOW() - INTERVAL 3 MINUTE);
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Salpicón de frutas'), 'Salpicón de frutas', 10900, 2, 21800);

INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota, creado_en, estado_actualizado_en)
VALUES (1, @t2, 1, CONCAT('T', @t2, '-008'), 31700, 'efectivo', NULL,
        NOW() - INTERVAL 1 MINUTE, NOW() - INTERVAL 1 MINUTE);
SET @o := LAST_INSERT_ID();
INSERT INTO orden_items (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal) VALUES
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Arepa de chócolo con queso'), 'Arepa de chócolo con queso', 15900, 1, 15900),
  (@o, (SELECT id FROM productos WHERE food_truck_id=1 AND nombre='Aguapanela con queso'), 'Aguapanela con queso', 7900, 2, 15800);

-- ---------------------------------------------------------------------------
-- 6. Fotos de los productos
--    Los archivos viven en ADSO.menu08.com/subidas/ y van versionados. Su
--    procedencia y licencia estan en docs/creditos-imagenes.md.
-- ---------------------------------------------------------------------------
UPDATE productos SET foto = 'festin-aguapanela-con-queso.jpg' WHERE food_truck_id = 1 AND nombre = 'Aguapanela con queso';
UPDATE productos SET foto = 'festin-arepa-de-chocolo-con-queso.jpg' WHERE food_truck_id = 1 AND nombre = 'Arepa de chócolo con queso';
UPDATE productos SET foto = 'festin-arepa-de-huevo.jpg' WHERE food_truck_id = 1 AND nombre = 'Arepa de huevo';
UPDATE productos SET foto = 'festin-arroz-con-leche.jpg' WHERE food_truck_id = 1 AND nombre = 'Arroz con leche';
UPDATE productos SET foto = 'festin-bandeja-paisa.jpg' WHERE food_truck_id = 1 AND nombre = 'Bandeja paisa';
UPDATE productos SET foto = 'festin-bunuelos.jpg' WHERE food_truck_id = 1 AND nombre = 'Buñuelos';
UPDATE productos SET foto = 'festin-cerveza-nacional.jpg' WHERE food_truck_id = 1 AND nombre = 'Cerveza nacional';
UPDATE productos SET foto = 'festin-chorizo-santarrosano-con-arepa.jpg' WHERE food_truck_id = 1 AND nombre = 'Chorizo santarrosano con arepa';
UPDATE productos SET foto = 'festin-costilla-de-cerdo-bbq.jpg' WHERE food_truck_id = 1 AND nombre = 'Costilla de cerdo BBQ';
UPDATE productos SET foto = 'festin-empanadas-con-aji.jpg' WHERE food_truck_id = 1 AND nombre = 'Empanadas con ají';
UPDATE productos SET foto = 'festin-hamburguesa-del-truck.jpg' WHERE food_truck_id = 1 AND nombre = 'Hamburguesa del truck';
UPDATE productos SET foto = 'festin-jugo-de-lulo.jpg' WHERE food_truck_id = 1 AND nombre = 'Jugo de lulo';
UPDATE productos SET foto = 'festin-lechona-tolimense.jpg' WHERE food_truck_id = 1 AND nombre = 'Lechona tolimense';
UPDATE productos SET foto = 'festin-limonada-de-coco.jpg' WHERE food_truck_id = 1 AND nombre = 'Limonada de coco';
UPDATE productos SET foto = 'festin-patacon-con-hogao.jpg' WHERE food_truck_id = 1 AND nombre = 'Patacón con hogao';
UPDATE productos SET foto = 'festin-perro-caliente-colombiano.jpg' WHERE food_truck_id = 1 AND nombre = 'Perro caliente colombiano';
UPDATE productos SET foto = 'festin-picada-parrillera.jpg' WHERE food_truck_id = 1 AND nombre = 'Picada parrillera';
UPDATE productos SET foto = 'festin-punta-de-anca-250-g.jpg' WHERE food_truck_id = 1 AND nombre = 'Punta de anca 250 g';
UPDATE productos SET foto = 'festin-salchipapa.jpg' WHERE food_truck_id = 1 AND nombre = 'Salchipapa';
UPDATE productos SET foto = 'festin-salpicon-de-frutas.jpg' WHERE food_truck_id = 1 AND nombre = 'Salpicón de frutas';
