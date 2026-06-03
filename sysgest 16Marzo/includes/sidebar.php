<?php
// ============================================================
// GestInt — Cabecera y barra lateral compartida
// Requiere: $paginaActiva (string) definida antes de incluir
// ============================================================
$rol       = $_SESSION['rol']    ?? 'empleado';
$nombre    = $_SESSION['nombre'] ?? 'Usuario';
$paginaActiva = $paginaActiva   ?? '';

$menu = [
    ['href' => '../pages/dashboard.php',  'icon' => '⊞',  'label' => 'Dashboard',   'activa' => 'dashboard',  'acceso' => true],
    ['href' => '../pages/empleados.php',  'icon' => '👤', 'label' => 'Empleados',   'activa' => 'empleados',  'acceso' => esRRHH()],
    ['href' => '../pages/inventario.php', 'icon' => '📦', 'label' => 'Inventario',  'activa' => 'inventario', 'acceso' => esAlmacen()],
    ['href' => '../pages/usuarios.php',   'icon' => '🔑', 'label' => 'Usuarios',    'activa' => 'usuarios',   'acceso' => esAdmin()],
    ['href' => '../pages/perfil.php',     'icon' => '👁',  'label' => 'Mi Perfil',   'activa' => 'perfil',     'acceso' => true],
];
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">GI</span>
        <span class="logo-text">GestInt</span>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $item): ?>
            <?php if (!$item['acceso']) continue; ?>
            <a href="<?= $item['href'] ?>"
               class="nav-link <?= $paginaActiva === $item['activa'] ? 'activa' : '' ?>">
                <span class="nav-icon"><?= $item['icon'] ?></span>
                <span class="nav-label"><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
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
