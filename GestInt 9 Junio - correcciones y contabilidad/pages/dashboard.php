<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Procesar fichaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'fichar') {
    $uid_f = (int)$_SESSION['usuario_id'];
    $sc = $conn->prepare("SELECT tipo, fecha FROM fichajes WHERE usuario_id = ? AND DATE(fecha) = CURDATE() ORDER BY fecha ASC");
    $sc->bind_param('i', $uid_f);
    $sc->execute();
    $hoy_rows = $sc->get_result()->fetch_all(MYSQLI_ASSOC);
    $n = count($hoy_rows);

    if ($n === 0) {
        $ins = $conn->prepare("INSERT INTO fichajes (usuario_id, tipo) VALUES (?, 'entrada')");
        $ins->bind_param('i', $uid_f); $ins->execute();
        header('Location: dashboard.php?fich=entrada'); exit;
    } elseif ($n === 1 && $hoy_rows[0]['tipo'] === 'entrada') {
        $ins = $conn->prepare("INSERT INTO fichajes (usuario_id, tipo) VALUES (?, 'salida')");
        $ins->bind_param('i', $uid_f); $ins->execute();
        // Pasar solo los minutos totales trabajados para evitar problemas con + en URL
        $entrada_dt = new DateTime($hoy_rows[0]['fecha']);
        $salida_dt  = new DateTime();
        $diff_min   = max(0, (int)(($salida_dt->getTimestamp() - $entrada_dt->getTimestamp()) / 60));
        header("Location: dashboard.php?fich=salida&dm={$diff_min}"); exit;
    }
    header('Location: dashboard.php'); exit;
}

// Construir mensaje de fichaje a partir de los parámetros GET
$msg_fichaje = '';
if (isset($_GET['fich'])) {
    if ($_GET['fich'] === 'entrada') {
        $msg_fichaje = '✅ Entrada registrada. ¡Buen día!';
    } elseif ($_GET['fich'] === 'salida' && isset($_GET['dm'])) {
        $dm    = (int)$_GET['dm'];
        $hh    = intdiv($dm, 60);
        $mm    = $dm % 60;
        $diff  = $dm - 480; // respecto a 8h
        $txt_tiempo = "<strong>{$hh}h {$mm}min trabajados</strong>";
        if ($diff >= 0) {
            $eh = intdiv($diff, 60); $em = $diff % 60;
            $msg_fichaje = "🚪 Salida registrada &mdash; $txt_tiempo <span style='color:#16a34a;font-weight:700;margin-left:8px;'>+{$eh}h{$em}m horas extra ✓</span>";
        } elseif ($diff > -30) {
            $msg_fichaje = "🚪 Salida registrada &mdash; $txt_tiempo <span style='color:#16a34a;margin-left:8px;'>Jornada completada ✓</span>";
        } else {
            $fh = intdiv(abs($diff), 60); $fm = abs($diff) % 60;
            $msg_fichaje = "🚪 Salida registrada &mdash; $txt_tiempo <span style='color:#d97706;font-weight:700;margin-left:8px;'>⚠ Faltan {$fh}h{$fm}m para 8h</span>";
        }
    }
}

// Datos para las tarjetas del resumen
$total_empleados = $conn->query("SELECT COUNT(*) FROM empleados WHERE activo=1")->fetch_row()[0] ?? 0;
$total_articulos = $conn->query("SELECT COUNT(*) FROM inventario")->fetch_row()[0] ?? 0;
$stock_bajo      = $conn->query("SELECT COUNT(*) FROM inventario WHERE cantidad <= stock_minimo")->fetch_row()[0] ?? 0;
$pedidos_activos = $conn->query("SELECT COUNT(*) FROM pedidos WHERE estado != 'recibido'")->fetch_row()[0] ?? 0;
$ultimos_emp     = $conn->query("SELECT nombre,apellidos,departamento,fecha_alta FROM empleados WHERE activo=1 ORDER BY fecha_alta DESC LIMIT 5");
$stock_alerta    = $conn->query("SELECT nombre,cantidad,stock_minimo FROM inventario WHERE cantidad<=stock_minimo ORDER BY cantidad ASC LIMIT 5");

// Estado de fichaje del usuario actual hoy
$uid = (int)$_SESSION['usuario_id'];
$sf  = $conn->prepare("SELECT tipo, fecha FROM fichajes WHERE usuario_id = ? AND DATE(fecha) = CURDATE() ORDER BY fecha ASC");
$sf->bind_param('i', $uid);
$sf->execute();
$fichajes_hoy = $sf->get_result()->fetch_all(MYSQLI_ASSOC);
$n_fich       = count($fichajes_hoy);
$completado   = $n_fich >= 2;
$puede_entrada = $n_fich === 0;
$puede_salida  = $n_fich === 1 && $fichajes_hoy[0]['tipo'] === 'entrada';

