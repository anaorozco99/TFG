-- ============================================================
-- GestInt — Base de datos para InfinityFree
-- Importar en phpMyAdmin sobre la BD: if0_42132698_gestint
-- Contraseñas en bcrypt ($2b$ compatible con password_verify PHP)
-- NOTA: Si el import falla en las tablas pedidos / pedidos_ventas,
--       es porque tu MySQL no soporta columnas GENERATED.
--       En ese caso elimina las líneas "fecha_llegada" y "fecha_entrega".
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- DROP TABLES (orden inverso de dependencia)
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `pedidos_ventas_lineas`;
DROP TABLE IF EXISTS `pedidos_ventas`;
DROP TABLE IF EXISTS `pedidos_lineas`;
DROP TABLE IF EXISTS `pedidos`;
DROP TABLE IF EXISTS `logs_acceso`;
DROP TABLE IF EXISTS `fichajes`;
DROP TABLE IF EXISTS `justificantes`;
DROP TABLE IF EXISTS `tickets`;
DROP TABLE IF EXISTS `vacaciones`;
DROP TABLE IF EXISTS `clientes`;
DROP TABLE IF EXISTS `empleados`;
DROP TABLE IF EXISTS `inventario`;
DROP TABLE IF EXISTS `usuarios`;

-- -----------------------------------------------------------
-- TABLAS
-- -----------------------------------------------------------

CREATE TABLE `usuarios` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(80) NOT NULL,
  `apellidos` VARCHAR(100) NOT NULL DEFAULT '',
  `usuario` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(120) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `rol` ENUM('admin','director','rrhh','almacen','empleado','soporte') NOT NULL DEFAULT 'empleado',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `empleados` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT UNSIGNED NOT NULL,
  `nombre` VARCHAR(80) NOT NULL,
  `apellidos` VARCHAR(100) NOT NULL,
  `dni` CHAR(9) NOT NULL UNIQUE,
  `email` VARCHAR(120) NOT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `departamento` VARCHAR(60) NOT NULL,
  `cargo` VARCHAR(80) DEFAULT NULL,
  `fecha_alta` DATE NOT NULL,
  `salario` DECIMAL(10,2) DEFAULT 0.00,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_emp_usr` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `inventario` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(150) NOT NULL UNIQUE,
  `categoria` VARCHAR(60) NOT NULL DEFAULT 'Otros',
  `descripcion` TEXT DEFAULT NULL,
  `cantidad` INT NOT NULL DEFAULT 0,
  `stock_minimo` INT NOT NULL DEFAULT 5,
  `unidad` VARCHAR(30) NOT NULL DEFAULT 'unidad',
  `proveedor` VARCHAR(100) DEFAULT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `fecha_entrada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pedidos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `estado` ENUM('pendiente','enviado','recibido') NOT NULL DEFAULT 'pendiente',
  `fecha_pedido` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_llegada` DATETIME GENERATED ALWAYS AS (DATE_ADD(`fecha_pedido`, INTERVAL 24 HOUR)) STORED,
  `notas` TEXT DEFAULT NULL,
  CONSTRAINT `fk_ped_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pedidos_lineas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT UNSIGNED NOT NULL,
  `inventario_id` INT UNSIGNED NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `cantidad` INT NOT NULL DEFAULT 1,
  CONSTRAINT `fk_lin_ped` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lin_inv` FOREIGN KEY (`inventario_id`) REFERENCES `inventario`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `logs_acceso` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_log_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `clientes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `empresa` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(120) NOT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `direccion` VARCHAR(200) DEFAULT NULL,
  `ciudad` VARCHAR(80) DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pedidos_ventas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `estado` ENUM('pendiente','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  `fecha_pedido` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_entrega` DATETIME GENERATED ALWAYS AS (DATE_ADD(`fecha_pedido`, INTERVAL 48 HOUR)) STORED,
  `notas` TEXT DEFAULT NULL,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_pv_cli` FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`),
  CONSTRAINT `fk_pv_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `vacaciones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `fecha_ini` DATE NOT NULL,
  `fecha_fin` DATE NOT NULL,
  `dias` INT NOT NULL DEFAULT 1,
  `motivo` VARCHAR(200) DEFAULT NULL,
  `estado` ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `respuesta` VARCHAR(200) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_vac_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `tickets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `asunto` VARCHAR(200) NOT NULL,
  `descripcion` TEXT NOT NULL,
  `estado` ENUM('abierto','en_proceso','resuelto') NOT NULL DEFAULT 'abierto',
  `usuario_id` INT UNSIGNED DEFAULT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_tick_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `fichajes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('entrada','salida') NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_fich_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `justificantes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `descripcion` VARCHAR(200) NOT NULL,
  `archivo` VARCHAR(255) NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_just_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pedidos_ventas_lineas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT UNSIGNED NOT NULL,
  `inventario_id` INT UNSIGNED NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `cantidad` INT NOT NULL DEFAULT 1,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_pvl_ped` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos_ventas`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pvl_inv` FOREIGN KEY (`inventario_id`) REFERENCES `inventario`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------
