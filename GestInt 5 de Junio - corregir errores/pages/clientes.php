<?php
// Gestión de clientes: listado, alta, edición y desactivación de clientes
require_once '../includes/auth.php';
require_once '../includes/db.php';

$msg    = '';
$tipo   = '';
$accion = $_GET['accion'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($accion === 'desactivar' && $id > 0) {
    $s = $conn->prepare("UPDATE clientes SET activo = 0 WHERE id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $msg = 'Cliente desactivado.'; $tipo = 'ok';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos  = ['nombre','empresa','email','telefono','direccion','ciudad'];
    $datos   = [];
    $errores = [];
    foreach ($campos as $c) $datos[$c] = trim($_POST[$c] ?? '');

    if (strlen($datos['nombre']) < 2)                           $errores[] = 'El nombre es obligatorio.';
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL))    $errores[] = 'Email no válido.';

    if (empty($errores)) {
        $edit_id = (int)($_POST['edit_id'] ?? 0);
        if ($edit_id > 0) {
            $s = $conn->prepare("UPDATE clientes SET nombre=?,empresa=?,email=?,telefono=?,direccion=?,ciudad=? WHERE id=?");
            $s->bind_param('ssssssi',$datos['nombre'],$datos['empresa'],$datos['email'],$datos['telefono'],$datos['direccion'],$datos['ciudad'],$edit_id);
            $s->execute();
            if ($conn->errno === 1062) {
                $msg = 'Ya existe un cliente con ese nombre.'; $tipo = 'error';
            } else {
                $msg = 'Cliente actualizado.'; $tipo = 'ok'; $accion = '';
            }
        } else {
            $s = $conn->prepare("INSERT INTO clientes (nombre,empresa,email,telefono,direccion,ciudad) VALUES (?,?,?,?,?,?)");
            $s->bind_param('ssssss',$datos['nombre'],$datos['empresa'],$datos['email'],$datos['telefono'],$datos['direccion'],$datos['ciudad']);
            $s->execute();
            if ($conn->errno === 1062) {
                $msg = 'Ya existe un cliente con ese nombre.'; $tipo = 'error'; $accion = 'nuevo';
            } else {
                $msg = 'Cliente añadido.'; $tipo = 'ok'; $accion = '';
            }
        }
    } else {
        $msg = implode(' ', $errores); $tipo = 'error'; $accion = 'nuevo';
    }
}

$cli_editar = null;
if ($accion === 'editar' && $id > 0) {
    $s = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $cli_editar = $s->get_result()->fetch_assoc();
}

$buscar = trim($_GET['buscar'] ?? '');
$pag    = max(1, (int)($_GET['pag'] ?? 1));
$porPag = 15;
$offset = ($pag - 1) * $porPag;

$where  = "WHERE activo = 1";
$params = []; $tipos = '';
if ($buscar !== '') {
    $like   = "%$buscar%";
    $where .= " AND (nombre LIKE ? OR empresa LIKE ? OR email LIKE ? OR ciudad LIKE ?)";
    $params = [$like, $like, $like, $like]; $tipos = 'ssss';
}

$total_q = $conn->prepare("SELECT COUNT(*) FROM clientes $where");
if ($params) $total_q->bind_param($tipos, ...$params);
$total_q->execute();
$total_filas = $total_q->get_result()->fetch_row()[0];
$total_pags  = max(1, (int)ceil($total_filas / $porPag));

$q = $conn->prepare("SELECT * FROM clientes $where ORDER BY nombre LIMIT ? OFFSET ?");
$params[] = $porPag; $params[] = $offset; $tipos .= 'ii';
$q->bind_param($tipos, ...$params);
$q->execute();
$filas = $q->get_result()->fetch_all(MYSQLI_ASSOC); // array para poder reutilizar en modales

