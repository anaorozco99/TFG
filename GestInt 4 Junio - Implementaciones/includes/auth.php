<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }
function esAdmin(): bool   { return in_array($_SESSION['rol'] ?? '', ['admin','director','soporte']); }
function esRRHH(): bool    { return in_array($_SESSION['rol'] ?? '', ['admin','director','rrhh','soporte']); }
function esAlmacen(): bool { return in_array($_SESSION['rol'] ?? '', ['admin','director','almacen','soporte']); }
function esSoporte(): bool { return in_array($_SESSION['rol'] ?? '', ['admin','director','soporte']); }
function nombreRol(string $rol): string {
    return match($rol) {
        'admin'    => 'Administrador',
        'director' => 'Director',
        'rrhh'     => 'Resp. RRHH',
        'almacen'  => 'Resp. Almacén',
        'soporte'  => 'Soporte IT',
        default    => 'Empleado'
    };
}
