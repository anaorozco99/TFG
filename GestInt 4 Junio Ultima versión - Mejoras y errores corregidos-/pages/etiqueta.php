<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Coge los datos del pedido para generar la etiqueta de envío
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: pedidos_ventas.php'); exit; }

$s = $conn->prepare("
    SELECT pv.id, pv.fecha_pedido, pv.fecha_entrega, pv.total,
           c.nombre AS cli_nombre, c.empresa, c.email, c.telefono, c.direccion,
           u.nombre AS vend_nombre, u.apellidos AS vend_apellidos
    FROM pedidos_ventas pv
    JOIN clientes c ON c.id = pv.cliente_id
    JOIN usuarios u ON u.id = pv.usuario_id
    WHERE pv.id = ?
");
$s->bind_param('i', $id);
$s->execute();
$p = $s->get_result()->fetch_assoc();
if (!$p) { header('Location: pedidos_ventas.php'); exit; }
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8">
<title>Etiqueta envío — Pedido #<?= $p['id'] ?></title>
<style>
  body { font-family: 'Segoe UI', sans-serif; background: #fff; margin: 0; padding: 20px; }
  .etiqueta {
    border: 2px solid #28597A;
    border-radius: 8px;
    width: 400px;
    padding: 24px;
    margin: 0 auto;
  }
  .cabecera {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #28597A;
    padding-bottom: 12px;
    margin-bottom: 16px;
  }
  .empresa { font-size: 11px; color: #666; line-height: 1.6; }
  .pedido-num { font-size: 22px; font-weight: 800; color: #28597A; }
  .seccion-titulo { font-size: 10px; text-transform: uppercase; color: #999; letter-spacing: 1px; margin-bottom: 4px; }
  .destinatario { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
  .direccion { font-size: 13px; color: #333; line-height: 1.6; }
  .pie {
    border-top: 1px dashed #ccc;
    margin-top: 16px;
    padding-top: 12px;
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #666;
  }
  @media print {
    body { padding: 0; }
    .no-print { display: none; }
    .etiqueta { border: 2px solid #000; }
  }
</style>
</head><body>

<div class="etiqueta">
  <!-- Cabecera: remitente + número de pedido -->
  <div class="cabecera">
    <div class="empresa">
      <strong>DUNDER MIFFLIN PAPER CO.</strong><br>
      Scranton Branch<br>
      1725 Slough Ave, Scranton PA
    </div>
    <div style="text-align:right;">
      <div style="font-size:10px;color:#999;">PEDIDO</div>
      <div class="pedido-num">#<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?></div>
    </div>
  </div>

  <!-- Destinatario -->
  <div class="seccion-titulo">Destinatario</div>
  <div class="destinatario"><?= htmlspecialchars($p['cli_nombre']) ?></div>
  <div class="direccion">
    <?php if ($p['empresa']): ?>
      <?= htmlspecialchars($p['empresa']) ?><br>
    <?php endif; ?>
    <?php if ($p['direccion']): ?>
      <?= htmlspecialchars($p['direccion']) ?><br>
    <?php endif; ?>
    <?php if ($p['telefono']): ?>
      Tel: <?= htmlspecialchars($p['telefono']) ?><br>
    <?php endif; ?>
    <?php if ($p['email']): ?>
      <?= htmlspecialchars($p['email']) ?>
    <?php endif; ?>
  </div>

  <!-- Pie: fechas y vendedor -->
  <div class="pie">
    <div>Pedido: <?= date('d/m/Y', strtotime($p['fecha_pedido'])) ?></div>
    <div>Entrega: <?= date('d/m/Y', strtotime($p['fecha_entrega'])) ?></div>
    <div>Vendedor: <?= htmlspecialchars($p['vend_nombre']) ?></div>
  </div>
</div>

<!-- Botón de imprimir (no aparece al imprimir) -->
<div class="no-print" style="text-align:center;margin-top:20px;">
  <button onclick="window.print()" style="padding:10px 24px;background:#28597A;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">
    🖨️ Imprimir etiqueta
  </button>
  <button onclick="window.close()" style="padding:10px 24px;background:#e2e8f0;color:#333;border:none;border-radius:6px;cursor:pointer;font-size:14px;margin-left:8px;">
    Cerrar
  </button>
</div>

</body></html>