// Saludo personalizado con nombre y fecha en español
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$dias   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses  = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$fecha_saludo = $dias[date('w')].' '.date('j').' de '.$meses[(int)date('n')].' de '.date('Y');

// Datos para gráficos de gastos y ganancias (últimos 6 meses)
$chart_labels = $chart_ganancias = $chart_gastos = [];
for ($i = 5; $i >= 0; $i--) {
    $ts   = strtotime("-$i months");
    $ym   = date('Y-m', $ts);
    $chart_labels[] = date('M/y', $ts);
    $rg = $conn->query("SELECT IFNULL(SUM(total),0) FROM pedidos_ventas WHERE DATE_FORMAT(fecha_pedido,'%Y-%m')='$ym' AND estado!='cancelado'")->fetch_row();
    $chart_ganancias[] = round((float)($rg[0] ?? 0), 2);
    $rk = $conn->query("SELECT IFNULL(SUM(pl.cantidad*inv.precio_unitario),0) FROM pedidos p JOIN pedidos_lineas pl ON pl.pedido_id=p.id JOIN inventario inv ON inv.id=pl.inventario_id WHERE DATE_FORMAT(p.fecha_pedido,'%Y-%m')='$ym'")->fetch_row();
    $chart_gastos[] = round((float)($rk[0] ?? 0), 2);
}