-- USUARIOS (IDs 1-17)
-- Hashes bcrypt generados con cost=12, compatibles con password_verify()
-- -----------------------------------------------------------
INSERT INTO `usuarios` (`id`,`nombre`,`apellidos`,`usuario`,`email`,`password`,`rol`) VALUES
(1,  'Michael', 'Scott',          'michael', 'michael.scott@dundermifflin.com',  '$2b$12$QyoB9eZT9YkOKBU.sXnsEe.JALjqS6elJbehKI3WTCvnDL96FOZPG', 'director'),
(2,  'Dwight',  'Schrute',        'dwight',  'dwight.schrute@dundermifflin.com', '$2b$12$0ZH./26x1Ro/7XgE95xwIuiyjV/EPJLhyIyHc.yyPbnXSeoEfcMdC', 'empleado'),
(3,  'Jim',     'Halpert',        'jim',     'jim.halpert@dundermifflin.com',    '$2b$12$OjTQlaG0G0YH9rcS063TH.TG/TuABWYbzGzG02svY/Z2Tr5uELkM6', 'empleado'),
(4,  'Pam',     'Beesly',         'pam',     'pam.beesly@dundermifflin.com',     '$2b$12$1TSBotn9O.8MW29iue4i8OvT14vftIt940pLI7EZw4L89YhS5oC9.', 'rrhh'),
(5,  'Toby',    'Flenderson',     'toby',    'toby.flenderson@dundermifflin.com','$2b$12$63Tn.sknnVM/VVOXdTFkRuNsDNBhHSABd7Z0xsRkH.WViwx6muxa6', 'rrhh'),
(6,  'Angela',  'Martin',         'angela',  'angela.martin@dundermifflin.com',  '$2b$12$8BVD9OGaRVabYwN2JVm7bug2EIrtBgwc1f4f.TeDOGcL9eUVuEXxC', 'empleado'),
(7,  'Oscar',   'Martinez',       'oscar',   'oscar.martinez@dundermifflin.com', '$2b$12$XwaGJeZCyUXer9Wacg7eUuvOMJeR34ZFCHYBjAISfZ93M8zOJMWPS', 'empleado'),
(8,  'Kevin',   'Malone',         'kevin',   'kevin.malone@dundermifflin.com',   '$2b$12$xlUpWHABdwYRBk0N55Cd1Os/SVX5Gtt5ScZgSupDNO//ioVuOtGTW', 'empleado'),
(9,  'Stanley', 'Hudson',         'stanley', 'stanley.hudson@dundermifflin.com', '$2b$12$O4QfYRPj.ohCeMDbVzKfmuMPfp5ReLj9so2segBhzy9Mdrc8BC4I6', 'empleado'),
(10, 'Phyllis', 'Vance',          'phyllis', 'phyllis.vance@dundermifflin.com',  '$2b$12$qHFnTNXpJdGsjSJQD1DQT.fhcgc5OB.pqpmrAI/xcFDs8xF0cJAZO', 'empleado'),
(11, 'Andy',    'Bernard',        'andy',    'andy.bernard@dundermifflin.com',   '$2b$12$xnxZ88XRGn/PH8l9WD0I9u1eDQYQJjf7nxhMyHWyrpX7B7oI4qrsS', 'empleado'),
(12, 'Darryl',  'Philbin',        'darryl',  'darryl.philbin@dundermifflin.com', '$2b$12$5hzsxyeSpiUCWOc3VvG1x.sfeT4LH/7lmyhNZFTyqSqhk49pjs/Ye', 'almacen'),
(13, 'Roy',     'Anderson',       'roy',     'roy.anderson@dundermifflin.com',   '$2b$12$J1MYDJCykWesQHHyavsUvexQ6jOPDgLOfQVdrcSFGLaRm4Y7/St/.', 'almacen'),
(14, 'Ryan',    'Howard',         'ryan',    'ryan.howard@dundermifflin.com',    '$2b$12$jLBU/TbsuPM99CJwCgC25uNZEDjVIo4sZ.naTX2ePvVu8eJs2/TlK', 'empleado'),
(15, 'Ana',     'Orozco Asensio', 'ana',     'anaorozcoasensio@gmail.com',       '$2b$12$QG4KnOe7x0cEWPyoNryGDeM3H1tDvchCHQfkXAFfhfZA6tji5VodC', 'soporte'),
(16, 'Alberto', 'Sánchez',        'alberto', 'alberto.sanchez@dundermifflin.com','$2b$12$INIWIYZyW8gid5P1zDaIqOsHpP1hdpz4oOwyP/GArELqJpMq82iFO', 'soporte'),
(17, 'Sistema', '',               'sistema', 'sistema@dundermifflin.com',        '$2b$12$WQRUEx8Ykj.aOsyPGIi8WO6V8k.F0MQziaZp2mboxgHJmFOEkRCeK', 'admin');

