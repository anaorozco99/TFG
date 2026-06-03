<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$total_empleados = $conn->query("SELECT COUNT(*) FROM empleados WHERE activo = 1")->fetch_row()[0] ?? 0;
$total_articulos = $conn->query("SELECT COUNT(*) FROM inventario")->fetch_row()[0] ?? 0;
$stock_bajo      = $conn->query("SELECT COUNT(*) FROM inventario WHERE cantidad <= stock_minimo")->fetch_row()[0] ?? 0;
$total_usuarios  = $conn->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetch_row()[0] ?? 0;

$ultimos_emp = $conn->query(
    "SELECT nombre, apellidos, departamento, fecha_alta FROM empleados ORDER BY fecha_alta DESC LIMIT 5"
);
$stock_alerta = $conn->query(
    "SELECT nombre, cantidad, stock_minimo FROM inventario WHERE cantidad <= stock_minimo ORDER BY cantidad ASC LIMIT 5"
);
$pedidos_activos = $conn->query(
    "SELECT COUNT(*) FROM pedidos WHERE estado != 'recibido'"
)->fetch_row()[0] ?? 0;

$paginaActiva = 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GestInt — Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="contenido">
        <div class="topbar">
            <h1>Dashboard</h1>
            <div class="topbar-right text-gris"><?= date('d/m/Y — H:i') ?></div>
        </div>
        <main class="main">

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Empleados activos</div>
                    <div class="stat-valor"><?= $total_empleados ?></div>
                </div>
                <div class="stat-card verde">
                    <div class="stat-label">Artículos en inventario</div>
                    <div class="stat-valor"><?= $total_articulos ?></div>
                </div>
                <div class="stat-card <?= $stock_bajo > 0 ? 'rojo' : 'verde' ?>">
                    <div class="stat-label">Stock bajo alerta</div>
                    <div class="stat-valor"><?= $stock_bajo ?></div>
                </div>
                <div class="stat-card <?= $pedidos_activos > 0 ? 'naranja' : '' ?>">
                    <div class="stat-label">Pedidos en curso</div>
                    <div class="stat-valor"><?= $pedidos_activos ?></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

                <?php if (esRRHH()): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h2>Últimas incorporaciones</h2>
                        <a href="empleados.php" class="btn btn-gris btn-sm">Ver todos</a>
                    </div>
                    <table>
                        <thead><tr><th>Empleado</th><th>Departamento</th><th>Alta</th></tr></thead>
                        <tbody>
                        <?php if ($ultimos_emp && $ultimos_emp->num_rows > 0): ?>
                            <?php while ($e = $ultimos_emp->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellidos']) ?></td>
                                <td><span class="badge badge-azul"><?= htmlspecialchars($e['departamento']) ?></span></td>
                                <td class="text-gris"><?= date('d/m/Y', strtotime($e['fecha_alta'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-gris text-center">Sin datos</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (esAlmacen()): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h2>⚠ Stock bajo mínimo</h2>
                        <a href="inventario.php" class="btn btn-gris btn-sm">Ver inventario</a>
                    </div>
                    <table>
                        <thead><tr><th>Artículo</th><th>Stock</th><th>Mínimo</th></tr></thead>
                        <tbody>
                        <?php if ($stock_alerta && $stock_alerta->num_rows > 0): ?>
                            <?php while ($a = $stock_alerta->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['nombre']) ?></td>
                                <td><span class="badge badge-rojo"><?= $a['cantidad'] ?></span></td>
                                <td class="text-gris"><?= $a['stock_minimo'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-gris text-center">Todo en orden ✓</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</div>
</body>
</html>
