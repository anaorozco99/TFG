<?php
$host = getenv('DB_HOST') ?: 'localhost';
$conn = new mysqli($host, 'root', '', 'gestint');
$conn->set_charset('utf8mb4');
if ($conn->connect_error) die('<p style="color:red">Error: ' . $conn->connect_error . '</p>');

$ok = []; $err = [];

// borrar todo si ya existe (en orden inverso de dependencia)
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$borrar = ['pedidos_ventas_lineas','pedidos_ventas','pedidos_lineas','pedidos',
           'logs_acceso','fichajes','justificantes','tickets','vacaciones','clientes','empleados','inventario','usuarios'];
foreach ($borrar as $t) {
    $conn->query("DROP TABLE IF EXISTS `$t`");
}
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
$ok[] = "Base de datos limpiada";

$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$tablas = [
"usuarios" => "CREATE TABLE IF NOT EXISTS usuarios (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(80) NOT NULL, apellidos VARCHAR(100) NOT NULL DEFAULT '', usuario VARCHAR(50) NOT NULL UNIQUE, email VARCHAR(120) NOT NULL, password VARCHAR(255) NOT NULL, rol ENUM('admin','director','rrhh','almacen','empleado','soporte') NOT NULL DEFAULT 'empleado', activo TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",

"empleados" => "CREATE TABLE IF NOT EXISTS empleados (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_usuario INT UNSIGNED NOT NULL, nombre VARCHAR(80) NOT NULL, apellidos VARCHAR(100) NOT NULL, dni CHAR(9) NOT NULL UNIQUE, email VARCHAR(120) NOT NULL, telefono VARCHAR(20) DEFAULT NULL, departamento VARCHAR(60) NOT NULL, cargo VARCHAR(80) DEFAULT NULL, fecha_alta DATE NOT NULL, salario DECIMAL(10,2) DEFAULT 0.00, activo TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_emp_usr FOREIGN KEY (id_usuario) REFERENCES usuarios(id)) ENGINE=InnoDB",

"inventario" => "CREATE TABLE IF NOT EXISTS inventario (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(150) NOT NULL UNIQUE, categoria VARCHAR(60) NOT NULL DEFAULT 'Otros', descripcion TEXT DEFAULT NULL, cantidad INT NOT NULL DEFAULT 0, stock_minimo INT NOT NULL DEFAULT 5, unidad VARCHAR(30) NOT NULL DEFAULT 'unidad', proveedor VARCHAR(100) DEFAULT NULL, precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00, fecha_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",

"pedidos" => "CREATE TABLE IF NOT EXISTS pedidos (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id INT UNSIGNED NOT NULL, estado ENUM('pendiente','enviado','recibido') NOT NULL DEFAULT 'pendiente', fecha_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, fecha_llegada DATETIME GENERATED ALWAYS AS (DATE_ADD(fecha_pedido, INTERVAL 24 HOUR)) STORED, notas TEXT DEFAULT NULL, CONSTRAINT fk_ped_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id)) ENGINE=InnoDB",

"pedidos_lineas" => "CREATE TABLE IF NOT EXISTS pedidos_lineas (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pedido_id INT UNSIGNED NOT NULL, inventario_id INT UNSIGNED NOT NULL, nombre VARCHAR(150) NOT NULL, cantidad INT NOT NULL DEFAULT 1, CONSTRAINT fk_lin_ped FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE, CONSTRAINT fk_lin_inv FOREIGN KEY (inventario_id) REFERENCES inventario(id)) ENGINE=InnoDB",

"logs_acceso" => "CREATE TABLE IF NOT EXISTS logs_acceso (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id INT UNSIGNED NOT NULL, ip VARCHAR(45) NOT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_log_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id)) ENGINE=InnoDB",

"clientes" => "CREATE TABLE IF NOT EXISTS clientes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL UNIQUE, empresa VARCHAR(100) DEFAULT NULL, email VARCHAR(120) NOT NULL, telefono VARCHAR(20) DEFAULT NULL, direccion VARCHAR(200) DEFAULT NULL, ciudad VARCHAR(80) DEFAULT NULL, activo TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",

"pedidos_ventas" => "CREATE TABLE IF NOT EXISTS pedidos_ventas (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cliente_id INT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL, estado ENUM('pendiente','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente', fecha_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, fecha_entrega DATETIME GENERATED ALWAYS AS (DATE_ADD(fecha_pedido, INTERVAL 48 HOUR)) STORED, notas TEXT DEFAULT NULL, total DECIMAL(10,2) NOT NULL DEFAULT 0.00, CONSTRAINT fk_pv_cli FOREIGN KEY (cliente_id) REFERENCES clientes(id), CONSTRAINT fk_pv_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id)) ENGINE=InnoDB",

"vacaciones" => "CREATE TABLE IF NOT EXISTS vacaciones (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id INT UNSIGNED NOT NULL, fecha_ini DATE NOT NULL, fecha_fin DATE NOT NULL, dias INT NOT NULL DEFAULT 1, motivo VARCHAR(200) DEFAULT NULL, estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente', respuesta VARCHAR(200) DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_vac_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id)) ENGINE=InnoDB",

"tickets" => "CREATE TABLE IF NOT EXISTS tickets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, email VARCHAR(120) NOT NULL, asunto VARCHAR(200) NOT NULL, descripcion TEXT NOT NULL, estado ENUM('abierto','en_proceso','resuelto') NOT NULL DEFAULT 'abierto', usuario_id INT UNSIGNED DEFAULT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_tick_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB",

"fichajes" => "CREATE TABLE IF NOT EXISTS fichajes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id INT UNSIGNED NOT NULL, tipo ENUM('entrada','salida') NOT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_fich_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id)) ENGINE=InnoDB",

"justificantes" => "CREATE TABLE IF NOT EXISTS justificantes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id INT UNSIGNED NOT NULL, descripcion VARCHAR(200) NOT NULL, archivo VARCHAR(255) NOT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_just_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id)) ENGINE=InnoDB",

"pedidos_ventas_lineas" => "CREATE TABLE IF NOT EXISTS pedidos_ventas_lineas (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pedido_id INT UNSIGNED NOT NULL, inventario_id INT UNSIGNED NOT NULL, nombre VARCHAR(150) NOT NULL, cantidad INT NOT NULL DEFAULT 1, precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00, CONSTRAINT fk_pvl_ped FOREIGN KEY (pedido_id) REFERENCES pedidos_ventas(id) ON DELETE CASCADE, CONSTRAINT fk_pvl_inv FOREIGN KEY (inventario_id) REFERENCES inventario(id)) ENGINE=InnoDB",
];

