<?php
// ============================================================
// GestInt — Gestión de Inventario (CRUD completo)
// ============================================================
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!esAlmacen()) {
    header('Location: dashboard.php');
    exit;
}

$msg   = '';
$tipo  = '';
$accion = $_GET['accion'] ?? '';
$id    = (int)($_GET['id'] ?? 0);

// ---- ELIMINAR ---------------------------------------------
if ($accion === 'eliminar' && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM inventario WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $msg  = 'Artículo eliminado del inventario.';
    $tipo = 'ok';
}

// ---- GUARDAR ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos   = [];
    $errores = [];
    $campos  = ['nombre','categoria','descripcion','cantidad','stock_minimo','unidad','proveedor','precio_unitario'];

    foreach ($campos as $c) $datos[$c] = trim($_POST[$c] ?? '');

    if (strlen($datos['nombre']) < 2)     $errores[] = 'El nombre es obligatorio.';
    if (!is_numeric($datos['cantidad']) || $datos['cantidad'] < 0) $errores[] = 'Cantidad no válida.';
    if (!is_numeric($datos['stock_minimo']))  $errores[] = 'Stock mínimo no válido.';
    if (!is_numeric($datos['precio_unitario'])) $errores[] = 'Precio no válido.';

    if (empty($errores)) {
        $edit_id = (int)($_POST['edit_id'] ?? 0);

        if ($edit_id > 0) {
            $stmt = $conn->prepare(
                "UPDATE inventario SET nombre=?,categoria=?,descripcion=?,cantidad=?,
                 stock_minimo=?,unidad=?,proveedor=?,precio_unitario=? WHERE id=?"
            );
            $stmt->bind_param('sssiiissdi',
                $datos['nombre'], $datos['categoria'], $datos['descripcion'],
                $datos['cantidad'], $datos['stock_minimo'], $datos['unidad'],
                $datos['proveedor'], $datos['precio_unitario'], $edit_id
            );
            $msg = 'Artículo actualizado.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO inventario (nombre,categoria,descripcion,cantidad,stock_minimo,unidad,proveedor,precio_unitario,fecha_entrada)
                 VALUES (?,?,?,?,?,?,?,?,NOW())"
            );
            $stmt->bind_param('sssiisd',
                $datos['nombre'], $datos['categoria'], $datos['descripcion'],
                $datos['cantidad'], $datos['stock_minimo'], $datos['unidad'],
                $datos['proveedor'], $datos['precio_unitario']
            );
            $msg = 'Artículo añadido al inventario.';
        }
        $stmt->execute();
        $tipo   = 'ok';
        $accion = '';
    } else {
        $msg   = implode(' ', $errores);
        $tipo  = 'error';
        $accion = 'nuevo';
    }
}

// ---- EDITAR datos ----------------------------------------
$art_editar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM inventario WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $art_editar = $stmt->get_result()->fetch_assoc();
}

// ---- PAGINACIÓN / FILTROS --------------------------------
$buscar = trim($_GET['buscar'] ?? '');
$cat    = $_GET['cat'] ?? '';
$solo_bajo = isset($_GET['bajo']);
$pag    = max(1, (int)($_GET['pag'] ?? 1));
$porPag = 12;
$offset = ($pag - 1) * $porPag;

$where = "WHERE 1=1";
$params = []; $tipos = '';

if ($buscar !== '') {
    $like = "%$buscar%";
    $where .= " AND (nombre LIKE ? OR proveedor LIKE ? OR categoria LIKE ?)";
    $params = [$like, $like, $like]; $tipos = 'sss';
}
if ($cat !== '') {
    $where .= " AND categoria = ?";
    $params[] = $cat; $tipos .= 's';
}
if ($solo_bajo) {
    $where .= " AND cantidad <= stock_minimo";
}

$total_q = $conn->prepare("SELECT COUNT(*) FROM inventario $where");
if ($params) $total_q->bind_param($tipos, ...$params);
$total_q->execute();
$total_filas = $total_q->get_result()->fetch_row()[0];
$total_pags  = max(1, (int)ceil($total_filas / $porPag));

$q = $conn->prepare("SELECT * FROM inventario $where ORDER BY nombre LIMIT ? OFFSET ?");
$params[] = $porPag; $params[] = $offset; $tipos .= 'ii';
$q->bind_param($tipos, ...$params);
$q->execute();
$articulos = $q->get_result();

$cats_q = $conn->query("SELECT DISTINCT categoria FROM inventario ORDER BY categoria");

