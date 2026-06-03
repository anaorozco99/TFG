<?php
// ============================================================
// GestInt — Gestión de Usuarios del sistema (solo Admin)
// ============================================================
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!esAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$msg  = '';
$tipo = '';
$accion = $_GET['accion'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

// ---- ACTIVAR / DESACTIVAR --------------------------------
if (in_array($accion, ['activar','desactivar']) && $id > 0 && $id !== $_SESSION['usuario_id']) {
    $activo = $accion === 'activar' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
    $stmt->bind_param('ii', $activo, $id);
    $stmt->execute();
    $msg  = $activo ? 'Usuario activado.' : 'Usuario desactivado.';
    $tipo = 'ok';
}

// ---- GUARDAR ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos   = [];
    $errores = [];
    foreach (['nombre','apellidos','usuario','email','rol'] as $c) {
        $datos[$c] = trim($_POST[$c] ?? '');
    }
    $contra = $_POST['contra'] ?? '';
    $edit_id = (int)($_POST['edit_id'] ?? 0);

    if (strlen($datos['nombre']) < 2)    $errores[] = 'Nombre obligatorio.';
    if (strlen($datos['usuario']) < 3)   $errores[] = 'Usuario mínimo 3 caracteres.';
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Email no válido.';
    if ($edit_id === 0 && strlen($contra) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';

    // Comprobar usuario duplicado
    $check = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
    $check->bind_param('si', $datos['usuario'], $edit_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) $errores[] = 'Ese nombre de usuario ya existe.';

    if (empty($errores)) {
        if ($edit_id > 0) {
            if ($contra !== '') {
                $hash = password_hash($contra, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET nombre=?,apellidos=?,usuario=?,email=?,rol=?,password=? WHERE id=?");
                $stmt->bind_param('ssssssi', $datos['nombre'],$datos['apellidos'],$datos['usuario'],$datos['email'],$datos['rol'],$hash,$edit_id);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre=?,apellidos=?,usuario=?,email=?,rol=? WHERE id=?");
                $stmt->bind_param('sssssi', $datos['nombre'],$datos['apellidos'],$datos['usuario'],$datos['email'],$datos['rol'],$edit_id);
            }
            $msg = 'Usuario actualizado.';
        } else {
            $hash = password_hash($contra, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre,apellidos,usuario,email,rol,password,activo) VALUES (?,?,?,?,?,?,1)");
            $stmt->bind_param('ssssss', $datos['nombre'],$datos['apellidos'],$datos['usuario'],$datos['email'],$datos['rol'],$hash);
            $msg = 'Usuario creado correctamente.';
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

// Cargar usuario a editar
$usr_editar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $usr_editar = $stmt->get_result()->fetch_assoc();
}

$usuarios = $conn->query("SELECT * FROM usuarios ORDER BY apellidos, nombre");
$paginaActiva = 'usuarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GestInt — Usuarios</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="contenido">
        <div class="topbar">
            <h1>Usuarios del sistema</h1>
            <div class="topbar-right">
                <button class="btn btn-primario" onclick="abrirModal('modal-usuario')">+ Nuevo usuario</button>
            </div>
        </div>
        <main class="main">
            <?php if ($msg): ?>
                <div class="alerta alerta-<?= $tipo === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div class="panel">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($u = $usuarios->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-600"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                        <td class="text-gris"><?= htmlspecialchars($u['usuario']) ?></td>
                        <td class="text-gris"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php $badges = ['admin'=>'badge-rojo','rrhh'=>'badge-azul','almacen'=>'badge-naranja','empleado'=>'badge-gris']; ?>
                            <span class="badge <?= $badges[$u['rol']] ?? 'badge-gris' ?>"><?= nombreRol($u['rol']) ?></span>
                        </td>
                        <td>
                            <?= $u['activo']
                                ? '<span class="badge badge-verde">Activo</span>'
                                : '<span class="badge badge-rojo">Inactivo</span>' ?>
                        </td>
                        <td>
                            <div class="d-flex gap-8">
                                <a href="?accion=editar&id=<?= $u['id'] ?>" class="btn btn-gris btn-sm">✏️ Editar</a>
                                <?php if ($u['id'] !== $_SESSION['usuario_id']): ?>
                                    <?php if ($u['activo']): ?>
                                        <a href="?accion=desactivar&id=<?= $u['id'] ?>"
                                           class="btn btn-rojo btn-sm"
                                           onclick="return confirm('¿Desactivar este usuario?')">Desactivar</a>
                                    <?php else: ?>
                                        <a href="?accion=activar&id=<?= $u['id'] ?>" class="btn btn-verde btn-sm">Activar</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Modal usuario -->
<div class="modal-overlay <?= in_array($accion,['nuevo','editar']) ? 'abierto' : '' ?>" id="modal-usuario">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $usr_editar ? 'Editar usuario' : 'Nuevo usuario' ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('modal-usuario')">✕</button>
        </div>
        <form method="POST">
            <?php if ($usr_editar): ?>
                <input type="hidden" name="edit_id" value="<?= $usr_editar['id'] ?>">
            <?php endif; ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-grupo">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" required value="<?= htmlspecialchars($usr_editar['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Apellidos</label>
                        <input type="text" name="apellidos" value="<?= htmlspecialchars($usr_editar['apellidos'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Usuario *</label>
                        <input type="text" name="usuario" required value="<?= htmlspecialchars($usr_editar['usuario'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Email *</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($usr_editar['email'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Rol</label>
                        <select name="rol">
                            <?php foreach (['admin'=>'Administrador','rrhh'=>'Resp. RRHH','almacen'=>'Resp. Almacén','empleado'=>'Empleado'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= ($usr_editar['rol'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Contraseña <?= $usr_editar ? '(dejar en blanco para no cambiar)' : '*' ?></label>
                        <input type="password" name="contra" <?= $usr_editar ? '' : 'required' ?> minlength="8"
                               placeholder="Mínimo 8 caracteres">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gris" onclick="cerrarModal('modal-usuario')">Cancelar</button>
                <button type="submit" class="btn btn-primario"><?= $usr_editar ? 'Guardar cambios' : 'Crear usuario' ?></button>
            </div>
        </form>
    </div>
</div>
<script src="../js/app.js"></script>
</body>
</html>