foreach ($tablas as $n => $sql) {
    $conn->query($sql);
    if ($conn->error) $err[] = "Tabla $n: " . $conn->error;
    else $ok[] = "Tabla <b>$n</b> creada";
}
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// usuarios y empleados del seed
$datos = [
    ['Michael','Scott',  'michael','michael.scott@dundermifflin.com', 'Michael1234','director',['12345678Z','michael.scott@dundermifflin.com', '570100001','Dirección',   'Director Regional',         '2005-03-24',65000]],
    ['Dwight', 'Schrute','dwight', 'dwight.schrute@dundermifflin.com','Dwight1234', 'empleado',['23456789D','dwight.schrute@dundermifflin.com','570100002','Ventas',      'Asistente del Director',    '2005-03-24',42000]],
    ['Jim',    'Halpert','jim',    'jim.halpert@dundermifflin.com',    'Jim1234!!',  'empleado',['34567890V','jim.halpert@dundermifflin.com',   '570100003','Ventas',      'Comercial',                 '2005-03-24',40000]],
    ['Pam',    'Beesly', 'pam',   'pam.beesly@dundermifflin.com',     'Pam1234!!',  'rrhh',   ['45678901G','pam.beesly@dundermifflin.com',    '570100004','Recepción',   'Recepcionista',             '2005-03-24',32000]],
    ['Toby',   'Flenderson','toby','toby.flenderson@dundermifflin.com','Toby1234!',  'rrhh',   ['56789012B','toby.flenderson@dundermifflin.com','570100005','RRHH',        'Técnico de RRHH',           '2005-03-24',38000]],
    ['Angela', 'Martin', 'angela','angela.martin@dundermifflin.com',   'Angela1234', 'empleado',['67890123B','angela.martin@dundermifflin.com', '570100006','Contabilidad','Jefa de Contabilidad',      '2005-03-24',41000]],
    ['Oscar',  'Martinez','oscar','oscar.martinez@dundermifflin.com',  'Oscar1234!', 'empleado',['78901234X','oscar.martinez@dundermifflin.com','570100007','Contabilidad','Contable',                  '2005-03-24',39000]],
    ['Kevin',  'Malone', 'kevin', 'kevin.malone@dundermifflin.com',    'Kevin1234!', 'empleado',['89012345E','kevin.malone@dundermifflin.com',  '570100008','Contabilidad','Contable',                  '2005-03-24',36000]],
    ['Stanley','Hudson', 'stanley','stanley.hudson@dundermifflin.com', 'Stanley1234','empleado',['90123456A','stanley.hudson@dundermifflin.com','570100009','Ventas',      'Comercial',                 '2005-03-24',40000]],
    ['Phyllis','Vance',  'phyllis','phyllis.vance@dundermifflin.com',  'Phyllis1234','empleado',['12345679S','phyllis.vance@dundermifflin.com', '570100010','Ventas',      'Comercial',                 '2005-03-24',39000]],
    ['Andy',   'Bernard','andy',  'andy.bernard@dundermifflin.com',    'Andy1234!!', 'empleado',['11223344B','andy.bernard@dundermifflin.com',  '570100011','Ventas',      'Comercial',                 '2007-01-15',39000]],
    ['Darryl', 'Philbin','darryl','darryl.philbin@dundermifflin.com',  'Darryl1234', 'almacen', ['22334455Y','darryl.philbin@dundermifflin.com','570100012','Almacén',     'Jefe de Almacén',           '2005-03-24',37000]],
    ['Roy',    'Anderson','roy',  'roy.anderson@dundermifflin.com',    'Roy12345!',  'almacen', ['33445566R','roy.anderson@dundermifflin.com',  '570100013','Almacén',     'Auxiliar de Almacén',       '2005-03-24',32000]],
    ['Ryan',   'Howard', 'ryan',  'ryan.howard@dundermifflin.com',     'Ryan1234!',  'empleado',['44556677T','ryan.howard@dundermifflin.com',   '570100014','Ventas',      'Comercial',                 '2007-01-15',38000]],
    ['Ana',    'Orozco Asensio','ana','anaorozcoasensio@gmail.com',    'Ana1234!',   'soporte', ['98765432A','anaorozcoasensio@gmail.com',       '570100015','IT',          'Técnico de Soporte IT',     '2024-09-01',30000]],
    ['Alberto','Sánchez',      'alberto','alberto.sanchez@dundermifflin.com','Alberto1234!','soporte',['87654321B','alberto.sanchez@dundermifflin.com','570100016','IT','Técnico de Soporte IT','2023-06-01',31000]],
];