-- -----------------------------------------------------------
-- EMPLEADOS (id_usuario = ID del usuario correspondiente)
-- -----------------------------------------------------------
INSERT INTO `empleados` (`id_usuario`,`nombre`,`apellidos`,`dni`,`email`,`telefono`,`departamento`,`cargo`,`fecha_alta`,`salario`) VALUES
(1,  'Michael', 'Scott',          '12345678Z', 'michael.scott@dundermifflin.com',  '570100001', 'Dirección',   'Director Regional',       '2005-03-24', 65000.00),
(2,  'Dwight',  'Schrute',        '23456789D', 'dwight.schrute@dundermifflin.com', '570100002', 'Ventas',      'Asistente del Director',  '2005-03-24', 42000.00),
(3,  'Jim',     'Halpert',        '34567890V', 'jim.halpert@dundermifflin.com',    '570100003', 'Ventas',      'Comercial',               '2005-03-24', 40000.00),
(4,  'Pam',     'Beesly',         '45678901G', 'pam.beesly@dundermifflin.com',     '570100004', 'Recepción',   'Recepcionista',           '2005-03-24', 32000.00),
(5,  'Toby',    'Flenderson',     '56789012B', 'toby.flenderson@dundermifflin.com','570100005', 'RRHH',        'Técnico de RRHH',         '2005-03-24', 38000.00),
(6,  'Angela',  'Martin',         '67890123B', 'angela.martin@dundermifflin.com',  '570100006', 'Contabilidad','Jefa de Contabilidad',    '2005-03-24', 41000.00),
(7,  'Oscar',   'Martinez',       '78901234X', 'oscar.martinez@dundermifflin.com', '570100007', 'Contabilidad','Contable',                '2005-03-24', 39000.00),
(8,  'Kevin',   'Malone',         '89012345E', 'kevin.malone@dundermifflin.com',   '570100008', 'Contabilidad','Contable',                '2005-03-24', 36000.00),
(9,  'Stanley', 'Hudson',         '90123456A', 'stanley.hudson@dundermifflin.com', '570100009', 'Ventas',      'Comercial',               '2005-03-24', 40000.00),
(10, 'Phyllis', 'Vance',          '12345679S', 'phyllis.vance@dundermifflin.com',  '570100010', 'Ventas',      'Comercial',               '2005-03-24', 39000.00),
(11, 'Andy',    'Bernard',        '11223344B', 'andy.bernard@dundermifflin.com',   '570100011', 'Ventas',      'Comercial',               '2007-01-15', 39000.00),
(12, 'Darryl',  'Philbin',        '22334455Y', 'darryl.philbin@dundermifflin.com', '570100012', 'Almacén',     'Jefe de Almacén',         '2005-03-24', 37000.00),
(13, 'Roy',     'Anderson',       '33445566R', 'roy.anderson@dundermifflin.com',   '570100013', 'Almacén',     'Auxiliar de Almacén',     '2005-03-24', 32000.00),
(14, 'Ryan',    'Howard',         '44556677T', 'ryan.howard@dundermifflin.com',    '570100014', 'Ventas',      'Comercial',               '2007-01-15', 38000.00),
(15, 'Ana',     'Orozco Asensio', '98765432A', 'anaorozcoasensio@gmail.com',       '570100015', 'IT',          'Técnico de Soporte IT',   '2024-09-01', 30000.00),
(16, 'Alberto', 'Sánchez',        '87654321B', 'alberto.sanchez@dundermifflin.com','570100016', 'IT',          'Técnico de Soporte IT',   '2023-06-01', 31000.00);

