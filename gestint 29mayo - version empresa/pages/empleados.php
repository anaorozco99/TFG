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

// ---- BAJA: desactiva empleado Y su usuario ---------------
if ($accion === 'eliminar' && $id > 0) {
    $res = $conn->prepare("SELECT id_usuario FROM empleados WHERE id = ?");
    $res->bind_param('i', $id);
    $res->execute();
    $fila = $res->get_result()->fetch_assoc();

    $upd = $conn->prepare("UPDATE empleados SET activo = 0 WHERE id = ?");
    $upd->bind_param('i', $id);
    $upd->execute();

    if ($fila && $fila['id_usuario']) {
        $uid = (int)$fila['id_usuario'];
        $updu = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
        $updu->bind_param('i', $uid);
        $updu->execute();
    }

    $msg  = 'Empleado dado de baja. Usuario del sistema desactivado.';
    $tipo = 'ok';
}

// ---- GUARDAR (alta o edición) ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos  = ['nombre','apellidos','dni','email','telefono','departamento','cargo','fecha_alta','salario'];
    $datos   = [];
    $errores = [];

    foreach ($campos as $c) $datos[$c] = trim($_POST[$c] ?? '');

    if (strlen($datos['nombre']) < 2)    $errores[] = 'El nombre es obligatorio.';
    if (strlen($datos['apellidos']) < 2) $errores[] = 'Los apellidos son obligatorios.';
    $letras_dni = 'TRWAGMYFPDXBNJZSQVHLCKE';
    $num_dni    = (int)substr($datos['dni'], 0, 8);
    $letra_dni  = strtoupper(substr($datos['dni'], -1));
    if (!preg_match('/^\d{8}[A-Za-z]$/', $datos['dni'])) {
        $errores[] = 'DNI no válido — debe tener 8 números y 1 letra (ej: 12345678A).';
    } elseif ($letras_dni[$num_dni % 23] !== $letra_dni) {
        $errores[] = 'DNI no válido — la letra no corresponde al número.';
    }
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Email no válido.';
    if (empty($datos['departamento']))   $errores[] = 'El departamento es obligatorio.';
    if (!is_numeric($datos['salario']) || $datos['salario'] < 0) $errores[] = 'Salario no válido.';

    if (empty($errores)) {
        $edit_id = (int)($_POST['edit_id'] ?? 0);

        if ($edit_id > 0) {
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
            $msg = 'Empleado actualizado.';
        } else {
            // Generar usuario único basado en el nombre
            $usuario_login = strtolower(explode(' ', $datos['nombre'])[0]);
            $base = $usuario_login;
            $i = 1;
            while (true) {
                $check = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
                $check->bind_param('s', $usuario_login);
                $check->execute();
                if ($check->get_result()->num_rows === 0) break;
                $usuario_login = $base . $i++;
            }

            $pass_inicial = ucfirst($usuario_login) . '1234';
            $hash = password_hash($pass_inicial, PASSWORD_DEFAULT);

            // Crear usuario
            $stmtU = $conn->prepare(
                "INSERT INTO usuarios (nombre, apellidos, usuario, email, password, rol) VALUES (?,?,?,?,?,'empleado')"
            );
            $stmtU->bind_param('sssss',
                $datos['nombre'], $datos['apellidos'], $usuario_login, $datos['email'], $hash
            );
            $stmtU->execute();
            $nuevo_uid = $conn->insert_id;

            // Crear empleado vinculado
            $stmtE = $conn->prepare(
                "INSERT INTO empleados (id_usuario, nombre, apellidos, dni, email, telefono, departamento, cargo, fecha_alta, salario, activo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,1)"
            );
            $stmtE->bind_param('issssssssd',
                $nuevo_uid, $datos['nombre'], $datos['apellidos'], $datos['dni'],
                $datos['email'], $datos['telefono'], $datos['departamento'],
                $datos['cargo'], $datos['fecha_alta'], $datos['salario']
            );
            $stmtE->execute();

            $msg = "Empleado dado de alta. Usuario: <b>$usuario_login</b> / contraseña inicial: <b>$pass_inicial</b>";
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

$where  = "WHERE e.activo = 1";
$params = [];
$tipos  = '';

if ($buscar !== '') {
    $like    = "%$buscar%";
    $where  .= " AND (e.nombre LIKE ? OR e.apellidos LIKE ? OR e.dni LIKE ? OR e.email LIKE ?)";
    $params  = [$like, $like, $like, $like];
    $tipos   = 'ssss';
}
if ($depto !== '') {
    $where   .= " AND e.departamento = ?";
    $params[] = $depto;
    $tipos   .= 's';
}

$total_q = $conn->prepare("SELECT COUNT(*) FROM empleados e $where");
if ($params) $total_q->bind_param($tipos, ...$params);
$total_q->execute();
$total_filas = $total_q->get_result()->fetch_row()[0];
$total_pags  = max(1, (int)ceil($total_filas / $porPag));

$q = $conn->prepare(
    "SELECT e.*, u.usuario FROM empleados e
     LEFT JOIN usuarios u ON u.id = e.id_usuario
     $where ORDER BY e.apellidos, e.nombre LIMIT ? OFFSET ?"
);
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
                <div class="alerta alerta-<?= $tipo === 'ok' ? 'ok' : 'error' ?>"><?= $msg ?></div>
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
                            <th>Email</th><th>Alta</th><th>Usuario</th><th>Acciones</th>
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
                            <td class="text-gris"><?= htmlspecialchars($e['usuario'] ?? '—') ?></td>
                            <td>
                                <div class="d-flex gap-8">
                                    <a href="?accion=editar&id=<?= $e['id'] ?>" class="btn btn-gris btn-sm">✏️ Editar</a>
                                    <a href="?accion=eliminar&id=<?= $e['id'] ?>"
                                       class="btn btn-rojo btn-sm"
                                       onclick="return confirm('¿Dar de baja? Su usuario también quedará desactivado.')">Baja</a>
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
                <?php if (!$emp_editar): ?>
                    <div class="alerta alerta-aviso" style="margin-bottom:14px;">
                        Al dar de alta un empleado se creará automáticamente su usuario de acceso.
                    </div>
                <?php endif; ?>
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
