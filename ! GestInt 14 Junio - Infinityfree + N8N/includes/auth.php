<?php
// si no hay sesión activa, mandamos al login
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }

$_rol = $_SESSION['rol'] ?? '';

// ─── rol base ────────────────────────────────────────────────────────────────

// acceso total
function esSistema(): bool    { return $GLOBALS['_rol'] === 'sistema'; }

// it tiene acceso igual que sistema salvo protecciones específicas
function esIT(): bool         { return in_array($GLOBALS['_rol'], ['sistema','it']); }

// dirección: visión global en lectura + gestión propia
function esDireccion(): bool  { return $GLOBALS['_rol'] === 'direccion'; }

// ─── acceso por sección ───────────────────────────────────────────────────────

// empleados, fichajes, justificantes, vacaciones (gestión)
function puedeRRHH(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','direccion','rrhh']);
}

// inventario, reponer stock, pedidos stock (con edición)
function puedeAlmacen(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','direccion','almacen']);
}

// inventario visible también para recepción (solo lectura)
function puedeVerInventario(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','direccion','almacen','recepcion']);
}

// crear ventas, gestionar clientes
function puedeVentas(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','direccion','ventas']);
}

// pedidos de clientes (ver): ventas, almacen, contabilidad, recepcion
function puedePedidosClientes(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','direccion','ventas','almacen','contabilidad','recepcion']);
}

// gestión de clientes (crear/editar): ventas y dirección
function puedeClientes(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','direccion','ventas']);
}

// contabilidad/cuentas
function puedeCuentas(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','direccion','contabilidad']);
}

// logs, tickets sistema, backup
function puedeSistema(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it']);
}

// ver página usuarios (it/sistema editan, dirección solo lee)
function puedeVerUsuarios(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','direccion']);
}

// logs de acceso
function puedeLogs(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','direccion']);
}

// ─── permisos de edición ──────────────────────────────────────────────────────

// editar inventario (no recepción, no dirección, no contabilidad)
function puedeEditarInventario(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','almacen']);
}

// marcar pedidos de stock como recibidos
function puedeEditarPedidosStock(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','almacen']);
}

// cambiar estado / ver factura y etiqueta de pedidos clientes (ventas + almacen)
function puedeEditarPedidosClientes(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','ventas','almacen']);
}

// crear nuevos pedidos de clientes (solo ventas)
function puedeCrearPedidosClientes(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','ventas']);
}

// crear/editar clientes (dirección es solo lectura)
function puedeEditarClientes(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','ventas']);
}

// crear/editar usuarios (dirección es solo lectura)
function puedeEditarUsuarios(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it']);
}

// ─── permisos específicos ─────────────────────────────────────────────────────

// ver fichajes de todos los empleados
function puedeVerFichajes(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','rrhh']);
}

// aprobar o rechazar vacaciones
function puedeAprobarVacaciones(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','rrhh']);
}

// ver justificantes de otros (no solo los propios)
function puedeVerJustificantes(): bool {
    return in_array($GLOBALS['_rol'], ['sistema','it','rrhh','direccion']);
}

// ─── banner "Vista [Departamento]" para rol IT ────────────────────────────────
function vistaITBanner(string $paginaActiva): string {
    if ($GLOBALS['_rol'] !== 'it') return '';
    $mapa = [
        'empleados'      => 'RRHH',
        'tab_fichajes'   => 'RRHH',
        'tab_just'       => 'RRHH',
        'vacaciones'     => 'RRHH',
        'tab_cal'        => 'RRHH',
        'tab_sol'        => 'RRHH',
        'tab_aprobadas'  => 'RRHH',
        'gestion_sol'    => 'RRHH',
        'inventario'     => 'Almacén',
        'carrito'        => 'Almacén',
        'pedidos'        => 'Almacén',
        'clientes'       => 'Ventas',
        'ventas'         => 'Ventas',
        'pedidos_ventas' => 'Ventas',
        'contabilidad'   => 'Contabilidad',
        'usuarios'       => 'Sistema',
    ];
    $dept = $mapa[$paginaActiva] ?? '';
    return $dept ? "Vista $dept" : '';
}

// ─── nombre legible del rol ───────────────────────────────────────────────────
function nombreRol(string $rol): string {
    return match($rol) {
        'sistema'      => 'Sistema',
        'it'           => 'Soporte IT',
        'direccion'    => 'Dirección',
        'rrhh'         => 'RRHH',
        'almacen'      => 'Almacén',
        'ventas'       => 'Ventas',
        'contabilidad' => 'Contabilidad',
        'recepcion'    => 'Recepción',
        default        => 'Usuario'
    };
}