-- -----------------------------------------------------------
-- INVENTARIO (IDs 1-17)
-- -----------------------------------------------------------
INSERT INTO `inventario` (`nombre`,`categoria`,`cantidad`,`stock_minimo`,`unidad`,`proveedor`,`precio_unitario`) VALUES
('Papel A4 80g (500 hojas)',      'Papel',      500, 100, 'paquete', 'Navigator',   4.20),
('Papel A3 80g (500 hojas)',      'Papel',      120,  30, 'paquete', 'Navigator',   7.50),
('Papel reciclado A4 (500h)',     'Papel',      200,  50, 'paquete', 'Steinbeis',   3.60),
('Sobres blancos C4 (caja 250)',  'Sobres',      40,  10, 'caja',    'Liderpapel', 12.50),
('Sobres ventana DL (caja 500)',  'Sobres',      35,  10, 'caja',    'Liderpapel',  9.90),
('Carpetas A4 azul (caja 50)',    'Carpetas',    60,  15, 'caja',    'Leitz',      18.00),
('Archivadores A4 negro',         'Carpetas',    45,  10, 'unidad',  'Leitz',       3.80),
('Bolígrafos azul BIC (caja)',    'Escritura',   25,   5, 'caja',    'BIC',         8.50),
('Bolígrafos negro BIC (caja)',   'Escritura',   20,   5, 'caja',    'BIC',         8.50),
('Rotuladores permanentes (12)',  'Escritura',   18,   5, 'caja',    'Sharpie',    11.00),
('Tóner HP LaserJet negro',       'Consumibles',  6,   2, 'unidad',  'HP',         49.00),
('Tóner HP color (pack 4)',       'Consumibles',  4,   2, 'pack',    'HP',        139.00),
('Grapadora de mesa',             'Oficina',     12,   3, 'unidad',  'Rapid',      14.00),
('Grapas 26/6 (caja 5000)',       'Oficina',     30,  10, 'caja',    'Rapid',       3.20),
('Post-it 76x76 (pack 12)',       'Oficina',     50,  15, 'pack',    '3M',         12.50),
('Celo 19mm x 33m (pack 10)',     'Oficina',     40,  10, 'pack',    '3M',          9.80),
('Tijeras de oficina',            'Oficina',     10,   3, 'unidad',  'Maped',       4.50);

-- -----------------------------------------------------------
-- CLIENTES (IDs 1-9)
-- -----------------------------------------------------------
INSERT INTO `clientes` (`nombre`,`empresa`,`email`,`telefono`,`direccion`,`ciudad`) VALUES
('Vance Refrigeration', 'Vance Refrigeration Corp.', 'bob.vance@vancerefrigeration.com', '570200001', 'Calle Industrial 1',  'Scranton'),
('Prince Family Paper', 'Prince Family Paper Co.',   'info@princepaper.com',             '570200002', 'Av. Principal 45',    'Carbondale'),
('Lackawanna County',   'Condado de Lackawanna',     'compras@lackawanna.gov',           '570200003', 'Calle Gobierno 100',  'Scranton'),
('Dunmore High School', 'Instituto Dunmore',         'pedidos@dunmore.edu',              '570200004', 'Calle Escolar 50',    'Dunmore'),
('Michael Davis',       'Davis & Sons',              'mdavis@davissons.com',             '570200005', 'Calle Mayor 12',      'Scranton'),
('Sarah Connor',        'Connor Industries',         'sarah@connorind.com',              '570200006', 'Av. del Sol 34',      'Allentown'),
('Robert Mifflin',      'Mifflin & Partners',        'rmifflin@mifflinp.com',            '570200007', 'Calle Comercial 5',   'Wilkes-Barre'),
('Karen Filippelli',    'Utica Office Supply',       'karen@uticaoffice.com',            '570200008', 'Park Ave 100',        'Utica'),
('Todd Packer',         'Packer Sales LLC',          'tpacker@packersales.com',          '570200009', 'Industrial St 22',    'Harrisburg');

