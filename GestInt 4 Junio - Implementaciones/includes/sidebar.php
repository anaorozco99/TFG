<?php
$rol    = $_SESSION['rol']    ?? 'empleado';
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$paginaActiva = $paginaActiva ?? '';

// Los separadores usan 'sep' en lugar de 'href'
$menu = [
    ['href'=>'../pages/dashboard.php',     'icon'=>'⊞',  'label'=>'Inicio',           'id'=>'inicio',         'ok'=>true],

    ['sep'=>'RRHH',                                                                                             'ok'=>esRRHH()],
    ['href'=>'../pages/empleados.php',     'icon'=>'👤', 'label'=>'Empleados',         'id'=>'empleados',      'ok'=>esRRHH()],

    ['sep'=>'Almacén',                                                                                          'ok'=>esAlmacen()],
    ['href'=>'../pages/inventario.php',    'icon'=>'📦', 'label'=>'Inventario',        'id'=>'inventario',     'ok'=>esAlmacen()],
    ['href'=>'../pages/carrito.php',       'icon'=>'🛒', 'label'=>'Reponer stock',     'id'=>'carrito',        'ok'=>esAlmacen()],
    ['href'=>'../pages/pedidos.php',       'icon'=>'🚚', 'label'=>'Pedidos stock',     'id'=>'pedidos',        'ok'=>esAlmacen()],

    ['sep'=>'Ventas',                                                                                           'ok'=>true],
    ['href'=>'../pages/clientes.php',      'icon'=>'🏢', 'label'=>'Clientes',          'id'=>'clientes',       'ok'=>true],
    ['href'=>'../pages/ventas.php',        'icon'=>'💼', 'label'=>'Ventas a clientes', 'id'=>'ventas',         'ok'=>true],
    ['href'=>'../pages/pedidos_ventas.php','icon'=>'📋', 'label'=>'Pedidos clientes',  'id'=>'pedidos_ventas', 'ok'=>true],

    ['sep'=>'Sistema',                                                                                          'ok'=>esAdmin()],
    ['href'=>'../pages/usuarios.php',      'icon'=>'🔑', 'label'=>'Usuarios',          'id'=>'usuarios',       'ok'=>esAdmin()],
    ['href'=>'../pages/logs.php',          'icon'=>'📜', 'label'=>'Logs acceso',       'id'=>'logs',           'ok'=>esSoporte()],

    ['href'=>'../pages/perfil.php',        'icon'=>'👁',  'label'=>'Mi perfil',         'id'=>'perfil',         'ok'=>true],
];
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">DM</span>
        <div style="display:flex;flex-direction:column;line-height:1.2;">
            <span class="logo-text" style="font-size:13px;letter-spacing:.5px;">DUNDER MIFFLIN</span>
            <span style="font-size:10px;color:rgba(255,255,255,.55);">Scranton Branch</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu as $item):
            if (!$item['ok']) continue;
            if (isset($item['sep'])): ?>
                <div class="nav-sep"><?= $item['sep'] ?></div>
            <?php else: ?>
            <a href="<?= $item['href'] ?>" class="nav-link <?= $paginaActiva === $item['id'] ? 'activa' : '' ?>">
                <span class="nav-icon"><?= $item['icon'] ?></span>
                <span class="nav-label"><?= $item['label'] ?></span>
            </a>
            <?php endif;
        endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($nombre) ?></span>
                <span class="user-rol"><?= nombreRol($rol) ?></span>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout" title="Cerrar sesión">⏻</a>
    </div>
</aside>

<!-- modal de confirmación reutilizable en todas las páginas -->
<div class="modal-overlay" id="modal-confirm">
    <div class="modal" style="max-width:360px;">
        <div class="modal-body" style="padding:28px 20px 16px;text-align:center;">
            <p id="confirm-msg" style="font-size:15px;font-weight:500;line-height:1.5;"></p>
        </div>
        <div class="modal-footer" style="justify-content:center;gap:12px;">
            <button class="btn btn-gris" onclick="cerrarModal('modal-confirm')">Cancelar</button>
            <button class="btn btn-rojo" id="confirm-si" onclick="ejecutarConfirm()">Confirmar</button>
        </div>
    </div>
</div>
