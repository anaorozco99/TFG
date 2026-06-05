<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$msg  = '';
$tipo = '';

// Solo admin y almacén pueden cambiar el estado de los pedidos
$puede_cambiar_estado = esAdmin() || esAlmacen();

// Procesar cambio de estado o cancelación
if (isset($_GET['accion']) && isset($_GET['id']) && $puede_cambiar_estado) {
    $pid   = (int)$_GET['id'];
    $nueva = $_GET['accion'];

    // Estados permitidos: enviado, entregado, cancelado
    if (in_array($nueva, ['enviado', 'entregado', 'cancelado']) && $pid > 0) {

        // Cancelar solo si el pedido no ha sido enviado aún
        if ($nueva === 'cancelado') {
            $check = $conn->prepare("SELECT estado FROM pedidos_ventas WHERE id = ?");
            $check->bind_param('i', $pid);
            $check->execute();
            $estado_actual = $check->get_result()->fetch_row()[0] ?? '';
            if ($estado_actual === 'enviado' || $estado_actual === 'entregado') {
                $msg  = "No se puede cancelar un pedido que ya ha sido enviado.";
                $tipo = 'error';
                $nueva = ''; // no hacer el update
            }
        }

        if ($nueva !== '') {
            $upd = $conn->prepare("UPDATE pedidos_ventas SET estado = ? WHERE id = ?");
            $upd->bind_param('si', $nueva, $pid);
            $upd->execute();
            $msg  = "Pedido #$pid marcado como $nueva.";
            $tipo = 'ok';
        }
    }
}

// Filtros activos
$filtro          = $_GET['filtro']          ?? 'todos';
$filtro_cliente  = (int)($_GET['cliente']   ?? 0);
$filtro_vendedor = (int)($_GET['vendedor']  ?? 0);

// Construir WHERE según filtros
$where = "WHERE 1=1";
if ($filtro === 'activos')    $where .= " AND pv.estado NOT IN ('entregado','cancelado')";
if ($filtro === 'entregados') $where .= " AND pv.estado = 'entregado'";
if ($filtro === 'cancelados') $where .= " AND pv.estado = 'cancelado'";
if ($filtro_cliente  > 0)     $where .= " AND pv.cliente_id  = $filtro_cliente";
if ($filtro_vendedor > 0)     $where .= " AND pv.usuario_id  = $filtro_vendedor";

