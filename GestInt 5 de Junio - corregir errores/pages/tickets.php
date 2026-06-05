<?php
// Tickets de soporte: gestión para el equipo de soporte IT
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!esSoporte()) { header('Location: dashboard.php'); exit; }

$msg  = '';
$tipo = '';
$uid  = (int)$_SESSION['usuario_id'];

// Cambiar estado o asignarse el ticket
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $tid    = (int)$_GET['id'];
    $accion = $_GET['accion'];

    if ($accion === 'asignar') {
        // Me asigno el ticket y lo pongo en proceso
        $upd = $conn->prepare("UPDATE tickets SET estado = 'en_proceso', usuario_id = ? WHERE id = ?");
        $upd->bind_param('ii', $uid, $tid);
        $upd->execute();
        $msg = "Ticket #$tid asignado a ti."; $tipo = 'ok';
    } elseif (in_array($accion, ['resuelto','abierto']) && $tid > 0) {
        $upd = $conn->prepare("UPDATE tickets SET estado = ?, usuario_id = ? WHERE id = ?");
        $upd->bind_param('sii', $accion, $uid, $tid);
        $upd->execute();
        $msg = "Ticket #$tid marcado como $accion."; $tipo = 'ok';
    }
}

// Pestaña activa
$tab = $_GET['tab'] ?? 'abiertos';

// Construir WHERE según pestaña
if ($tab === 'mios') {
    $where = "WHERE t.usuario_id = $uid AND t.estado != 'resuelto'";
} elseif ($tab === 'resueltos') {
    $where = "WHERE t.estado = 'resuelto'";
} elseif ($tab === 'todos') {
    $where = "WHERE 1=1";
} else {
    // abiertos = abiertos + en_proceso (sin asignar o con cualquier agente)
    $where = "WHERE t.estado IN ('abierto','en_proceso')";
}

$lista = $conn->query("
    SELECT t.id, t.nombre, t.email, t.asunto, t.descripcion, t.estado, t.fecha,
           u.nombre AS agente_nombre, u.apellidos AS agente_apellidos
    FROM tickets t
    LEFT JOIN usuarios u ON u.id = t.usuario_id
    $where
    ORDER BY t.fecha DESC
")->fetch_all(MYSQLI_ASSOC);

// Contar mis tickets activos para el badge
$n_mios = $conn->query("SELECT COUNT(*) FROM tickets WHERE usuario_id = $uid AND estado != 'resuelto'")->fetch_row()[0] ?? 0;

$paginaActiva = 'tickets';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Tickets de soporte</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
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

        <!-- Pestañas -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:10px 16px;">
                <div class="barra-filtros">
                    <a href="?tab=abiertos"  class="btn <?= $tab==='abiertos'  ?'btn-primario':'btn-gris' ?> btn-sm">Abiertos / En proceso</a>
                    <a href="?tab=mios"      class="btn <?= $tab==='mios'      ?'btn-primario':'btn-gris' ?> btn-sm">
                        Mis asignados <?php if($n_mios>0): ?><span style="background:rgba(255,255,255,.3);border-radius:10px;padding:1px 7px;margin-left:4px;"><?= $n_mios ?></span><?php endif; ?>
                    </a>
                    <a href="?tab=resueltos" class="btn <?= $tab==='resueltos' ?'btn-primario':'btn-gris' ?> btn-sm">Resueltos</a>
                    <a href="?tab=todos"     class="btn <?= $tab==='todos'     ?'btn-primario':'btn-gris' ?> btn-sm">Todos</a>
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
                        <div class="d-flex gap-8" style="flex-wrap:wrap;">
                            <?php if ($t['estado'] === 'abierto'): ?>
                                <!-- Asignarme el ticket -->
                                <a href="?accion=asignar&id=<?= $t['id'] ?>&tab=<?= $tab ?>"
                                   class="btn btn-primario btn-sm">Asignarme</a>
                            <?php elseif ($t['estado'] === 'en_proceso'): ?>
                                <a href="#" class="btn btn-verde btn-sm"
                                   onclick="confirmarLink('¿Marcar ticket como resuelto?', '?accion=resuelto&id=<?= $t['id'] ?>&tab=<?= $tab ?>', 'verde')">Resolver</a>
                            <?php else: ?>
                                <a href="?accion=abierto&id=<?= $t['id'] ?>&tab=<?= $tab ?>"
                                   class="btn btn-gris btn-sm">Reabrir</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-gris text-center" style="padding:28px;">No hay tickets en esta sección.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</div>

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
