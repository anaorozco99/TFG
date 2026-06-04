<?php
$rol = $_SESSION['rol'] ?? 'empleado';
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$paginaActiva = $paginaActiva ?? '';
$menu = [
    ['href'=>'../pages/dashboard.php', 'icon'=>'⊞',  'label'=>'Dashboard',   'id'=>'dashboard',  'ok'=>true],
    ['href'=>'../pages/empleados.php', 'icon'=>'👤', 'label'=>'Empleados',   'id'=>'empleados',  'ok'=>esRRHH()],
    ['href'=>'../pages/inventario.php','icon'=>'📦', 'label'=>'Inventario',  'id'=>'inventario', 'ok'=>esAlmacen()],
    ['href'=>'../pages/carrito.php',   'icon'=>'🛒', 'label'=>'Pedir stock', 'id'=>'carrito',    'ok'=>esAlmacen()],
    ['href'=>'../pages/pedidos.php',   'icon'=>'🚚', 'label'=>'Pedidos',     'id'=>'pedidos',    'ok'=>esAlmacen()],
    ['href'=>'../pages/usuarios.php',  'icon'=>'🔑', 'label'=>'Usuarios',    'id'=>'usuarios',   'ok'=>esAdmin()],
    ['href'=>'../pages/perfil.php',    'icon'=>'👁',  'label'=>'Mi perfil',   'id'=>'perfil',     'ok'=>true],
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
        <?php foreach($menu as $item): if(!$item['ok']) continue; ?>
        <a href="<?=$item['href']?>" class="nav-link <?=$paginaActiva===$item['id']?'activa':''?>">
            <span class="nav-icon"><?=$item['icon']?></span>
            <span class="nav-label"><?=$item['label']?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?=strtoupper(substr($nombre,0,1))?></div>
            <div class="user-info">
                <span class="user-name"><?=htmlspecialchars($nombre)?></span>
                <span class="user-rol"><?=nombreRol($rol)?></span>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout" title="Cerrar sesión">⏻</a>
    </div>
</aside>