-- -----------------------------------------------------------
-- PEDIDOS DE STOCK (IDs 1-7)
-- usuario_id: darryl=12, roy=13
-- -----------------------------------------------------------
INSERT INTO `pedidos` (`id`,`usuario_id`,`estado`,`fecha_pedido`,`notas`) VALUES
(1, 12, 'recibido', '2026-01-15 09:30:00', 'Pedido trimestral de papel'),
(2, 12, 'recibido', '2026-03-22 11:00:00', 'Reposición consumibles'),
(3, 12, 'pendiente','2026-06-02 08:45:00', 'Urgente antes de fin de mes'),
(4, 12, 'recibido', '2026-02-10 08:00:00', 'Reposición papel Q1'),
(5, 13, 'recibido', '2026-03-05 09:30:00', 'Material escritura y grapas'),
(6, 12, 'recibido', '2026-04-18 10:00:00', 'Consumibles impresoras'),
(7, 13, 'pendiente','2026-06-01 08:00:00', 'Pedido mensual junio');

-- inventario_id: inv1=1,inv2=2,inv3=3,inv4=4,inv5=5,inv6=6,inv7=7,inv8=8,inv9=9,inv11=11,inv12=12,inv14=14,inv15=15,inv16=16
INSERT INTO `pedidos_lineas` (`pedido_id`,`inventario_id`,`nombre`,`cantidad`) VALUES
-- pedido 1
(1, 1,  'Papel A4 80g (500 hojas)',     20),
(1, 2,  'Papel A3 80g (500 hojas)',      5),
(1, 4,  'Sobres blancos C4 (caja 250)',  3),
-- pedido 2
(2, 11, 'Tóner HP LaserJet negro',      3),
(2, 15, 'Post-it 76x76 (pack 12)',     10),
(2, 16, 'Celo 19mm x 33m (pack 10)',    8),
-- pedido 3
(3, 8,  'Bolígrafos azul BIC (caja)',   5),
(3, 9,  'Bolígrafos negro BIC (caja)',  3),
(3, 14, 'Grapas 26/6 (caja 5000)',      4),
-- pedido 4
(4, 1,  'Papel A4 80g (500 hojas)',    30),
(4, 2,  'Papel A3 80g (500 hojas)',    10),
-- pedido 5
(5, 8,  'Bolígrafos azul BIC (caja)',   8),
(5, 14, 'Grapas 26/6 (caja 5000)',      6),
(5, 15, 'Post-it 76x76 (pack 12)',      5),
-- pedido 6
(6, 11, 'Tóner HP LaserJet negro',      4),
(6, 12, 'Tóner HP color (pack 4)',      2),
-- pedido 7
(7, 1,  'Papel A4 80g (500 hojas)',    20),
(7, 4,  'Sobres blancos C4 (caja 250)', 5),
(7, 16, 'Celo 19mm x 33m (pack 10)',   4);

-- -----------------------------------------------------------
-- PEDIDOS DE VENTAS (IDs 1-9)
-- cliente_id: Vance=1,Prince=2,Lackawanna=3,Dunmore=4,M.Davis=5,S.Connor=6,R.Mifflin=7,K.Filippelli=8,T.Packer=9
-- usuario_id: jim=3, phyllis=10, dwight=2, stanley=9, andy=11
-- -----------------------------------------------------------
INSERT INTO `pedidos_ventas` (`id`,`cliente_id`,`usuario_id`,`estado`,`fecha_pedido`,`notas`,`total`) VALUES
(1, 1, 3,  'entregado','2026-02-10 10:00:00','Pedido mensual habitual',    67.00),
(2, 3, 10, 'entregado','2026-04-05 09:30:00','',                          166.00),
(3, 2, 2,  'enviado',  '2026-05-28 14:15:00','Segundo pedido del año',    282.00),
(4, 4, 3,  'pendiente','2026-06-03 11:00:00','Para inicio de curso',      675.00),
(5, 8, 9,  'entregado','2026-01-20 10:00:00','Pedido inicial de año',     197.70),
(6, 5, 3,  'enviado',  '2026-03-15 11:00:00','Segundo pedido trimestre',  201.00),
(7, 7, 2,  'pendiente','2026-05-10 09:00:00','Material para nueva oficina',465.00),
(8, 9, 10, 'entregado','2026-04-25 14:00:00','',                          120.00),
(9, 1, 11, 'pendiente','2026-06-02 10:30:00','Pedido urgente fin semestre',208.00);

