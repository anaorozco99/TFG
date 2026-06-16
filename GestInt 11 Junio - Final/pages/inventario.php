<?php
// Gestión del inventario de artículos: ver stock, añadir, editar y borrar productos
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!puedeVerInventario()) { header('Location: dashboard.php'); exit; }

// recepcion y contabilidad solo pueden ver el inventario, no editarlo
$solo_lectura = !puedeEditarInventario();

$msg    = '';
$tipo   = '';
$accion = $solo_lectura ? '' : ($_GET['accion'] ?? '');
$id     = (int)($_GET['id'] ?? 0);

// eliminar artículo del inventario (registro en logs antes de borrar)
if ($accion === 'eliminar' && $id > 0) {
    $nom_q = $conn->prepare("SELECT nombre FROM inventario WHERE id=?");
    $nom_q->bind_param('i',$id); $nom_q->execute();
    $nom_el = $nom_q->get_result()->fetch_row()[0] ?? '';
    $stmt = $conn->prepare("DELETE FROM inventario WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    log_act('eliminar', 'inventario', $id, $nom_el);
    $msg = 'Artículo eliminado.'; $tipo = 'ok';
}

// guardar artículo nuevo o editado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos  = ['nombre','categoria','descripcion','cantidad','stock_minimo','unidad','proveedor','precio_unitario'];
    $datos   = [];
    $errores = [];
    foreach ($campos as $c) $datos[$c] = trim($_POST[$c] ?? '');

    if (strlen($datos['nombre']) < 2)               $errores[] = 'El nombre es obligatorio.';
    if (!is_numeric($datos['cantidad'])        || $datos['cantidad'] < 0)       $errores[] = 'Cantidad no válida.';
    if (!is_numeric($datos['stock_minimo']))                                     $errores[] = 'Stock mínimo no válido.';
    if (!is_numeric($datos['precio_unitario']))                                  $errores[] = 'Precio no válido.';

    if (empty($errores)) {
        $edit_id = (int)($_POST['edit_id'] ?? 0);

        if ($edit_id > 0) {
            $stmt = $conn->prepare("UPDATE inventario SET nombre=?,categoria=?,descripcion=?,cantidad=?,stock_minimo=?,unidad=?,proveedor=?,precio_unitario=? WHERE id=?");
            $stmt->bind_param('sssiissdi', $datos['nombre'],$datos['categoria'],$datos['descripcion'],$datos['cantidad'],$datos['stock_minimo'],$datos['unidad'],$datos['proveedor'],$datos['precio_unitario'],$edit_id);
            $stmt->execute();
            if ($conn->errno === 1062) {
                $msg = 'Ya existe otro artículo con ese nombre.'; $tipo = 'error';
            } else {
                log_act('editar', 'inventario', $edit_id, "{$datos['nombre']} — stock: {$datos['cantidad']}");
                $msg = 'Artículo actualizado.'; $tipo = 'ok'; $accion = '';
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO inventario (nombre,categoria,descripcion,cantidad,stock_minimo,unidad,proveedor,precio_unitario,fecha_entrada) VALUES (?,?,?,?,?,?,?,?,NOW())");
            $stmt->bind_param('sssiissd', $datos['nombre'],$datos['categoria'],$datos['descripcion'],$datos['cantidad'],$datos['stock_minimo'],$datos['unidad'],$datos['proveedor'],$datos['precio_unitario']);
            $stmt->execute();
            if ($conn->errno === 1062) {
                $msg  = 'Ya existe un artículo con ese nombre. Edítalo para actualizar el stock.';
                $tipo = 'error';
                $accion = 'nuevo';
            } else {
                log_act('crear', 'inventario', (int)$conn->insert_id, "{$datos['nombre']} — stock: {$datos['cantidad']}");
                $msg = 'Artículo añadido.'; $tipo = 'ok'; $accion = '';
            }
        }
    } else {
        $msg = implode(' ', $errores); $tipo = 'error'; $accion = 'nuevo';
    }
}

$art_editar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM inventario WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $art_editar = $stmt->get_result()->fetch_assoc();
}

// Unidades en camino (pedidos de stock no recibidos)
$ec_q = $conn->query("SELECT pl.inventario_id, SUM(pl.cantidad) AS total FROM pedidos_lineas pl JOIN pedidos p ON p.id = pl.pedido_id WHERE p.estado != 'recibido' GROUP BY pl.inventario_id");
$en_camino = [];
while ($ec = $ec_q->fetch_assoc()) {
    $en_camino[(int)$ec['inventario_id']] = (int)$ec['total'];
}

$buscar    = trim($_GET['buscar'] ?? '');
$cat       = $_GET['cat'] ?? '';
$solo_bajo = isset($_GET['bajo']);
$pag       = max(1, (int)($_GET['pag'] ?? 1));
$porPag    = 12;
$offset    = ($pag - 1) * $porPag;

$where  = "WHERE 1=1";
$params = []; $tipos = '';

