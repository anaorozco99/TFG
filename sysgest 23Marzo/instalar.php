<?php
// Instalador — ejecutar una sola vez y luego borrar este archivo
require_once 'includes/db.php';

$ok  = [];
$err = [];

$tablas = [
    "usuarios" => "CREATE TABLE IF NOT EXISTS usuarios (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre     VARCHAR(80)  NOT NULL,
        apellidos  VARCHAR(100) NOT NULL DEFAULT '',
        usuario    VARCHAR(50)  NOT NULL UNIQUE,
        email      VARCHAR(120) NOT NULL,
        password   VARCHAR(255) NOT NULL,
        rol        ENUM('admin','rrhh','almacen','empleado') NOT NULL DEFAULT 'empleado',
        activo     TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "empleados" => "CREATE TABLE IF NOT EXISTS empleados (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre       VARCHAR(80)  NOT NULL,
        apellidos    VARCHAR(100) NOT NULL,
        dni          CHAR(9)      NOT NULL UNIQUE,
        email        VARCHAR(120) NOT NULL,
        telefono     VARCHAR(20)  DEFAULT NULL,
        departamento VARCHAR(60)  NOT NULL,
        cargo        VARCHAR(80)  DEFAULT NULL,
        fecha_alta   DATE         NOT NULL,
        salario      DECIMAL(10,2) DEFAULT 0.00,
        activo       TINYINT(1)   NOT NULL DEFAULT 1,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "inventario" => "CREATE TABLE IF NOT EXISTS inventario (
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
    ) ENGINE=InnoDB",

    "pedidos" => "CREATE TABLE IF NOT EXISTS pedidos (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id    INT UNSIGNED NOT NULL,
        estado        ENUM('pendiente','enviado','recibido') NOT NULL DEFAULT 'pendiente',
        fecha_pedido  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_llegada DATETIME GENERATED ALWAYS AS (DATE_ADD(fecha_pedido, INTERVAL 24 HOUR)) STORED,
        notas         TEXT DEFAULT NULL
    ) ENGINE=InnoDB",

    "pedidos_lineas" => "CREATE TABLE IF NOT EXISTS pedidos_lineas (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pedido_id     INT UNSIGNED NOT NULL,
        inventario_id INT UNSIGNED NOT NULL,
        nombre        VARCHAR(150) NOT NULL,
        cantidad      INT NOT NULL DEFAULT 1,
        FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "logs_acceso" => "CREATE TABLE IF NOT EXISTS logs_acceso (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT UNSIGNED NOT NULL,
        ip         VARCHAR(45)  NOT NULL,
        fecha      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
];

foreach ($tablas as $nombre => $sql) {
    $conn->query($sql);
    if ($conn->error) {
        $err[] = "Tabla $nombre: " . $conn->error;
    } else {
        $ok[] = "Tabla <b>$nombre</b> creada";
    }
}

// Usuarios del sistema
// admin     → Administrador total
// pedro     → Admin (sysadmin del departamento de informática)
// maria     → RRHH
// carlos    → Almacén
// laura     → Empleado (administración)
// ana       → Empleado (ventas)

$usuarios = [
    ['Administrador', 'Sistema',    'admin',  'admin@gestint.local',  'Admin1234',  'admin'],
    ['Pedro',         'Martínez Ruiz',  'pedro',  'pedro@gestint.local',  'Pedro1234',  'admin'],
    ['María',         'García López',   'maria',  'maria@gestint.local',  'Maria1234',  'rrhh'],
    ['Carlos',        'Fernández Gil',  'carlos', 'carlos@gestint.local', 'Carlos1234', 'almacen'],
    ['Laura',         'Sánchez Mora',   'laura',  'laura@gestint.local',  'Laura1234',  'empleado'],
    ['Ana',           'Jiménez Vega',   'ana',    'ana@gestint.local',    'Ana12345',   'empleado'],
];

foreach ($usuarios as [$nom, $ape, $usr, $email, $pass, $rol]) {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO usuarios (nombre, apellidos, usuario, email, password, rol) VALUES (?,?,?,?,?,?)"
    );
    $stmt->bind_param('ssssss', $nom, $ape, $usr, $email, $hash, $rol);
    $stmt->execute();
    if ($conn->error) {
        $err[] = "Usuario $usr: " . $conn->error;
    } else {
        $ok[] = "Usuario <b>$usr</b> — rol: $rol — contraseña: $pass";
    }
}

// Empleados de prueba
$empleados = [
    ['María',  'García López',  '11111111A', 'maria@gestint.local',  '600111222', 'RRHH',          'Técnico RRHH',        '2022-03-01', 28000],
    ['Pedro',  'Martínez Ruiz', '22222222B', 'pedro@gestint.local',  '600333444', 'Informática',   'Sysadmin',            '2021-06-15', 32000],
    ['Laura',  'Sánchez Mora',  '33333333C', 'laura@gestint.local',  '600555666', 'Administración','Aux. Administración', '2023-01-10', 22000],
    ['Carlos', 'Fernández Gil', '44444444D', 'carlos@gestint.local', '600777888', 'Almacén',       'Resp. Almacén',       '2020-09-01', 26000],
    ['Ana',    'Jiménez Vega',  '55555555E', 'ana@gestint.local',    '600999000', 'Ventas',        'Comercial',           '2023-05-20', 24000],
];