foreach ($datos as [$nom,$ape,$usr,$email,$pass,$rol,$emp]) {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $s = $conn->prepare("INSERT IGNORE INTO usuarios (nombre,apellidos,usuario,email,password,rol) VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssss',$nom,$ape,$usr,$email,$hash,$rol);
    $s->execute();
    if ($conn->error) { $err[] = "Usuario $usr: ".$conn->error; continue; }
    $ok[] = "Usuario <b>$usr</b> — $rol";
    $uid = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0];
    [$dni,$ee,$tel,$dep,$cargo,$fecha,$sal] = $emp;
    $se = $conn->prepare("INSERT IGNORE INTO empleados (id_usuario,nombre,apellidos,dni,email,telefono,departamento,cargo,fecha_alta,salario) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $se->bind_param('issssssssd',$uid,$nom,$ape,$dni,$ee,$tel,$dep,$cargo,$fecha,$sal);
    $se->execute();
    if ($conn->error) $err[] = "Empleado $nom: ".$conn->error;
    else $ok[] = "Empleado <b>$nom $ape</b> — DNI: $dni";
}

// Usuario "sistema": admin especial, solo se le puede cambiar la contraseña
$hash_sis = password_hash('Sistema2024!', PASSWORD_DEFAULT);
$s_sis = $conn->prepare("INSERT IGNORE INTO usuarios (nombre,apellidos,usuario,email,password,rol) VALUES ('Sistema','','sistema','sistema@dundermifflin.com',?,'admin')");
$s_sis->bind_param('s', $hash_sis);
$s_sis->execute();
$ok[] = "Usuario <b>sistema</b> (admin protegido) creado";

// inventario inicial
$inv = [
    ['Papel A4 80g (500 hojas)',       'Papel',      500,100,'paquete','Navigator',    4.20],
    ['Papel A3 80g (500 hojas)',       'Papel',      120, 30,'paquete','Navigator',    7.50],
    ['Papel reciclado A4 (500h)',      'Papel',      200, 50,'paquete','Steinbeis',    3.60],
    ['Sobres blancos C4 (caja 250)',   'Sobres',      40, 10,'caja',  'Liderpapel',  12.50],
    ['Sobres ventana DL (caja 500)',   'Sobres',      35, 10,'caja',  'Liderpapel',   9.90],
    ['Carpetas A4 azul (caja 50)',     'Carpetas',    60, 15,'caja',  'Leitz',       18.00],
    ['Archivadores A4 negro',          'Carpetas',    45, 10,'unidad','Leitz',        3.80],
    ['Bolígrafos azul BIC (caja)',     'Escritura',   25,  5,'caja',  'BIC',          8.50],
    ['Bolígrafos negro BIC (caja)',    'Escritura',   20,  5,'caja',  'BIC',          8.50],
    ['Rotuladores permanentes (12)',   'Escritura',   18,  5,'caja',  'Sharpie',     11.00],
    ['Tóner HP LaserJet negro',        'Consumibles',  6,  2,'unidad','HP',          49.00],
    ['Tóner HP color (pack 4)',        'Consumibles',  4,  2,'pack',  'HP',         139.00],
    ['Grapadora de mesa',              'Oficina',     12,  3,'unidad','Rapid',       14.00],
    ['Grapas 26/6 (caja 5000)',        'Oficina',     30, 10,'caja',  'Rapid',        3.20],
    ['Post-it 76x76 (pack 12)',        'Oficina',     50, 15,'pack',  '3M',          12.50],
    ['Celo 19mm x 33m (pack 10)',      'Oficina',     40, 10,'pack',  '3M',           9.80],
    ['Tijeras de oficina',             'Oficina',     10,  3,'unidad','Maped',        4.50],
];

foreach ($inv as [$n,$c,$cant,$min,$u,$prov,$p]) {
    $s = $conn->prepare("INSERT IGNORE INTO inventario (nombre,categoria,cantidad,stock_minimo,unidad,proveedor,precio_unitario) VALUES (?,?,?,?,?,?,?)");
    $s->bind_param('ssiissd',$n,$c,$cant,$min,$u,$prov,$p);
    $s->execute();
    if ($conn->error) $err[] = "Inventario $n: ".$conn->error;
    else $ok[] = "Artículo <b>$n</b> añadido";
}

// clientes de ejemplo
// Vance Refrigeration y Prince Family Paper salen en la serie.
// Lackawanna County es el condado real de Scranton, mencionado en varios episodios.
// Dunder Mifflin Infinity fue la web que montó Ryan en T4.
$clientes_seed = [
    ['Vance Refrigeration', 'Vance Refrigeration Corp.',  'bob.vance@vancerefrigeration.com','570200001','Calle Industrial 1', 'Scranton'],
    ['Prince Family Paper', 'Prince Family Paper Co.',    'info@princepaper.com',            '570200002','Av. Principal 45',   'Carbondale'],
    ['Lackawanna County',   'Condado de Lackawanna',      'compras@lackawanna.gov',          '570200003','Calle Gobierno 100', 'Scranton'],
    ['Dunmore High School', 'Instituto Dunmore',          'pedidos@dunmore.edu',             '570200004','Calle Escolar 50',   'Dunmore'],
];

foreach ($clientes_seed as [$nom,$emp,$email,$tel,$dir,$ciu]) {
    $s = $conn->prepare("INSERT IGNORE INTO clientes (nombre,empresa,email,telefono,direccion,ciudad) VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssss',$nom,$emp,$email,$tel,$dir,$ciu);
    $s->execute();
    if ($conn->error) $err[] = "Cliente $nom: ".$conn->error;
    else $ok[] = "Cliente <b>$nom</b> añadido";
}

// pedidos de stock de ejemplo (con fechas 2026)
$pedidos_stock_seed = [
    ['darryl','recibido','2026-01-15 09:30:00','Pedido trimestral de papel',[
        ['Papel A4 80g (500 hojas)',20],['Papel A3 80g (500 hojas)',5],['Sobres blancos C4 (caja 250)',3],
    ]],
    ['darryl','recibido','2026-03-22 11:00:00','Reposición consumibles',[
        ['Tóner HP LaserJet negro',3],['Post-it 76x76 (pack 12)',10],['Celo 19mm x 33m (pack 10)',8],
    ]],
    ['darryl','pendiente','2026-06-02 08:45:00','Urgente antes de fin de mes',[
        ['Bolígrafos azul BIC (caja)',5],['Bolígrafos negro BIC (caja)',3],['Grapas 26/6 (caja 5000)',4],
    ]],
];
foreach ($pedidos_stock_seed as [$usr,$est,$fech,$notas,$lineas]) {
    $uid = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0] ?? null;
    if (!$uid) continue;
    $sp = $conn->prepare("INSERT INTO pedidos (usuario_id,estado,fecha_pedido,notas) VALUES (?,?,?,?)");
    $sp->bind_param('isss',$uid,$est,$fech,$notas);
    $sp->execute();
    $pid = $conn->insert_id;
    foreach ($lineas as [$nom,$cant]) {
        $iid = $conn->query("SELECT id FROM inventario WHERE nombre='$nom' LIMIT 1")->fetch_row()[0] ?? null;
        if (!$iid) continue;
        $sl = $conn->prepare("INSERT INTO pedidos_lineas (pedido_id,inventario_id,nombre,cantidad) VALUES (?,?,?,?)");
        $sl->bind_param('iisi',$pid,$iid,$nom,$cant);
        $sl->execute();
    }
    $ok[] = "Pedido stock #$pid ($est)";
}

// pedidos de ventas de ejemplo (con fechas 2026)
$pedidos_ventas_seed = [
    ['jim','Vance Refrigeration','entregado','2026-02-10 10:00:00','Pedido mensual habitual',[
        ['Papel A4 80g (500 hojas)',10,4.20],['Sobres blancos C4 (caja 250)',2,12.50],
    ]],
    ['phyllis','Lackawanna County','entregado','2026-04-05 09:30:00','',[
        ['Archivadores A4 negro',20,3.80],['Carpetas A4 azul (caja 50)',5,18.00],
    ]],
    ['dwight','Prince Family Paper','enviado','2026-05-28 14:15:00','Segundo pedido del año',[
        ['Papel A4 80g (500 hojas)',50,4.20],['Papel reciclado A4 (500h)',20,3.60],
    ]],
    ['jim','Dunmore High School','pendiente','2026-06-03 11:00:00','Para inicio de curso',[
        ['Bolígrafos azul BIC (caja)',30,8.50],['Papel A4 80g (500 hojas)',100,4.20],
    ]],
];
foreach ($pedidos_ventas_seed as [$usr,$cli,$est,$fech,$notas,$lineas]) {
    $uid = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0] ?? null;
    $cid = $conn->query("SELECT id FROM clientes WHERE nombre='$cli' LIMIT 1")->fetch_row()[0] ?? null;
    if (!$uid || !$cid) continue;
    $total = array_sum(array_map(fn($l) => $l[1] * $l[2], $lineas));
    $sp = $conn->prepare("INSERT INTO pedidos_ventas (cliente_id,usuario_id,estado,fecha_pedido,notas,total) VALUES (?,?,?,?,?,?)");
    $sp->bind_param('iisssd',$cid,$uid,$est,$fech,$notas,$total);
    $sp->execute();
    $pid = $conn->insert_id;
    foreach ($lineas as [$nom,$cant,$precio]) {
        $iid = $conn->query("SELECT id FROM inventario WHERE nombre='$nom' LIMIT 1")->fetch_row()[0] ?? null;
        if (!$iid) continue;
        $sl = $conn->prepare("INSERT INTO pedidos_ventas_lineas (pedido_id,inventario_id,nombre,cantidad,precio_unitario) VALUES (?,?,?,?,?)");
        $sl->bind_param('iisid',$pid,$iid,$nom,$cant,$precio);
        $sl->execute();
    }
    $ok[] = "Pedido cliente #$pid → $cli ($est)";
}


