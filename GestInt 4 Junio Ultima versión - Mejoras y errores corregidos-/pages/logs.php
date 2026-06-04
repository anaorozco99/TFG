<?php
// Registros de acceso: muestra quién ha entrado al sistema y desde qué IP
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!esSoporte()) { header('Location: dashboard.php'); exit; }

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

$paginaActiva = 'logs';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Logs de acceso</title>
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
    <div class="topbar">
        <h1>Logs de acceso</h1>
        <div class="topbar-right"><span class="text-gris"><?= $total ?> registros</span></div>
    </div>
    <main class="main">
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:12px 16px;">
                <form method="GET" class="barra-filtros">
                    <input type="text" name="buscar" placeholder="Nombre, usuario, IP..."
                           value="<?= htmlspecialchars($buscar) ?>">
                    <select name="rango">
                        <option value="todo"   <?= $rango==='todo'   ?'selected':'' ?>>Todo el historial</option>
                        <option value="hoy"    <?= $rango==='hoy'    ?'selected':'' ?>>Hoy</option>
                        <option value="semana" <?= $rango==='semana' ?'selected':'' ?>>Últimos 7 días</option>
                    </select>
                    <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                    <a href="logs.php" class="btn btn-gris btn-sm">Limpiar</a>
                </form>
            </div>
        </div>

        <div class="panel">
            <table>
                <thead>
                    <tr><th>#</th><th>Usuario</th><th>Nombre</th><th>Rol</th><th>IP</th><th>Fecha y hora</th></tr>
                </thead>
                <tbody>
                <?php if ($logs->num_rows > 0): while ($l = $logs->fetch_assoc()):
                    $badges = ['admin'=>'badge-rojo','rrhh'=>'badge-azul','almacen'=>'badge-naranja','soporte'=>'badge-verde','empleado'=>'badge-gris'];
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
                    $qs = http_build_query(['buscar'=>$buscar,'rango'=>$rango,'pag'=>$p]); ?>
                    <?php if ($p === $pag): ?><span class="actual"><?= $p ?></span>
                    <?php else: ?><a href="?<?= $qs ?>"><?= $p ?></a><?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</div>
<script src="../js/app.js"></script>
</body></html>
