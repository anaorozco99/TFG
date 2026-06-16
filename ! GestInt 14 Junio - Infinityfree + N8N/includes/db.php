<?php
// Conexión a la base de datos — versión InfinityFree (un solo usuario MySQL)
define('DB_HOST', 'sql211.infinityfree.com');
define('DB_USER', 'if0_42132698');
define('DB_PASS', 'jkkrejLaCivXtV');
define('DB_NAME', 'if0_42132698_gestint');

// aseguramos sesión antes de leer $_SESSION
if (session_status() === PHP_SESSION_NONE) session_start();

// conexión única para todos los roles
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
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