// ---- Clientes adicionales ----
$clientes_extra = [
    ['Michael Davis',    'Davis & Sons',          'mdavis@davissons.com',       '570200005','Calle Mayor 12',    'Scranton'],
    ['Sarah Connor',     'Connor Industries',     'sarah@connorind.com',        '570200006','Av. del Sol 34',    'Allentown'],
    ['Robert Mifflin',   'Mifflin & Partners',    'rmifflin@mifflinp.com',      '570200007','Calle Comercial 5', 'Wilkes-Barre'],
    ['Karen Filippelli', 'Utica Office Supply',   'karen@uticaoffice.com',      '570200008','Park Ave 100',      'Utica'],
    ['Todd Packer',      'Packer Sales LLC',      'tpacker@packersales.com',    '570200009','Industrial St 22',  'Harrisburg'],
];
foreach ($clientes_extra as [$nom,$emp,$email,$tel,$dir,$ciu]) {
    $s = $conn->prepare("INSERT IGNORE INTO clientes (nombre,empresa,email,telefono,direccion,ciudad) VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssss',$nom,$emp,$email,$tel,$dir,$ciu);
    $s->execute();
    if (!$conn->error) $ok[] = "Cliente extra <b>$nom</b>";
}

// ---- Pedidos de stock adicionales ----
$pedidos_stock_extra = [
    ['darryl','recibido','2026-02-10 08:00:00','Reposición papel Q1',[
        ['Papel A4 80g (500 hojas)',30],['Papel A3 80g (500 hojas)',10],
    ]],
    ['roy','recibido','2026-03-05 09:30:00','Material escritura y grapas',[
        ['Bolígrafos azul BIC (caja)',8],['Grapas 26/6 (caja 5000)',6],['Post-it 76x76 (pack 12)',5],
    ]],
    ['darryl','recibido','2026-04-18 10:00:00','Consumibles impresoras',[
        ['Tóner HP LaserJet negro',4],['Tóner HP color (pack 4)',2],
    ]],
    ['roy','pendiente','2026-06-01 08:00:00','Pedido mensual junio',[
        ['Papel A4 80g (500 hojas)',20],['Sobres blancos C4 (caja 250)',5],['Celo 19mm x 33m (pack 10)',4],
    ]],
];
foreach ($pedidos_stock_extra as [$usr,$est,$fech,$notas,$lineas]) {
    $uid_s = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0] ?? null;
    if (!$uid_s) continue;
    $sp = $conn->prepare("INSERT INTO pedidos (usuario_id,estado,fecha_pedido,notas) VALUES (?,?,?,?)");
    $sp->bind_param('isss',$uid_s,$est,$fech,$notas);
    $sp->execute();
    $pid = $conn->insert_id;
    foreach ($lineas as [$nom,$cant]) {
        $iid = $conn->query("SELECT id FROM inventario WHERE nombre='$nom' LIMIT 1")->fetch_row()[0] ?? null;
        if (!$iid) continue;
        $sl = $conn->prepare("INSERT INTO pedidos_lineas (pedido_id,inventario_id,nombre,cantidad) VALUES (?,?,?,?)");
        $sl->bind_param('iisi',$pid,$iid,$nom,$cant);
        $sl->execute();
    }
    $ok[] = "Pedido stock extra #$pid ($est)";
}