INSERT INTO `pedidos_ventas_lineas` (`pedido_id`,`inventario_id`,`nombre`,`cantidad`,`precio_unitario`) VALUES
-- pv1: jim→Vance
(1, 1,  'Papel A4 80g (500 hojas)',    10, 4.20),
(1, 4,  'Sobres blancos C4 (caja 250)', 2,12.50),
-- pv2: phyllis→Lackawanna
(2, 7,  'Archivadores A4 negro',       20, 3.80),
(2, 6,  'Carpetas A4 azul (caja 50)',   5,18.00),
-- pv3: dwight→Prince Family
(3, 1,  'Papel A4 80g (500 hojas)',    50, 4.20),
(3, 3,  'Papel reciclado A4 (500h)',   20, 3.60),
-- pv4: jim→Dunmore
(4, 8,  'Bolígrafos azul BIC (caja)',  30, 8.50),
(4, 1,  'Papel A4 80g (500 hojas)',   100, 4.20),
-- pv5: stanley→Karen Filippelli
(5, 1,  'Papel A4 80g (500 hojas)',    40, 4.20),
(5, 5,  'Sobres ventana DL (caja 500)', 3, 9.90),
-- pv6: jim→Michael Davis
(6, 6,  'Carpetas A4 azul (caja 50)',   8,18.00),
(6, 7,  'Archivadores A4 negro',       15, 3.80),
-- pv7: dwight→Robert Mifflin
(7, 8,  'Bolígrafos azul BIC (caja)',  20, 8.50),
(7, 9,  'Bolígrafos negro BIC (caja)', 10, 8.50),
(7, 1,  'Papel A4 80g (500 hojas)',    50, 4.20),
-- pv8: phyllis→Todd Packer
(8, 13, 'Grapadora de mesa',            5,14.00),
(8, 14, 'Grapas 26/6 (caja 5000)',     10, 3.20),
(8, 17, 'Tijeras de oficina',           4, 4.50),
-- pv9: andy→Vance
(9, 3,  'Papel reciclado A4 (500h)',   30, 3.60),
(9, 15, 'Post-it 76x76 (pack 12)',      8,12.50);

-- -----------------------------------------------------------
-- FICHAJES
-- usuario_id: pam=4,jim=3,dwight=2,darryl=12,roy=13,ana=15,toby=5,stanley=9,phyllis=10,oscar=7
-- -----------------------------------------------------------
INSERT INTO `fichajes` (`usuario_id`,`tipo`,`fecha`) VALUES
-- primera tanda (instalar.php)
(4,  'entrada','2026-04-07 08:05:00'), (4,  'salida','2026-04-07 17:02:00'),
(4,  'entrada','2026-04-08 08:10:00'), (4,  'salida','2026-04-08 17:15:00'),
(4,  'entrada','2026-05-05 08:00:00'), (4,  'salida','2026-05-05 17:00:00'),
(4,  'entrada','2026-05-06 08:03:00'), (4,  'salida','2026-05-06 17:08:00'),
(4,  'entrada','2026-06-02 07:58:00'), (4,  'salida','2026-06-02 17:01:00'),
(4,  'entrada','2026-06-03 08:05:00'), (4,  'salida','2026-06-03 17:05:00'),
(3,  'entrada','2026-05-20 09:00:00'), (3,  'salida','2026-05-20 18:00:00'),
(3,  'entrada','2026-05-21 09:05:00'), (3,  'salida','2026-05-21 18:10:00'),
(3,  'entrada','2026-06-02 08:55:00'), (3,  'salida','2026-06-02 18:00:00'),
(2,  'entrada','2026-05-15 07:30:00'), (2,  'salida','2026-05-15 16:30:00'),
(2,  'entrada','2026-06-03 07:25:00'), (2,  'salida','2026-06-03 16:25:00'),
(12, 'entrada','2026-05-28 08:00:00'), (12, 'salida','2026-05-28 17:00:00'),
(12, 'entrada','2026-06-02 08:00:00'), (12, 'salida','2026-06-02 17:00:00'),
(15, 'entrada','2026-06-04 09:00:00'), (15, 'salida','2026-06-04 18:00:00'),
(15, 'entrada','2026-06-05 09:00:00'),
-- segunda tanda (más fichajes)
(4,  'entrada','2026-05-12 08:05:00'), (4,  'salida','2026-05-12 17:10:00'),
(4,  'entrada','2026-05-13 08:02:00'), (4,  'salida','2026-05-13 17:05:00'),
(4,  'entrada','2026-05-14 08:10:00'), (4,  'salida','2026-05-14 17:00:00'),
(3,  'entrada','2026-05-12 09:00:00'), (3,  'salida','2026-05-12 18:15:00'),
(3,  'entrada','2026-05-13 09:05:00'), (3,  'salida','2026-05-13 17:50:00'),
(5,  'entrada','2026-05-12 08:00:00'), (5,  'salida','2026-05-12 17:00:00'),
(5,  'entrada','2026-05-13 08:05:00'), (5,  'salida','2026-05-13 17:05:00'),
(5,  'entrada','2026-05-14 08:10:00'), (5,  'salida','2026-05-14 17:00:00'),
(9,  'entrada','2026-05-12 08:45:00'), (9,  'salida','2026-05-12 17:45:00'),
(9,  'entrada','2026-05-13 08:50:00'), (9,  'salida','2026-05-13 17:50:00'),
(4,  'entrada','2026-05-26 08:00:00'), (4,  'salida','2026-05-26 17:00:00'),
(4,  'entrada','2026-05-27 08:05:00'), (4,  'salida','2026-05-27 17:10:00'),
(4,  'entrada','2026-05-28 08:00:00'), (4,  'salida','2026-05-28 16:30:00'),
(12, 'entrada','2026-05-26 07:50:00'), (12, 'salida','2026-05-26 16:50:00'),
(12, 'entrada','2026-05-27 07:55:00'), (12, 'salida','2026-05-27 16:55:00'),
(13, 'entrada','2026-05-26 08:00:00'), (13, 'salida','2026-05-26 17:00:00'),
(13, 'entrada','2026-05-27 08:05:00'), (13, 'salida','2026-05-27 17:05:00'),
(5,  'entrada','2026-06-02 08:00:00'), (5,  'salida','2026-06-02 17:00:00'),
(5,  'entrada','2026-06-03 08:05:00'), (5,  'salida','2026-06-03 17:05:00'),
(5,  'entrada','2026-06-04 08:02:00'), (5,  'salida','2026-06-04 17:02:00'),
(3,  'entrada','2026-06-03 09:00:00'), (3,  'salida','2026-06-03 18:05:00'),
(3,  'entrada','2026-06-04 09:10:00'), (3,  'salida','2026-06-04 18:20:00'),
(9,  'entrada','2026-06-02 08:40:00'), (9,  'salida','2026-06-02 17:40:00'),
(10, 'entrada','2026-06-02 08:30:00'), (10, 'salida','2026-06-02 17:30:00'),
(10, 'entrada','2026-06-03 08:25:00'), (10, 'salida','2026-06-03 17:25:00'),
(7,  'entrada','2026-06-02 08:15:00'), (7,  'salida','2026-06-02 17:15:00'),
(7,  'entrada','2026-06-03 08:20:00'), (7,  'salida','2026-06-03 17:20:00');

