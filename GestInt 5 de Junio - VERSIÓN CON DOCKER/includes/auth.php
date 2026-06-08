<?php
// Comprobación de sesión: si no hay usuario logueado, redirigir al login
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }

// Soporte IT tiene acceso total (igual que admin/director)
function esAdmin(): bool   { return in_array($_SESSION['rol'] ?? '', ['admin','director','soporte']); }
function esRRHH(): bool    { return in_array($_SESSION['rol'] ?? '', ['admin','director','rrhh','soporte']); }
function esAlmacen(): bool { return in_array($_SESSION['rol'] ?? '', ['admin','director','almacen','soporte']); }
function esSoporte(): bool { return in_array($_SESSION['rol'] ?? '', ['admin','director','soporte']); }
function esVentas(): bool  { return in_array($_SESSION['rol'] ?? '', ['admin','director','empleado','soporte']); }

// Nombre legible del rol
function nombreRol(string $rol): string {
    return match($rol) {
        'admin'    => 'Administrador',
        'director' => 'Director',
        'rrhh'     => 'Resp. RRHH',
        'almacen'  => 'Resp. Almacén',
        'soporte'  => 'Soporte IT',
        default    => 'Ventas'
    };
}
