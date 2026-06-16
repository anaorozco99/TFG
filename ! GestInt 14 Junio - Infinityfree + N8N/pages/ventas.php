<?php
// Ventas a clientes: crear pedidos seleccionando cliente y artículos del inventario
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!puedeCrearPedidosClientes()) { header('Location: dashboard.php'); exit; }

if (!isset($_SESSION['carrito_ventas'])) $_SESSION['carrito_ventas'] = [];
if (!isset($_SESSION['cliente_ventas'])) $_SESSION['cliente_ventas'] = 0;

$msg  = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cliente') {
        $_SESSION['cliente_ventas'] = (int)($_POST['cliente_id'] ?? 0);

    } elseif ($accion === 'añadir') {
        $inv_id   = (int)($_POST['inventario_id'] ?? 0);
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
        if ($inv_id > 0) {
            $s = $conn->prepare("SELECT id, nombre, precio_unitario, cantidad AS stock, unidad FROM inventario WHERE id = ?");
            $s->bind_param('i', $inv_id);
            $s->execute();
            $art = $s->get_result()->fetch_assoc();
            if ($art) {
                $ya_en_carrito = $_SESSION['carrito_ventas'][$inv_id]['cantidad'] ?? 0;
                if ($ya_en_carrito + $cantidad > $art['stock']) {
                    $msg  = 'Stock insuficiente. Disponible: ' . ($art['stock'] - $ya_en_carrito) . ' ' . $art['unidad'] . '.';
                    $tipo = 'error';
                } else {
                    if (isset($_SESSION['carrito_ventas'][$inv_id])) {
                        $_SESSION['carrito_ventas'][$inv_id]['cantidad'] += $cantidad;
                    } else {
                        $_SESSION['carrito_ventas'][$inv_id] = [
                            'inventario_id'   => $inv_id,
                            'nombre'          => $art['nombre'],
                            'cantidad'        => $cantidad,
                            'precio_unitario' => (float)$art['precio_unitario'],
                        ];
                    }
                    $msg  = '"' . htmlspecialchars($art['nombre']) . '" añadido.';
                    $tipo = 'ok';
                }
            }
        }

    } elseif ($accion === 'cambiar') {
        $inv_id   = (int)($_POST['inventario_id'] ?? 0);
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
        if (isset($_SESSION['carrito_ventas'][$inv_id])) {
            $_SESSION['carrito_ventas'][$inv_id]['cantidad'] = $cantidad;
        }

    } elseif ($accion === 'eliminar') {
        $inv_id = (int)($_POST['inventario_id'] ?? 0);
        unset($_SESSION['carrito_ventas'][$inv_id]);

    } elseif ($accion === 'vaciar') {
        $_SESSION['carrito_ventas'] = [];
        $msg = 'Carrito vaciado.'; $tipo = 'aviso';

    } elseif ($accion === 'confirmar') {
        $cliente_id = (int)($_SESSION['cliente_ventas'] ?? 0);
        if (empty($_SESSION['carrito_ventas'])) {
            $msg = 'El carrito está vacío.'; $tipo = 'error';
        } elseif ($cliente_id <= 0) {
            $msg = 'Selecciona un cliente antes de confirmar.'; $tipo = 'error';
        } else {
            $notas = trim($_POST['notas'] ?? '');
            $uid   = $_SESSION['usuario_id'];

            // verificar stock antes de confirmar
            $error_stock = '';
            foreach ($_SESSION['carrito_ventas'] as $l) {
                $sc = $conn->prepare("SELECT cantidad FROM inventario WHERE id = ?");
                $sc->bind_param('i', $l['inventario_id']);
                $sc->execute();
                $stock_actual = $sc->get_result()->fetch_row()[0] ?? 0;
                if ($l['cantidad'] > $stock_actual) {
                    $error_stock = 'Stock insuficiente para "' . $l['nombre'] . '". Disponible: ' . $stock_actual . '.';
                    break;
                }
            }
            if ($error_stock) {
                $msg = $error_stock; $tipo = 'error';
            } else {

            $total = 0.0;
            foreach ($_SESSION['carrito_ventas'] as $l) {
                $total += $l['cantidad'] * $l['precio_unitario'];
            }
            // crear pedido
            $s = $conn->prepare("INSERT INTO pedidos_ventas (cliente_id, usuario_id, notas, total) VALUES (?,?,?,?)");
            $s->bind_param('iisd', $cliente_id, $uid, $notas, $total);
            $s->execute();
            $pedido_id = $conn->insert_id;

            $sL = $conn->prepare("INSERT INTO pedidos_ventas_lineas (pedido_id, inventario_id, nombre, cantidad, precio_unitario) VALUES (?,?,?,?,?)");
            $sS = $conn->prepare("UPDATE inventario SET cantidad = cantidad - ? WHERE id = ?");
            $carrito_snapshot = $_SESSION['carrito_ventas']; // guardar antes de vaciar
            foreach ($carrito_snapshot as $l) {
                $sL->bind_param('iisid', $pedido_id, $l['inventario_id'], $l['nombre'], $l['cantidad'], $l['precio_unitario']);
                $sL->execute();
                $sS->bind_param('ii', $l['cantidad'], $l['inventario_id']);
                $sS->execute();
            }
            $_SESSION['carrito_ventas'] = [];
            $_SESSION['cliente_ventas'] = 0;
            $msg  = "Pedido #$pedido_id confirmado. Total: " . number_format($total, 2, ',', '.') . ' €';
            $tipo = 'ok';

            // ── N8N: notificar pedido a cliente ──────────────────────────────
            require_once '../includes/n8n.php';
            // Obtener nombre del cliente para el webhook
            $sc_n = $conn->prepare("SELECT nombre, empresa FROM clientes WHERE id = ?");
            $sc_n->bind_param('i', $cliente_id);
            $sc_n->execute();
            $cli_data_n = $sc_n->get_result()->fetch_assoc();
            $cli_nombre_webhook = $cli_data_n
                ? $cli_data_n['nombre'] . ($cli_data_n['empresa'] ? ' — ' . $cli_data_n['empresa'] : '')
                : '';
            n8n_notify(N8N_WEBHOOK_PEDIDO_CLIENTE, [
                'pedido_id' => $pedido_id,
                'cliente'   => $cli_nombre_webhook,
                'total'     => $total,
                'usuario'   => $_SESSION['nombre'] ?? '',
                'fecha'     => date('d/m/Y H:i'),
            ]);

            // ── N8N: alertar stock bajo si algún artículo bajó del mínimo ────
            // Se usa IN en una sola query para evitar problemas con result sets en bucle
            $ids = implode(',', array_map(fn($l) => (int)$l['inventario_id'], $carrito_snapshot));
            $bajo_res = $conn->query("SELECT nombre, cantidad, stock_minimo FROM inventario WHERE id IN ($ids) AND cantidad <= stock_minimo");
            while ($row = $bajo_res->fetch_assoc()) {
                n8n_notify(N8N_WEBHOOK_STOCK_BAJO, [
                    'producto'     => $row['nombre'],
                    'cantidad'     => $row['cantidad'],
                    'stock_minimo' => $row['stock_minimo'],
                    'fecha'        => date('d/m/Y H:i'),
                ]);
            }
            } // fin else stock ok
        }
    }
}