-- -----------------------------------------------------------
-- LOGS DE ACCESO
-- -----------------------------------------------------------
INSERT INTO `logs_acceso` (`usuario_id`,`ip`,`fecha`) VALUES
(1,  '192.168.1.1',  '2026-05-20 07:58:00'),
(4,  '192.168.1.10', '2026-05-20 08:02:00'),
(5,  '192.168.1.11', '2026-05-20 08:05:00'),
(12, '192.168.1.50', '2026-05-20 07:50:00'),
(13, '192.168.1.51', '2026-05-20 07:55:00'),
(2,  '192.168.1.30', '2026-05-20 07:28:00'),
(3,  '192.168.1.31', '2026-05-20 08:58:00'),
(9,  '192.168.1.32', '2026-05-20 08:43:00'),
(6,  '192.168.1.40', '2026-05-20 08:10:00'),
(7,  '192.168.1.41', '2026-05-20 08:15:00'),
(4,  '192.168.1.10', '2026-05-21 08:01:00'),
(5,  '192.168.1.11', '2026-05-21 08:03:00'),
(12, '192.168.1.50', '2026-05-21 07:52:00'),
(3,  '192.168.1.31', '2026-05-21 09:05:00'),
(2,  '192.168.1.30', '2026-05-21 07:30:00'),
(1,  '192.168.1.1',  '2026-05-26 07:55:00'),
(4,  '192.168.1.10', '2026-05-26 08:00:00'),
(12, '192.168.1.50', '2026-05-26 07:48:00'),
(13, '192.168.1.51', '2026-05-26 07:58:00'),
(9,  '192.168.1.32', '2026-05-26 08:40:00'),
(10, '192.168.1.33', '2026-05-26 08:28:00'),
(1,  '192.168.1.1',  '2026-06-02 07:57:00'),
(4,  '192.168.1.10', '2026-06-02 07:59:00'),
(5,  '192.168.1.11', '2026-06-02 08:02:00'),
(12, '192.168.1.50', '2026-06-02 07:50:00'),
(15, '192.168.1.60', '2026-06-02 08:58:00'),
(3,  '192.168.1.31', '2026-06-02 08:53:00'),
(7,  '192.168.1.41', '2026-06-02 08:12:00'),
(4,  '192.168.1.10', '2026-06-03 08:03:00'),
(5,  '192.168.1.11', '2026-06-03 08:05:00'),
(15, '192.168.1.60', '2026-06-03 09:03:00'),
(16, '192.168.1.61', '2026-06-03 09:00:00'),
(3,  '192.168.1.31', '2026-06-03 09:08:00'),
(2,  '192.168.1.30', '2026-06-03 07:23:00'),
(1,  '192.168.1.1',  '2026-06-04 07:58:00'),
(4,  '192.168.1.10', '2026-06-04 08:00:00'),
(15, '192.168.1.60', '2026-06-04 08:59:00'),
(16, '192.168.1.61', '2026-06-04 09:02:00'),
(7,  '192.168.1.41', '2026-06-04 08:18:00'),
(4,  '192.168.1.10', '2026-06-05 07:56:00'),
(15, '192.168.1.60', '2026-06-05 09:01:00'),
(3,  '192.168.1.31', '2026-06-05 08:55:00');

