<?php
// Pedidos de stock: ver el estado de los pedidos y marcarlos como recibidos
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!esAlmacen()) { header('Location: dashboard.php'); exit; }

$msg  = '';
$tipo = '';

if (isset($_GET['recibir']) && (int)$_GET['recibir'] > 0) {
    $pid  = (int)$_GET['recibir'];
    $stmt = $conn->prepare("SELECT id, estado FROM pedidos WHERE id = ?");
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();

    if ($pedido && $pedido['estado'] !== 'recibido') {
        $upd = $conn->prepare("UPDATE pedidos SET estado = 'recibido' WHERE id = ?");
        $upd->bind_param('i', $pid);
        $upd->execute();

        $lineas = $conn->prepare("SELECT inventario_id, cantidad FROM pedidos_lineas WHERE pedido_id = ?");
        $lineas->bind_param('i', $pid);
        $lineas->execute();
        $res = $lineas->get_result();
        while ($l = $res->fetch_assoc()) {
            $s = $conn->prepare("UPDATE inventario SET cantidad = cantidad + ? WHERE id = ?");
            $s->bind_param('ii', $l['cantidad'], $l['inventario_id']);
            $s->execute();
        }
        $msg  = "Pedido #$pid recibido. Stock actualizado.";
        $tipo = 'ok';
    }
}

$filtro = $_GET['filtro'] ?? 'todos';
$where  = "WHERE 1=1";
if ($filtro === 'pendientes') $where .= " AND p.estado != 'recibido'";
if ($filtro === 'recibidos')  $where .= " AND p.estado = 'recibido'";

$res = $conn->query(
    "SELECT p.id, p.estado, p.fecha_pedido, p.fecha_llegada, p.notas,
            u.nombre, u.apellidos,
            COUNT(pl.id) AS num_lineas,
            SUM(pl.cantidad) AS total_uds
     FROM pedidos p
     JOIN usuarios u ON u.id = p.usuario_id
     LEFT JOIN pedidos_lineas pl ON pl.pedido_id = p.id
     $where
     GROUP BY p.id, p.estado, p.fecha_pedido, p.fecha_llegada, p.notas, u.nombre, u.apellidos
     ORDER BY p.fecha_pedido DESC"
);

$lista = [];
while ($row = $res->fetch_assoc()) { $lista[] = $row; }

$paginaActiva = 'pedidos';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Pedidos stock</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
<style>
    .countdown         { font-size:13px; font-weight:600; color:var(--naranja); }
    .countdown.llegado { color:var(--verde); }
</style>
</head><body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="contenido">
        <div class="topbar">
            <h1>Pedidos stock</h1>
            <div class="topbar-right">
                <a href="carrito.php" class="btn btn-primario">Nuevo pedido</a>
            </div>
        </div>
        <main class="main">
            <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div><?php endif; ?>

            <div class="panel" style="margin-bottom:16px;">
                <div class="panel-body" style="padding:10px 16px;">
                    <div class="barra-filtros">
                        <a href="?filtro=todos"      class="btn <?= $filtro==='todos'      ?'btn-primario':'btn-gris' ?> btn-sm">Todos</a>
                        <a href="?filtro=pendientes" class="btn <?= $filtro==='pendientes' ?'btn-primario':'btn-gris' ?> btn-sm">En camino</a>
                        <a href="?filtro=recibidos"  class="btn <?= $filtro==='recibidos'  ?'btn-primario':'btn-gris' ?> btn-sm">Recibidos</a>
                    </div>
                </div>
            </div>

            <div class="panel">
                <table>
                    <thead>
                        <tr><th>#</th><th>Solicitado por</th><th>Fecha pedido</th><th>Llegada estimada</th><th>Artículos</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($lista)): foreach ($lista as $p):
                        $ahora    = new DateTime();
                        $llegada  = new DateTime($p['fecha_llegada']);
                        $llegado  = $ahora >= $llegada;
                        $diff     = $ahora->diff($llegada);
                        $restante = $llegado ? 'Listo para recibir' : $diff->h . 'h ' . $diff->i . 'min restantes';
                    ?>
                    <tr>
                        <td class="fw-600">#<?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['nombre'].' '.$p['apellidos']) ?></td>
                        <td class="text-gris"><?= date('d/m/Y H:i', strtotime($p['fecha_pedido'])) ?></td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($p['fecha_llegada'])) ?>
                            <?php if ($p['estado'] !== 'recibido'): ?>
                                <div class="countdown <?= $llegado?'llegado':'' ?>">
                                    <?= $llegado?'✅ ':'⏱ ' ?><?= $restante ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-azul"><?= $p['num_lineas'] ?> ref.</span>
                            <span class="text-gris"><?= $p['total_uds'] ?> uds.</span><br>
                            <a href="#" onclick="abrirModal('det<?= $p['id'] ?>'); return false;"
                               style="font-size:12px;color:var(--azul-medio);">Ver detalle</a>
                        </td>
                        <td>
                            <?php if ($p['estado']==='recibido'): ?>
                                <span class="badge badge-verde">Recibido</span>
                            <?php elseif ($llegado): ?>
                                <span class="badge badge-naranja">Listo para recibir</span>
                            <?php else: ?>
                                <span class="badge badge-azul">En camino</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['estado']!=='recibido'): ?>
                                <!-- Se puede marcar recibido en cualquier momento, sin esperar las 24h -->
                                <a href="#"
                                   class="btn btn-verde btn-sm"
                                   onclick="confirmarLink('¿Confirmar recepción? Se actualizará el stock.', '?recibir=<?= $p['id'] ?>&filtro=<?= $filtro ?>', 'verde')">Marcar recibido</a>
                            <?php else: ?>
                                <span class="text-gris" style="font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" class="text-gris text-center" style="padding:28px;">No hay pedidos.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<?php foreach ($lista as $p): ?>
<div class="modal-overlay" id="det<?= $p['id'] ?>">
    <div class="modal">
        <div class="modal-header">
            <h3>Pedido #<?= $p['id'] ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('det<?= $p['id'] ?>')">✕</button>
        </div>
        <div class="modal-body">
            <?php
            $stmtL = $conn->prepare("SELECT pl.nombre, pl.cantidad, i.proveedor FROM pedidos_lineas pl LEFT JOIN inventario i ON i.id = pl.inventario_id WHERE pl.pedido_id = ?");
            $stmtL->bind_param('i', $p['id']);
            $stmtL->execute();
            $lres = $stmtL->get_result();
            ?>
            <table>
                <thead><tr><th>Artículo</th><th>Cantidad</th><th>Proveedor</th></tr></thead>
                <tbody>
                <?php while ($l = $lres->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['nombre']) ?></td>
                        <td><?= $l['cantidad'] ?></td>
                        <td class="text-gris"><?= htmlspecialchars($l['proveedor'] ?? '—') ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php if ($p['notas']): ?>
                <div class="alerta alerta-aviso" style="margin-top:12px;">
                    <strong>Notas:</strong> <?= htmlspecialchars($p['notas']) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button class="btn btn-gris" onclick="cerrarModal('det<?= $p['id'] ?>')">Cerrar</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="../js/app.js"></script>
<script>
setTimeout(function() { location.reload(); }, 300000);
</script>
</body></html>
                                                                                  