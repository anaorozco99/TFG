<?php
$rol    = $_SESSION['rol']    ?? 'ventas';
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$paginaActiva = $paginaActiva ?? '';

// banner "Vista [Dept]" para IT cuando visita secciones de otros departamentos
$it_banner = vistaITBanner($paginaActiva);

// ─── definición del menú ─────────────────────────────────────────────────────
// 'ok'  = condición para mostrar el item
// 'sub' = aparece indentado bajo el ítem padre

$menu = [
    ['href'=>'../pages/dashboard.php',                   'icon'=>'⊞',  'label'=>'Inicio',             'id'=>'inicio',        'ok'=>true],

    // ── RRHH ──────────────────────────────────────────────────────────────────
    ['sep'=>'RRHH',                                                                                     'ok'=>puedeRRHH() || $rol==='recepcion'],
    ['href'=>'../pages/empleados.php',                   'icon'=>'👥', 'label'=>'Empleados',           'id'=>'empleados',     'ok'=>puedeRRHH() || $rol==='recepcion'],
    ['href'=>'../pages/empleados.php?tab=fichajes',      'icon'=>'⏰', 'label'=>'Fichajes',            'id'=>'tab_fichajes',  'ok'=>puedeVerFichajes(),                   'sub'=>true],
    ['href'=>'../pages/empleados.php?tab=justificantes', 'icon'=>'📄', 'label'=>'Justificantes',       'id'=>'tab_just',      'ok'=>puedeVerJustificantes(),              'sub'=>true],
    // vacaciones: gestión completa para rrhh/it/sistema/dirección
    ['href'=>'../pages/vacaciones.php',                  'icon'=>'🌴', 'label'=>'Vacaciones',          'id'=>'vacaciones',    'ok'=>puedeRRHH()],
    ['href'=>'../pages/vacaciones.php?tab=calendario',   'icon'=>'📅', 'label'=>'Calendario',          'id'=>'tab_cal',       'ok'=>puedeRRHH(),                          'sub'=>true],
    ['href'=>'../pages/vacaciones.php?tab=solicitudes',  'icon'=>'📝', 'label'=>'Solicitudes',         'id'=>'tab_sol',       'ok'=>puedeRRHH(),                          'sub'=>true],
    ['href'=>'../pages/vacaciones.php?tab=aprobadas',    'icon'=>'✅', 'label'=>'Aprobadas',           'id'=>'tab_aprobadas', 'ok'=>puedeRRHH(),                          'sub'=>true],
    ['href'=>'../pages/vacaciones.php?tab=gestion',      'icon'=>'⚙', 'label'=>'Gestión solicitudes', 'id'=>'gestion_sol',   'ok'=>puedeAprobarVacaciones(),             'sub'=>true],
    // solo mis vacaciones (roles que no ven rrhh completo)
    ['href'=>'../pages/vacaciones.php',                  'icon'=>'🌴', 'label'=>'Mis vacaciones',      'id'=>'mis_vac',       'ok'=>!puedeRRHH() && in_array($rol,['ventas','contabilidad','almacen','recepcion'])],
    ['href'=>'../pages/vacaciones.php?tab=calendario',   'icon'=>'📅', 'label'=>'Calendario',          'id'=>'mi_cal',        'ok'=>!puedeRRHH() && in_array($rol,['ventas','contabilidad','almacen','recepcion']), 'sub'=>true],

    // ── Almacén ────────────────────────────────────────────────────────────────
    ['sep'=>'Almacén',                                                                                  'ok'=>puedeVerInventario() || in_array($rol,['contabilidad'])],
    ['href'=>'../pages/inventario.php',                  'icon'=>'📦', 'label'=>'Inventario',          'id'=>'inventario',    'ok'=>puedeVerInventario()],
    ['href'=>'../pages/carrito.php',                     'icon'=>'🛒', 'label'=>'Reponer stock',       'id'=>'carrito',       'ok'=>puedeEditarPedidosStock()],
    ['href'=>'../pages/pedidos.php',                     'icon'=>'🚚', 'label'=>'Pedidos stock',       'id'=>'pedidos',       'ok'=>puedeAlmacen() || $rol==='contabilidad'],

    // ── Ventas ─────────────────────────────────────────────────────────────────
    ['sep'=>'Ventas',                                                                                   'ok'=>puedePedidosClientes()],
    ['href'=>'../pages/clientes.php',                    'icon'=>'🏢', 'label'=>'Clientes',            'id'=>'clientes',      'ok'=>puedeClientes()],
    ['href'=>'../pages/ventas.php',                      'icon'=>'💼', 'label'=>'Ventas a clientes',   'id'=>'ventas',        'ok'=>puedeCrearPedidosClientes()],
    ['href'=>'../pages/pedidos_ventas.php',              'icon'=>'📋', 'label'=>'Pedidos clientes',    'id'=>'pedidos_ventas','ok'=>puedePedidosClientes()],

    // ── Contabilidad ───────────────────────────────────────────────────────────
    ['sep'=>'Contabilidad',                                                                             'ok'=>puedeCuentas()],
    ['href'=>'../pages/contabilidad.php',                'icon'=>'💰', 'label'=>'Cuentas',             'id'=>'contabilidad',  'ok'=>puedeCuentas()],

    // ── Sistema ────────────────────────────────────────────────────────────────
    ['sep'=>'Sistema',                                                                                  'ok'=>puedeSistema() || puedeVerUsuarios()],
    ['href'=>'../pages/usuarios.php',                    'icon'=>'🔑', 'label'=>'Usuarios',            'id'=>'usuarios',      'ok'=>puedeVerUsuarios()],
    ['href'=>'../pages/logs.php',                        'icon'=>'📜', 'label'=>'Logs acceso',         'id'=>'logs',          'ok'=>puedeLogs()],
    ['href'=>'../pages/tickets.php',                     'icon'=>'🎫', 'label'=>'Tickets soporte',     'id'=>'tickets',       'ok'=>puedeSistema()],
    ['href'=>'../backup.php',                            'icon'=>'💾', 'label'=>'Backup BD',           'id'=>'backup',        'ok'=>puedeSistema()],

    ['href'=>'../pages/perfil.php',                      'icon'=>'👤', 'label'=>'Mi perfil',           'id'=>'perfil',        'ok'=>true],
];
?>
<button class="btn-menu-movil" onclick="abrirSidebar()" title="Menú" style="position:fixed;top:14px;left:14px;z-index:201;">☰</button>