// ---- Pedidos de clientes adicionales ----
$pedidos_ventas_extra = [
    ['stanley','Karen Filippelli','entregado','2026-01-20 10:00:00','Pedido inicial de año',[
        ['Papel A4 80g (500 hojas)',40,4.20],['Sobres ventana DL (caja 500)',3,9.90],
    ]],
    ['jim','Michael Davis','enviado','2026-03-15 11:00:00','Segundo pedido trimestre',[
        ['Carpetas A4 azul (caja 50)',8,18.00],['Archivadores A4 negro',15,3.80],
    ]],
    ['dwight','Robert Mifflin','pendiente','2026-05-10 09:00:00','Material para nueva oficina',[
        ['Bolígrafos azul BIC (caja)',20,8.50],['Bolígrafos negro BIC (caja)',10,8.50],['Papel A4 80g (500 hojas)',50,4.20],
    ]],
    ['phyllis','Todd Packer','entregado','2026-04-25 14:00:00','',[
        ['Grapadora de mesa',5,14.00],['Grapas 26/6 (caja 5000)',10,3.20],['Tijeras de oficina',4,4.50],
    ]],
    ['andy','Vance Refrigeration','pendiente','2026-06-02 10:30:00','Pedido urgente fin de semestre',[
        ['Papel reciclado A4 (500h)',30,3.60],['Post-it 76x76 (pack 12)',8,12.50],
    ]],
];
foreach ($pedidos_ventas_extra as [$usr,$cli,$est,$fech,$notas,$lineas]) {
    $uid_v = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0] ?? null;
    $cid_v = $conn->query("SELECT id FROM clientes WHERE nombre='$cli' LIMIT 1")->fetch_row()[0] ?? null;
    if (!$uid_v || !$cid_v) continue;
    $total_v = array_sum(array_map(fn($l) => $l[1] * $l[2], $lineas));
    $sp = $conn->prepare("INSERT INTO pedidos_ventas (cliente_id,usuario_id,estado,fecha_pedido,notas,total) VALUES (?,?,?,?,?,?)");
    $sp->bind_param('iisssd',$cid_v,$uid_v,$est,$fech,$notas,$total_v);
    $sp->execute();
    $pid = $conn->insert_id;
    foreach ($lineas as [$nom,$cant,$precio]) {
        $iid = $conn->query("SELECT id FROM inventario WHERE nombre='$nom' LIMIT 1")->fetch_row()[0] ?? null;
        if (!$iid) continue;
        $sl = $conn->prepare("INSERT INTO pedidos_ventas_lineas (pedido_id,inventario_id,nombre,cantidad,precio_unitario) VALUES (?,?,?,?,?)");
        $sl->bind_param('iisid',$pid,$iid,$nom,$cant,$precio);
        $sl->execute();
    }
    $ok[] = "Pedido cliente extra #$pid → $cli ($est)";
}

// ---- Fichajes de ejemplo (últimos 2 meses, varios empleados) ----
$fichajes_seed = [
    // [usuario, fecha_entrada, fecha_salida]
    ['pam',     '2026-04-07 08:05:00','2026-04-07 17:02:00'],
    ['pam',     '2026-04-08 08:10:00','2026-04-08 17:15:00'],
    ['pam',     '2026-05-05 08:00:00','2026-05-05 17:00:00'],
    ['pam',     '2026-05-06 08:03:00','2026-05-06 17:08:00'],
    ['pam',     '2026-06-02 07:58:00','2026-06-02 17:01:00'],
    ['pam',     '2026-06-03 08:05:00','2026-06-03 17:05:00'],
    ['jim',     '2026-05-20 09:00:00','2026-05-20 18:00:00'],
    ['jim',     '2026-05-21 09:05:00','2026-05-21 18:10:00'],
    ['jim',     '2026-06-02 08:55:00','2026-06-02 18:00:00'],
    ['dwight',  '2026-05-15 07:30:00','2026-05-15 16:30:00'],
    ['dwight',  '2026-06-03 07:25:00','2026-06-03 16:25:00'],
    ['darryl',  '2026-05-28 08:00:00','2026-05-28 17:00:00'],
    ['darryl',  '2026-06-02 08:00:00','2026-06-02 17:00:00'],
    ['ana',     '2026-06-04 09:00:00','2026-06-04 18:00:00'],
    ['ana',     '2026-06-05 09:00:00',null],
];
foreach ($fichajes_seed as [$usr,$entrada,$salida]) {
    $uid_f = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0] ?? null;
    if (!$uid_f) continue;
    $ins = $conn->prepare("INSERT INTO fichajes (usuario_id,tipo,fecha) VALUES (?,'entrada',?)");
    $ins->bind_param('is',$uid_f,$entrada);
    $ins->execute();
    if ($salida) {
        $ins2 = $conn->prepare("INSERT INTO fichajes (usuario_id,tipo,fecha) VALUES (?,'salida',?)");
        $ins2->bind_param('is',$uid_f,$salida);
        $ins2->execute();
    }
}
$ok[] = "Fichajes de ejemplo añadidos";