$paginaActiva = 'inventario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GestInt — Inventario</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="contenido">
        <div class="topbar">
            <h1>Inventario</h1>
            <div class="topbar-right">
                <span class="text-gris"><?= $total_filas ?> artículos</span>
                <button class="btn btn-primario" onclick="abrirModal('modal-articulo')">+ Nuevo artículo</button>
            </div>
        </div>

        <main class="main">
            <?php if ($msg): ?>
                <div class="alerta alerta-<?= $tipo === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="panel" style="margin-bottom:16px;">
                <div class="panel-body" style="padding:12px 16px;">
                    <form method="GET" class="barra-filtros">
                        <input type="text" name="buscar" placeholder="Buscar artículo, proveedor..."
                               value="<?= htmlspecialchars($buscar) ?>">
                        <select name="cat">
                            <option value="">Todas las categorías</option>
                            <?php while ($c = $cats_q->fetch_row()): ?>
                                <option value="<?= htmlspecialchars($c[0]) ?>" <?= $cat === $c[0] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c[0]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="bajo" <?= $solo_bajo ? 'checked' : '' ?>>
                            Solo stock bajo
                        </label>
                        <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                        <a href="inventario.php" class="btn btn-gris btn-sm">Limpiar</a>
                    </form>
                </div>
            </div>

            <!-- Tabla -->
            <div class="panel">
                <table>
                    <thead>
                        <tr>
                            <th>Artículo</th>
                            <th>Categoría</th>
                            <th>Cantidad</th>
                            <th>Stock mín.</th>
                            <th>Unidad</th>
                            <th>Precio unit.</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($articulos->num_rows > 0): ?>
                        <?php while ($a = $articulos->fetch_assoc()): ?>
                            <?php $bajo = $a['cantidad'] <= $a['stock_minimo']; ?>
                        <tr>
                            <td class="fw-600"><?= htmlspecialchars($a['nombre']) ?></td>
                            <td><span class="badge badge-gris"><?= htmlspecialchars($a['categoria']) ?></span></td>
                            <td><?= $a['cantidad'] ?></td>
                            <td class="text-gris"><?= $a['stock_minimo'] ?></td>
                            <td class="text-gris"><?= htmlspecialchars($a['unidad']) ?></td>
                            <td><?= number_format($a['precio_unitario'], 2, ',', '.') ?> €</td>
                            <td class="text-gris"><?= htmlspecialchars($a['proveedor']) ?></td>
                            <td>
                                <?php if ($bajo): ?>
                                    <span class="badge badge-rojo">⚠ Stock bajo</span>
                                <?php else: ?>
                                    <span class="badge badge-verde">OK</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-8">
                                    <a href="?accion=editar&id=<?= $a['id'] ?>" class="btn btn-gris btn-sm">✏️</a>
                                    <a href="?accion=eliminar&id=<?= $a['id'] ?>"
                                       class="btn btn-rojo btn-sm"
                                       onclick="return confirm('¿Eliminar este artículo del inventario?')">🗑</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-gris text-center" style="padding:24px;">Sin artículos.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($total_pags > 1): ?>
                <div class="paginacion" style="padding:12px 16px;">
                    <?php for ($p = 1; $p <= $total_pags; $p++): ?>
                        <?php $qs = http_build_query(['buscar'=>$buscar,'cat'=>$cat,'pag'=>$p]); ?>
                        <?php if ($p === $pag): ?>
                            <span class="actual"><?= $p ?></span>
                        <?php else: ?>
                            <a href="?<?= $qs ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Modal artículo -->
<div class="modal-overlay <?= in_array($accion, ['nuevo','editar']) ? 'abierto' : '' ?>" id="modal-articulo">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $art_editar ? 'Editar artículo' : 'Nuevo artículo' ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('modal-articulo')">✕</button>
        </div>
        <form method="POST">
            <?php if ($art_editar): ?>
                <input type="hidden" name="edit_id" value="<?= $art_editar['id'] ?>">
            <?php endif; ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-grupo full">
                        <label>Nombre del artículo *</label>
                        <input type="text" name="nombre" required
                               value="<?= htmlspecialchars($art_editar['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Categoría</label>
                        <select name="categoria">
                            <?php foreach (['Consumibles','Herramientas','Electrónica','Mobiliario','Limpieza','Seguridad','Oficina','Otros'] as $c): ?>
                                <option value="<?= $c ?>" <?= ($art_editar['categoria'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Proveedor</label>
                        <input type="text" name="proveedor"
                               value="<?= htmlspecialchars($art_editar['proveedor'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Cantidad actual *</label>
                        <input type="number" name="cantidad" min="0"
                               value="<?= htmlspecialchars($art_editar['cantidad'] ?? 0) ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Stock mínimo</label>
                        <input type="number" name="stock_minimo" min="0"
                               value="<?= htmlspecialchars($art_editar['stock_minimo'] ?? 5) ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Unidad</label>
                        <select name="unidad">
                            <?php foreach (['unidad','caja','kg','litro','metro','paquete'] as $u): ?>
                                <option value="<?= $u ?>" <?= ($art_editar['unidad'] ?? '') === $u ? 'selected' : '' ?>><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Precio unitario (€)</label>
                        <input type="number" name="precio_unitario" min="0" step="0.01"
                               value="<?= htmlspecialchars($art_editar['precio_unitario'] ?? 0) ?>">
                    </div>
                    <div class="form-grupo full">
                        <label>Descripción</label>
                        <textarea name="descripcion"><?= htmlspecialchars($art_editar['descripcion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gris" onclick="cerrarModal('modal-articulo')">Cancelar</button>
                <button type="submit" class="btn btn-primario"><?= $art_editar ? 'Guardar cambios' : 'Añadir artículo' ?></button>
            </div>
        </form>
    </div>
</div>

<script src="../js/app.js"></script>
</body>
</html>
