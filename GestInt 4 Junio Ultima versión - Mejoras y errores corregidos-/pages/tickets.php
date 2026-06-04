<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Solo soporte IT puede entrar en esta sección
if (!esSoporte()) { header('Location: dashboard.php'); exit; }

$msg  = '';
$tipo = '';

// Cambiar estado de un ticket
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $tid    = (int)$_GET['id'];
    $nueva  = $_GET['accion'];
    if (in_array($nueva, ['en_proceso', 'resuelto', 'abierto']) && $tid > 0) {
        $upd = $conn->prepare("UPDATE tickets SET estado = ?, usuario_id = ? WHERE id = ?");
        $uid = (int)$_SESSION['usuario_id'];
        $upd->bind_param('sii', $nueva, $uid, $tid);
        $upd->execute();
        $msg  = "Ticket #$tid actualizado.";
        $tipo = 'ok';
    }
}

// Filtro de estado
$filtro = $_GET['filtro'] ?? 'abiertos';
$where  = "WHERE 1=1";
if ($filtro === 'abiertos')    $where .= " AND t.estado IN ('abierto','en_proceso')";
if ($filtro === 'resueltos')   $where .= " AND t.estado = 'resuelto'";

$lista = $conn->query("
    SELECT t.id, t.nombre, t.email, t.asunto, t.descripcion, t.estado, t.fecha,
           u.nombre AS agente_nombre, u.apellidos AS agente_apellidos
    FROM tickets t
    LEFT JOIN usuarios u ON u.id = t.usuario_id
    $where
    ORDER BY t.fecha DESC
")->fetch_all(MYSQLI_ASSOC);

$paginaActiva = 'tickets';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Tickets de soporte</title>
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
    <div class="topbar">
        <h1>Tickets de soporte</h1>
        <div class="topbar-right text-gris"><?= count($lista) ?> tickets</div>
    </div>
    <main class="main">
        <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- Filtros de estado -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:10px 16px;">
                <div class="barra-filtros">
                    <a href="?filtro=abiertos"  class="btn <?= $filtro==='abiertos'  ?'btn-primario':'btn-gris' ?> btn-sm">Abiertos / En proceso</a>
                    <a href="?filtro=todos"     class="btn <?= $filtro==='todos'     ?'btn-primario':'btn-gris' ?> btn-sm">Todos</a>
                    <a href="?filtro=resueltos" class="btn <?= $filtro==='resueltos' ?'btn-primario':'btn-gris' ?> btn-sm">Resueltos</a>
                </div>
            </div>
        </div>

        <div class="panel">
            <table>
                <thead>
                    <tr><th>#</th><th>Solicitante</th><th>Asunto</th><th>Estado</th><th>Agente</th><th>Fecha</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php if (!empty($lista)): foreach ($lista as $t): ?>
                <tr>
                    <td class="fw-600">#<?= $t['id'] ?></td>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($t['nombre']) ?></div>
                        <div class="text-gris"><?= htmlspecialchars($t['email']) ?></div>
                    </td>
                    <td>
                        <a href="#" onclick="abrirModal('tick<?= $t['id'] ?>'); return false;"
                           style="color:var(--azul-medio);font-weight:600;">
                            <?= htmlspecialchars($t['asunto']) ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($t['estado'] === 'resuelto'): ?>
                            <span class="badge badge-verde">Resuelto</span>
                        <?php elseif ($t['estado'] === 'en_proceso'): ?>
                            <span class="badge badge-azul">En proceso</span>
                        <?php else: ?>
                            <span class="badge badge-naranja">Abierto</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-gris">
                        <?= $t['agente_nombre'] ? htmlspecialchars($t['agente_nombre'].' '.$t['agente_apellidos']) : '—' ?>
                    </td>
                    <td class="text-gris"><?= date('d/m/Y H:i', strtotime($t['fecha'])) ?></td>
                    <td>
                        <div class="d-flex gap-8">
                            <!-- Botones de cambio de estado según el estado actual -->
                            <?php if ($t['estado'] === 'abierto'): ?>
                                <a href="?accion=en_proceso&id=<?= $t['id'] ?>&filtro=<?= $filtro ?>"
                                   class="btn btn-primario btn-sm">Asignar</a>
                            <?php elseif ($t['estado'] === 'en_proceso'): ?>
                                <a href="#" class="btn btn-verde btn-sm"
                                   onclick="confirmarLink('¿Marcar ticket como resuelto?', '?accion=resuelto&id=<?= $t['id'] ?>&filtro=<?= $filtro ?>', 'verde')">Resolver</a>
                            <?php else: ?>
                                <a href="?accion=abierto&id=<?= $t['id'] ?>&filtro=<?= $filtro ?>"
                                   class="btn btn-gris btn-sm">Reabrir</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-gris text-center" style="padding:28px;">No hay tickets.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</div>

<!-- Modales de detalle de cada ticket -->
<?php foreach ($lista as $t): ?>
<div class="modal-overlay" id="tick<?= $t['id'] ?>">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <h3>Ticket #<?= $t['id'] ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('tick<?= $t['id'] ?>')">✕</button>
        </div>
        <div class="modal-body">
            <p><strong>Solicitante:</strong> <?= htmlspecialchars($t['nombre']) ?> (<?= htmlspecialchars($t['email']) ?>)</p>
            <p style="margin-top:8px;"><strong>Asunto:</strong> <?= htmlspecialchars($t['asunto']) ?></p>
            <div style="margin-top:12px;padding:12px;background:var(--gris-fondo);border-radius:6px;font-size:14px;line-height:1.6;">
                <?= nl2br(htmlspecialchars($t['descripcion'])) ?>
            </div>
            <p style="margin-top:12px;" class="text-gris">Recibido: <?= date('d/m/Y H:i', strtotime($t['fecha'])) ?></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primario" onclick="cerrarModal('tick<?= $t['id'] ?>')">Cerrar</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="../js/app.js"></script>
</body></html>
