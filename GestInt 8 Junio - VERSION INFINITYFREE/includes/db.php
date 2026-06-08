<?php
// ============================================================
// VERSIÓN INFINITYFREE
// InfinityFree solo permite UN usuario MySQL por base de datos
// en el plan gratuito, así que no podemos usar el sistema de
// múltiples usuarios por rol que tenemos en XAMPP local.
// ============================================================

// Rellena estos datos con los de tu panel InfinityFree:
// Sección: MySQL Databases → los 4 valores están ahí.
define('DB_HOST', 'sql211.infinityfree.com');
define('DB_USER', 'if0_42132698');
define('DB_PASS', 'jkkrejLaCivXtV');
define('DB_NAME', 'if0_42132698_gestint');      // ← crea esta BD en el panel antes de subir

// Aseguramos que la sesión esté iniciada antes de usarla
if (session_status() === PHP_SESSION_NONE) session_start();

// Conexión única — un solo usuario con acceso completo (limitación del hosting gratuito)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

// Si falla la conexión, paramos todo y mostramos el error
if ($conn->connect_error) {
    die('<p style="color:red;padding:2rem;">Error de conexión: ' . $conn->connect_error . '</p>');
}
