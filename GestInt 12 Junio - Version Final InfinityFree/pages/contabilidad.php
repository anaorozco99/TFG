<?php
// Contabilidad: visión financiera unificada (ventas = ingresos, pedidos inventario = gastos)
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!puedeCuentas()) { header('Location: dashboard.php'); exit; }

// ---- Filtros ----
$filtro_tipo     = $_GET['tipo']     ?? '';           // 'venta' | 'gasto' | ''
$filtro_emp      = (int)($_GET['emp'] ?? 0);          // usuario_id vendedor
$filtro_orden    = $_GET['orden']    ?? 'fecha_desc'; // 'importe_desc' | 'importe_asc' | 'fecha_desc'
$filtro_mes      = $_GET['mes']      ?? '';           // YYYY-MM
$filtro_año      = (int)($_GET['anio'] ?? date('Y'));

// ---- Ventas (ingresos) ----
$ventas_rows = $conn->query("
    SELECT pv.id, pv.fecha_pedido AS fecha, pv.total AS importe, pv.estado,
           c.nombre AS contraparte, u.nombre AS emp_nombre, u.apellidos AS emp_apellidos, u.id AS emp_id,
           'venta' AS tipo
    FROM pedidos_ventas pv
    JOIN clientes c ON c.id = pv.cliente_id
    JOIN usuarios u ON u.id = pv.usuario_id
    WHERE pv.estado != 'cancelado'
")->fetch_all(MYSQLI_ASSOC);

// ---- Gastos (pedidos inventario) ----
// usamos IFNULL con subquery correlacionada para calcular el importe por pedido
$gastos_rows = $conn->query("
    SELECT p.id, p.fecha_pedido AS fecha,
           IFNULL((SELECT SUM(pl.cantidad * inv.precio_unitario)
                   FROM pedidos_lineas pl
                   JOIN inventario inv ON inv.id = pl.inventario_id
                   WHERE pl.pedido_id = p.id), 0) AS importe,
           p.estado,
           CONCAT('Stock — pedido #', LPAD(p.id,4,'0')) AS contraparte,
           u.nombre AS emp_nombre, u.apellidos AS emp_apellidos, u.id AS emp_id,
           'gasto' AS tipo
    FROM pedidos p
    JOIN usuarios u ON u.id = p.usuario_id
")->fetch_all(MYSQLI_ASSOC);

// ---- Unir y aplicar filtros ----
$movimientos = array_merge($ventas_rows, $gastos_rows);

// Filtro tipo
if ($filtro_tipo === 'venta') {
    $movimientos = array_filter($movimientos, fn($r) => $r['tipo'] === 'venta');
} elseif ($filtro_tipo === 'gasto') {
    $movimientos = array_filter($movimientos, fn($r) => $r['tipo'] === 'gasto');
}

// Filtro empleado
if ($filtro_emp > 0) {
    $movimientos = array_filter($movimientos, fn($r) => (int)$r['emp_id'] === $filtro_emp);
}

// Filtro mes
if ($filtro_mes) {
    $movimientos = array_filter($movimientos, fn($r) => substr($r['fecha'],0,7) === $filtro_mes);
}

// Orden
usort($movimientos, function($a, $b) use ($filtro_orden) {
    if ($filtro_orden === 'importe_desc') return $b['importe'] <=> $a['importe'];
    if ($filtro_orden === 'importe_asc')  return $a['importe'] <=> $b['importe'];
    return strcmp($b['fecha'], $a['fecha']); // fecha_desc
});

$movimientos = array_values($movimientos);

// ---- Totales del periodo filtrado ----
$total_ingresos = array_sum(array_map(fn($r) => $r['tipo']==='venta' ? (float)$r['importe'] : 0, $movimientos));
$total_gastos   = array_sum(array_map(fn($r) => $r['tipo']==='gasto' ? (float)$r['importe'] : 0, $movimientos));
$balance        = $total_ingresos - $total_gastos;

// ---- Lista de empleados de ventas para el filtro ----
$empleados_ventas = $conn->query("
    SELECT DISTINCT u.id, u.nombre, u.apellidos
    FROM pedidos_ventas pv JOIN usuarios u ON u.id = pv.usuario_id
    ORDER BY u.nombre
")->fetch_all(MYSQLI_ASSOC);

// ---- Datos para gráficos (12 meses del año seleccionado) ----
$meses_labels = [];
$datos_ingresos_g = [];
$datos_gastos_g   = [];
for ($m = 1; $m <= 12; $m++) {
    $ym = sprintf('%04d-%02d', $filtro_año, $m);
    $meses_labels[] = date('M', mktime(0,0,0,$m,1));
    // Ingresos ese mes
    $r = $conn->query("SELECT IFNULL(SUM(total),0) FROM pedidos_ventas WHERE DATE_FORMAT(fecha_pedido,'%Y-%m')='$ym' AND estado!='cancelado'")->fetch_row();
    $datos_ingresos_g[] = round((float)($r[0] ?? 0), 2);
    // Gastos ese mes
    $r2 = $conn->query("
        SELECT IFNULL(SUM(pl.cantidad * inv.precio_unitario),0)
        FROM pedidos p
        JOIN pedidos_lineas pl ON pl.pedido_id = p.id
        JOIN inventario inv ON inv.id = pl.inventario_id
        WHERE DATE_FORMAT(p.fecha_pedido,'%Y-%m')='$ym'
    ")->fetch_row();
    $datos_gastos_g[] = round((float)($r2[0] ?? 0), 2);
}

// ---- Distribución por vendedor ----
$dist_vendedores = $conn->query("
    SELECT CONCAT(u.nombre,' ',u.apellidos) AS nombre, IFNULL(SUM(pv.total),0) AS total
    FROM pedidos_ventas pv JOIN usuarios u ON u.id = pv.usuario_id
    WHERE pv.estado != 'cancelado'
    GROUP BY u.id ORDER BY total DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// ---- Exportar CSV (Excel) ----
if (isset($_GET['export']) && puedeCuentas()) {
    // solo roles con acceso a contabilidad pueden exportar
    $nombre_archivo = 'movimientos_' . ($filtro_mes ?: date('Y-m')) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    $out = fopen('php://output', 'w');
    // BOM para que Excel lo abra en UTF-8 sin problemas
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['#', 'Tipo', 'Fecha', 'Contraparte', 'Empleado', 'Estado', 'Importe (EUR)'], ';');
    foreach ($movimientos as $i => $r) {
        fputcsv($out, [
            $i + 1,
            $r['tipo'] === 'venta' ? 'Venta (+)' : 'Gasto (−)',
            date('d/m/Y H:i', strtotime($r['fecha'])),
            $r['contraparte'],
            $r['emp_nombre'] . ' ' . $r['emp_apellidos'],
            ucfirst($r['estado']),
            number_format((float)$r['importe'], 2, ',', '.'),
        ], ';');
    }
    // Totales al final
    fputcsv($out, [], ';');
    fputcsv($out, ['', '', '', '', 'TOTAL INGRESOS', '', number_format($total_ingresos,2,',','.').' €'], ';');
    fputcsv($out, ['', '', '', '', 'TOTAL GASTOS',   '', number_format($total_gastos,2,',','.').' €'], ';');
    fputcsv($out, ['', '', '', '', 'BALANCE',        '', number_format($balance,2,',','.').' €'], ';');
    fclose($out);
    exit;
}

$paginaActiva = 'contabilidad';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Contabilidad</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
.kpi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:20px; }
.kpi { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.kpi-label { font-size:12px; color:#64748b; margin-bottom:6px; }
.kpi-valor { font-size:24px; font-weight:800; }
.kpi-verde { color:#16a34a; }
.kpi-rojo  { color:#dc2626; }
.kpi-azul  { color:#069DE0; }
.graficos-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
.chart-wrap canvas { max-height:220px; }
.chart-wrap { background:#fff; border-radius:10px; padding:14px 16px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.chart-titulo { font-size:13px; font-weight:700; color:#1e293b; margin-bottom:10px; }
.badge-venta { background:#dcfce7; color:#16a34a; }
.badge-gasto { background:#fee2e2; color:#dc2626; }
.tipo-dot { width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:5px; }
.dot-venta { background:#16a34a; }
.dot-gasto { background:#dc2626; }
@media(max-width:900px){ .kpi-grid{grid-template-columns:1fr 1fr;} .graficos-grid{grid-template-columns:1fr;} }
</style>
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
    <div class="topbar">
        <h1>Contabilidad</h1>
        <div class="topbar-right text-gris">
            <?= count($movimientos) ?> movimientos
        </div>
    </div>
    <main class="main">

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi">
                <div class="kpi-label">Ingresos (ventas)</div>
                <div class="kpi-valor kpi-verde"><?= number_format($total_ingresos,2,',','.') ?> €</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Gastos (inventario)</div>
                <div class="kpi-valor kpi-rojo"><?= number_format($total_gastos,2,',','.') ?> €</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Balance</div>
                <div class="kpi-valor <?= $balance >= 0 ? 'kpi-verde' : 'kpi-rojo' ?>"><?= number_format($balance,2,',','.') ?> €</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="graficos-grid">
            <div class="chart-wrap">
                <div class="chart-titulo">Evolución anual <?= $filtro_año ?></div>
                <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
                    <?php foreach ([date('Y')-1, date('Y')] as $ay): ?>
                    <a href="?<?= http_build_query(array_merge($_GET,['anio'=>$ay])) ?>"
                       class="btn btn-sm <?= $filtro_año===$ay?'btn-primario':'btn-gris' ?>"><?= $ay ?></a>
                    <?php endforeach; ?>
                </div>
                <canvas id="chartEvo" height="80"></canvas>
            </div>
            <div class="chart-wrap">
                <div class="chart-titulo">Ventas por vendedor</div>
                <canvas id="chartPie" height="80"></canvas>
            </div>
        </div>

        <!-- Filtros -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:10px 16px;">
                <form method="GET" class="barra-filtros" style="flex-wrap:wrap;gap:8px;">
                    <select name="tipo" style="font-size:13px;padding:5px 10px;">
                        <option value="">Ventas y gastos</option>
                        <option value="venta"  <?= $filtro_tipo==='venta' ?'selected':''?>>Solo ventas (+)</option>
                        <option value="gasto"  <?= $filtro_tipo==='gasto' ?'selected':''?>>Solo gastos (−)</option>
                    </select>
                    <select name="emp" style="font-size:13px;padding:5px 10px;">
                        <option value="0">Todos los empleados</option>
                        <?php foreach ($empleados_ventas as $ev): ?>
                        <option value="<?= $ev['id'] ?>" <?= $filtro_emp===(int)$ev['id']?'selected':''?>>
                            <?= htmlspecialchars($ev['nombre'].' '.$ev['apellidos']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="mes" style="font-size:13px;padding:5px 10px;">
                        <option value="">Mes</option>
                        <?php
                        $mn = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                        for ($i = 17; $i >= 0; $i--) {
                            $ts  = strtotime("-$i months");
                            $val = date('Y-m', $ts);
                            $lbl = $mn[(int)date('n',$ts)] . ' ' . date('Y',$ts);
                            echo '<option value="'.htmlspecialchars($val).'"'.($filtro_mes===$val?' selected':'').'>'.htmlspecialchars($lbl).'</option>';
                        }
                        ?>
                    </select>
                    <select name="orden" style="font-size:13px;padding:5px 10px;">
                        <option value="fecha_desc"   <?= $filtro_orden==='fecha_desc'  ?'selected':''?>>Más recientes</option>
                        <option value="importe_desc" <?= $filtro_orden==='importe_desc'?'selected':''?>>Mayor importe</option>
                        <option value="importe_asc"  <?= $filtro_orden==='importe_asc' ?'selected':''?>>Menor importe</option>
                    </select>
                    <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                    <?php if ($filtro_tipo || $filtro_emp || $filtro_mes): ?>
                    <a href="contabilidad.php" class="btn btn-gris btn-sm">✕ Limpiar</a>
                    <?php endif; ?>
                    <?php if (puedeCuentas()): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'1'])) ?>"
                       class="btn btn-gris btn-sm" style="margin-left:auto;">Exportar Excel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Tabla de movimientos -->
        <div class="panel">
            <div class="panel-header"><h2>Movimientos</h2></div>
            <?php if (!empty($movimientos)): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Contraparte</th>
                        <th>Empleado</th>
                        <th>Estado</th>
                        <th style="text-align:right;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movimientos as $mov): ?>
                <tr>
                    <td class="text-gris"><?= $mov['id'] ?></td>
                    <td class="text-gris"><?= $mov['tipo'] === 'venta' ? 'Venta' : 'Gasto' ?></td>
                    <td><?= date('d/m/Y', strtotime($mov['fecha'])) ?></td>
                    <td class="fw-600"><?= htmlspecialchars($mov['contraparte']) ?></td>
                    <td class="text-gris"><?= htmlspecialchars($mov['emp_nombre'].' '.$mov['emp_apellidos']) ?></td>
                    <td><span class="badge badge-<?= $mov['estado']==='recibido'||$mov['estado']==='entregado'?'verde':($mov['estado']==='pendiente'?'naranja':'azul') ?>"><?= ucfirst($mov['estado']) ?></span></td>
                    <td style="text-align:right;font-weight:700;color:<?= $mov['tipo']==='venta'?'#16a34a':'#dc2626' ?>;">
                        <?= ($mov['tipo']==='venta'?'+':'−') ?> <?= number_format((float)$mov['importe'],2,',','.') ?> €
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="panel-body"><p class="text-gris">No hay movimientos con los filtros aplicados.</p></div>
            <?php endif; ?>
        </div>

    </main>
</div>
</div>

<script src="../js/app.js"></script>
<script>
// ---- Gráfico evolución anual ----
new Chart(document.getElementById('chartEvo'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($meses_labels) ?>,
        datasets: [
            {
                label: 'Ingresos',
                data: <?= json_encode($datos_ingresos_g) ?>,
                backgroundColor: 'rgba(22,163,74,.7)',
                borderColor: '#16a34a',
                borderWidth: 1,
                borderRadius: 4,
                order: 2
            },
            {
                label: 'Gastos',
                data: <?= json_encode($datos_gastos_g) ?>,
                backgroundColor: 'rgba(220,38,38,.6)',
                borderColor: '#dc2626',
                borderWidth: 1,
                borderRadius: 4,
                order: 2
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
        scales: {
            y: { ticks: { callback: v => v.toLocaleString('es-ES') + ' €', font: { size: 11 } } },
            x: { ticks: { font: { size: 11 } } }
        }
    }
});

// ---- Gráfico distribución por vendedor (pie) ----
<?php
$pie_labels  = array_column($dist_vendedores, 'nombre');
$pie_valores = array_map(fn($r) => round((float)$r['total'],2), $dist_vendedores);
$pie_colores = ['#069DE0','#16a34a','#d97706','#9333ea','#dc2626','#0891b2','#65a30d','#ea580c'];
?>
new Chart(document.getElementById('chartPie'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($pie_labels) ?>,
        datasets: [{
            data: <?= json_encode($pie_valores) ?>,
            backgroundColor: <?= json_encode($pie_colores) ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 12 } },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.label + ': ' + ctx.parsed.toLocaleString('es-ES',{minimumFractionDigits:2}) + ' €'
                }
            }
        }
    }
});
</script>
</body></html>
