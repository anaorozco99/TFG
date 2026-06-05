<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: pedidos_ventas.php'); exit; }

$s = $conn->prepare("
    SELECT pv.*,
           c.nombre AS cli_nombre, c.empresa AS cli_empresa, c.email AS cli_email,
           c.telefono AS cli_tel, c.direccion AS cli_dir, c.ciudad AS cli_ciudad,
           u.nombre AS vend_nombre, u.apellidos AS vend_apellidos
    FROM pedidos_ventas pv
    JOIN clientes c ON c.id = pv.cliente_id
    JOIN usuarios u ON u.id = pv.usuario_id
    WHERE pv.id = ?
");
$s->bind_param('i', $id);
$s->execute();
$ped = $s->get_result()->fetch_assoc();
if (!$ped) { header('Location: pedidos_ventas.php'); exit; }

$ls = $conn->prepare("SELECT * FROM pedidos_ventas_lineas WHERE pedido_id = ?");
$ls->bind_param('i', $id);
$ls->execute();
$lineas = $ls->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8">
<title>Factura #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?> — Dunder Mifflin</title>
<style>
    body { font-family:'Segoe UI',system-ui,sans-serif; max-width:760px; margin:0 auto; padding:40px; color:#1e293b; }
    .no-print { text-align:center; margin-bottom:24px; }
    .btn-p { background:#069DE0; color:#fff; border:none; padding:10px 28px; font-size:14px; font-weight:600; border-radius:6px; cursor:pointer; }
    .back  { color:#475569; font-size:13px; margin-left:12px; text-decoration:none; }
    .header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #28597A; padding-bottom:20px; margin-bottom:28px; }
    .company .logo { font-size:26px; font-weight:900; color:#28597A; letter-spacing:1px; }
    .company .sub  { font-size:12px; color:#069DE0; font-weight:700; margin-top:2px; }
    .company .info { font-size:11px; color:#475569; margin-top:6px; line-height:1.6; }
    .fnum  { font-size:22px; font-weight:800; color:#28597A; text-align:right; }
    .fdate { font-size:12px; color:#475569; text-align:right; margin-top:4px; line-height:1.6; }
    .dos { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; }
    .sec h3 { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#069DE0; font-weight:700; margin-bottom:6px; border-bottom:1px solid #cbd5e1; padding-bottom:3px; }
    .sec p  { font-size:13px; margin:2px 0; color:#475569; }
    .sec strong { color:#1e293b; }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    thead th { background:#28597A; color:#fff; padding:10px 12px; text-align:left; font-size:12px; }
    thead th.r, td.r { text-align:right; }
    tbody td { padding:9px 12px; border-bottom:1px solid #f1f5f9; }
    .tot td { font-weight:700; border-top:2px solid #28597A; background:#f8fafc; font-size:15px; padding:12px; }
    .footer { margin-top:40px; border-top:1px solid #cbd5e1; padding-top:14px; text-align:center; font-size:11px; color:#94a3b8; }
    @media print { .no-print { display:none; } body { padding:20px; } }
</style>
</head><body>

<div class="no-print">
    <button class="btn-p" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    <a href="#" onclick="window.close(); return false;" class="btn-p" style="background:#dc2626;margin-left:12px;">← Cerrar</a>
</div>

<div class="header">
    <div class="company">
        <div class="logo">DUNDER MIFFLIN</div>
        <div class="sub">Paper Company · Scranton Branch</div>
        <div class="info">
            1725 Slough Ave, Scranton, PA 18503<br>
            CIF: B-12345678 · Tel: +1 (570) 555-0100<br>
            info@dundermifflin.com
        </div>
    </div>
    <div>
        <div class="fnum">FACTURA #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?></div>
        <div class="fdate">
            Emisión: <?= date('d/m/Y', strtotime($ped['fecha_pedido'])) ?><br>
            Entrega: <?= date('d/m/Y', strtotime($ped['fecha_entrega'])) ?><br>
            Estado: <strong><?= ucfirst($ped['estado']) ?></strong>
        </div>
    </div>
</div>

<div class="dos">
    <div class="sec">
        <h3>Facturar a</h3>
        <p><strong><?= htmlspecialchars($ped['cli_nombre']) ?></strong></p>
        <?php if ($ped['cli_empresa']): ?><p><?= htmlspecialchars($ped['cli_empresa']) ?></p><?php endif; ?>
        <?php if ($ped['cli_dir']): ?><p><?= htmlspecialchars($ped['cli_dir']) ?><?= $ped['cli_ciudad'] ? ', '.$ped['cli_ciudad'] : '' ?></p><?php endif; ?>
        <?php if ($ped['cli_email']): ?><p><?= htmlspecialchars($ped['cli_email']) ?></p><?php endif; ?>
        <?php if ($ped['cli_tel']): ?><p><?= htmlspecialchars($ped['cli_tel']) ?></p><?php endif; ?>
    </div>
    <div class="sec">
        <h3>Comercial</h3>
        <p><strong><?= htmlspecialchars($ped['vend_nombre'].' '.$ped['vend_apellidos']) ?></strong></p>
        <p>Dunder Mifflin Scranton</p>
    </div>
</div>

<div class="sec" style="margin-bottom:24px;">
    <h3>Detalle del pedido</h3>
    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="r" style="width:70px;">Cant.</th>
                <th class="r" style="width:110px;">Precio unit.</th>
                <th class="r" style="width:110px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lineas as $l):
            $sub = $l['cantidad'] * $l['precio_unitario'];
        ?>
        <tr>
            <td><?= htmlspecialchars($l['nombre']) ?></td>
            <td class="r"><?= $l['cantidad'] ?></td>
            <td class="r"><?= number_format($l['precio_unitario'],2,',','.') ?> €</td>
            <td class="r"><?= number_format($sub,2,',','.') ?> €</td>
        </tr>
        <?php endforeach; ?>
        <tr class="tot">
            <td colspan="3">TOTAL</td>
            <td class="r"><?= number_format($ped['total'],2,',','.') ?> €</td>
        </tr>
        </tbody>
    </table>
</div>

<?php if ($ped['notas']): ?>
<div class="sec">
    <h3>Notas</h3>
    <p><?= htmlspecialchars($ped['notas']) ?></p>
</div>
<?php endif; ?>

<div class="footer">
    Dunder Mifflin Paper Company · Scranton Branch · Gracias por su confianza
</div>
</body></html>
