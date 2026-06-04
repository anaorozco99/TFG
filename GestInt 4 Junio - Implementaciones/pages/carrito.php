<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!esAlmacen()) { header('Location: dashboard.php'); exit; }

if (!isset($_SESSION['carrito'])) $_SESSION['carrito'] = [];

$msg  = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'añadir') {
        $inv_id   = (int)($_POST['inventario_id'] ?? 0);
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
        if ($inv_id > 0) {
            $stmt = $conn->prepare("SELECT id, nombre FROM inventario WHERE id = ?");
            $stmt->bind_param('i', $inv_id);
            $stmt->execute();
            $art = $stmt->get_result()->fetch_assoc();
            if ($art) {
                if (isset($_SESSION['carrito'][$inv_id])) {
                    $_SESSION['carrito'][$inv_id]['cantidad'] += $cantidad;
                } else {
                    $_SESSION['carrito'][$inv_id] = ['inventario_id'=>$inv_id,'nombre'=>$art['nombre'],'cantidad'=>$cantidad];
                }
                $msg = '"'.htmlspecialchars($art['nombre']).'" añadido al carrito.'; $tipo = 'ok';
            }
        }

    } elseif ($accion === 'cambiar') {
        $inv_id   = (int)($_POST['inventario_id'] ?? 0);
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
        if (isset($_SESSION['carrito'][$inv_id])) {
            $_SESSION['carrito'][$inv_id]['cantidad'] = $cantidad;
        }

    } elseif ($accion === 'eliminar') {
        $inv_id = (int)($_POST['inventario_id'] ?? 0);
        unset($_SESSION['carrito'][$inv_id]);
        $msg = 'Artículo eliminado del carrito.'; $tipo = 'ok';

    } elseif ($accion === 'vaciar') {
        $_SESSION['carrito'] = [];
        $msg = 'Carrito vaciado.'; $tipo = 'aviso';

    } elseif ($accion === 'confirmar') {
        if (empty($_SESSION['carrito'])) {
            $msg = 'El carrito está vacío.'; $tipo = 'error';
        } else {
            $notas = trim($_POST['notas'] ?? '');
            $uid   = $_SESSION['usuario_id'];
            $stmt  = $conn->prepare("INSERT INTO pedidos (usuario_id, estado, notas) VALUES (?, 'pendiente', ?)");
            $stmt->bind_param('is', $uid, $notas);
            $stmt->execute();
            $pedido_id = $conn->insert_id;
            $stmtL = $conn->prepare("INSERT INTO pedidos_lineas (pedido_id, inventario_id, nombre, cantidad) VALUES (?,?,?,?)");
            foreach ($_SESSION['carrito'] as $linea) {
                $stmtL->bind_param('iisi', $pedido_id,$linea['inventario_id'],$linea['nombre'],$linea['cantidad']);
                $stmtL->execute();
            }
            $_SESSION['carrito'] = [];
            $msg  = "Pedido #$pedido_id confirmado. Llegará en 24 horas.";
            $tipo = 'ok';
        }
    }
}

$articulos = $conn->query("SELECT id, nombre, cantidad, stock_minimo, unidad FROM inventario ORDER BY nombre");

$paginaActiva = 'carrito';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Pedir stock</title>
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="contenido">
        <div class="topbar">
            <h1>Reponer stock</h1>
            <div class="topbar-right">
                <a href="pedidos.php" class="btn btn-gris btn-sm">Ver pedidos</a>
                <?php if (!empty($_SESSION['carrito'])): ?>
                    <span class="badge badge-azul"><?= count($_SESSION['carrito']) ?> artículo(s)</span>
                <?php endif; ?>
            </div>
        </div>
        <main class="main">
            <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div><?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:20px;align-items:start;">

                <div class="panel">
                    <div class="panel-header"><h2>Añadir artículo</h2></div>
                    <div class="panel-body">
                        <form method="POST" class="form-grid">
                            <input type="hidden" name="accion" value="añadir">
                            <div class="form-grupo full">
                                <label>Buscar artículo</label>
                                <input type="text" id="filtro-stock"
                                       placeholder="🔍 Escribe para filtrar..."
                                       oninput="filtrarStock(this.value)"
                                       autocomplete="off">
                            </div>
                            <div class="form-grupo full">
                                <label>Artículo</label>
                                <select name="inventario_id" id="sel-stock" required size="8"
                                        style="height:auto;overflow-y:auto;">
                                    <?php while ($a = $articulos->fetch_assoc()): ?>
                                    <option value="<?= $a['id'] ?>">
                                        <?= htmlspecialchars($a['nombre']) ?>
                                        (stock: <?= $a['cantidad'] ?> <?= htmlspecialchars($a['unidad']) ?>)
                                        <?= $a['cantidad'] <= $a['stock_minimo'] ? ' ⚠' : '' ?>
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

                <div class="panel">
                    <div class="panel-header">
                        <h2>Carrito</h2>
                        <?php if (!empty($_SESSION['carrito'])): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="accion" value="vaciar">
                            <button type="button" class="btn btn-gris btn-sm"
                                    onclick="confirmar('¿Vaciar el carrito?', () => this.closest('form').submit())">🗑 Vaciar</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($_SESSION['carrito'])): ?>
                        <div class="panel-body text-gris text-center" style="padding:30px;">El carrito está vacío.</div>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Artículo</th><th style="width:120px;">Cantidad</th><th style="width:50px;"></th></tr></thead>
                            <tbody>
                            <?php foreach ($_SESSION['carrito'] as $inv_id => $linea): ?>
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
                                <td>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="inventario_id" value="<?= $inv_id ?>">
                                        <button type="submit" class="btn btn-rojo btn-sm">✕</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="panel-body" style="border-top:1px solid var(--gris-borde);">
                            <form method="POST" class="form-grid">
                                <input type="hidden" name="accion" value="confirmar">
                                <div class="form-grupo full">
                                    <label>Notas (opcional)</label>
                                    <textarea name="notas" placeholder="Ej: urgente, almacén norte..."></textarea>
                                </div>
                                <div class="form-grupo full">
                                    <div class="alerta alerta-aviso" style="margin-bottom:10px;">
                                        ⏱ El pedido llegará en <strong>24 horas</strong>.
                                    </div>
                                    <button type="button" class="btn btn-verde w-100" style="padding:14px;font-size:15px;"
                                            onclick="confirmar('¿Confirmar el pedido?', () => this.closest('form').submit(), 'verde')">
                                        Confirmar pedido (<?= count($_SESSION['carrito']) ?> artículos)
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="../js/app.js"></script>
<script>
function filtrarStock(q) {
    const term = q.toLowerCase();
    document.querySelectorAll('#sel-stock option').forEach(opt => {
        opt.hidden = term !== '' && !opt.text.toLowerCase().includes(term);
    });
}
</script>
</body></html>
