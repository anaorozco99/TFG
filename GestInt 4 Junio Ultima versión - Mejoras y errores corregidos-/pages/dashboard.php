<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Datos para las tarjetas del resumen
$total_empleados = $conn->query("SELECT COUNT(*) FROM empleados WHERE activo=1")->fetch_row()[0] ?? 0;
$total_articulos = $conn->query("SELECT COUNT(*) FROM inventario")->fetch_row()[0] ?? 0;
$stock_bajo      = $conn->query("SELECT COUNT(*) FROM inventario WHERE cantidad <= stock_minimo")->fetch_row()[0] ?? 0;
$pedidos_activos = $conn->query("SELECT COUNT(*) FROM pedidos WHERE estado != 'recibido'")->fetch_row()[0] ?? 0;
$ultimos_emp     = $conn->query("SELECT nombre,apellidos,departamento,fecha_alta FROM empleados WHERE activo=1 ORDER BY fecha_alta DESC LIMIT 5");
$stock_alerta    = $conn->query("SELECT nombre,cantidad,stock_minimo FROM inventario WHERE cantidad<=stock_minimo ORDER BY cantidad ASC LIMIT 5");

// Saludo personalizado con nombre del usuario y fecha en español
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$dias   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses  = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$fecha_saludo = $dias[date('w')].' '.date('j').' de '.$meses[(int)date('n')].' de '.date('Y');

$paginaActiva = 'inicio';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Inicio</title>
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
  <div class="topbar">
    <h1>Inicio</h1>
    <div class="topbar-right">
      <?php if ($_SESSION['rol'] === 'admin'): ?>
        <!-- Solo el admin puede descargar una copia de seguridad de la BD -->
        <a href="../backup.php" class="btn btn-gris btn-sm" title="Descargar backup SQL">💾 Backup BD</a>
      <?php endif; ?>
      <span class="text-gris"><?=date('d/m/Y — H:i')?></span>
    </div>
  </div>
  <main class="main">

    <!-- Saludo al usuario con su nombre y la fecha de hoy -->
    <div class="saludo-banner">
      <div>
        <p class="saludo-hola">Hola, <strong><?=htmlspecialchars($nombre_usuario)?></strong> 👋</p>
        <p class="saludo-fecha">Hoy es <?=htmlspecialchars($fecha_saludo)?></p>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Empleados activos</div><div class="stat-valor"><?=$total_empleados?></div></div>
      <div class="stat-card verde"><div class="stat-label">Artículos en inventario</div><div class="stat-valor"><?=$total_articulos?></div></div>
      <div class="stat-card <?=$stock_bajo>0?'rojo':'verde'?>"><div class="stat-label">Stock bajo alerta</div><div class="stat-valor"><?=$stock_bajo?></div></div>
      <div class="stat-card <?=$pedidos_activos>0?'naranja':''?>"><div class="stat-label">Pedidos stock en curso</div><div class="stat-valor"><?=$pedidos_activos?></div></div>
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
  </main>
</div>
</div>
</body></html>