$articulos = $conn->query("SELECT id, nombre, precio_unitario, cantidad, stock_minimo, unidad FROM inventario WHERE cantidad > 0 ORDER BY nombre");
$cli_res   = $conn->query("SELECT id, nombre, empresa FROM clientes WHERE activo = 1 ORDER BY nombre");
$cliente_sel = (int)($_SESSION['cliente_ventas'] ?? 0);

// Nombre del cliente seleccionado
$cli_nombre_sel = '';
if ($cliente_sel > 0) {
    $sc = $conn->prepare("SELECT nombre, empresa FROM clientes WHERE id = ?");
    $sc->bind_param('i', $cliente_sel);
    $sc->execute();
    $cli_data = $sc->get_result()->fetch_assoc();
    if ($cli_data) $cli_nombre_sel = $cli_data['nombre'] . ($cli_data['empresa'] ? ' — ' . $cli_data['empresa'] : '');
}

$total_carrito = 0.0;
foreach ($_SESSION['carrito_ventas'] as $l) {
    $total_carrito += $l['cantidad'] * $l['precio_unitario'];
}

$paginaActiva = 'ventas';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Ventas a clientes</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
    <div class="topbar">
        <h1>Ventas a clientes</h1>
        <div class="topbar-right">
            <a href="pedidos_ventas.php" class="btn btn-gris btn-sm">Ver pedidos</a>
            <?php if (!empty($_SESSION['carrito_ventas'])): ?>
                <span class="badge badge-azul"><?= count($_SESSION['carrito_ventas']) ?> artículo(s)</span>
            <?php endif; ?>
        </div>
    </div>
    <main class="main">
        <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div><?php endif; ?>

        <!-- Selección de cliente -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header">
                <h2>Cliente del pedido</h2>
                <?php if (!$cli_nombre_sel): ?>
                    <span class="badge badge-rojo">Sin cliente seleccionado</span>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <form method="POST" class="barra-filtros">
                    <input type="hidden" name="accion" value="cliente">
                    <select name="cliente_id" required style="min-width:260px;">
                        <option value="0">-- Selecciona un cliente --</option>
                        <?php while ($c = $cli_res->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $cliente_sel === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?><?= $c['empresa'] ? ' — '.$c['empresa'] : '' ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <!-- Si ya hay cliente seleccionado, el botón aparece en verde como confirmación -->
                    <?php if ($cliente_sel > 0): ?>
                        <button type="submit" class="btn btn-sm" style="background:var(--verde);color:#fff;">✓ Seleccionado</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primario btn-sm">Seleccionar</button>
                    <?php endif; ?>
                    <a href="clientes.php" class="btn btn-gris btn-sm" style="font-size:12px;">+ Nuevo cliente</a>
                </form>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px;align-items:start;">

            <!-- Añadir artículo -->
            <div class="panel">
                <div class="panel-header"><h2>Añadir artículo</h2></div>
                <div class="panel-body">
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="accion" value="añadir">
                        <div class="form-grupo full">
                            <label>Buscar artículo</label>
                            <input type="text" id="filtro-art"
                                   placeholder="🔍 Escribe para filtrar..."
                                   oninput="filtrarArts(this.value)"
                                   autocomplete="off">
                        </div>
                        <div class="form-grupo full">
                            <label>Artículo</label>
                            <select name="inventario_id" id="sel-art" required size="7"
                                    style="height:auto;overflow-y:auto;">
                                <?php
                                $articulos->data_seek(0);
                                while ($a = $articulos->fetch_assoc()):
                                    $bajo = $a['cantidad'] <= $a['stock_minimo'];
                                ?>
                                <option value="<?= $a['id'] ?>" data-precio="<?= $a['precio_unitario'] ?>">
                                    <?= htmlspecialchars($a['nombre']) ?>
                                    (<?= $a['cantidad'] ?> <?= htmlspecialchars($a['unidad']) ?>
                                    — <?= number_format($a['precio_unitario'],2,',','.') ?> €)
                                    <?= $bajo ? ' ⚠' : '' ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-grupo full">
                            <label>Cantidad</label>
                            <input type="number" name="cantidad" min="1" value="1" required>
                        </div>
                        <div class="form-grupo full">
                            <button type="submit" class="btn btn-primario w-100">+ Añadir al carrito</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Carrito -->
            <div class="panel">
                <div class="panel-header">
                    <h2>Carrito</h2>
                    <?php if (!empty($_SESSION['carrito_ventas'])): ?>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="accion" value="vaciar">
                        <button type="button" class="btn btn-gris btn-sm"
                                onclick="confirmar('¿Vaciar el carrito?', () => this.closest('form').submit())">🗑 Vaciar</button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($_SESSION['carrito_ventas'])): ?>
                    <div class="panel-body text-gris text-center" style="padding:30px;">El carrito está vacío.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Artículo</th>
                                <th style="width:110px;">Cantidad</th>
                                <th style="text-align:right;">Precio u.</th>
                                <th style="text-align:right;">Subtotal</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($_SESSION['carrito_ventas'] as $inv_id => $linea):
                            $subtotal = $linea['cantidad'] * $linea['precio_unitario'];
                        ?>
                        <tr>
                            <td class="fw-600"><?= htmlspecialchars($linea['nombre']) ?></td>
                            <td>
                                <form method="POST" style="display:flex;gap:4px;">
                                    <input type="hidden" name="accion" value="cambiar">
                                    <input type="hidden" name="inventario_id" value="<?= $inv_id ?>">
                                    <input type="number" name="cantidad" min="1"
                                           value="<?= $linea['cantidad'] ?>"
                                           style="width:70px;padding:4px 8px;"
                                           onchange="this.form.submit()">
                                </form>
                            </td>
                            <td style="text-align:right;font-size:13px;"><?= number_format($linea['precio_unitario'],2,',','.') ?> €</td>
                            <td style="text-align:right;font-weight:600;"><?= number_format($subtotal,2,',','.') ?> €</td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="inventario_id" value="<?= $inv_id ?>">
                                    <button type="submit" class="btn btn-rojo btn-sm">✕</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f8fafc;">
                            <td colspan="3" style="text-align:right;font-weight:700;padding:12px 14px;">TOTAL</td>
                            <td style="text-align:right;font-weight:800;font-size:16px;padding:12px 14px;">
                                <?= number_format($total_carrito,2,',','.') ?> €
                            </td>
                            <td></td>
                        </tr>
                        </tbody>
                    </table>

                    <div class="panel-body" style="border-top:1px solid var(--gris-borde);">
                        <form method="POST" class="form-grid">
                            <input type="hidden" name="accion" value="confirmar">
                            <div class="form-grupo full">
                                <label>Notas (opcional)</label>
                                <textarea name="notas" placeholder="Ej: entrega urgente, dirección especial..."></textarea>
                            </div>
                            <div class="form-grupo full">
                                <div class="alerta alerta-aviso" style="margin-bottom:10px;">
                                    📦 El pedido llegará al cliente en <strong>48 horas</strong>. El stock se descuenta al confirmar.
                                </div>
                                <?php if (!$cliente_sel): ?>
                                    <div class="alerta alerta-error">Selecciona un cliente antes de confirmar.</div>
                                <?php else: ?>
                                    <button type="button" class="btn btn-verde w-100" style="padding:14px;font-size:15px;"
                                            onclick="confirmar('¿Confirmar el pedido?', () => this.closest('form').submit(), 'verde')">
                                        Confirmar pedido (<?= count($_SESSION['carrito_ventas']) ?> artículos)
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</div>

<script>
// Filtra las opciones del select de artículos según lo que se escribe
function filtrarArts(val) {
    var sel = document.getElementById('sel-art');
    var texto = val.toLowerCase();
    for (var i = 0; i < sel.options.length; i++) {
        var op = sel.options[i];
        op.style.display = op.text.toLowerCase().includes(texto) ? '' : 'none';
    }
}
</script>
<script src="../js/app.js"></script>
</body></html>