foreach ($empleados as [$nom, $ape, $dni, $email, $tel, $dep, $cargo, $fecha, $sal]) {
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO empleados (nombre,apellidos,dni,email,telefono,departamento,cargo,fecha_alta,salario) VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('ssssssssd', $nom, $ape, $dni, $email, $tel, $dep, $cargo, $fecha, $sal);
    $stmt->execute();
    if ($conn->error) {
        $err[] = "Empleado $nom: " . $conn->error;
    } else {
        $ok[] = "Empleado <b>$nom $ape</b> añadido";
    }
}

// Inventario de prueba
$inventario = [
    ['Papel A4 500 hojas',   'Consumibles', 120, 20, 'caja',   'Lyreco',    3.50],
    ['Bolígrafos azules',    'Consumibles',   8,  5, 'caja',   'BIC',       4.20],
    ['Tóner impresora HP',   'Consumibles',   2,  3, 'unidad', 'HP',       45.00],
    ['Silla de oficina',     'Mobiliario',   15,  2, 'unidad', 'Ofiprix', 180.00],
    ['Monitor 24"',          'Electrónica',   6,  2, 'unidad', 'LG',      220.00],
    ['Teclado USB',          'Electrónica',  20,  5, 'unidad', 'Logitech', 25.00],
    ['Ratón inalámbrico',    'Electrónica',  18,  5, 'unidad', 'Logitech', 18.00],
    ['Archivador A4',        'Oficina',      35, 10, 'unidad', 'Leitz',     3.80],
    ['Guantes nitrilo (100)','Seguridad',     4,  5, 'caja',   'Ansell',   12.00],
    ['Gel hidroalcohólico',  'Limpieza',      9, 10, 'unidad', 'Diversey',  6.50],
];

foreach ($inventario as [$nom, $cat, $cant, $min, $uni, $prov, $precio]) {
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO inventario (nombre,categoria,cantidad,stock_minimo,unidad,proveedor,precio_unitario) VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('ssiissd', $nom, $cat, $cant, $min, $uni, $prov, $precio);
    $stmt->execute();
    if ($conn->error) {
        $err[] = "Inventario $nom: " . $conn->error;
    } else {
        $ok[] = "Artículo <b>$nom</b> añadido";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>GestInt — Instalador</title>
    <style>
        body  { font-family: sans-serif; max-width: 640px; margin: 50px auto; padding: 0 20px; }
        h1    { color: #1a3a5c; margin-bottom: 24px; }
        .ok   { background: #f0fdf4; border-left: 4px solid #16a34a; padding: 8px 14px; margin: 5px 0; border-radius: 4px; font-size: 14px; }
        .err  { background: #fef2f2; border-left: 4px solid #dc2626; padding: 8px 14px; margin: 5px 0; border-radius: 4px; font-size: 14px; }
        .btn  { display:inline-block; margin-top:24px; padding:12px 28px; background:#2563a8; color:#fff; border-radius:6px; text-decoration:none; font-weight:600; }
        .warn { background:#fffbeb; border-left:4px solid #d97706; padding:12px 14px; margin-top:20px; border-radius:4px; font-size:13px; }
        table { width:100%; border-collapse:collapse; margin-top:20px; font-size:13px; }
        th    { background:#1a3a5c; color:#fff; padding:8px 12px; text-align:left; }
        td    { padding:7px 12px; border-bottom:1px solid #e2e8f0; }
        tr:nth-child(even) td { background:#f8fafc; }
    </style>
</head>
<body>
    <h1>GestInt — Instalador</h1>

    <?php foreach ($ok as $m): ?>
        <div class="ok">✓ <?= $m ?></div>
    <?php endforeach; ?>
    <?php foreach ($err as $m): ?>
        <div class="err">✗ <?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>

    <?php if (empty($err)): ?>
        <div class="warn">
            ⚠ <strong>Borra este archivo</strong> una vez hayas entrado al sistema.
        </div>

        <h2 style="margin-top:28px;color:#1a3a5c;">Usuarios creados</h2>
        <table>
            <tr><th>Usuario</th><th>Contraseña</th><th>Rol</th><th>Perfil</th></tr>
            <tr><td>admin</td> <td>Admin1234</td>  <td>Administrador</td><td>Cuenta general de administración</td></tr>
            <tr><td>pedro</td> <td>Pedro1234</td>  <td>Administrador</td><td>Sysadmin — Informática</td></tr>
            <tr><td>maria</td> <td>Maria1234</td>  <td>Resp. RRHH</td>   <td>RRHH — gestión de empleados</td></tr>
            <tr><td>carlos</td><td>Carlos1234</td> <td>Resp. Almacén</td><td>Almacén — inventario y pedidos</td></tr>
            <tr><td>laura</td> <td>Laura1234</td>  <td>Empleado</td>     <td>Administración — solo lectura</td></tr>
            <tr><td>ana</td>   <td>Ana12345</td>   <td>Empleado</td>     <td>Ventas — solo lectura</td></tr>
        </table>

        <a href="login.php" class="btn">Ir al login →</a>
    <?php else: ?>
        <div class="err" style="margin-top:16px;">Hay errores. Revisa la configuración de includes/db.php.</div>
    <?php endif; ?>
</body>
</html>
