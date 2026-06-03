<?php
// ============================================================
// GestInt — Conexión a la base de datos
// Modifica estos valores según tu instalación
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'ana');
define('DB_PASS', 'Admin1234');
define('DB_NAME', 'gestint');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('<p style="color:red;font-family:sans-serif;padding:2rem;">
         Error de conexión: ' . $conn->connect_error . '</p>');
}
