<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestint');

// credenciales por rol
$credenciales = [
    'admin'   => ['admin_gestint',   'Admin2024!'],
    'rrhh'    => ['rrhh_gestint',    'RRHH2024!'],
    'almacen' => ['almacen_gestint', 'Almacen2024!'],
    'empleado'=> ['empleado_gestint','Empleado2024!'],
    'login'   => ['login_gestint',   'Login2024!'],
];

// si hay sesión activa usamos el usuario del rol, si no el de login
$rol_actual = $_SESSION['rol'] ?? 'login';
[$db_user, $db_pass] = $credenciales[$rol_actual] ?? $credenciales['login'];

$conn = new mysqli(DB_HOST, $db_user, $db_pass, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('<p style="color:red;padding:2rem;">Error de conexión: ' . $conn->connect_error . '</p>');
}
