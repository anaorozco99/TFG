<?php
// Comprobación de sesión: si no hay usuario logueado, redirigir al login
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }

$_rol = $_SESSION['rol'] ?? '';

// --- Funciones de acceso por área ---

// Admin puro: gestión de usuarios y sistema (admin + soporte IT)
function esAdmin(): bool        { return in_array($GLOBALS['_rol'], ['admin','soporte']); }

// Director: acceso amplio pero sin módulos de sistema/RR.HH. operativos
function esDirector(): bool     { return $GLOBALS['_rol'] === 'director'; }

// RRHH: gestión de empleados y vacaciones (incluye director para vista)
function esRRHH(): bool         { return in_array($GLOBALS['_rol'], ['admin','director','rrhh','soporte']); }

// Almacén: inventario y pedidos de stock
function esAlmacen(): bool      { return in_array($GLOBALS['_rol'], ['admin','director','almacen','soporte']); }

// Soporte IT: logs, tickets, backup (director NO incluido)
function esSoporte(): bool      { return in_array($GLOBALS['_rol'], ['admin','soporte']); }

// Ventas: clientes y pedidos de venta
function esVentas(): bool       { return in_array($GLOBALS['_rol'], ['admin','director','empleado','soporte']); }

// Contabilidad: ver módulo de cuentas (admin, director, soporte, contabilidad/angela/oscar/kevin por rol empleado con dept Contabilidad)
// Simplificado: admin, director, soporte ven contabilidad siempre; empleados de contabilidad también
function esContabilidad(): bool { return in_array($GLOBALS['_rol'], ['admin','director','soporte','rrhh']); }

// --- Permisos granulares ---

// Puede ver fichajes (director NO)
function puedeVerFichajes(): bool { return in_array($GLOBALS['_rol'], ['admin','rrhh','soporte']); }

// Puede aprobar o denegar vacaciones (director NO, solo lectura)
function puedeAprobarVacaciones(): bool { return in_array($GLOBALS['_rol'], ['admin','rrhh','soporte']); }

// Nombre legible del rol
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
