<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Solo admin y superiores pueden entrar aquí
if (!esAdmin()) { header('Location: dashboard.php'); exit; }

// Solo el rol 'admin' puro puede editar usuarios privilegiados (admin/director/soporte/sistema)
$es_admin_puro = $_SESSION['rol'] === 'admin';

$msg    = '';
$tipo   = '';
$accion = $_GET['accion'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// Comprueba si un usuario es "protegido" (solo admin puro puede tocarlo)
function esProtegido($conn, $uid): bool {
    $s = $conn->prepare("SELECT rol, usuario FROM usuarios WHERE id = ?");
    $s->bind_param('i', $uid);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r && (in_array($r['rol'], ['admin','director','soporte']) || $r['usuario'] === 'sistema');
}

// Comprueba si es el usuario "sistema" especial
function esSistema($conn, $uid): bool {
    $s = $conn->prepare("SELECT usuario FROM usuarios WHERE id = ?");
    $s->bind_param('i', $uid);
    $s->execute();
    $r = $s->get_result()->fetch_row();
    return $r && $r[0] === 'sistema';
}

// Activar/desactivar usuario
if (in_array($accion, ['activar','desactivar']) && $id > 0) {
    if ($id === (int)$_SESSION['usuario_id']) {
        $msg = 'No puedes desactivarte a ti mismo.'; $tipo = 'error';
    } elseif (esProtegido($conn, $id) && !$es_admin_puro) {
        // Director y soporte no pueden tocar usuarios privilegiados
        $msg = 'No tienes permiso para modificar este usuario.'; $tipo = 'error';
    } elseif (esSistema($conn, $id)) {
        $msg = 'El usuario sistema no se puede desactivar.'; $tipo = 'error';
    } else {
        $activo = $accion === 'activar' ? 1 : 0;
        $stmt   = $conn->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
        $stmt->bind_param('ii', $activo, $id);
        $stmt->execute();
        $msg  = $activo ? 'Usuario activado.' : 'Usuario desactivado.';
        $tipo = 'ok';
    }
}

// Guardar formulario (crear o editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos   = [];
    $errores = [];
    foreach (['nombre','apellidos','usuario','email','rol'] as $c) {
        $datos[$c] = trim($_POST[$c] ?? '');
    }
    $contra  = $_POST['contra'] ?? '';
    $edit_id = (int)($_POST['edit_id'] ?? 0);

    // Bloquear edición de usuarios protegidos si no eres admin puro
    if ($edit_id > 0 && esProtegido($conn, $edit_id) && !$es_admin_puro) {
        $msg = 'No tienes permiso para editar este usuario.'; $tipo = 'error';
        $accion = '';
    } else {
        // Validaciones básicas
        if (strlen($datos['nombre']) < 2)  $errores[] = 'Nombre obligatorio.';
        if (strlen($datos['usuario']) < 3) $errores[] = 'Usuario mínimo 3 caracteres.';
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Email no válido.';
        if ($edit_id === 0 && strlen($contra) < 8) $errores[] = 'Contraseña mínimo 8 caracteres.';

        // Nombre de usuario único
        $check = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
        $check->bind_param('si', $datos['usuario'], $edit_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) $errores[] = 'Ese nombre de usuario ya existe.';

        // Usuario "sistema": solo se puede cambiar la contraseña
        $es_sistema_edit = $edit_id > 0 && esSistema($conn, $edit_id);
        if ($es_sistema_edit) {
            // Recuperar datos actuales y no dejar cambiarlos
            $s_sis = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
            $s_sis->bind_param('i', $edit_id);
            $s_sis->execute();
            $sis = $s_sis->get_result()->fetch_assoc();
            $datos['nombre']    = $sis['nombre'];
            $datos['apellidos'] = $sis['apellidos'];
            $datos['usuario']   = $sis['usuario'];
            $datos['email']     = $sis['email'];
            $datos['rol']       = $sis['rol'];
        }

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
                $msg = 'Usuario creado.';
            }
            $stmt->execute();
            if ($conn->error) { $msg = 'Error: '.$conn->error; $tipo = 'error'; }
            else { $tipo = 'ok'; $accion = ''; }
        } else {
            $msg = implode(' ', $errores); $tipo = 'error'; $accion = 'nuevo';
        }
    }
}

// Cargar datos del usuario a editar
$usr_editar = null;
if ($accion === 'editar' && $id > 0) {
    // Bloquear edición si no eres admin puro y el usuario es protegido
    if (esProtegido($conn, $id) && !$es_admin_puro) {
        $msg = 'No tienes permiso para editar este usuario.'; $tipo = 'error'; $accion = '';
    } else {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $usr_editar = $stmt->get_result()->fetch_assoc();
    }
}

