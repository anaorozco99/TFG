<?php
// Registros de acceso: muestra quién ha entrado al sistema y desde qué IP
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!puedeLogs()) { header('Location: dashboard.php'); exit; }

$tab = $_GET['tab'] ?? 'accesos';

// ---- Tab accesos ----
$buscar = trim($_GET['buscar'] ?? '');
$rango  = $_GET['rango'] ?? 'todo';
$pag    = max(1, (int)($_GET['pag'] ?? 1));
$porPag = 50;
$offset = ($pag - 1) * $porPag;

$where  = "WHERE 1=1";
$params = [];
$tipos  = '';

if ($buscar !== '') {
    $like   = "%$buscar%";
    $where .= " AND (u.nombre LIKE ? OR u.apellidos LIKE ? OR u.usuario LIKE ? OR l.ip LIKE ?)";
    $params = [$like, $like, $like, $like];
    $tipos  = 'ssss';
}
if ($rango === 'hoy')    $where .= " AND DATE(l.fecha) = CURDATE()";
if ($rango === 'semana') $where .= " AND l.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

$total_q = $conn->prepare("SELECT COUNT(*) FROM logs_acceso l JOIN usuarios u ON u.id = l.usuario_id $where");
if ($params) $total_q->bind_param($tipos, ...$params);
$total_q->execute();
$total = $total_q->get_result()->fetch_row()[0];
$total_pags = max(1, (int)ceil($total / $porPag));

$q = $conn->prepare("SELECT l.id, l.ip, l.fecha, u.nombre, u.apellidos, u.usuario, u.rol FROM logs_acceso l JOIN usuarios u ON u.id = l.usuario_id $where ORDER BY l.fecha DESC LIMIT ? OFFSET ?");
$params[] = $porPag;
$params[] = $offset;
$tipos   .= 'ii';
$q->bind_param($tipos, ...$params);
$q->execute();
$logs = $q->get_result();

// ---- Tab actividad ----
$act_buscar = trim($_GET['abuscar'] ?? '');
$act_tabla  = $_GET['atab'] ?? '';
$act_rango  = $_GET['arango'] ?? 'todo';
$act_pag    = max(1, (int)($_GET['apag'] ?? 1));
$act_offset = ($act_pag - 1) * $porPag;

$aw = "WHERE 1=1";
$ap = []; $at = '';
if ($act_buscar !== '') {
    $like = "%$act_buscar%";
    $aw  .= " AND (la.usuario_nombre LIKE ? OR la.detalle LIKE ?)";
    $ap   = [$like, $like]; $at = 'ss';
}
if ($act_tabla !== '') { $aw .= " AND la.tabla = ?"; $ap[] = $act_tabla; $at .= 's'; }
if ($act_rango === 'hoy')    $aw .= " AND DATE(la.fecha) = CURDATE()";
if ($act_rango === 'semana') $aw .= " AND la.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

$act_total_q = $conn->prepare("SELECT COUNT(*) FROM logs_actividad la $aw");
if ($ap) $act_total_q->bind_param($at, ...$ap);
$act_total_q->execute();
$act_total = $act_total_q->get_result()->fetch_row()[0];
$act_pags  = max(1, (int)ceil($act_total / $porPag));

$aq = $conn->prepare("SELECT la.* FROM logs_actividad la $aw ORDER BY la.fecha DESC LIMIT ? OFFSET ?");
$ap[] = $porPag; $ap[] = $act_offset; $at .= 'ii';
$aq->bind_param($at, ...$ap);
$aq->execute();
$actividad = $aq->get_result()->fetch_all(MYSQLI_ASSOC);

