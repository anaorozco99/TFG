<?php
// Conexión a la base de datos
// Cada rol del sistema usa su propio usuario de MySQL con permisos distintos
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestint');

// Tabla de usuarios MySQL según el rol de la sesión activa
$credenciales = [
    'admin'    => ['admin_gestint',    'Admin2024!'],
    'rrhh'     => ['rrhh_gestint',     'RRHH2024!'],
    'almacen'  => ['almacen_gestint',  'Almacen2024!'],
    'empleado' => ['empleado_gestint', 'Empleado2024!'],
    'soporte'  => ['soporte_gestint',  'Soporte2024!'],
    'login'    => ['login_gestint',    'Login2024!'],
];

// Aseguramos que la sesión esté iniciada antes de leer $_SESSION
if (session_status() === PHP_SESSION_NONE) session_start();

// Coge el rol de la sesión activa; si no hay sesión usa el usuario de login (solo puede insertar logs)
$rol_actual = $_SESSION['rol'] ?? 'login';
[$db_user, $db_pass] = $credenciales[$rol_actual] ?? $credenciales['login'];

// Abrimos la conexión con el usuario MySQL correspondiente
$conn = new mysqli(DB_HOST, $db_user, $db_pass, DB_NAME);
$conn->set_charset('utf8mb4');

// Si falla la conexión, paramos todo y mostramos el error
if ($conn->connect_error) {
    die('<p style="color:red;padding:2rem;">Error de conexión: ' . $conn->connect_error . '</p>');
}
