<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!esRRHH()) {
    header('Location: dashboard.php');
    exit;
}

$msg    = '';
$tipo   = '';
$accion = $_GET['accion'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// Dar de baja al empleado y desactivar su usuario vinculado (mismo email)
if ($accion === 'eliminar' && $id > 0) {
    $stmt = $conn->prepare("SELECT email FROM empleados WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();

    $conn->prepare("UPDATE empleados SET activo = 0 WHERE id = ?")->bind_param('i', $id);
    $stmt2 = $conn->prepare("UPDATE empleados SET activo = 0 WHERE id = ?");
    $stmt2->bind_param('i', $id);
    $stmt2->execute();

    // Desactivar el usuario con el mismo email, protegiendo al admin
    if ($emp) {
        $upd = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE email = ? AND usuario != 'admin'");
        $upd->bind_param('s', $emp['email']);
        $upd->execute();
    }

    $msg  = 'Empleado dado de baja. Su usuario ha sido desactivado.';
    $tipo = 'ok';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos  = ['nombre','apellidos','dni','email','telefono','departamento','cargo','fecha_alta','salario'];
    $datos   = [];
    $errores = [];

    foreach ($campos as $c) $datos[$c] = trim($_POST[$c] ?? '');

    if (strlen($datos['nombre']) < 2)    $errores[] = 'El nombre es obligatorio.';
    if (strlen($datos['apellidos']) < 2) $errores[] = 'Los apellidos son obligatorios.';
    if (!preg_match('/^\d{8}[A-Za-z]$/', $datos['dni'])) $errores[] = 'DNI no válido (8 dígitos + letra).';
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Email no válido.';
    if (empty($datos['departamento']))   $errores[] = 'El departamento es obligatorio.';
    if (!is_numeric($datos['salario']) || $datos['salario'] < 0) $errores[] = 'Salario no válido.';

    if (empty($errores)) {
        $edit_id = (int)($_POST['edit_id'] ?? 0);

        if ($edit_id > 0) {
            // Obtener email anterior para localizar el usuario vinculado
            $prev = $conn->prepare("SELECT email FROM empleados WHERE id = ?");
            $prev->bind_param('i', $edit_id);
            $prev->execute();
            $email_anterior = $prev->get_result()->fetch_assoc()['email'] ?? '';

            // Actualizar empleado
            $stmt = $conn->prepare(
                "UPDATE empleados SET nombre=?,apellidos=?,dni=?,email=?,telefono=?,
                 departamento=?,cargo=?,fecha_alta=?,salario=? WHERE id=?"
            );
            $stmt->bind_param('ssssssssdi',
                $datos['nombre'], $datos['apellidos'], $datos['dni'],
                $datos['email'],  $datos['telefono'],  $datos['departamento'],
                $datos['cargo'],  $datos['fecha_alta'], $datos['salario'], $edit_id
            );
            $stmt->execute();

            // Sincronizar usuario vinculado (mismo email anterior), sin tocar al admin
            if ($email_anterior !== '') {
                $sync = $conn->prepare(
                    "UPDATE usuarios SET nombre=?, apellidos=?, email=?
                     WHERE email = ? AND usuario != 'admin'"
                );
                $sync->bind_param('ssss',
                    $datos['nombre'], $datos['apellidos'], $datos['email'], $email_anterior
                );
                $sync->execute();
            }

            $msg = 'Empleado actualizado. Usuario sincronizado.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO empleados (nombre,apellidos,dni,email,telefono,departamento,cargo,fecha_alta,salario,activo)
                 VALUES (?,?,?,?,?,?,?,?,?,1)"
            );
            $stmt->bind_param('ssssssssd',
                $datos['nombre'], $datos['apellidos'], $datos['dni'],
                $datos['email'],  $datos['telefono'],  $datos['departamento'],
                $datos['cargo'],  $datos['fecha_alta'], $datos['salario']
            );
            $stmt->execute();
            $msg = 'Empleado dado de alta.';
        }

        $tipo   = 'ok';
        $accion = '';
    } else {
        $msg    = implode(' ', $errores);
        $tipo   = 'error';
        $accion = 'nuevo';
    }
}

$emp_editar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM empleados WHERE id = ? AND activo = 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $emp_editar = $stmt->get_result()->fetch_assoc();
}

$buscar = trim($_GET['buscar'] ?? '');
$depto  = $_GET['depto'] ?? '';
$pag    = max(1, (int)($_GET['pag'] ?? 1));
$porPag = 10;
$offset = ($pag - 1) * $porPag;

$where  = "WHERE activo = 1";
$params = [];
$tipos  = '';

if ($buscar !== '') {
    $like    = "%$buscar%";
    $where  .= " AND (nombre LIKE ? OR apellidos LIKE ? OR dni LIKE ? OR email LIKE ?)";
    $params  = [$like, $like, $like, $like];
    $tipos   = 'ssss';
}
if ($depto !== '') {
    $where   .= " AND departamento = ?";
    $params[] = $depto;
    $tipos   .= 's';
}

$total_q = $conn->prepare("SELECT COUNT(*) FROM empleados $where");
if ($params) $total_q->bind_param($tipos, ...$params);
$total_q->execute();
$total_filas = $total_q->get_result()->fetch_row()[0];
$total_pags  = max(1, (int)ceil($total_filas / $porPag));