// ---- Logs de acceso — IPs por departamento, inserts en orden cronológico ----
// Dirección: 192.168.1.1 | RRHH: .10-.19 | Ventas: .30-.39 | Contabilidad: .40-.49 | Almacén: .50-.59 | IT: .60-.69
$logs_seed = [
    // ID 1 — mayo semana 3
    ['michael', '192.168.1.1',  '2026-05-20 07:58:00'],
    ['pam',     '192.168.1.10', '2026-05-20 08:02:00'],
    ['toby',    '192.168.1.11', '2026-05-20 08:05:00'],
    ['darryl',  '192.168.1.50', '2026-05-20 07:50:00'],
    ['roy',     '192.168.1.51', '2026-05-20 07:55:00'],
    ['dwight',  '192.168.1.30', '2026-05-20 07:28:00'],
    ['jim',     '192.168.1.31', '2026-05-20 08:58:00'],
    ['stanley', '192.168.1.32', '2026-05-20 08:43:00'],
    ['angela',  '192.168.1.40', '2026-05-20 08:10:00'],
    ['oscar',   '192.168.1.41', '2026-05-20 08:15:00'],
    // ID 11 — mayo semana 3 (día 21)
    ['pam',     '192.168.1.10', '2026-05-21 08:01:00'],
    ['toby',    '192.168.1.11', '2026-05-21 08:03:00'],
    ['darryl',  '192.168.1.50', '2026-05-21 07:52:00'],
    ['jim',     '192.168.1.31', '2026-05-21 09:05:00'],
    ['dwight',  '192.168.1.30', '2026-05-21 07:30:00'],
    // ID 16 — mayo semana 4
    ['michael', '192.168.1.1',  '2026-05-26 07:55:00'],
    ['pam',     '192.168.1.10', '2026-05-26 08:00:00'],
    ['darryl',  '192.168.1.50', '2026-05-26 07:48:00'],
    ['roy',     '192.168.1.51', '2026-05-26 07:58:00'],
    ['stanley', '192.168.1.32', '2026-05-26 08:40:00'],
    ['phyllis', '192.168.1.33', '2026-05-26 08:28:00'],
    // ID 22 — junio semana 1
    ['michael', '192.168.1.1',  '2026-06-02 07:57:00'],
    ['pam',     '192.168.1.10', '2026-06-02 07:59:00'],
    ['toby',    '192.168.1.11', '2026-06-02 08:02:00'],
    ['darryl',  '192.168.1.50', '2026-06-02 07:50:00'],
    ['ana',     '192.168.1.60', '2026-06-02 08:58:00'],
    ['jim',     '192.168.1.31', '2026-06-02 08:53:00'],
    ['oscar',   '192.168.1.41', '2026-06-02 08:12:00'],
    // ID 29 — junio día 3
    ['pam',     '192.168.1.10', '2026-06-03 08:03:00'],
    ['toby',    '192.168.1.11', '2026-06-03 08:05:00'],
    ['ana',     '192.168.1.60', '2026-06-03 09:03:00'],
    ['alberto', '192.168.1.61', '2026-06-03 09:00:00'],
    ['jim',     '192.168.1.31', '2026-06-03 09:08:00'],
    ['dwight',  '192.168.1.30', '2026-06-03 07:23:00'],
    // ID 35 — junio día 4
    ['michael', '192.168.1.1',  '2026-06-04 07:58:00'],
    ['pam',     '192.168.1.10', '2026-06-04 08:00:00'],
    ['ana',     '192.168.1.60', '2026-06-04 08:59:00'],
    ['alberto', '192.168.1.61', '2026-06-04 09:02:00'],
    ['oscar',   '192.168.1.41', '2026-06-04 08:18:00'],
    // ID 40 — junio día 5
    ['pam',     '192.168.1.10', '2026-06-05 07:56:00'],
    ['ana',     '192.168.1.60', '2026-06-05 09:01:00'],
    ['jim',     '192.168.1.31', '2026-06-05 08:55:00'],
];
// Insertar en orden para que los IDs sean secuenciales
foreach ($logs_seed as [$usr,$ip,$fecha]) {
    $uid_l = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0] ?? null;
    if (!$uid_l) continue;
    $ins = $conn->prepare("INSERT INTO logs_acceso (usuario_id,ip,fecha) VALUES (?,?,?)");
    $ins->bind_param('iss',$uid_l,$ip,$fecha);
    $ins->execute();
}
$ok[] = "Logs de acceso (" . count($logs_seed) . " registros, IPs por departamento) añadidos";

// ---- Justificantes de ejemplo (archivos placeholder) ----
$just_seed = [
    ['pam',     'Baja médica 15/04/2026',     'placeholder_pam_baja.pdf',     '2026-04-15 10:00:00'],
    ['pam',     'Asistencia médica 03/05/2026','placeholder_pam_medico.pdf',   '2026-05-03 11:00:00'],
    ['jim',     'Día personal 20/03/2026',     'placeholder_jim_personal.pdf', '2026-03-20 09:00:00'],
    ['dwight',  'Baja por gripe 08/02/2026',   'placeholder_dwight_gripe.pdf', '2026-02-08 08:30:00'],
    ['stanley', 'Consulta médica 10/04/2026',  'placeholder_stanley_med.pdf',  '2026-04-10 09:30:00'],
    ['kevin',   'Urgencias 25/03/2026',        'placeholder_kevin_urgencias.pdf','2026-03-25 15:00:00'],
    ['angela',  'Dentista 12/05/2026',         'placeholder_angela_dentista.pdf','2026-05-12 10:00:00'],
    ['toby',    'Asistencia médica 02/06/2026','placeholder_toby_medico.pdf',  '2026-06-02 08:45:00'],
];
foreach ($just_seed as [$usr,$desc,$archivo,$fecha]) {
    $uid_j = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0] ?? null;
    if (!$uid_j) continue;
    $ins = $conn->prepare("INSERT INTO justificantes (usuario_id,descripcion,archivo,fecha) VALUES (?,?,?,?)");
    $ins->bind_param('isss',$uid_j,$desc,$archivo,$fecha);
    $ins->execute();
}
$ok[] = "Justificantes de ejemplo añadidos";