$res = $conn->query("
    SELECT pv.id, pv.estado, pv.fecha_pedido, pv.fecha_entrega, pv.notas, pv.total,
           c.id AS cid, c.nombre AS cli_nombre, c.empresa AS cli_empresa,
           u.id AS uid, u.nombre AS vend_nombre, u.apellidos AS vend_apellidos,
           COUNT(pvl.id) AS num_lineas, SUM(pvl.cantidad) AS total_uds
    FROM pedidos_ventas pv
    JOIN clientes c ON c.id = pv.cliente_id
    JOIN usuarios u ON u.id = pv.usuario_id
    LEFT JOIN pedidos_ventas_lineas pvl ON pvl.pedido_id = pv.id
    $where
    GROUP BY pv.id, pv.estado, pv.fecha_pedido, pv.fecha_entrega, pv.notas, pv.total,
             c.id, c.nombre, c.empresa, u.id, u.nombre, u.apellidos
    ORDER BY pv.fecha_pedido DESC
");
$lista = $res->fetch_all(MYSQLI_ASSOC);

// Listas para los selectores de filtro (todos los clientes y vendedores que tengan pedidos)
$clientes_filter  = $conn->query("SELECT DISTINCT c.id, c.nombre FROM clientes c JOIN pedidos_ventas pv ON pv.cliente_id = c.id ORDER BY c.nombre");
$vendedores_filter = $conn->query("SELECT DISTINCT u.id, u.nombre, u.apellidos FROM usuarios u JOIN pedidos_ventas pv ON pv.usuario_id = u.id ORDER BY u.nombre");

// Parámetros para mantener el filtro activo al navegar
$qs = http_build_query(array_filter([
    'filtro'   => $filtro !== 'todos' ? $filtro : null,
    'cliente'  => $filtro_cliente  ?: null,
    'vendedor' => $filtro_vendedor ?: null,
]));
$qs = $qs ? "&$qs" : '';

$paginaActiva = 'pedidos_ventas';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Pedidos clientes</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
    <div class="topbar">
        <h1>Pedidos clientes</h1>
        <div class="topbar-right">
            <a href="ventas.php" class="btn btn-primario">Nuevo pedido</a>
        </div>
    </div>
    <main class="main">
        <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- Filtros: estado, cliente, vendedor -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:10px 16px;">
                <form method="GET" class="barra-filtros" style="flex-wrap:wrap;gap:8px;">

                    <!-- Pestañas de estado -->
                    <div class="d-flex gap-8">
                        <a href="?filtro=todos<?= $filtro_cliente?'&cliente='.$filtro_cliente:'' ?><?= $filtro_vendedor?'&vendedor='.$filtro_vendedor:'' ?>"
                           class="btn <?= $filtro==='todos'     ?'btn-primario':'btn-gris' ?> btn-sm">Todos</a>
                        <a href="?filtro=activos<?= $filtro_cliente?'&cliente='.$filtro_cliente:'' ?><?= $filtro_vendedor?'&vendedor='.$filtro_vendedor:'' ?>"
                           class="btn <?= $filtro==='activos'   ?'btn-primario':'btn-gris' ?> btn-sm">En curso</a>
                        <a href="?filtro=entregados<?= $filtro_cliente?'&cliente='.$filtro_cliente:'' ?><?= $filtro_vendedor?'&vendedor='.$filtro_vendedor:'' ?>"
                           class="btn <?= $filtro==='entregados'?'btn-primario':'btn-gris' ?> btn-sm">Entregados</a>
                        <a href="?filtro=cancelados<?= $filtro_cliente?'&cliente='.$filtro_cliente:'' ?><?= $filtro_vendedor?'&vendedor='.$filtro_vendedor:'' ?>"
                           class="btn <?= $filtro==='cancelados'?'btn-primario':'btn-gris' ?> btn-sm">Cancelados</a>
                    </div>

                    <!-- Filtro por cliente -->
                    <select name="cliente" onchange="this.form.submit()" style="min-width:180px;">
                        <option value="0">Todos los clientes</option>
                        <?php while ($cf = $clientes_filter->fetch_assoc()): ?>
                            <option value="<?= $cf['id'] ?>" <?= $filtro_cliente === (int)$cf['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cf['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <!-- Filtro por vendedor -->
                    <select name="vendedor" onchange="this.form.submit()" style="min-width:180px;">
                        <option value="0">Todos los vendedores</option>
                        <?php while ($vf = $vendedores_filter->fetch_assoc()): ?>
                            <option value="<?= $vf['id'] ?>" <?= $filtro_vendedor === (int)$vf['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vf['nombre'].' '.$vf['apellidos']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <!-- Mantener filtro de estado en el select -->
                    <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro) ?>">

                    <?php if ($filtro_cliente || $filtro_vendedor): ?>
                        <a href="?filtro=<?= $filtro ?>" class="btn btn-gris btn-sm">✕ Limpiar filtros</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="panel">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Cliente</th><th>Vendedor</th>
                        <th>Fecha pedido</th><th>Entrega estimada</th>
                        <th>Artículos</th><th>Total</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($lista)): foreach ($lista as $p): ?>
                <tr>
                    <td class="fw-600">#<?= $p['id'] ?></td>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($p['cli_nombre']) ?></div>
                        <?php if ($p['cli_empresa']): ?>
                            <div class="text-gris"><?= htmlspecialchars($p['cli_empresa']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-gris"><?= htmlspecialchars($p['vend_nombre'].' '.$p['vend_apellidos']) ?></td>
                    <td class="text-gris"><?= date('d/m/Y H:i', strtotime($p['fecha_pedido'])) ?></td>
                    <td class="text-gris"><?= date('d/m/Y H:i', strtotime($p['fecha_entrega'])) ?></td>
                    <td>
                        <span class="badge badge-azul"><?= $p['num_lineas'] ?> ref.</span>
                        <span class="text-gris"><?= $p['total_uds'] ?> uds.</span>
                    </td>
                    <td class="fw-600"><?= number_format($p['total'],2,',','.') ?> €</td>
                    <td>
                        <?php if ($p['estado'] === 'entregado'): ?>
                            <span class="badge badge-verde">Entregado</span>
                        <?php elseif ($p['estado'] === 'enviado'): ?>
                            <span class="badge badge-azul">Enviado</span>
                        <?php elseif ($p['estado'] === 'cancelado'): ?>
                            <span class="badge badge-rojo">Cancelado</span>
                        <?php else: ?>
                            <span class="badge badge-naranja">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-8" style="flex-wrap:wrap;">
                            <!-- Ver detalle siempre disponible -->
                            <a href="#" onclick="abrirModal('det<?= $p['id'] ?>'); return false;"
                               class="btn btn-gris btn-sm">Ver</a>

                            <!-- Factura si ya fue enviado o entregado -->
                            <?php if (in_array($p['estado'], ['enviado','entregado'])): ?>
                                <a href="factura.php?id=<?= $p['id'] ?>" target="_blank"
                                   class="btn btn-gris btn-sm">🖨️ Factura</a>
                            <?php endif; ?>

                            <?php if ($puede_cambiar_estado): ?>

                                <!-- Etiqueta de envío: solo cuando el pedido aún no ha sido enviado -->
                                <?php if ($p['estado'] === 'pendiente'): ?>
                                    <a href="etiqueta.php?id=<?= $p['id'] ?>" target="_blank"
                                       class="btn btn-gris btn-sm" title="Etiqueta de envío">📦 Etiqueta</a>
                                <?php endif; ?>

                                <!-- Cambiar estado: pendiente → enviado → entregado -->
                                <?php if ($p['estado'] === 'pendiente'): ?>
                                    <a href="#" class="btn btn-primario btn-sm"
                                       onclick="confirmarLink('¿Marcar como enviado?', '?accion=enviado&id=<?= $p['id'] ?><?= $qs ?>')">Enviado</a>
                                    <!-- Cancelar solo mientras no se haya enviado -->
                                    <a href="#" class="btn btn-rojo btn-sm"
                                       onclick="confirmarLink('¿Cancelar el pedido #<?= $p['id'] ?>? Esta acción no se puede deshacer.', '?accion=cancelado&id=<?= $p['id'] ?><?= $qs ?>', 'rojo')">Cancelar</a>
                                <?php elseif ($p['estado'] === 'enviado'): ?>
                                    <a href="#" class="btn btn-verde btn-sm"
                                       onclick="confirmarLink('¿Confirmar entrega al cliente?', '?accion=entregado&id=<?= $p['id'] ?><?= $qs ?>', 'verde')">Entregado</a>
                                <?php endif; ?>

                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-gris text-center" style="padding:28px;">No hay pedidos.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</div>

<?php foreach ($lista as $p):
    $lineas_s = $conn->prepare("SELECT pvl.*, i.proveedor FROM pedidos_ventas_lineas pvl LEFT JOIN inventario i ON i.id = pvl.inventario_id WHERE pvl.pedido_id = ?");
    $lineas_s->bind_param('i', $p['id']);
    $lineas_s->execute();
    $lineas = $lineas_s->get_result();
?>
<div class="modal-overlay" id="det<?= $p['id'] ?>">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3>Pedido #<?= $p['id'] ?> — <?= htmlspecialchars($p['cli_nombre']) ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('det<?= $p['id'] ?>')">✕</button>
        </div>
        <div class="modal-body">
            <table>
                <thead><tr><th>Artículo</th><th>Proveedor</th><th style="text-align:right;">Cant.</th><th style="text-align:right;">Precio u.</th><th style="text-align:right;">Subtotal</th></tr></thead>
                <tbody>
                <?php while ($l = $lineas->fetch_assoc()):
                    $sub = $l['cantidad'] * $l['precio_unitario'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($l['nombre']) ?></td>
                    <td class="text-gris"><?= htmlspecialchars($l['proveedor'] ?? '—') ?></td>
                    <td style="text-align:right;"><?= $l['cantidad'] ?></td>
                    <td style="text-align:right;"><?= number_format($l['precio_unitario'],2,',','.') ?> €</td>
                    <td style="text-align:right;font-weight:600;"><?= number_format($sub,2,',','.') ?> €</td>
                </tr>
                <?php endwhile; ?>
                <tr style="background:#f8fafc;">
                    <td colspan="4" style="text-align:right;font-weight:700;">TOTAL</td>
                    <td style="text-align:right;font-weight:800;"><?= number_format($p['total'],2,',','.') ?> €</td>
                </tr>
                </tbody>
            </table>
            <?php if ($p['notas']): ?>
                <div class="alerta alerta-aviso" style="margin-top:12px;">
                    <strong>Notas:</strong> <?= htmlspecialchars($p['notas']) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <?php if (in_array($p['estado'], ['enviado','entregado'])): ?>
                <a href="factura.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-gris">🖨️ Ver factura</a>
            <?php endif; ?>
            <button class="btn btn-primario" onclick="cerrarModal('det<?= $p['id'] ?>')">Cerrar</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="../js/app.js"></script>
</body></html>