$paginaActiva = 'logs';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Logs de acceso</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
    <div class="topbar">
        <h1>Logs</h1>
        <div class="topbar-right"><span class="text-gris"><?= $tab==='accesos' ? "$total registros de acceso" : "$act_total registros de actividad" ?></span></div>
    </div>
    <main class="main">

        <!-- Tabs -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:8px 16px;">
                <div class="barra-filtros">
                    <a href="logs.php?tab=accesos"   class="btn <?= $tab==='accesos'   ?'btn-primario':'btn-gris' ?> btn-sm">Accesos</a>
                    <a href="logs.php?tab=actividad" class="btn <?= $tab==='actividad' ?'btn-primario':'btn-gris' ?> btn-sm">Actividad</a>
                </div>
            </div>
        </div>

        <?php if ($tab === 'accesos'): ?>
        <!-- ===== ACCESOS ===== -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:12px 16px;">
                <form method="GET" class="barra-filtros">
                    <input type="hidden" name="tab" value="accesos">
                    <input type="text" name="buscar" placeholder="Nombre, usuario, IP..."
                           value="<?= htmlspecialchars($buscar) ?>">
                    <select name="rango">
                        <option value="todo"   <?= $rango==='todo'   ?'selected':'' ?>>Todo el historial</option>
                        <option value="hoy"    <?= $rango==='hoy'    ?'selected':'' ?>>Hoy</option>
                        <option value="semana" <?= $rango==='semana' ?'selected':'' ?>>Últimos 7 días</option>
                    </select>
                    <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                    <a href="logs.php?tab=accesos" class="btn btn-gris btn-sm">Limpiar</a>
                </form>
            </div>
        </div>
        <div class="panel">
            <table>
                <thead><tr><th>#</th><th>Usuario</th><th>Nombre</th><th>Rol</th><th>IP</th><th>Fecha y hora</th></tr></thead>
                <tbody>
                <?php if ($logs->num_rows > 0): while ($l = $logs->fetch_assoc()):
                    $badges = ['sistema'=>'badge-rojo','it'=>'badge-verde','direccion'=>'badge-azul','rrhh'=>'badge-azul','almacen'=>'badge-naranja','ventas'=>'badge-gris','contabilidad'=>'badge-gris','recepcion'=>'badge-gris'];
                ?>
                <tr>
                    <td class="text-gris"><?= $l['id'] ?></td>
                    <td class="fw-600"><?= htmlspecialchars($l['usuario']) ?></td>
                    <td><?= htmlspecialchars($l['nombre'].' '.$l['apellidos']) ?></td>
                    <td><span class="badge <?= $badges[$l['rol']] ?? 'badge-gris' ?>"><?= nombreRol($l['rol']) ?></span></td>
                    <td class="text-gris" style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($l['ip']) ?></td>
                    <td class="text-gris"><?= date('d/m/Y H:i:s', strtotime($l['fecha'])) ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" class="text-gris text-center" style="padding:24px;">Sin registros.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_pags > 1): ?>
            <div class="paginacion" style="padding:12px 16px;">
                <?php for ($p = 1; $p <= $total_pags; $p++):
                    $qs = http_build_query(['tab'=>'accesos','buscar'=>$buscar,'rango'=>$rango,'pag'=>$p]); ?>
                    <?php if ($p === $pag): ?><span class="actual"><?= $p ?></span>
                    <?php else: ?><a href="?<?= $qs ?>"><?= $p ?></a><?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- ===== ACTIVIDAD ===== -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:12px 16px;">
                <form method="GET" class="barra-filtros">
                    <input type="hidden" name="tab" value="actividad">
                    <input type="text" name="abuscar" placeholder="Empleado, detalle..."
                           value="<?= htmlspecialchars($act_buscar) ?>">
                    <select name="atab">
                        <option value="">Todas las secciones</option>
                        <?php foreach(['vacaciones'=>'Vacaciones','empleados'=>'Empleados','inventario'=>'Inventario','usuarios'=>'Usuarios','pedidos'=>'Pedidos stock','pedidos_ventas'=>'Pedidos ventas','tickets'=>'Tickets','clientes'=>'Clientes'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $act_tabla===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="arango">
                        <option value="todo"   <?= $act_rango==='todo'   ?'selected':'' ?>>Todo</option>
                        <option value="hoy"    <?= $act_rango==='hoy'    ?'selected':'' ?>>Hoy</option>
                        <option value="semana" <?= $act_rango==='semana' ?'selected':'' ?>>Últimos 7 días</option>
                    </select>
                    <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                    <a href="logs.php?tab=actividad" class="btn btn-gris btn-sm">Limpiar</a>
                </form>
            </div>
        </div>
        <div class="panel">
            <?php
            $colores_rol = ['sistema'=>'badge-rojo','it'=>'badge-verde','direccion'=>'badge-azul','rrhh'=>'badge-azul','almacen'=>'badge-naranja','ventas'=>'badge-gris','contabilidad'=>'badge-gris','recepcion'=>'badge-gris'];
            $nombres_tabla = [
                'vacaciones'     => 'Vacaciones',
                'empleados'      => 'Empleados',
                'inventario'     => 'Inventario',
                'usuarios'       => 'Usuarios',
                'pedidos'        => 'Pedidos stock',
                'pedidos_ventas' => 'Pedidos ventas',
                'tickets'        => 'Tickets',
                'clientes'       => 'Clientes',
            ];
            ?>
            <table>
                <thead><tr><th>#</th><th>Empleado</th><th>Rol</th><th>Acción</th><th>Sección</th><th>Detalle</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php if (!empty($actividad)): foreach ($actividad as $a): ?>
                <tr>
                    <td class="text-gris"><?= $a['id'] ?></td>
                    <td class="fw-600"><?= htmlspecialchars($a['usuario_nombre']) ?></td>
                    <td><span class="badge <?= $colores_rol[$a['rol']] ?? 'badge-gris' ?>"><?= nombreRol($a['rol']) ?></span></td>
                    <td><?= ucfirst(htmlspecialchars($a['accion'])) ?></td>
                    <td class="text-gris"><?= htmlspecialchars($nombres_tabla[$a['tabla']] ?? ucfirst($a['tabla'])) ?></td>
                    <td><?= $a['registro_id'] ? "Registro #".$a['registro_id']." — " : '' ?><?= htmlspecialchars($a['detalle'] ?? '—') ?></td>
                    <td class="text-gris"><?= date('d/m/Y H:i', strtotime($a['fecha'])) ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-gris text-center" style="padding:24px;">Sin actividad registrada.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($act_pags > 1): ?>
            <div class="paginacion" style="padding:12px 16px;">
                <?php for ($p = 1; $p <= $act_pags; $p++):
                    $qs = http_build_query(['tab'=>'actividad','abuscar'=>$act_buscar,'atab'=>$act_tabla,'arango'=>$act_rango,'apag'=>$p]); ?>
                    <?php if ($p === $act_pag): ?><span class="actual"><?= $p ?></span>
                    <?php else: ?><a href="?<?= $qs ?>"><?= $p ?></a><?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
</div>
<script src="../js/app.js"></script>
</body></html>