// ---- Más fichajes (semanas completas para varios empleados) ----
$mas_fichajes = [
    // Semana 2 mayo
    ['pam',    '2026-05-12 08:05:00','2026-05-12 17:10:00'],
    ['pam',    '2026-05-13 08:02:00','2026-05-13 17:05:00'],
    ['pam',    '2026-05-14 08:10:00','2026-05-14 17:00:00'],
    ['jim',    '2026-05-12 09:00:00','2026-05-12 18:15:00'],
    ['jim',    '2026-05-13 09:05:00','2026-05-13 17:50:00'],
    ['toby',   '2026-05-12 08:00:00','2026-05-12 17:00:00'],
    ['toby',   '2026-05-13 08:05:00','2026-05-13 17:05:00'],
    ['toby',   '2026-05-14 08:10:00','2026-05-14 17:00:00'],
    ['stanley','2026-05-12 08:45:00','2026-05-12 17:45:00'],
    ['stanley','2026-05-13 08:50:00','2026-05-13 17:50:00'],
    // Semana última mayo
    ['pam',    '2026-05-26 08:00:00','2026-05-26 17:00:00'],
    ['pam',    '2026-05-27 08:05:00','2026-05-27 17:10:00'],
    ['pam',    '2026-05-28 08:00:00','2026-05-28 16:30:00'],
    ['darryl', '2026-05-26 07:50:00','2026-05-26 16:50:00'],
    ['darryl', '2026-05-27 07:55:00','2026-05-27 16:55:00'],
    ['roy',    '2026-05-26 08:00:00','2026-05-26 17:00:00'],
    ['roy',    '2026-05-27 08:05:00','2026-05-27 17:05:00'],
    // Primera semana junio
    ['toby',   '2026-06-02 08:00:00','2026-06-02 17:00:00'],
    ['toby',   '2026-06-03 08:05:00','2026-06-03 17:05:00'],
    ['toby',   '2026-06-04 08:02:00','2026-06-04 17:02:00'],
    ['jim',    '2026-06-03 09:00:00','2026-06-03 18:05:00'],
    ['jim',    '2026-06-04 09:10:00','2026-06-04 18:20:00'],
    ['stanley','2026-06-02 08:40:00','2026-06-02 17:40:00'],
    ['phyllis','2026-06-02 08:30:00','2026-06-02 17:30:00'],
    ['phyllis','2026-06-03 08:25:00','2026-06-03 17:25:00'],
    ['oscar',  '2026-06-02 08:15:00','2026-06-02 17:15:00'],
    ['oscar',  '2026-06-03 08:20:00','2026-06-03 17:20:00'],
];
foreach ($mas_fichajes as [$usr,$entrada,$salida]) {
    $uid_mf = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0] ?? null;
    if (!$uid_mf) continue;
    $i1 = $conn->prepare("INSERT INTO fichajes (usuario_id,tipo,fecha) VALUES (?,'entrada',?)");
    $i1->bind_param('is',$uid_mf,$entrada); $i1->execute();
    $i2 = $conn->prepare("INSERT INTO fichajes (usuario_id,tipo,fecha) VALUES (?,'salida',?)");
    $i2->bind_param('is',$uid_mf,$salida); $i2->execute();
}
$ok[] = "Fichajes adicionales añadidos";

// ---- Vacaciones de ejemplo ----
$vacaciones_seed = [
    ['pam',    '2026-07-01','2026-07-15',15,'Vacaciones verano',       'aprobada', 'Aprobado por RRHH'],
    ['jim',    '2026-08-01','2026-08-07', 7,'Viaje familiar',          'aprobada', 'Aprobado'],
    ['dwight', '2026-07-14','2026-07-18', 5,'Granja de remolacha',     'pendiente',''],
    ['stanley','2026-09-01','2026-09-05', 5,'Asunto personal',         'pendiente',''],
    ['kevin',  '2026-06-20','2026-06-25', 6,'Semana de descanso',      'rechazada','No hay cobertura en esa semana'],
];
foreach ($vacaciones_seed as [$usr,$ini,$fin,$dias,$mot,$est,$resp]) {
    $uid_v2 = $conn->query("SELECT id FROM usuarios WHERE usuario='$usr' LIMIT 1")->fetch_row()[0] ?? null;
    if (!$uid_v2) continue;
    $ins = $conn->prepare("INSERT INTO vacaciones (usuario_id,fecha_ini,fecha_fin,dias,motivo,estado,respuesta) VALUES (?,?,?,?,?,?,?)");
    $ins->bind_param('issssss',$uid_v2,$ini,$fin,$dias,$mot,$est,$resp);
    $ins->execute();
}
$ok[] = "Vacaciones de ejemplo añadidas";

// ---- Tickets de ejemplo ----
$tickets_seed = [
    ['Pam Beesly',    'pam.beesly@dundermifflin.com',   'No puedo acceder al sistema',   'Desde ayer no me deja entrar, me dice contraseña incorrecta.', 'resuelto', 'ana'],
    ['Kevin Malone',  'kevin.malone@dundermifflin.com',  'La impresora no funciona',      'La impresora del tercer piso no imprime desde el lunes.', 'en_proceso', 'ana'],
    ['Stanley Hudson','stanley.hudson@dundermifflin.com','Pantalla muy lenta',            'El ordenador tarda 10 minutos en arrancar.', 'abierto', null],
    ['Andy Bernard',  'andy.bernard@dundermifflin.com',  'Error al subir justificante',   'Al intentar subir un PDF me sale error 500.', 'resuelto', 'alberto'],
];
foreach ($tickets_seed as [$nom,$email,$asunto,$desc,$est,$agente]) {
    $uid_a = null;
    if ($agente) $uid_a = $conn->query("SELECT id FROM usuarios WHERE usuario='$agente' LIMIT 1")->fetch_row()[0] ?? null;
    $ins = $conn->prepare("INSERT INTO tickets (nombre,email,asunto,descripcion,estado,usuario_id) VALUES (?,?,?,?,?,?)");
    $ins->bind_param('sssssi',$nom,$email,$asunto,$desc,$est,$uid_a);
    $ins->execute();
}
$ok[] = "Tickets de ejemplo añadidos";

