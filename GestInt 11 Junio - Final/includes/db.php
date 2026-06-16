<?php
// Conexión a la base de datos
// Cada rol del sistema usa su propio usuario de MySQL con permisos distintos
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestint');

// usuario MySQL según el rol de la sesión activa
$credenciales = [
    'sistema'      => ['sistema_gestint',      'Sistema2026*'],
    'it'           => ['it_gestint',           'IT2024!'],
    'direccion'    => ['direccion_gestint',     'Direccion2024!'],
    'rrhh'         => ['rrhh_gestint',         'RRHH2024!'],
    'almacen'      => ['almacen_gestint',       'Almacen2024!'],
    'ventas'       => ['ventas_gestint',        'Ventas2024!'],
    'contabilidad' => ['contabilidad_gestint',  'Contab2024!'],
    'recepcion'    => ['recepcion_gestint',     'Recepcion2024!'],
    'login'        => ['login_gestint',         'Login2024!'],
];

// aseguramos sesión antes de leer $_SESSION
if (session_status() === PHP_SESSION_NONE) session_start();

// si el rol no está en el mapa, usamos login (permisos mínimos)
$rol_actual = $_SESSION['rol'] ?? 'login';
[$db_user, $db_pass] = $credenciales[$rol_actual] ?? $credenciales['login'];

// abrimos conexión con el usuario MySQL del rol
$conn = new mysqli(DB_HOST, $db_user, $db_pass, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('<p style="color:red;padding:2rem;">Error de conexión: ' . $conn->connect_error . '</p>');
}

// registra una acción en el log de actividad
function log_act(string $accion, string $tabla, ?int $id, string $detalle): void {
    global $conn;
    $uid    = (int)($_SESSION['usuario_id'] ?? 0);
    $nombre = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellidos'] ?? ''));
    $rol    = $_SESSION['rol'] ?? '';
    if (!$uid) return;
    $st = $conn->prepare("INSERT INTO logs_actividad (usuario_id, usuario_nombre, rol, accion, tabla, registro_id, detalle) VALUES (?,?,?,?,?,?,?)");
    if (!$st) return; // si el usuario MySQL no tiene permiso, falla en silencio
    $st->bind_param('issssis', $uid, $nombre, $rol, $accion, $tabla, $id, $detalle);
    $st->execute();
}