$paginaActiva = 'clientes';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Clientes</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
    <div class="topbar">
        <h1>Clientes</h1>
        <div class="topbar-right">
            <span class="text-gris"><?= $total_filas ?> clientes</span>
            <button class="btn btn-primario" onclick="abrirModal('modal-cliente')">+ Nuevo cliente</button>
        </div>
    </div>
    <main class="main">
        <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div><?php endif; ?>

        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:12px 16px;">
                <form method="GET" class="barra-filtros">
                    <input type="text" name="buscar" placeholder="Nombre, empresa, ciudad..."
                           value="<?= htmlspecialchars($buscar) ?>">
                    <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                    <a href="clientes.php" class="btn btn-gris btn-sm">Limpiar</a>
                </form>
            </div>
        </div>

        <div class="panel">
            <table>
                <thead>
                    <tr><th>Nombre</th><th>Empresa</th><th>Email</th><th>Teléfono</th><th>Ciudad</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php if (!empty($filas)): foreach ($filas as $c): ?>
                <tr>
                    <td>
                        <a href="#" onclick="abrirModal('det<?= $c['id'] ?>'); return false;"
                           class="fw-600" style="color:var(--azul-medio);">
                            <?= htmlspecialchars($c['nombre']) ?>
                        </a>
                    </td>
                    <td class="text-gris"><?= htmlspecialchars($c['empresa'] ?? '—') ?></td>
                    <td class="text-gris"><?= htmlspecialchars($c['email']) ?></td>
                    <td class="text-gris"><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                    <td><span class="badge badge-azul"><?= htmlspecialchars($c['ciudad'] ?? '—') ?></span></td>
                    <td>
                        <div class="d-flex gap-8">
                            <a href="?accion=editar&id=<?= $c['id'] ?>" class="btn btn-gris btn-sm">✏️ Editar</a>
                            <a href="#"
                               class="btn btn-rojo btn-sm"
                               onclick="confirmarLink('¿Desactivar este cliente?', '?accion=desactivar&id=<?= $c['id'] ?>')">Baja</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-gris text-center" style="padding:24px;">Sin clientes.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_pags > 1): ?>
            <div class="paginacion" style="padding:12px 16px;">
                <?php for ($p = 1; $p <= $total_pags; $p++):
                    $qs = http_build_query(['buscar'=>$buscar,'pag'=>$p]); ?>
                    <?php if ($p === $pag): ?><span class="actual"><?= $p ?></span>
                    <?php else: ?><a href="?<?= $qs ?>"><?= $p ?></a><?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</div>

<?php foreach ($filas as $c): ?>
<div class="modal-overlay" id="det<?= $c['id'] ?>">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3><?= htmlspecialchars($c['nombre']) ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('det<?= $c['id'] ?>')">✕</button>
        </div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;font-size:14px;">
                <div><span class="text-gris">Empresa</span><br><strong><?= htmlspecialchars($c['empresa'] ?? '—') ?></strong></div>
                <div><span class="text-gris">Ciudad</span><br><strong><?= htmlspecialchars($c['ciudad'] ?? '—') ?></strong></div>
                <div><span class="text-gris">Email</span><br><strong><?= htmlspecialchars($c['email']) ?></strong></div>
                <div><span class="text-gris">Teléfono</span><br><strong><?= htmlspecialchars($c['telefono'] ?? '—') ?></strong></div>
                <div class="full" style="grid-column:1/-1"><span class="text-gris">Dirección</span><br><strong><?= htmlspecialchars($c['direccion'] ?? '—') ?></strong></div>
                <div><span class="text-gris">Alta en el sistema</span><br><span class="text-gris"><?= date('d/m/Y', strtotime($c['created_at'])) ?></span></div>
            </div>
        </div>
        <div class="modal-footer">
            <a href="?accion=editar&id=<?= $c['id'] ?>" class="btn btn-gris">✏️ Editar</a>
            <button class="btn btn-primario" onclick="cerrarModal('det<?= $c['id'] ?>')">Cerrar</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<div class="modal-overlay <?= in_array($accion,['nuevo','editar'])?'abierto':'' ?>" id="modal-cliente">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $cli_editar ? 'Editar cliente' : 'Nuevo cliente' ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('modal-cliente')">✕</button>
        </div>
        <form method="POST">
            <?php if ($cli_editar): ?><input type="hidden" name="edit_id" value="<?= $cli_editar['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-grupo">
                        <label>Nombre / Contacto *</label>
                        <input type="text" name="nombre" required
                               value="<?= htmlspecialchars($cli_editar['nombre'] ?? $_POST['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Empresa</label>
                        <input type="text" name="empresa"
                               value="<?= htmlspecialchars($cli_editar['empresa'] ?? $_POST['empresa'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Email *</label>
                        <input type="email" name="email"
                               value="<?= htmlspecialchars($cli_editar['email'] ?? $_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Teléfono</label>
                        <input type="text" name="telefono"
                               value="<?= htmlspecialchars($cli_editar['telefono'] ?? $_POST['telefono'] ?? '') ?>">
                    </div>
                    <div class="form-grupo full">
                        <label>Dirección</label>
                        <input type="text" name="direccion"
                               value="<?= htmlspecialchars($cli_editar['direccion'] ?? $_POST['direccion'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Ciudad</label>
                        <input type="text" name="ciudad"
                               value="<?= htmlspecialchars($cli_editar['ciudad'] ?? $_POST['ciudad'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gris" onclick="cerrarModal('modal-cliente')">Cancelar</button>
                <button type="submit" class="btn btn-primario"><?= $cli_editar ? 'Guardar cambios' : 'Añadir cliente' ?></button>
            </div>
        </form>
    </div>
</div>
<script src="../js/app.js"></script>
</body></html>