// usuarios de mysql por rol
$tablas_todas = ['usuarios','empleados','inventario','pedidos','pedidos_lineas','logs_acceso','clientes','pedidos_ventas','pedidos_ventas_lineas','fichajes','justificantes','tickets','vacaciones'];
$usuarios_mysql = [
    'login_gestint'    => ['Login2024!',    ['INSERT ON gestint.logs_acceso','INSERT ON gestint.tickets']],
    'admin_gestint'    => ['Admin2024!',    ['ALL PRIVILEGES ON gestint.*']],
    'soporte_gestint'  => ['Soporte2024!',  ['ALL PRIVILEGES ON gestint.*']],
    'rrhh_gestint'     => ['RRHH2024!',     ['INSERT, UPDATE, DELETE ON gestint.empleados','INSERT, UPDATE, DELETE ON gestint.usuarios','INSERT ON gestint.fichajes','INSERT ON gestint.justificantes','INSERT ON gestint.tickets','INSERT ON gestint.vacaciones','UPDATE ON gestint.vacaciones']],
    'almacen_gestint'  => ['Almacen2024!',  ['INSERT, UPDATE, DELETE ON gestint.inventario','INSERT, UPDATE, DELETE ON gestint.pedidos','INSERT, UPDATE, DELETE ON gestint.pedidos_lineas','INSERT ON gestint.fichajes','UPDATE ON gestint.pedidos_ventas','INSERT ON gestint.vacaciones']],
    'empleado_gestint' => ['Empleado2024!', ['UPDATE (password) ON gestint.usuarios','INSERT, UPDATE ON gestint.pedidos_ventas','INSERT, UPDATE ON gestint.pedidos_ventas_lineas','INSERT, UPDATE, DELETE ON gestint.clientes','INSERT ON gestint.fichajes','INSERT ON gestint.justificantes','INSERT ON gestint.tickets','INSERT ON gestint.vacaciones']],
];

foreach ($usuarios_mysql as $usr => [$pass, $escritura]) {
    $conn->query("CREATE USER IF NOT EXISTS '$usr'@'%' IDENTIFIED WITH mysql_native_password BY '$pass'");
    if ($conn->error) { $err[] = "MySQL $usr: ".$conn->error; continue; }
    if (!in_array($usr, ['admin_gestint','soporte_gestint'])) {
        foreach ($tablas_todas as $t) $conn->query("GRANT SELECT ON gestint.$t TO '$usr'@'%'");
    }
    foreach ($escritura as $g) $conn->query("GRANT $g TO '$usr'@'%'");
    $ok[] = "Usuario MySQL <b>$usr</b> creado";
}
$conn->query("FLUSH PRIVILEGES");
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalador — Dunder Mifflin</title>
<style>body{font-family:sans-serif;max-width:750px;margin:50px auto;padding:0 20px}h1{color:#28597A;margin-bottom:4px}.sub{color:#069DE0;font-weight:700;margin-bottom:24px}.ok{background:#f0fdf4;border-left:4px solid #16a34a;padding:8px 14px;margin:3px 0;border-radius:4px;font-size:13px}.err{background:#fef2f2;border-left:4px solid #dc2626;padding:8px 14px;margin:3px 0;border-radius:4px;font-size:13px}.warn{background:#fffbeb;border-left:4px solid #d97706;padding:12px 14px;margin-top:20px;border-radius:4px;font-size:13px}.btn{display:inline-block;margin-top:24px;padding:12px 28px;background:#069DE0;color:#fff;border-radius:6px;text-decoration:none;font-weight:700}table{width:100%;border-collapse:collapse;margin-top:16px;font-size:13px}th{background:#28597A;color:#fff;padding:8px 12px;text-align:left}td{padding:7px 12px;border-bottom:1px solid #e2e8f0}tr:nth-child(even)td{background:#f8fafc}</style>
</head><body>
<h1>DUNDER MIFFLIN INC.</h1><p class="sub">Scranton Branch — Instalador GestInt</p>
<?php foreach($ok as $m): ?><div class="ok">✓ <?=$m?></div><?php endforeach; ?>
<?php foreach($err as $m): ?><div class="err">✗ <?=htmlspecialchars($m)?></div><?php endforeach; ?>
<?php if(empty($err)): ?>
<div class="warn">⚠ <strong>Borra este archivo</strong> una vez hayas entrado al sistema.</div>
<h2 style="margin-top:28px;color:#28597A;">Credenciales de acceso</h2>
<table><tr><th>Usuario</th><th>Contraseña</th><th>Rol</th><th>Empleado</th></tr>
<tr><td>michael</td><td>Michael1234</td><td>Director</td><td>Michael Scott</td></tr>
<tr><td>pam</td><td>Pam1234!!</td><td>Resp. RRHH</td><td>Pam Beesly</td></tr>
<tr><td>toby</td><td>Toby1234!</td><td>Resp. RRHH</td><td>Toby Flenderson</td></tr>
<tr><td>darryl</td><td>Darryl1234</td><td>Resp. Almacén</td><td>Darryl Philbin</td></tr>
<tr><td>roy</td><td>Roy12345!</td><td>Resp. Almacén</td><td>Roy Anderson</td></tr>
<tr><td>ryan</td><td>Ryan1234!</td><td>Ventas</td><td>Ryan Howard</td></tr>
<tr><td>ana</td><td>Ana1234!</td><td>Soporte IT</td><td>Ana Orozco Asensio</td></tr>
<tr><td>alberto</td><td>Alberto1234!</td><td>Soporte IT</td><td>Alberto Sánchez</td></tr>
<tr><td>sistema</td><td>Sistema2024!</td><td>Admin (solo contraseña)</td><td>—</td></tr>
<tr><td>dwight</td><td>Dwight1234</td><td>Ventas</td><td>Dwight Schrute</td></tr>
<tr><td>jim</td><td>Jim1234!!</td><td>Ventas</td><td>Jim Halpert</td></tr>
<tr><td>angela</td><td>Angela1234</td><td>Ventas</td><td>Angela Martin</td></tr>
<tr><td>oscar</td><td>Oscar1234!</td><td>Ventas</td><td>Oscar Martinez</td></tr>
<tr><td>kevin</td><td>Kevin1234!</td><td>Ventas</td><td>Kevin Malone</td></tr>
<tr><td>stanley</td><td>Stanley1234</td><td>Ventas</td><td>Stanley Hudson</td></tr>
<tr><td>phyllis</td><td>Phyllis1234</td><td>Ventas</td><td>Phyllis Vance</td></tr>
<tr><td>andy</td><td>Andy1234!!</td><td>Ventas</td><td>Andy Bernard</td></tr>
</table>
<a href="login.php" class="btn">Ir al login →</a>
<?php else: ?>
<div class="err" style="margin-top:16px;">Hay errores. Revisa la conexión en la línea 2 de instalar.php.</div>
<?php endif; ?>
</body></html>
