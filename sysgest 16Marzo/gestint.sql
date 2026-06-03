
CREATE DATABASE IF NOT EXISTS gestint
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE gestint;

-- ---- Usuarios del sistema --------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(80)  NOT NULL,
    apellidos   VARCHAR(100) NOT NULL DEFAULT '',
    usuario     VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(120) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    rol         ENUM('admin','rrhh','almacen','empleado') NOT NULL DEFAULT 'empleado',
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Usuario administrador por defecto
-- Contraseña: Admin1234  (cámbiala después del primer login)
INSERT INTO usuarios (nombre, apellidos, usuario, email, password, rol)
VALUES ('Administrador', 'GestInt', 'admin', 'admin@empresa.local',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ---- Empleados -------------------------------------------
CREATE TABLE IF NOT EXISTS empleados (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(80)  NOT NULL,
    apellidos     VARCHAR(100) NOT NULL,
    dni           CHAR(9)      NOT NULL UNIQUE,
    email         VARCHAR(120) NOT NULL,
    telefono      VARCHAR(20)  DEFAULT NULL,
    departamento  VARCHAR(60)  NOT NULL,
    cargo         VARCHAR(80)  DEFAULT NULL,
    fecha_alta    DATE         NOT NULL,
    salario       DECIMAL(10,2) DEFAULT 0.00,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Datos de prueba
INSERT INTO empleados (nombre, apellidos, dni, email, telefono, departamento, cargo, fecha_alta, salario) VALUES
('María',  'García López',   '11111111A', 'maria@empresa.local',  '600111222', 'RRHH',         'Técnico RRHH',      '2022-03-01', 28000.00),
('Pedro',  'Martínez Ruiz',  '22222222B', 'pedro@empresa.local',  '600333444', 'Informática',  'Sysadmin',          '2021-06-15', 32000.00),
('Laura',  'Sánchez Mora',   '33333333C', 'laura@empresa.local',  '600555666', 'Administración','Aux. Administración','2023-01-10', 22000.00),
('Carlos', 'Fernández Gil',  '44444444D', 'carlos@empresa.local', '600777888', 'Almacén',      'Resp. Almacén',     '2020-09-01', 26000.00),
('Ana',    'Jiménez Vega',   '55555555E', 'ana@empresa.local',    '600999000', 'Ventas',       'Comercial',         '2023-05-20', 24000.00);

-- ---- Inventario ------------------------------------------
CREATE TABLE IF NOT EXISTS inventario (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    categoria       VARCHAR(60)  NOT NULL DEFAULT 'Otros',
    descripcion     TEXT         DEFAULT NULL,
    cantidad        INT          NOT NULL DEFAULT 0,
    stock_minimo    INT          NOT NULL DEFAULT 5,
    unidad          VARCHAR(30)  NOT NULL DEFAULT 'unidad',
    proveedor       VARCHAR(100) DEFAULT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fecha_entrada   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Datos de prueba
INSERT INTO inventario (nombre, categoria, cantidad, stock_minimo, unidad, proveedor, precio_unitario) VALUES
('Papel A4 500 hojas',    'Consumibles', 120, 20, 'caja',    'Lyreco',    3.50),
('Bolígrafos azules',     'Consumibles',   8,  5, 'caja',    'BIC',       4.20),
('Tóner impresora HP',    'Consumibles',   2,  3, 'unidad',  'HP',       45.00),
('Silla de oficina',      'Mobiliario',   15,  2, 'unidad',  'Ofiprix', 180.00),
('Monitor 24"',           'Electrónica',   6,  2, 'unidad',  'LG',      220.00),
('Teclado USB',           'Electrónica',  20,  5, 'unidad',  'Logitech', 25.00),
('Ratón inalámbrico',     'Electrónica',  18,  5, 'unidad',  'Logitech', 18.00),
('Archivador A4',         'Oficina',      35, 10, 'unidad',  'Leitz',     3.80),
('Guantes nitrilo (100)', 'Seguridad',     4,  5, 'caja',    'Ansell',   12.00),
('Gel hidroalcohólico 1L','Limpieza',      9, 10, 'unidad',  'Diversey',  6.50);

-- ---- Logs de acceso --------------------------------------
CREATE TABLE IF NOT EXISTS logs_acceso (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    ip          VARCHAR(45)  NOT NULL,
    fecha       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Usuario de la aplicación (mínimo privilegio)
-- Ejecutar como root:
CREATE USER 'ana'@'localhost' IDENTIFIED BY 'Admin1234';
GRANT SELECT, INSERT, UPDATE, DELETE ON gestint.* TO 'ana'@'localhost';
FLUSH PRIVILEGES;
-- ============================================================
