<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

function esAdmin(): bool {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

function esRRHH(): bool {
    return isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin', 'rrhh']);
}

function esAlmacen(): bool {
    return isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin', 'almacen']);
}

function nombreRol(string $rol): string {
    return match($rol) {
        'admin'   => 'Administrador',
        'rrhh'    => 'Resp. RRHH',
        'almacen' => 'Resp. Almacén',
        default   => 'Empleado',
    };
}