$paginaActiva = 'inicio';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Inicio</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
  <div class="topbar">
    <h1>Inicio</h1>
    <div class="topbar-right">
      <?php if ($_SESSION['rol'] === 'admin'): ?>
        <a href="../backup.php" class="btn btn-gris btn-sm" title="Descargar backup SQL">💾 Backup BD</a>
      <?php endif; ?>
      <?php if (isset($_GET['ticket_ok'])): ?>
        <span class="badge badge-verde">✓ Ticket enviado</span>
      <?php endif; ?>
      <span class="text-gris"><?=date('d/m/Y — H:i')?></span>
    </div>
  </div>
  <main class="main">

    <?php if ($msg_fichaje): ?>
    <div class="alerta alerta-ok" style="margin-bottom:16px;"><?= $msg_fichaje ?></div>
    <?php endif; ?>

    <!-- Banner de saludo con fichaje integrado -->
    <div class="saludo-banner">
      <div style="flex:1;">
        <p class="saludo-hola">Hola, <strong><?=htmlspecialchars($nombre_usuario)?></strong> 👋</p>
        <p class="saludo-fecha">Hoy es <?=htmlspecialchars($fecha_saludo)?></p>
        <!-- Resumen de fichajes de hoy -->
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
          <?php foreach ($fichajes_hoy as $f): ?>
            <span style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:20px;font-size:12px;">
              <?= ucfirst($f['tipo']) ?> <?= date('H:i', strtotime($f['fecha'])) ?>
            </span>
          <?php endforeach; ?>
          <?php if (empty($fichajes_hoy)): ?>
            <span style="opacity:.7;font-size:12px;">Sin fichajes hoy todavía</span>
          <?php endif; ?>
        </div>
      </div>
      <!-- Botón de fichaje -->
      <div style="margin-left:20px;text-align:center;flex-shrink:0;">
        <?php if ($completado): ?>
          <span style="background:rgba(255,255,255,.2);padding:10px 18px;border-radius:8px;font-size:14px;display:block;">
            ✅ Jornada completa
          </span>
        <?php elseif ($puede_entrada): ?>
          <form method="POST">
            <input type="hidden" name="accion" value="fichar">
            <button type="button" class="btn" style="background:#fff;color:var(--azul);font-weight:700;padding:10px 20px;"
                    onclick="confirmar('¿Confirmas que quieres registrar tu ENTRADA ahora?', () => this.closest('form').submit(), 'verde')">
              ✅ Registrar entrada
            </button>
          </form>
        <?php else: ?>
          <form method="POST">
            <input type="hidden" name="accion" value="fichar">
            <button type="button" class="btn" style="background:rgba(255,255,255,.15);color:#fff;font-weight:700;padding:10px 20px;border:2px solid rgba(255,255,255,.4);"
                    onclick="confirmar('¿Confirmas que quieres registrar tu SALIDA ahora?', () => this.closest('form').submit(), 'rojo')">
              🚪 Registrar salida
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tarjetas de resumen (solo para roles con acceso a esa info) -->
    <div class="stats-grid">
      <?php if(esRRHH()): ?>
      <div class="stat-card"><div class="stat-label">Empleados activos</div><div class="stat-valor"><?=$total_empleados?></div></div>
      <?php endif; ?>
      <?php if(esAlmacen()): ?>
      <div class="stat-card verde"><div class="stat-label">Artículos en inventario</div><div class="stat-valor"><?=$total_articulos?></div></div>
      <div class="stat-card <?=$stock_bajo>0?'rojo':'verde'?>"><div class="stat-label">Stock bajo alerta</div><div class="stat-valor"><?=$stock_bajo?></div></div>
      <div class="stat-card <?=$pedidos_activos>0?'naranja':''?>"><div class="stat-label">Pedidos stock en curso</div><div class="stat-valor"><?=$pedidos_activos?></div></div>
      <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
      <?php if(esRRHH()): ?>
      <div class="panel">
        <div class="panel-header"><h2>Últimas incorporaciones</h2><a href="empleados.php" class="btn btn-gris btn-sm">Ver todos</a></div>
        <table><thead><tr><th>Empleado</th><th>Departamento</th><th>Alta</th></tr></thead><tbody>
        <?php if($ultimos_emp && $ultimos_emp->num_rows>0): while($e=$ultimos_emp->fetch_assoc()): ?>
        <tr><td><?=htmlspecialchars($e['nombre'].' '.$e['apellidos'])?></td><td><span class="badge badge-azul"><?=htmlspecialchars($e['departamento'])?></span></td><td class="text-gris"><?=date('d/m/Y',strtotime($e['fecha_alta']))?></td></tr>
        <?php endwhile; else: ?><tr><td colspan="3" class="text-gris text-center">Sin datos</td></tr><?php endif; ?>
        </tbody></table>
      </div>
      <?php endif; ?>
      <?php if(esAlmacen()): ?>
      <div class="panel">
        <div class="panel-header"><h2>⚠ Stock bajo mínimo</h2><a href="inventario.php" class="btn btn-gris btn-sm">Ver inventario</a></div>
        <table><thead><tr><th>Artículo</th><th>Stock</th><th>Mínimo</th></tr></thead><tbody>
        <?php if($stock_alerta && $stock_alerta->num_rows>0): while($a=$stock_alerta->fetch_assoc()): ?>
        <tr><td><?=htmlspecialchars($a['nombre'])?></td><td><span class="badge badge-rojo"><?=$a['cantidad']?></span></td><td class="text-gris"><?=$a['stock_minimo']?></td></tr>
        <?php endwhile; else: ?><tr><td colspan="3" class="text-gris text-center">Todo en orden ✓</td></tr><?php endif; ?>
        </tbody></table>
      </div>
      <?php endif; ?>
    </div>
    <!-- Gráficos: Ganancias y Gastos (últimos 6 meses) -->
    <?php if (esContabilidad() || esAlmacen() || esVentas()): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
      <div class="panel">
        <div class="panel-header">
          <h2>Ganancias (últimos 6 meses)</h2>
          <a href="contabilidad.php" class="btn btn-gris btn-sm">Ver detalle</a>
        </div>
        <div style="padding:16px;"><canvas id="chartGanancias" height="120"></canvas></div>
      </div>
      <div class="panel">
        <div class="panel-header">
          <h2>Gastos inventario (últimos 6 meses)</h2>
          <a href="contabilidad.php?tipo=gasto" class="btn btn-gris btn-sm">Ver detalle</a>
        </div>
        <div style="padding:16px;"><canvas id="chartGastos" height="120"></canvas></div>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>
</div>

<script src="../js/app.js"></script>
<?php if (esContabilidad() || esAlmacen() || esVentas()): ?>
<script>
const labels = <?= json_encode($chart_labels) ?>;
new Chart(document.getElementById('chartGanancias'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Ganancias (€)',
            data: <?= json_encode($chart_ganancias) ?>,
            backgroundColor: 'rgba(22,163,74,.7)',
            borderColor: '#16a34a',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive:true,
        plugins:{ legend:{display:false} },
        scales:{
            y:{ ticks:{ callback: v => v.toLocaleString('es-ES')+'€', font:{size:11} } },
            x:{ ticks:{ font:{size:11} } }
        }
    }
});
new Chart(document.getElementById('chartGastos'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Gastos (€)',
            data: <?= json_encode($chart_gastos) ?>,
            backgroundColor: 'rgba(220,38,38,.6)',
            borderColor: '#dc2626',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive:true,
        plugins:{ legend:{display:false} },
        scales:{
            y:{ ticks:{ callback: v => v.toLocaleString('es-ES')+'€', font:{size:11} } },
            x:{ ticks:{ font:{size:11} } }
        }
    }
});
</script>
<?php endif; ?>
</body></html>