<aside class="sidebar">
<?php if (isset($_GET['ticket_ok'])): ?>
<div style="background:#16a34a;color:#fff;padding:8px 14px;font-size:12px;text-align:center;">
    ✓ Ticket enviado — el equipo de soporte lo revisará pronto
</div>
<?php endif; ?>
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
            <?php elseif (!empty($item['sub'])): ?>
            <a href="<?= $item['href'] ?>" class="nav-link nav-sub <?= $paginaActiva === $item['id'] ? 'activa' : '' ?>">
                <span class="nav-icon" style="font-size:11px;"><?= $item['icon'] ?></span>
                <span class="nav-label"><?= $item['label'] ?></span>
            </a>
            <?php else: ?>
            <a href="<?= $item['href'] ?>" class="nav-link <?= $paginaActiva === $item['id'] ? 'activa' : '' ?>">
                <span class="nav-icon"><?= $item['icon'] ?></span>
                <span class="nav-label"><?= $item['label'] ?></span>
            </a>
            <?php endif;
        endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <button onclick="abrirModal('modal-ticket')"
           style="width:100%;background:rgba(255,255,255,.08);border:none;cursor:pointer;display:flex;align-items:center;gap:8px;padding:9px 14px;color:rgba(255,255,255,.8);font-size:12px;border-bottom:1px solid rgba(255,255,255,.08);">
            🎫 <span>Abrir ticket de soporte</span>
        </button>
        <div class="sidebar-footer-user">
            <div class="sidebar-user">
                <div class="user-avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($nombre) ?></span>
                    <span class="user-rol"><?= nombreRol($rol) ?></span>
                </div>
            </div>
            <a href="../logout.php" class="btn-logout" title="Cerrar sesión">⏻</a>
        </div>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="cerrarSidebar()"></div>

<?php if ($it_banner): ?>
<div style="position:fixed;top:0;left:220px;width:22px;height:100vh;background:#dc2626;z-index:150;display:flex;align-items:center;justify-content:center;">
    <span style="writing-mode:vertical-rl;transform:rotate(180deg);color:#fff;font-size:11px;font-weight:700;letter-spacing:1px;white-space:nowrap;"><?= htmlspecialchars($it_banner) ?></span>
</div>
<?php endif; ?>


<!-- Modal confirmación genérico -->
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

<!-- Modal ticket rápido para todos los usuarios -->
<div class="modal-overlay" id="modal-ticket">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3>🎫 Abrir ticket de soporte</h3>
            <button class="modal-cerrar" onclick="cerrarModal('modal-ticket')">✕</button>
        </div>
        <form method="POST" action="../pages/ticket_rapido.php">
            <div class="modal-body">
                <div class="form-grupo">
                    <label>Asunto *</label>
                    <input type="text" name="t_asunto" required placeholder="Describe brevemente el problema">
                </div>
                <div class="form-grupo">
                    <label>Descripción *</label>
                    <textarea name="t_desc" rows="4" required placeholder="Explica el problema con detalle..."
                        style="width:100%;padding:8px 12px;border:1px solid var(--gris-borde);border-radius:6px;font-size:14px;resize:vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gris" onclick="cerrarModal('modal-ticket')">Cancelar</button>
                <button type="submit" class="btn btn-primario">Enviar ticket</button>
            </div>
        </form>
    </div>
</div>
