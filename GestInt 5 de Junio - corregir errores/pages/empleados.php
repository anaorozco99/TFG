<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!esRRHH()) { header('Location: dashboard.php'); exit; }

$msg    = '';
$tipo   = '';
$accion = $_GET['accion'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// Dar de baja al empleado y desactivar su usuario vinculado
if ($accion === 'eliminar' && $id > 0) {
    $stmt = $conn->prepare("SELECT email FROM empleados WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();

    $stmt2 = $conn->prepare("UPDATE empleados SET activo = 0 WHERE id = ?");
    $stmt2->bind_param('i', $id);
    $stmt2->execute();

    if ($emp) {
        $upd = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE email = ? AND rol != 'soporte'");
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

    $edit_id = (int)($_POST['edit_id'] ?? 0);

    // Campos extra solo al crear
    if ($edit_id === 0) {
        $datos['usuario'] = trim($_POST['usuario'] ?? '');
        $datos['rol']     = $_POST['rol'] ?? 'empleado';
        $datos['contra']  = $_POST['contra'] ?? '';
    }

    if (strlen($datos['nombre']) < 2)    $errores[] = 'El nombre es obligatorio.';
    if (strlen($datos['apellidos']) < 2) $errores[] = 'Los apellidos son obligatorios.';
    if (!preg_match('/^\d{8}[A-Za-z]$/', $datos['dni'])) $errores[] = 'DNI no válido (8 dígitos + letra).';
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Email no válido.';
    if (empty($datos['departamento']))   $errores[] = 'El departamento es obligatorio.';
    if (!is_numeric($datos['salario']) || $datos['salario'] < 0) $errores[] = 'Salario no válido.';

    if ($edit_id === 0) {
        if (strlen($datos['usuario']) < 3) {
            $errores[] = 'El usuario debe tener al menos 3 caracteres.';
        } else {
            $chk = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $chk->bind_param('s', $datos['usuario']);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errores[] = 'Ese nombre de usuario ya existe.';
        }
        if (strlen($datos['contra']) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }

    if (empty($errores)) {
        if ($edit_id > 0) {
            $prev = $conn->prepare("SELECT email FROM empleados WHERE id = ?");
            $prev->bind_param('i', $edit_id);
            $prev->execute();
            $email_anterior = $prev->get_result()->fetch_assoc()['email'] ?? '';

            $stmt = $conn->prepare("UPDATE empleados SET nombre=?,apellidos=?,dni=?,email=?,telefono=?,departamento=?,cargo=?,fecha_alta=?,salario=? WHERE id=?");
            $stmt->bind_param('ssssssssdi', $datos['nombre'],$datos['apellidos'],$datos['dni'],$datos['email'],$datos['telefono'],$datos['departamento'],$datos['cargo'],$datos['fecha_alta'],$datos['salario'],$edit_id);
            $stmt->execute();

            if ($email_anterior !== '') {
                $sync = $conn->prepare("UPDATE usuarios SET nombre=?,apellidos=?,email=? WHERE email=? AND rol!='soporte'");
                $sync->bind_param('ssss', $datos['nombre'],$datos['apellidos'],$datos['email'],$email_anterior);
                $sync->execute();
            }
            $msg = 'Empleado actualizado. Usuario sincronizado.';
        } else {
            $roles_validos = ['admin','rrhh','almacen','empleado','soporte'];
            $rol_u = in_array($datos['rol'], $roles_validos) ? $datos['rol'] : 'empleado';
            $hash  = password_hash($datos['contra'], PASSWORD_DEFAULT);
            $su = $conn->prepare("INSERT INTO usuarios (nombre,apellidos,usuario,email,rol,password,activo) VALUES (?,?,?,?,?,?,1)");
            $su->bind_param('ssssss', $datos['nombre'],$datos['apellidos'],$datos['usuario'],$datos['email'],$rol_u,$hash);
            $su->execute();
            $uid = $conn->insert_id;

            $stmt = $conn->prepare("INSERT INTO empleados (id_usuario,nombre,apellidos,dni,email,telefono,departamento,cargo,fecha_alta,salario,activo) VALUES (?,?,?,?,?,?,?,?,?,?,1)");
            $stmt->bind_param('issssssssd', $uid,$datos['nombre'],$datos['apellidos'],$datos['dni'],$datos['email'],$datos['telefono'],$datos['departamento'],$datos['cargo'],$datos['fecha_alta'],$datos['salario']);
            $stmt->execute();
            $msg = 'Empleado dado de alta y usuario creado.';
        }
        $tipo = 'ok'; $accion = '';
    } else {
        $msg = implode(' ', $errores); $tipo = 'error'; $accion = 'nuevo';
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
$params = []; $tipos = '';

if ($buscar !== '') {
    $like    = "%$buscar%";
    $where  .= " AND (e.nombre LIKE ? OR e.apellidos LIKE ? OR e.dni LIKE ? OR e.email LIKE ?)";
    $params  = [$like, $like, $like, $like]; $tipos = 'ssss';
}
if ($depto !== '') {
    $where   .= " AND e.departamento = ?";
    $params[] = $depto; $tipos .= 's';
}

$total_q = $conn->prepare("SELECT COUNT(*) FROM empleados e $where");
if ($params) $total_q->bind_param($tipos, ...$params);
$total_q->execute();
$total_filas = $total_q->get_result()->fetch_row()[0];
$total_pags  = max(1, (int)ceil($total_filas / $porPag));

$q = $conn->prepare("SELECT e.*, u.usuario, u.rol AS rol_usuario, u.activo AS usuario_activo FROM empleados e LEFT JOIN usuarios u ON u.id = e.id_usuario $where ORDER BY FIELD(u.rol,'admin','director','soporte','rrhh','almacen','empleado'), e.apellidos, e.nombre LIMIT ? OFFSET ?");
$params[] = $porPag; $params[] = $offset; $tipos .= 'ii';
$q->bind_param($tipos, ...$params);
$q->execute();
$filas = $q->get_result()->fetch_all(MYSQLI_ASSOC);

$deptos_q = $conn->query("SELECT DISTINCT departamento FROM empleados WHERE activo=1 ORDER BY departamento");

// Filtros de justificantes
$filtro_emp_just   = (int)($_GET['emp_just'] ?? 0);
$filtro_just_fecha = $_GET['just_fecha'] ?? '';
$filtro_just_buscar= trim($_GET['just_buscar'] ?? '');
$where_just = "WHERE 1=1";
if ($filtro_emp_just > 0)      $where_just .= " AND j.usuario_id = $filtro_emp_just";
if ($filtro_just_fecha !== '')  $where_just .= " AND DATE(j.fecha) = '" . $conn->real_escape_string($filtro_just_fecha) . "'";
if ($filtro_just_buscar !== '') {
    $bl = '%' . $conn->real_escape_string($filtro_just_buscar) . '%';
    $where_just .= " AND (j.descripcion LIKE '$bl' OR u.nombre LIKE '$bl' OR u.apellidos LIKE '$bl')";
}
$lista_justificantes = $conn->query("
    SELECT j.id, j.descripcion, j.archivo, j.fecha, u.nombre, u.apellidos
    FROM justificantes j
    JOIN usuarios u ON u.id = j.usuario_id
    $where_just
    ORDER BY j.fecha DESC LIMIT 200
")->fetch_all(MYSQLI_ASSOC);
$empleados_just = $conn->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN justificantes j ON j.usuario_id = u.id GROUP BY u.id ORDER BY u.apellidos");

// Filtros de fichajes
$fecha_fichajes   = $_GET['fecha_fich']  ?? date('Y-m-d');
$fich_depto       = $_GET['fich_depto']  ?? '';
$fich_empleado_id = (int)($_GET['fich_emp'] ?? 0);
$fich_tipo        = $_GET['fich_tipo']   ?? '';

$where_fich = "WHERE DATE(f.fecha) = ?";
if ($fich_depto !== '')      $where_fich .= " AND e.departamento = '" . $conn->real_escape_string($fich_depto) . "'";
if ($fich_empleado_id > 0)   $where_fich .= " AND f.usuario_id = $fich_empleado_id";
if ($fich_tipo !== '')        $where_fich .= " AND f.tipo = '" . $conn->real_escape_string($fich_tipo) . "'";

$fich_q = $conn->prepare("
    SELECT f.usuario_id, f.tipo, f.fecha, u.nombre, u.apellidos, e.departamento
    FROM fichajes f
    JOIN usuarios u ON u.id = f.usuario_id
    LEFT JOIN empleados e ON e.id_usuario = u.id AND e.activo = 1
    $where_fich
    ORDER BY u.apellidos, u.nombre, f.fecha ASC
");
$fich_q->bind_param('s', $fecha_fichajes);
$fich_q->execute();
$filas_fich = $fich_q->get_result()->fetch_all(MYSQLI_ASSOC);

// Agrupar por empleado para tener entrada + salida en la misma fila
$lista_fichajes = [];
foreach ($filas_fich as $f) {
    $id = $f['usuario_id'];
    if (!isset($lista_fichajes[$id])) {
        $lista_fichajes[$id] = [
            'nombre'      => $f['nombre'],
            'apellidos'   => $f['apellidos'],
            'departamento'=> $f['departamento'],
            'entrada'     => null,
            'salida'      => null,
        ];
    }
    $lista_fichajes[$id][$f['tipo']] = $f['fecha'];
}

// Listas para los selectores de filtro
$deptos_fich = $conn->query("SELECT DISTINCT departamento FROM empleados WHERE activo=1 ORDER BY departamento");
$empleados_fich = $conn->query("SELECT DISTINCT u.id, u.nombre, u.apellidos FROM usuarios u JOIN fichajes f ON f.usuario_id = u.id ORDER BY u.apellidos");

$tab = $_GET['tab'] ?? 'empleados';
// Ajustar paginaActiva según el tab para el sidebar
$paginaActiva_map = ['empleados'=>'empleados','fichajes'=>'tab_fichajes','justificantes'=>'tab_just'];
$paginaActiva = $paginaActiva_map[$tab] ?? 'empleados';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Empleados</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
</head><body>
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
            <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div><?php endif; ?>

            <!-- Navegación por pestañas -->
            <div class="panel" style="margin-bottom:16px;">
                <div class="panel-body" style="padding:8px 16px;">
                    <div class="barra-filtros">
                        <a href="empleados.php?tab=empleados"     class="btn <?= $tab==='empleados'     ?'btn-primario':'btn-gris' ?> btn-sm">👥 Empleados</a>
                        <a href="empleados.php?tab=fichajes"      class="btn <?= $tab==='fichajes'      ?'btn-primario':'btn-gris' ?> btn-sm">⏰ Fichajes</a>
                        <a href="empleados.php?tab=justificantes" class="btn <?= $tab==='justificantes' ?'btn-primario':'btn-gris' ?> btn-sm">📄 Justificantes</a>
                    </div>
                </div>
            </div>

            <?php if ($tab === 'empleados'): ?>
            <div class="panel" style="margin-bottom:16px;">
                <div class="panel-body" style="padding:12px 16px;">
                    <form method="GET" class="barra-filtros">
                        <input type="text" name="buscar" placeholder="Nombre, DNI, email..."
                               value="<?= htmlspecialchars($buscar) ?>">
                        <select name="depto">
                            <option value="">Todos los departamentos</option>
                            <?php while ($d = $deptos_q->fetch_row()): ?>
                                <option value="<?= htmlspecialchars($d[0]) ?>" <?= $depto===$d[0]?'selected':'' ?>>
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
                        <tr><th>Nombre</th><th>DNI</th><th>Departamento</th><th>Cargo</th><th>Email</th><th>Alta</th><th>Salario</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($filas)): foreach ($filas as $e): ?>
                    <tr>
                        <td>
                            <a href="#" onclick="abrirModal('det<?= $e['id'] ?>'); return false;"
                               class="fw-600" style="color:var(--azul-medio);">
                                <?= htmlspecialchars($e['nombre'].' '.$e['apellidos']) ?>
                            </a>
                        </td>
                        <td class="text-gris"><?= htmlspecialchars($e['dni']) ?></td>
                        <td><span class="badge badge-azul"><?= htmlspecialchars($e['departamento']) ?></span></td>
                        <td><?= htmlspecialchars($e['cargo']) ?></td>
                        <td class="text-gris"><?= htmlspecialchars($e['email']) ?></td>
                        <td class="text-gris"><?= date('d/m/Y', strtotime($e['fecha_alta'])) ?></td>
                        <td><?= number_format($e['salario'],2,',','.') ?> €</td>
                        <td>
                            <div class="d-flex gap-8">
                                <a href="?accion=editar&id=<?= $e['id'] ?>" class="btn btn-gris btn-sm">✏️ Editar</a>
                                <a href="#"
                                   class="btn btn-rojo btn-sm"
                                   onclick="confirmarLink('¿Dar de baja? También se desactivará su usuario.', '?accion=eliminar&id=<?= $e['id'] ?>')">Baja</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="8" class="text-gris text-center" style="padding:24px;">Sin resultados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pags > 1): ?>
                <div class="paginacion" style="padding:12px 16px;">
                    <?php for ($p = 1; $p <= $total_pags; $p++):
                        $qs = http_build_query(['buscar'=>$buscar,'depto'=>$depto,'pag'=>$p,'tab'=>'empleados']); ?>
                        <?php if ($p===$pag): ?><span class="actual"><?= $p ?></span>
                        <?php else: ?><a href="?<?= $qs ?>"><?= $p ?></a><?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; // tab empleados ?>

            <?php if ($tab === 'fichajes'): ?>
            <!-- Panel de fichajes con filtros avanzados -->
            <div class="panel">
                <div class="panel-header" style="flex-wrap:wrap;gap:8px;">
                    <h2>Registro de fichajes</h2>
                </div>
                <div class="panel-body" style="padding:10px 16px;border-bottom:1px solid var(--gris-borde);">
                    <form method="GET" class="barra-filtros" style="flex-wrap:wrap;gap:8px;">
                        <?php if ($buscar): ?><input type="hidden" name="buscar" value="<?= htmlspecialchars($buscar) ?>">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>"><?php endif; ?>
                        <?php if ($depto): ?><input type="hidden" name="depto"  value="<?= htmlspecialchars($depto)  ?>"><?php endif; ?>
                        <input type="date" name="fecha_fich" value="<?= $fecha_fichajes ?>"
                               style="padding:5px 10px;border:1px solid var(--gris-borde);border-radius:6px;font-size:13px;">
                        <select name="fich_depto" style="font-size:13px;padding:5px 10px;">
                            <option value="">Todos los depts.</option>
                            <?php while($d=$deptos_fich->fetch_row()): ?>
                            <option value="<?=$d[0]?>" <?=$fich_depto===$d[0]?'selected':''?>><?=$d[0]?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="fich_emp" style="font-size:13px;padding:5px 10px;">
                            <option value="0">Todos los empleados</option>
                            <?php while($ef=$empleados_fich->fetch_assoc()): ?>
                            <option value="<?=$ef['id']?>" <?=$fich_empleado_id===(int)$ef['id']?'selected':''?>><?=htmlspecialchars($ef['nombre'].' '.$ef['apellidos'])?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="fich_tipo" style="font-size:13px;padding:5px 10px;">
                            <option value="">Entrada y salida</option>
                            <option value="entrada" <?=$fich_tipo==='entrada'?'selected':''?>>Solo entradas</option>
                            <option value="salida"  <?=$fich_tipo==='salida' ?'selected':''?>>Solo salidas</option>
                        </select>
                        <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                        <a href="empleados.php" class="btn btn-gris btn-sm">↺ Hoy</a>
                    </form>
                </div>
                <?php if (!empty($lista_fichajes)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Empleado</th><th>Departamento</th>
                            <th>Entrada</th><th>Salida</th>
                            <th>Horas trabajadas</th><th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lista_fichajes as $f):
                        $entrada_ts = $f['entrada'] ? strtotime($f['entrada']) : null;
                        $salida_ts  = $f['salida']  ? strtotime($f['salida'])  : null;
                        $ref_ts     = $salida_ts ?? time(); // si no hay salida, comparar con ahora
                        $min_trab   = $entrada_ts ? (int)(($ref_ts - $entrada_ts) / 60) : 0;
                        $hh         = intdiv($min_trab, 60);
                        $mm         = $min_trab % 60;
                        $diff_8h    = $min_trab - 480; // respecto a 8h (480 min)
                    ?>
                    <tr>
                        <td class="fw-600"><?= htmlspecialchars($f['nombre'].' '.$f['apellidos']) ?></td>
                        <td><span class="badge badge-azul"><?= htmlspecialchars($f['departamento'] ?? '—') ?></span></td>
                        <td>
                            <?php if ($f['entrada']): ?>
                                <span class="badge badge-verde"><?= date('H:i', strtotime($f['entrada'])) ?></span>
                            <?php else: ?>
                                <span class="text-gris">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($f['salida']): ?>
                                <span class="badge badge-rojo"><?= date('H:i', strtotime($f['salida'])) ?></span>
                            <?php elseif ($f['entrada']): ?>
                                <span class="text-gris" style="font-size:11px;">En turno</span>
                            <?php else: ?>
                                <span class="text-gris">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-600">
                            <?php if ($entrada_ts): ?>
                                <?= $hh ?>h <?= str_pad($mm,2,'0',STR_PAD_LEFT) ?>min
                                <?php if (!$salida_ts): ?>
                                    <span class="text-gris" style="font-size:11px;">(en curso)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gris">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$entrada_ts): ?>
                                <span class="badge badge-gris">Sin fichar</span>
                            <?php elseif (!$salida_ts): ?>
                                <!-- Todavía trabajando -->
                                <?php $pend = 480 - $min_trab; $ph = intdiv(max(0,$pend),60); $pm = max(0,$pend)%60; ?>
                                <?php if ($pend > 0): ?>
                                    <span class="badge badge-naranja">⏳ Faltan <?= $ph ?>h<?= $pm ?>m</span>
                                <?php else: ?>
                                    <span class="badge badge-verde">+<?= intdiv(-$pend,60) ?>h<?= ((-$pend)%60) ?>m extra</span>
                                <?php endif; ?>
                            <?php elseif ($diff_8h >= 0): ?>
                                <span class="badge badge-verde">+<?= intdiv($diff_8h,60) ?>h<?= str_pad($diff_8h%60,2,'0',STR_PAD_LEFT) ?>m extra</span>
                            <?php elseif ($diff_8h >= -30): ?>
                                <span class="badge badge-verde">✓ Completa</span>
                            <?php else: ?>
                                <?php $fh = intdiv(abs($diff_8h),60); $fm = abs($diff_8h)%60; ?>
                                <span class="badge badge-rojo">–<?= $fh ?>h<?= str_pad($fm,2,'0',STR_PAD_LEFT) ?>m pendientes</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- Resumen del día -->
                <?php
                    $total_emp = count($lista_fichajes);
                    $con_salida = array_filter($lista_fichajes, fn($f) => $f['salida'] !== null);
                    $sin_salida = array_filter($lista_fichajes, fn($f) => $f['entrada'] !== null && $f['salida'] === null);
                    $sin_fich   = array_filter($lista_fichajes, fn($f) => $f['entrada'] === null);
                ?>
                <div style="padding:10px 16px;background:var(--gris-fondo);border-top:1px solid var(--gris-borde);display:flex;gap:16px;font-size:13px;">
                    <span>👥 <strong><?= $total_emp ?></strong> empleados</span>
                    <span>✅ <strong><?= count($con_salida) ?></strong> jornada completa</span>
                    <span>⏳ <strong><?= count($sin_salida) ?></strong> en turno</span>
                    <?php if (count($sin_fich)): ?><span>❌ <strong><?= count($sin_fich) ?></strong> sin fichar</span><?php endif; ?>
                </div>
                <?php else: ?>
                <div class="panel-body"><p class="text-gris">Sin fichajes registrados el <?= date('d/m/Y', strtotime($fecha_fichajes)) ?>.</p></div>
                <?php endif; ?>
            </div>
            <?php endif; // tab fichajes ?>

            <?php if ($tab === 'justificantes'): ?>
            <!-- Panel de justificantes con filtros avanzados -->
            <div class="panel" style="margin-top:20px;">
                <div class="panel-header">
                    <h2>Justificantes de ausencia</h2>
                </div>
                <div class="panel-body" style="padding:10px 16px;border-bottom:1px solid var(--gris-borde);">
                    <form method="GET" class="barra-filtros" style="flex-wrap:wrap;gap:8px;">
                        <?php if ($buscar): ?><input type="hidden" name="buscar" value="<?= htmlspecialchars($buscar) ?>"><?php endif; ?>
                        <?php if ($depto): ?><input type="hidden" name="depto"   value="<?= htmlspecialchars($depto)  ?>"><?php endif; ?>
                        <input type="text" name="just_buscar" placeholder="Buscar descripción o empleado..." value="<?= htmlspecialchars($filtro_just_buscar) ?>" style="min-width:220px;">
                        <select name="emp_just">
                            <option value="0">Todos los empleados</option>
                            <?php while ($ej = $empleados_just->fetch_assoc()): ?>
                                <option value="<?= $ej['id'] ?>" <?= $filtro_emp_just===(int)$ej['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($ej['nombre'].' '.$ej['apellidos']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <input type="date" name="just_fecha" value="<?= htmlspecialchars($filtro_just_fecha) ?>" style="padding:5px 10px;border:1px solid var(--gris-borde);border-radius:6px;font-size:13px;" title="Filtrar por fecha">
                        <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
                        <?php if ($filtro_emp_just || $filtro_just_fecha || $filtro_just_buscar): ?>
                            <a href="empleados.php" class="btn btn-gris btn-sm">✕ Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if (!empty($lista_justificantes)): ?>
                <table>
                    <thead><tr><th>Empleado</th><th>Descripción</th><th>Fecha</th><th>Archivo</th></tr></thead>
                    <tbody>
                    <?php foreach ($lista_justificantes as $j): ?>
                    <tr>
                        <td class="fw-600"><?= htmlspecialchars($j['nombre'].' '.$j['apellidos']) ?></td>
                        <td><?= htmlspecialchars($j['descripcion']) ?></td>
                        <td class="text-gris"><?= date('d/m/Y H:i', strtotime($j['fecha'])) ?></td>
                        <td>
                            <a href="../uploads/justificantes/<?= htmlspecialchars($j['archivo']) ?>"
                               target="_blank" class="btn btn-gris btn-sm">📄 Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="panel-body"><p class="text-gris">No hay justificantes registrados.</p></div>
                <?php endif; ?>
            </div>

            <?php endif; // tab justificantes ?>
        </main>
    </div>
</div>

<!-- Modal: editar/nuevo empleado -->
<div class="modal-overlay <?= in_array($accion,['nuevo','editar'])?'abierto':'' ?>" id="modal-empleado">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $emp_editar ? 'Editar empleado' : 'Nuevo empleado' ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('modal-empleado')">✕</button>
        </div>
        <form method="POST">
            <?php if ($emp_editar): ?><input type="hidden" name="edit_id" value="<?= $emp_editar['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-grupo">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" required value="<?= htmlspecialchars($emp_editar['nombre'] ?? $_POST['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Apellidos *</label>
                        <input type="text" name="apellidos" required value="<?= htmlspecialchars($emp_editar['apellidos'] ?? $_POST['apellidos'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>DNI *</label>
                        <input type="text" name="dni" placeholder="12345678A" value="<?= htmlspecialchars($emp_editar['dni'] ?? $_POST['dni'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($emp_editar['email'] ?? $_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" value="<?= htmlspecialchars($emp_editar['telefono'] ?? $_POST['telefono'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Departamento *</label>
                        <select name="departamento">
                            <?php foreach (['Dirección','Ventas','Contabilidad','RRHH','Recepción','Almacén','IT'] as $dep): ?>
                                <option value="<?= $dep ?>" <?= ($emp_editar['departamento']??'')===$dep?'selected':'' ?>><?= $dep ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Cargo</label>
                        <input type="text" name="cargo" value="<?= htmlspecialchars($emp_editar['cargo'] ?? $_POST['cargo'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Fecha de alta *</label>
                        <input type="date" name="fecha_alta" value="<?= htmlspecialchars($emp_editar['fecha_alta'] ?? $_POST['fecha_alta'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Salario bruto anual (€)</label>
                        <input type="number" name="salario" min="0" step="0.01" value="<?= htmlspecialchars($emp_editar['salario'] ?? $_POST['salario'] ?? '') ?>">
                    </div>
                    <?php if (!$emp_editar): ?>
                    <div class="form-grupo" style="grid-column:1/-1;border-top:1px solid var(--gris-borde);padding-top:12px;margin-top:4px;">
                        <label style="color:var(--azul-medio);font-weight:600;">Acceso al sistema</label>
                    </div>
                    <div class="form-grupo">
                        <label>Usuario *</label>
                        <input type="text" name="usuario" required minlength="3"
                               value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" placeholder="min. 3 caracteres">
                    </div>
                    <div class="form-grupo">
                        <label>Rol</label>
                        <select name="rol">
                            <?php foreach (['empleado'=>'Empleado','rrhh'=>'Resp. RRHH','almacen'=>'Resp. Almacén','soporte'=>'Soporte IT','admin'=>'Administrador'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= ($_POST['rol']??'empleado')===$k?'selected':'' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label>Contraseña inicial *</label>
                        <input type="password" name="contra" required minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gris" onclick="cerrarModal('modal-empleado')">Cancelar</button>
                <button type="submit" class="btn btn-primario"><?= $emp_editar ? 'Guardar cambios' : 'Dar de alta' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modales de detalle (uno por empleado) -->
<?php foreach ($filas as $e):
    $badges = ['admin'=>'badge-rojo','rrhh'=>'badge-azul','almacen'=>'badge-naranja','soporte'=>'badge-verde','empleado'=>'badge-gris'];
?>
<div class="modal-overlay" id="det<?= $e['id'] ?>">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <h3><?= htmlspecialchars($e['nombre'].' '.$e['apellidos']) ?></h3>
            <button class="modal-cerrar" onclick="cerrarModal('det<?= $e['id'] ?>')">✕</button>
        </div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;font-size:14px;">
                <div><span class="text-gris">DNI</span><br><strong><?= htmlspecialchars($e['dni']) ?></strong></div>
                <div><span class="text-gris">Email</span><br><strong><?= htmlspecialchars($e['email']) ?></strong></div>
                <div><span class="text-gris">Teléfono</span><br><strong><?= htmlspecialchars($e['telefono'] ?? '—') ?></strong></div>
                <div><span class="text-gris">Departamento</span><br>
                    <span class="badge badge-azul"><?= htmlspecialchars($e['departamento']) ?></span>
                </div>
                <div><span class="text-gris">Cargo</span><br><strong><?= htmlspecialchars($e['cargo'] ?? '—') ?></strong></div>
                <div><span class="text-gris">Fecha de alta</span><br><strong><?= date('d/m/Y', strtotime($e['fecha_alta'])) ?></strong></div>
                <div><span class="text-gris">Salario bruto</span><br><strong><?= number_format($e['salario'],2,',','.') ?> €</strong></div>
                <div></div>
            </div>
            <div style="border-top:1px solid var(--gris-borde);margin-top:14px;padding-top:14px;">
                <p style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--azul-medio);font-weight:700;margin-bottom:10px;">Acceso al sistema</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;font-size:14px;">
                    <div><span class="text-gris">Usuario</span><br><strong><?= htmlspecialchars($e['usuario'] ?? '—') ?></strong></div>
                    <div><span class="text-gris">Rol</span><br>
                        <?php if ($e['rol_usuario']): ?>
                            <span class="badge <?= $badges[$e['rol_usuario']] ?? 'badge-gris' ?>"><?= nombreRol($e['rol_usuario']) ?></span>
                        <?php else: ?>
                            <span class="text-gris">—</span>
                        <?php endif; ?>
                    </div>
                    <div><span class="text-gris">Estado cuenta</span><br>
                        <?php if (isset($e['usuario_activo'])): ?>
                            <?= $e['usuario_activo']
                                ? '<span class="badge badge-verde">Activo</span>'
                                : '<span class="badge badge-rojo">Inactivo</span>' ?>
                        <?php else: ?>
                            <span class="text-gris">—</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a href="?accion=editar&id=<?= $e['id'] ?>" class="btn btn-gris">✏️ Editar</a>
            <button class="btn btn-primario" onclick="cerrarModal('det<?= $e['id'] ?>')">Cerrar</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="../js/app.js"></script>
</body></html>