$usuarios     = $conn->query("SELECT * FROM usuarios ORDER BY apellidos, nombre");
$paginaActiva = 'usuarios';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Usuarios</title>
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="contenido">
        <div class="topbar">
            <h1>Usuarios del sistema</h1>
            <div class="topbar-right">
                <?php if ($es_admin_puro): ?>
                    <button class="btn btn-primario" onclick="abrirModal('modal-usuario')">+ Nuevo usuario</button>
                <?php endif; ?>
            </div>
        </div>
        <main class="main">
            <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

            <div class="panel">
                <table>
                    <thead>
                        <tr><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $badges = [
                        'admin'    => 'badge-rojo',
                        'director' => 'badge-azul',
                        'soporte'  => 'badge-verde',
                        'rrhh'     => 'badge-azul',
                        'almacen'  => 'badge-naranja',
                        'empleado' => 'badge-gris',
                    ];
                    while ($u = $usuarios->fetch_assoc()):
                        $es_sistema_row = $u['usuario'] === 'sistema';
                        $es_protegido_row = in_array($u['rol'], ['admin','director','soporte']) || $es_sistema_row;
                        $es_yo = (int)$u['id'] === (int)$_SESSION['usuario_id'];
                        // Solo admin puro puede editar usuarios protegidos; el resto solo ve acciones en los no protegidos
                        $puede_editar_este = !$es_protegido_row || $es_admin_puro;
                    ?>
                    <tr>
                        <td class="fw-600">
                            <?= htmlspecialchars($u['nombre'].' '.$u['apellidos']) ?>
                            <?php if ($es_sistema_row): ?>
                                <span class="badge badge-rojo" style="margin-left:4px;font-size:10px;">Sistema</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-gris"><?= htmlspecialchars($u['usuario']) ?></td>
                        <td class="text-gris"><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge <?= $badges[$u['rol']] ?? 'badge-gris' ?>"><?= nombreRol($u['rol']) ?></span></td>
                        <td><?= $u['activo'] ? '<span class="badge badge-verde">Activo</span>' : '<span class="badge badge-rojo">Inactivo</span>' ?></td>
                        <td>
                            <div class="d-flex gap-8">
                                <?php if ($puede_editar_este): ?>
                                    <?php if ($es_sistema_row): ?>
                                        <!-- Usuario sistema: solo contraseña -->
                                        <a href="?accion=editar&id=<?= $u['id'] ?>" class="btn btn-gris btn-sm">🔑 Contraseña</a>
                                    <?php else: ?>
                                        <a href="?accion=editar&id=<?= $u['id'] ?>" class="btn btn-gris btn-sm">✏️ Editar</a>
                                        <?php if (!$es_yo): ?>
                                            <?php if ($u['activo']): ?>
                                                <a href="#" class="btn btn-rojo btn-sm"
                                                   onclick="confirmarLink('¿Desactivar este usuario?', '?accion=desactivar&id=<?= $u['id'] ?>')">Desactivar</a>
                                            <?php else: ?>
                                                <a href="?accion=activar&id=<?= $u['id'] ?>" class="btn btn-verde btn-sm">Activar</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Sin permisos para tocar este usuario -->
                                    <span class="text-gris" style="font-size:12px;">—</span>
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

<!-- Modal crear/editar usuario -->
<div class="modal-overlay <?= in_array($accion,['nuevo','editar'])?'abierto':'' ?>" id="modal-usuario">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $usr_editar ? 'Editar usuario' : 'Nuevo usuario' ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('modal-usuario')">✕</button>
        </div>
        <form method="POST">
            <?php if ($usr_editar): ?><input type="hidden" name="edit_id" value="<?= $usr_editar['id'] ?>"><?php endif; ?>
            <?php $editando_sistema = $usr_editar && $usr_editar['usuario'] === 'sistema'; ?>
            <div class="modal-body">
                <?php if ($editando_sistema): ?>
                    <div class="alerta alerta-aviso">El usuario <strong>sistema</strong> solo permite cambiar la contraseña.</div>
                    <!-- Campos ocultos para no perder los datos -->
                    <input type="hidden" name="nombre"    value="<?= htmlspecialchars($usr_editar['nombre']) ?>">
                    <input type="hidden" name="apellidos" value="<?= htmlspecialchars($usr_editar['apellidos']) ?>">
                    <input type="hidden" name="usuario"   value="<?= htmlspecialchars($usr_editar['usuario']) ?>">
                    <input type="hidden" name="email"     value="<?= htmlspecialchars($usr_editar['email']) ?>">
                    <input type="hidden" name="rol"       value="<?= htmlspecialchars($usr_editar['rol']) ?>">
                <?php else: ?>
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
                            <?php
                            // Admin puro puede asignar cualquier rol; el resto no puede asignar admin/director/soporte
                            $roles_disponibles = $es_admin_puro
                                ? ['admin'=>'Administrador','director'=>'Director','soporte'=>'Soporte IT','rrhh'=>'Resp. RRHH','almacen'=>'Resp. Almacén','empleado'=>'Empleado']
                                : ['rrhh'=>'Resp. RRHH','almacen'=>'Resp. Almacén','empleado'=>'Empleado'];
                            foreach ($roles_disponibles as $k=>$v):
                            ?>
                                <option value="<?= $k ?>" <?= ($usr_editar['rol']??'')===$k?'selected':'' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    </div>
                <?php endif; ?>
                <div class="form-grupo" style="margin-top:<?= $editando_sistema?'0':'12px' ?>;">
                    <label>Contraseña <?= $usr_editar?'(vacío = no cambiar)':'*' ?></label>
                    <input type="password" name="contra" <?= $usr_editar?'':'required' ?> minlength="8" placeholder="Mínimo 8 caracteres">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gris" onclick="cerrarModal('modal-usuario')">Cancelar</button>
                <button type="submit" class="btn btn-primario"><?= $usr_editar?'Guardar cambios':'Crear usuario' ?></button>
            </div>
        </form>
    </div>
</div>
<script src="../js/app.js"></script>
</body></html>
