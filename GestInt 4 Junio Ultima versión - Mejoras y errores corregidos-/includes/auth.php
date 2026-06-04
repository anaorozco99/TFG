<?php
// Comprobación de sesión: si no hay usuario logueado, redirigir al login
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }

// Funciones para saber qué puede hacer el usuario según su rol
function esAdmin(): bool   { return in_array($_SESSION['rol'] ?? '', ['admin','director','soporte']); }
function esRRHH(): bool    { return in_array($_SESSION['rol'] ?? '', ['admin','director','rrhh','soporte']); }
function esAlmacen(): bool { return in_array($_SESSION['rol'] ?? '', ['admin','director','almacen','soporte']); }
function esSoporte(): bool { return in_array($_SESSION['rol'] ?? '', ['admin','director','soporte']); }

// Devuelve el nombre legible del rol para mostrarlo en pantalla
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
