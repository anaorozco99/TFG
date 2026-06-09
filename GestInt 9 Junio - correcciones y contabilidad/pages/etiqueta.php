<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: pedidos_ventas.php'); exit; }

$s = $conn->prepare("
    SELECT pv.id, pv.fecha_pedido, pv.fecha_entrega, pv.total, pv.notas,
           c.nombre AS cli_nombre, c.empresa, c.email AS cli_email, c.telefono AS cli_tel,
           c.direccion, c.ciudad,
           u.nombre AS vend_nombre, u.apellidos AS vend_apellidos,
           u.email AS vend_email
    FROM pedidos_ventas pv
    JOIN clientes c ON c.id = pv.cliente_id
    JOIN usuarios u ON u.id = pv.usuario_id
    WHERE pv.id = ?
");
$s->bind_param('i', $id);
$s->execute();
$p = $s->get_result()->fetch_assoc();
if (!$p) { header('Location: pedidos_ventas.php'); exit; }

// Calcular número de líneas del pedido
$lineas = $conn->query("SELECT COUNT(*) FROM pedidos_ventas_lineas WHERE pedido_id=$id")->fetch_row()[0] ?? 0;

$qr_data  = urlencode('Dunder Mifflin | Pedido #'.$p['id'].' | '.$p['cli_nombre'].' | '.date('d/m/Y',strtotime($p['fecha_pedido'])));
$qr_url   = 'https://api.qrserver.com/v1/create-qr-code/?size=90x90&data='.$qr_data;
$codigo   = 'DM-' . str_pad($p['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8">
<title>Etiqueta — Pedido #<?= $p['id'] ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; background: #fff; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; min-height:100vh; padding:30px 20px; }

.etiqueta {
    background: #fff;
    border: 2px solid #1a1a1a;
    border-radius: 10px;
    width: 580px;
    overflow: hidden;
}

/* ---- CABECERA ---- */
.cab {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
    padding: 16px 18px 14px;
    border-bottom: 2px solid #1a1a1a;
    align-items: flex-start;
}
.remitente-nombre { font-size: 15px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; }
.remitente-info { font-size: 11.5px; color: #444; line-height: 1.7; margin-top: 4px; }
.qr-box img { display:block; border: 1px solid #ddd; border-radius: 4px; }

/* ---- CUERPO ---- */
.cuerpo {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border-bottom: 2px solid #1a1a1a;
}

.destinatario-bloque {
    padding: 14px 18px;
    border-right: 2px solid #1a1a1a;
}
.to-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #555; margin-bottom: 6px; letter-spacing: .8px; }
.dest-nombre { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
.dest-empresa { font-size: 12px; color: #555; margin-bottom: 8px; }
.dest-fila { font-size: 12px; line-height: 1.8; color: #333; }
.dest-fila span { display: inline-block; width: 60px; color: #777; font-size: 11px; }

.detalles-bloque {
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.urgente-banner {
    background: #1a1a1a;
    color: #fff;
    text-align: center;
    font-size: 18px;
    font-weight: 900;
    letter-spacing: 2px;
    padding: 6px 0;
    border-radius: 4px;
    margin-bottom: 8px;
}
.urgente-sub { text-align:center; font-size: 9px; letter-spacing: 1.5px; color: #555; margin-bottom:10px; }
.detalle-fila { display:flex; justify-content:space-between; font-size: 11.5px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
.detalle-fila:last-child { border-bottom: none; }
.detalle-key { color: #666; }
.detalle-val { font-weight: 700; text-align:right; }

/* ---- PIE (código de barras) ---- */
.pie {
    padding: 12px 18px 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.pie svg { max-width: 100%; }
.pie-codigo { font-size: 11px; color: #555; letter-spacing: 2px; }

/* ---- Botones ---- */
.botones { margin-top: 20px; display: flex; gap: 10px; }
.btn-imp {
    padding: 10px 28px; background: #28597A; color: #fff;
    border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;
}
.btn-cerrar {
    padding: 10px 20px; background: #e2e8f0; color: #333;
    border: none; border-radius: 6px; cursor: pointer; font-size: 14px;
}
@media print {
    body { background: #fff; padding: 0; }
    .botones { display: none; }
    .etiqueta { box-shadow: none; border-radius: 0; }
}
</style>
</head><body>

<div class="etiqueta">

    <!-- CABECERA: remitente + QR -->
    <div class="cab">
        <div>
            <div class="remitente-nombre">Dunder Mifflin Paper Company</div>
            <div class="remitente-info">
                Scranton Branch — 1725 Slough Ave, Scranton PA 18503<br>
                Tel: +1 (570) 555-0100 &nbsp;·&nbsp; <?= htmlspecialchars($p['vend_email']) ?><br>
                Vendedor: <?= htmlspecialchars($p['vend_nombre'].' '.$p['vend_apellidos']) ?>
            </div>
        </div>
        <div class="qr-box">
            <img src="<?= $qr_url ?>" width="90" height="90" alt="QR pedido" onerror="this.style.display='none'">
        </div>
    </div>

    <!-- CUERPO: destinatario + detalles -->
    <div class="cuerpo">
        <div class="destinatario-bloque">
            <div class="to-label">Para / To:</div>
            <div class="dest-nombre"><?= htmlspecialchars($p['cli_nombre']) ?></div>
            <?php if ($p['empresa']): ?>
            <div class="dest-empresa"><?= htmlspecialchars($p['empresa']) ?></div>
            <?php endif; ?>
            <?php if ($p['direccion']): ?>
            <div class="dest-fila"><span>Dirección</span>: <?= htmlspecialchars($p['direccion']) ?></div>
            <?php endif; ?>
            <?php if ($p['ciudad']): ?>
            <div class="dest-fila"><span>Ciudad</span>: <?= htmlspecialchars($p['ciudad']) ?></div>
            <?php endif; ?>
            <?php if ($p['cli_tel']): ?>
            <div class="dest-fila"><span>Teléfono</span>: <?= htmlspecialchars($p['cli_tel']) ?></div>
            <?php endif; ?>
            <?php if ($p['cli_email']): ?>
            <div class="dest-fila"><span>Email</span>: <?= htmlspecialchars($p['cli_email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="detalles-bloque">
            <div class="urgente-banner">DUNDER MIFFLIN</div>
            <div class="urgente-sub">PAPER COMPANY · SCRANTON BRANCH</div>
            <div class="detalle-fila"><span class="detalle-key">Pedido nº</span><span class="detalle-val">#<?= str_pad($p['id'],6,'0',STR_PAD_LEFT) ?></span></div>
            <div class="detalle-fila"><span class="detalle-key">Fecha pedido</span><span class="detalle-val"><?= date('d/m/Y',strtotime($p['fecha_pedido'])) ?></span></div>
            <div class="detalle-fila"><span class="detalle-key">Entrega est.</span><span class="detalle-val"><?= date('d/m/Y',strtotime($p['fecha_entrega'])) ?></span></div>
            <div class="detalle-fila"><span class="detalle-key">Líneas</span><span class="detalle-val"><?= $lineas ?> artículo<?= $lineas!=1?'s':'' ?></span></div>
            <div class="detalle-fila"><span class="detalle-key">Total</span><span class="detalle-val"><?= number_format($p['total'],2,',','.') ?> €</span></div>
        </div>
    </div>

    <!-- PIE: código de barras -->
    <div class="pie">
        <svg id="barcode"></svg>
        <div class="pie-codigo"><?= $codigo ?></div>
    </div>

</div>

<div class="botones no-print">
    <button class="btn-imp" onclick="window.print()">🖨️ Imprimir etiqueta</button>
    <button class="btn-cerrar" onclick="window.close()">Cerrar</button>
</div>

<script>
JsBarcode("#barcode", "<?= $codigo ?>", {
    format: "CODE128",
    width: 2,
    height: 55,
    displayValue: false,
    margin: 0,
    lineColor: "#1a1a1a",
    background: "#ffffff"
});
</script>
</body></html>