if ($buscar !== '') {
    $like    = "%$buscar%";
    $where  .= " AND (nombre LIKE ? OR proveedor LIKE ? OR categoria LIKE ?)";
    $params  = [$like, $like, $like]; $tipos = 'sss';
}
if ($cat !== '') {
    $where   .= " AND categoria = ?";
    $params[] = $cat; $tipos .= 's';
}
if ($solo_bajo) $where .= " AND cantidad <= stock_minimo";

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
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Inventario</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="contenido">
        <div class="topbar">
            <h1>Inventario</h1>
            <div class="topbar-right">
                <span class="text-gris"><?= $total_filas ?> artículos</span>
                <?php if (!$solo_lectura): ?>
                <button class="btn btn-primario" onclick="abrirModal('modal-articulo')">+ Nuevo artículo</button>
                <?php endif; ?>
            </div>
        </div>
        <main class="main">
            <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div><?php endif; ?>

            <div class="panel" style="margin-bottom:16px;">
                <div class="panel-body" style="padding:12px 16px;">
                    <form method="GET" class="barra-filtros">
                        <input type="text" name="buscar" placeholder="Artículo, proveedor..." value="<?= htmlspecialchars($buscar) ?>">
                        <select name="cat">
                            <option value="">Todas las categorías</option>
                            <?php while ($c = $cats_q->fetch_row()): ?>
                                <option value="<?= htmlspecialchars($c[0]) ?>" <?= $cat===$c[0]?'selected':'' ?>><?= htmlspecialchars($c[0]) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="bajo" <?= $solo_bajo?'checked':'' ?>> Solo stock bajo
                        </label>
                        <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                        <a href="inventario.php" class="btn btn-gris btn-sm">Limpiar</a>
                    </form>
                </div>
            </div>

            <div class="panel">
                <table>
                    <thead>
                        <tr><th>Artículo</th><th>Categoría</th><th>En stock</th><th>En camino</th><th>Mínimo</th><th>Unidad</th><th>Precio unit.</th><th>Proveedor</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($articulos->num_rows > 0): while ($a = $articulos->fetch_assoc()):
                        $bajo   = $a['cantidad'] <= $a['stock_minimo'];
                        $camino = $en_camino[(int)$a['id']] ?? 0;
                    ?>
                    <tr>
                        <td class="fw-600"><?= htmlspecialchars($a['nombre']) ?></td>
                        <td><span class="badge badge-gris"><?= htmlspecialchars($a['categoria']) ?></span></td>
                        <td><?= $a['cantidad'] ?></td>
                        <td><?= $camino > 0 ? '<span class="badge badge-azul">+'.$camino.'</span>' : '<span class="text-gris">—</span>' ?></td>
                        <td class="text-gris"><?= $a['stock_minimo'] ?></td>
                        <td class="text-gris"><?= htmlspecialchars($a['unidad']) ?></td>
                        <td><?= number_format($a['precio_unitario'],2,',','.') ?> €</td>
                        <td class="text-gris"><?= htmlspecialchars($a['proveedor']) ?></td>
                        <td>
                            <?= $bajo ? '<span class="badge badge-rojo">⚠ Stock bajo</span>' : '<span class="badge badge-verde">OK</span>' ?>
                            <?php if ($camino > 0): ?><span class="badge badge-azul" style="display:block;margin-top:4px;">Pedido realizado</span><?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-8">
                                <?php if (!$solo_lectura): ?>
                                <a href="?accion=editar&id=<?= $a['id'] ?>" class="btn btn-gris btn-sm">✏️</a>
                                <a href="#"
                                   class="btn btn-rojo btn-sm"
                                   onclick="confirmarLink('¿Eliminar este artículo del inventario?', '?accion=eliminar&id=<?= $a['id'] ?>')">🗑</a>
                                <?php else: ?>
                                <span class="text-gris" style="font-size:11px;">Solo lectura</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="10" class="text-gris text-center" style="padding:24px;">Sin artículos.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pags > 1): ?>
                <div class="paginacion" style="padding:12px 16px;">
                    <?php for ($p = 1; $p <= $total_pags; $p++):
                        $qs = http_build_query(['buscar'=>$buscar,'cat'=>$cat,'pag'=>$p]); ?>
                        <?php if ($p===$pag): ?><span class="actual"><?= $p ?></span>
                        <?php else: ?><a href="?<?= $qs ?>"><?= $p ?></a><?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<div class="modal-overlay <?= in_array($accion,['nuevo','editar'])?'abierto':'' ?>" id="modal-articulo">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $art_editar ? 'Editar artículo' : 'Nuevo artículo' ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('modal-articulo')">✕</button>
        </div>
        <form method="POST">
            <?php if ($art_editar): ?><input type="hidden" name="edit_id" value="<?= $art_editar['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-grupo full">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" required value="<?= htmlspecialchars($art_editar['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Categoría</label>
                        <select name="categoria">
                            <?php foreach (['Papel','Sobres','Carpetas','Escritura','Consumibles','Herramientas','Electrónica','Mobiliario','Limpieza','Seguridad','Oficina','Otros'] as $c): ?>
                                <option value="<?= $c ?>" <?= ($art_editar['categoria']??'')===$c?'selected':'' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Proveedor</label>
                        <input type="text" name="proveedor" value="<?= htmlspecialchars($art_editar['proveedor'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Cantidad *</label>
                        <input type="number" name="cantidad" min="0" value="<?= $art_editar['cantidad'] ?? 0 ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Stock mínimo</label>
                        <input type="number" name="stock_minimo" min="0" value="<?= $art_editar['stock_minimo'] ?? 5 ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Unidad</label>
                        <select name="unidad">
                            <?php foreach (['unidad','caja','pack','kg','litro','metro','paquete'] as $u): ?>
                                <option value="<?= $u ?>" <?= ($art_editar['unidad']??'')===$u?'selected':'' ?>><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Precio unitario (€)</label>
                        <input type="number" name="precio_unitario" min="0" step="0.01" value="<?= $art_editar['precio_unitario'] ?? 0 ?>">
                    </div>
                    <div class="form-grupo full">
                        <label>Descripción</label>
                        <textarea name="descripcion"><?= htmlspecialchars($art_editar['descripcion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gris" onclick="cerrarModal('modal-articulo')">Cancelar</button>
                <button type="submit" class="btn btn-primario"><?= $art_editar ? 'Guardar cambios' : 'Añadir' ?></button>
            </div>
        </form>
    </div>
</div>
<script src="../js/app.js"></script>
</body></html>
