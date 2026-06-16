<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// it, sistema y dirección pueden entrar (dirección solo lectura)
if (!puedeVerUsuarios()) { header('Location: dashboard.php'); exit; }

// solo sistema puede editar usuarios privilegiados (sistema, dirección)
$es_sistema_puro = esSistema();
// it puede editar pero no a los protegidos
$puede_editar_usuarios = puedeEditarUsuarios();

$msg    = '';
$tipo   = '';
$accion = $_GET['accion'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// protegido = solo sistema puede editarlo (sistema, dirección, y el usuario especial 'sistema')
function esProtegidoBD($conn, $uid): bool {
    $s = $conn->prepare("SELECT rol, usuario FROM usuarios WHERE id = ?");
    $s->bind_param('i', $uid);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r && (in_array($r['rol'], ['sistema','direccion']) || $r['usuario'] === 'sistema');
}

// comprueba si es el usuario especial 'sistema'
function esCuentaSistema($conn, $uid): bool {
    $s = $conn->prepare("SELECT usuario FROM usuarios WHERE id = ?");
    $s->bind_param('i', $uid);
    $s->execute();
    $r = $s->get_result()->fetch_row();
    return $r && $r[0] === 'sistema';
}

// validación de contraseña: mín 6, mayús, minús, número y carácter especial
function validarPassword(string $pass): bool {
    return strlen($pass) >= 6
        && preg_match('/[A-Z]/', $pass)
        && preg_match('/[a-z]/', $pass)
        && preg_match('/[0-9]/', $pass)
        && preg_match('/[^A-Za-z0-9]/', $pass);
}

// Activar/desactivar usuario (solo sistema/it pueden, y dirección no puede)
if (in_array($accion, ['activar','desactivar']) && $id > 0 && $puede_editar_usuarios) {
    if ($id === (int)$_SESSION['usuario_id']) {
        $msg = 'No puedes desactivarte a ti mismo.'; $tipo = 'error';
    } elseif (esProtegidoBD($conn, $id) && !$es_sistema_puro) {
        // IT no puede tocar usuarios protegidos (sistema, dirección)
        $msg = 'No tienes permiso para modificar este usuario.'; $tipo = 'error';
    } elseif (esCuentaSistema($conn, $id)) {
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

// Guardar formulario (crear o editar) — solo sistema e IT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_editar_usuarios) {
    $datos   = [];
    $errores = [];
    foreach (['nombre','apellidos','usuario','email','rol'] as $c) {
        $datos[$c] = trim($_POST[$c] ?? '');
    }
    $contra  = $_POST['contra'] ?? '';
    $edit_id = (int)($_POST['edit_id'] ?? 0);

    // bloquear edición de usuarios protegidos si no es sistema puro
    if ($edit_id > 0 && esProtegidoBD($conn, $edit_id) && !$es_sistema_puro) {
        $msg = 'No tienes permiso para editar este usuario.'; $tipo = 'error';
        $accion = '';
    } else {
        // roles que puede asignar cada nivel
        $roles_permitidos = $es_sistema_puro
            ? ['sistema','it','direccion','rrhh','almacen','ventas','contabilidad','recepcion']
            : ['rrhh','almacen','ventas','contabilidad','recepcion']; // IT no puede crear sistema/it/direccion
        if (!in_array($datos['rol'], $roles_permitidos)) {
            $errores[] = 'Rol no válido o sin permiso para asignarlo.';
        }

        // validaciones básicas
        if (strlen($datos['nombre']) < 2)  $errores[] = 'Nombre obligatorio.';
        if (strlen($datos['usuario']) < 3) $errores[] = 'Usuario mínimo 3 caracteres.';
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Email no válido.';
        // contraseña: mín 6, mayús, minús, número, carácter especial
        if ($contra !== '' && !validarPassword($contra)) {
            $errores[] = 'Contraseña: mínimo 6 caracteres con mayúscula, minúscula, número y carácter especial.';
        }
        if ($edit_id === 0 && $contra === '') $errores[] = 'La contraseña es obligatoria al crear un usuario.';

        // Nombre de usuario único
        $check = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
        $check->bind_param('si', $datos['usuario'], $edit_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) $errores[] = 'Ese nombre de usuario ya existe.';

        // usuario "sistema": solo se puede cambiar la contraseña
        $es_sistema_edit = $edit_id > 0 && esCuentaSistema($conn, $edit_id);
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
    // bloquear si no es sistema puro y el usuario es protegido
    if (esProtegidoBD($conn, $id) && !$es_sistema_puro) {
        $msg = 'No tienes permiso para editar este usuario.'; $tipo = 'error'; $accion = '';
    } else {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $usr_editar = $stmt->get_result()->fetch_assoc();
    }
}

// Filtros de búsqueda y rol
$buscar_u  = trim($_GET['buscar'] ?? '');
$filtro_rol = $_GET['rol'] ?? '';
$orden_roles = "FIELD(rol,'sistema','it','direccion','rrhh','almacen','ventas','contabilidad','recepcion')";
$puede_ver_inactivos = in_array($_SESSION['rol'], ['sistema','it','rrhh','direccion']);

$where_u = "WHERE activo = 1";
if ($buscar_u !== '') {
    $like = '%' . $conn->real_escape_string($buscar_u) . '%';
    $where_u .= " AND (nombre LIKE '$like' OR apellidos LIKE '$like' OR usuario LIKE '$like')";
}
if ($filtro_rol !== '') {
    $rol_esc = $conn->real_escape_string($filtro_rol);
    $where_u .= " AND rol = '$rol_esc'";
}
$usuarios = $conn->query("SELECT * FROM usuarios $where_u ORDER BY $orden_roles, apellidos, nombre");

// usuarios desactivados (query separada, sin filtros de búsqueda para mantenerla simple)
$buscar_inact = trim($_GET['buscar_inact'] ?? '');
$where_inact  = "WHERE activo = 0";
if ($buscar_inact !== '') {
    $li = '%' . $conn->real_escape_string($buscar_inact) . '%';
    $where_inact .= " AND (nombre LIKE '$li' OR apellidos LIKE '$li' OR usuario LIKE '$li')";
}
$usuarios_inact = $puede_ver_inactivos
    ? $conn->query("SELECT * FROM usuarios $where_inact ORDER BY $orden_roles, apellidos, nombre")
    : null;
$paginaActiva = 'usuarios';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Usuarios</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="contenido">
        <div class="topbar">
            <h1>Usuarios del sistema</h1>
            <div class="topbar-right">
                <?php if ($puede_editar_usuarios): ?>
                    <button class="btn btn-primario" onclick="abrirModal('modal-usuario')">+ Nuevo usuario</button>
                <?php endif; ?>
            </div>
        </div>
        <main class="main">
            <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

            <!-- Filtros búsqueda y rol -->
            <div class="panel" style="margin-bottom:16px;">
                <div class="panel-body" style="padding:10px 16px;">
                    <form method="GET" class="barra-filtros">
                        <input type="text" name="buscar" placeholder="Buscar por nombre, usuario..." value="<?= htmlspecialchars($buscar_u) ?>" style="min-width:220px;">
                        <select name="rol" onchange="this.form.submit()">
                            <option value="">Todos los roles</option>
                            <?php foreach(['sistema'=>'Sistema','it'=>'Soporte IT','direccion'=>'Dirección','rrhh'=>'RRHH','almacen'=>'Almacén','ventas'=>'Ventas','contabilidad'=>'Contabilidad','recepcion'=>'Recepción'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= $filtro_rol===$k?'selected':'' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primario btn-sm">Buscar</button>
                        <?php if ($buscar_u || $filtro_rol): ?><a href="usuarios.php" class="btn btn-gris btn-sm">✕ Limpiar</a><?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="panel">
                <table>
                    <thead>
                        <tr><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $badges = [
                        'sistema'      => 'badge-rojo',
                        'it'           => 'badge-verde',
                        'direccion'    => 'badge-azul',
                        'rrhh'         => 'badge-azul',
                        'almacen'      => 'badge-naranja',
                        'ventas'       => 'badge-gris',
                        'contabilidad' => 'badge-naranja',
                        'recepcion'    => 'badge-gris',
                    ];
                    while ($u = $usuarios->fetch_assoc()):
                        $es_sistema_row   = $u['usuario'] === 'sistema';
                        $es_protegido_row = in_array($u['rol'], ['sistema','direccion']) || $es_sistema_row;
                        $es_yo            = (int)$u['id'] === (int)$_SESSION['usuario_id'];
                        // sistema puede editar a todos; IT no puede editar protegidos; dirección no puede editar a nadie
                        $puede_editar_este = $puede_editar_usuarios && (!$es_protegido_row || $es_sistema_puro);
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
            <?php if ($puede_ver_inactivos && $usuarios_inact): ?>
            <div class="panel" style="margin-top:24px;">
                <div class="panel-header">
                    <h2>Usuarios desactivados</h2>
                    <span class="badge badge-rojo"><?= $usuarios_inact->num_rows ?></span>
                </div>
                <div class="panel-body" style="padding:10px 16px;border-bottom:1px solid var(--gris-borde);">
                    <form method="GET" class="barra-filtros">
                        <input type="text" name="buscar_inact" placeholder="Buscar desactivados..." value="<?= htmlspecialchars($buscar_inact) ?>" style="min-width:200px;">
                        <?php if ($buscar_u): ?><input type="hidden" name="buscar" value="<?= htmlspecialchars($buscar_u) ?>"><?php endif; ?>
                        <?php if ($filtro_rol): ?><input type="hidden" name="rol" value="<?= htmlspecialchars($filtro_rol) ?>"><?php endif; ?>
                        <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                        <?php if ($buscar_inact): ?><a href="usuarios.php" class="btn btn-gris btn-sm">Limpiar</a><?php endif; ?>
                    </form>
                </div>
                <table>
                    <thead>
                        <tr><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($usuarios_inact->num_rows === 0): ?>
                    <tr><td colspan="5" class="text-gris text-center" style="padding:20px;">No hay usuarios desactivados.</td></tr>
                    <?php else: while ($ui = $usuarios_inact->fetch_assoc()):
                        $es_prot_ui = in_array($ui['rol'], ['sistema','direccion']) || $ui['usuario'] === 'sistema';
                        $puede_react_ui = $puede_editar_usuarios && (!$es_prot_ui || $es_sistema_puro);
                    ?>
                    <tr style="opacity:.7;">
                        <td class="fw-600"><?= htmlspecialchars($ui['nombre'].' '.$ui['apellidos']) ?></td>
                        <td class="text-gris"><?= htmlspecialchars($ui['usuario']) ?></td>
                        <td class="text-gris"><?= htmlspecialchars($ui['email']) ?></td>
                        <td><span class="badge <?= $badges[$ui['rol']] ?? 'badge-gris' ?>"><?= nombreRol($ui['rol']) ?></span></td>
                        <td>
                            <?php if ($puede_react_ui): ?>
                            <a href="?accion=activar&id=<?= $ui['id'] ?>" class="btn btn-verde btn-sm">Reactivar</a>
                            <?php else: ?>
                            <span class="text-gris" style="font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

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
                            // sistema puede asignar todos los roles; IT no puede asignar sistema/it/direccion
                            $roles_disponibles = $es_sistema_puro
                                ? ['sistema'=>'Sistema','it'=>'Soporte IT','direccion'=>'Dirección','rrhh'=>'RRHH','almacen'=>'Almacén','ventas'=>'Ventas','contabilidad'=>'Contabilidad','recepcion'=>'Recepción']
                                : ['rrhh'=>'RRHH','almacen'=>'Almacén','ventas'=>'Ventas','contabilidad'=>'Contabilidad','recepcion'=>'Recepción'];
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