-- -----------------------------------------------------------
-- JUSTIFICANTES
-- usuario_id: pam=4,jim=3,dwight=2,stanley=9,kevin=8,angela=6,toby=5
-- -----------------------------------------------------------
INSERT INTO `justificantes` (`usuario_id`,`descripcion`,`archivo`,`fecha`) VALUES
(4, 'Baja médica 15/04/2026',      'placeholder_pam_baja.pdf',         '2026-04-15 10:00:00'),
(4, 'Asistencia médica 03/05/2026','placeholder_pam_medico.pdf',        '2026-05-03 11:00:00'),
(3, 'Día personal 20/03/2026',     'placeholder_jim_personal.pdf',      '2026-03-20 09:00:00'),
(2, 'Baja por gripe 08/02/2026',   'placeholder_dwight_gripe.pdf',      '2026-02-08 08:30:00'),
(9, 'Consulta médica 10/04/2026',  'placeholder_stanley_med.pdf',       '2026-04-10 09:30:00'),
(8, 'Urgencias 25/03/2026',        'placeholder_kevin_urgencias.pdf',   '2026-03-25 15:00:00'),
(6, 'Dentista 12/05/2026',         'placeholder_angela_dentista.pdf',   '2026-05-12 10:00:00'),
(5, 'Asistencia médica 02/06/2026','placeholder_toby_medico.pdf',       '2026-06-02 08:45:00');

-- -----------------------------------------------------------
-- VACACIONES
-- usuario_id: pam=4,jim=3,dwight=2,stanley=9,kevin=8
-- -----------------------------------------------------------
INSERT INTO `vacaciones` (`usuario_id`,`fecha_ini`,`fecha_fin`,`dias`,`motivo`,`estado`,`respuesta`) VALUES
(4, '2026-07-01','2026-07-15',15,'Vacaciones verano',      'aprobada', 'Aprobado por RRHH'),
(3, '2026-08-01','2026-08-07', 7,'Viaje familiar',         'aprobada', 'Aprobado'),
(2, '2026-07-14','2026-07-18', 5,'Granja de remolacha',    'pendiente',''),
(9, '2026-09-01','2026-09-05', 5,'Asunto personal',        'pendiente',''),
(8, '2026-06-20','2026-06-25', 6,'Semana de descanso',     'rechazada','No hay cobertura en esa semana');

-- -----------------------------------------------------------
-- TICKETS
-- usuario_id: ana=15, alberto=16, NULL
-- -----------------------------------------------------------
INSERT INTO `tickets` (`nombre`,`email`,`asunto`,`descripcion`,`estado`,`usuario_id`) VALUES
('Pam Beesly',    'pam.beesly@dundermifflin.com',   'No puedo acceder al sistema', 'Desde ayer no me deja entrar, me dice contraseña incorrecta.',   'resuelto',   15),
('Kevin Malone',  'kevin.malone@dundermifflin.com',  'La impresora no funciona',    'La impresora del tercer piso no imprime desde el lunes.',        'en_proceso', 15),
('Stanley Hudson','stanley.hudson@dundermifflin.com','Pantalla muy lenta',          'El ordenador tarda 10 minutos en arrancar.',                      'abierto',    NULL),
('Andy Bernard',  'andy.bernard@dundermifflin.com',  'Error al subir justificante', 'Al intentar subir un PDF me sale error 500.',                    'resuelto',   16);