$q = $conn->prepare("SELECT * FROM empleados $where ORDER BY apellidos, nombre LIMIT ? OFFSET ?");
$params[] = $porPag;
$params[] = $offset;
$tipos   .= 'ii';
$q->bind_param($tipos, ...$params);
$q->execute();
$empleados = $q->get_result();

$deptos_q = $conn->query("SELECT DISTINCT departamento FROM empleados WHERE activo=1 ORDER BY departamento");

$paginaActiva = 'empleados';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dunder Mifflin — Empleados</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="contenido">
        <div class="topbar">
            <h1>Empleados</h1>
            <div class="topbar-right">
                <span class="text-gris"><?= $total_filas ?> empleados</span>
                <button class="btn btn-primario" onclick="abrirModal('modal-empleado')">+ Nuevo empleado</button>
            </div>
        </div>
        <main class="main">
            <?php if ($msg): ?>
                <div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div>
            <?php endif; ?>

            <div class="panel" style="margin-bottom:16px;">
                <div class="panel-body" style="padding:12px 16px;">
                    <form method="GET" class="barra-filtros">
                        <input type="text" name="buscar" placeholder="Nombre, DNI, email..."
                               value="<?= htmlspecialchars($buscar) ?>">
                        <select name="depto">
                            <option value="">Todos los departamentos</option>
                            <?php while ($d = $deptos_q->fetch_row()): ?>
                                <option value="<?= htmlspecialchars($d[0]) ?>" <?= $depto === $d[0] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d[0]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                        <a href="empleados.php" class="btn btn-gris btn-sm">Limpiar</a>
                    </form>
                </div>
            </div>

            <div class="panel">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th><th>DNI</th><th>Departamento</th><th>Cargo</th>
                            <th>Email</th><th>Alta</th><th>Salario</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($empleados->num_rows > 0): ?>
                        <?php while ($e = $empleados->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-600"><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellidos']) ?></td>
                            <td class="text-gris"><?= htmlspecialchars($e['dni']) ?></td>
                            <td><span class="badge badge-azul"><?= htmlspecialchars($e['departamento']) ?></span></td>
                            <td><?= htmlspecialchars($e['cargo']) ?></td>
                            <td class="text-gris"><?= htmlspecialchars($e['email']) ?></td>
                            <td class="text-gris"><?= date('d/m/Y', strtotime($e['fecha_alta'])) ?></td>
                            <td><?= number_format($e['salario'], 2, ',', '.') ?> €</td>
                            <td>
                                <div class="d-flex gap-8">
                                    <a href="?accion=editar&id=<?= $e['id'] ?>" class="btn btn-gris btn-sm">✏️ Editar</a>
                                    <a href="?accion=eliminar&id=<?= $e['id'] ?>"
                                       class="btn btn-rojo btn-sm"
                                       onclick="return confirm('¿Dar de baja? También se desactivará su usuario.')">Baja</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-gris text-center" style="padding:24px;">Sin resultados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pags > 1): ?>
                <div class="paginacion" style="padding:12px 16px;">
                    <?php for ($p = 1; $p <= $total_pags; $p++): ?>
                        <?php $qs = http_build_query(['buscar'=>$buscar,'depto'=>$depto,'pag'=>$p]); ?>
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

<div class="modal-overlay <?= in_array($accion, ['nuevo','editar']) ? 'abierto' : '' ?>" id="modal-empleado">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $emp_editar ? 'Editar empleado' : 'Nuevo empleado' ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('modal-empleado')">✕</button>
        </div>
        <form method="POST">
            <?php if ($emp_editar): ?>
                <input type="hidden" name="edit_id" value="<?= $emp_editar['id'] ?>">
            <?php endif; ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-grupo">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" required
                               value="<?= htmlspecialchars($emp_editar['nombre'] ?? $_POST['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Apellidos *</label>
                        <input type="text" name="apellidos" required
                               value="<?= htmlspecialchars($emp_editar['apellidos'] ?? $_POST['apellidos'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>DNI *</label>
                        <input type="text" name="dni" placeholder="12345678A"
                               value="<?= htmlspecialchars($emp_editar['dni'] ?? $_POST['dni'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Email *</label>
                        <input type="email" name="email"
                               value="<?= htmlspecialchars($emp_editar['email'] ?? $_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Teléfono</label>
                        <input type="text" name="telefono"
                               value="<?= htmlspecialchars($emp_editar['telefono'] ?? $_POST['telefono'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Departamento *</label>
                        <select name="departamento">
                            <?php foreach (['Dirección','Ventas','Contabilidad','RRHH','Recepción','Almacén'] as $dep): ?>
                                <option value="<?= $dep ?>" <?= ($emp_editar['departamento'] ?? '') === $dep ? 'selected' : '' ?>>
                                    <?= $dep ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Cargo</label>
                        <input type="text" name="cargo"
                               value="<?= htmlspecialchars($emp_editar['cargo'] ?? $_POST['cargo'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Fecha de alta *</label>
                        <input type="date" name="fecha_alta"
                               value="<?= htmlspecialchars($emp_editar['fecha_alta'] ?? $_POST['fecha_alta'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Salario bruto anual (€)</label>
                        <input type="number" name="salario" min="0" step="0.01"
                               value="<?= htmlspecialchars($emp_editar['salario'] ?? $_POST['salario'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gris" onclick="cerrarModal('modal-empleado')">Cancelar</button>
                <button type="submit" class="btn btn-primario">
                    <?= $emp_editar ? 'Guardar cambios' : 'Dar de alta' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../js/app.js"></script>
</body>
</html>
