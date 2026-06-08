<?php
// Perfil del usuario: datos personales, cambiar contraseña, histórico fichajes y justificantes
require_once '../includes/auth.php';
require_once '../includes/db.php';

$msg = ''; $tipo = '';
$uid = (int)$_SESSION['usuario_id'];

// Datos del usuario actual
$s = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$s->bind_param('i', $uid);
$s->execute();
$usuario = $s->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Cambiar contraseña
    if ($accion === 'contra') {
        $actual  = $_POST['contra_actual']  ?? '';
        $nueva   = $_POST['contra_nueva']   ?? '';
        $repetir = $_POST['contra_repetir'] ?? '';
        $errores = [];
        if (!password_verify($actual, $usuario['password'])) $errores[] = 'La contraseña actual es incorrecta.';
        if (strlen($nueva) < 8) $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        if ($nueva !== $repetir) $errores[] = 'Las contraseñas no coinciden.';
        if (empty($errores)) {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $upd  = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $upd->bind_param('si', $hash, $uid);
            $upd->execute();
            $msg = 'Contraseña actualizada.'; $tipo = 'ok';
        } else {
            $msg = implode(' ', $errores); $tipo = 'error';
        }
    }

    // Subir justificante de ausencia
    if ($accion === 'justificante') {
        $desc = trim($_POST['descripcion'] ?? '');
        $file = $_FILES['archivo'] ?? null;
        if ($desc === '' || !$file || $file['error'] !== 0) {
            $msg = 'Rellena la descripción y selecciona un archivo.'; $tipo = 'error';
        } else {
            $ext_ok = ['pdf','jpg','jpeg','png'];
            $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $ext_ok)) {
                $msg = 'Solo se permiten PDF, JPG o PNG.'; $tipo = 'error';
            } else {
                $dir = '../uploads/justificantes/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $nombre_archivo = time() . '_' . $uid . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . $nombre_archivo)) {
                    $ins = $conn->prepare("INSERT INTO justificantes (usuario_id, descripcion, archivo) VALUES (?, ?, ?)");
                    $ins->bind_param('iss', $uid, $desc, $nombre_archivo);
                    $ins->execute();
                    $msg = 'Justificante subido correctamente.'; $tipo = 'ok';
                } else {
                    $msg = 'Error al subir el archivo. Comprueba permisos de la carpeta.'; $tipo = 'error';
                }
            }
        }
    }
}

// Nombres de meses en español para el título
$meses_es = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$titulo_mes = $meses_es[(int)date('n')] . ' de ' . date('Y');

// Historial de fichajes del mes — agrupados por día para calcular horas
$s_hist = $conn->prepare("SELECT tipo, fecha FROM fichajes WHERE usuario_id = ? AND fecha >= DATE_FORMAT(NOW(),'%Y-%m-01') ORDER BY fecha ASC LIMIT 120");
$s_hist->bind_param('i', $uid);
$s_hist->execute();
$filas_hist = $s_hist->get_result()->fetch_all(MYSQLI_ASSOC);

// Agrupar por día: cada día tiene entrada y/o salida
$historial_fichajes = [];
foreach ($filas_hist as $f) {
    $dia = date('Y-m-d', strtotime($f['fecha']));
    if (!isset($historial_fichajes[$dia])) {
        $historial_fichajes[$dia] = ['entrada' => null, 'salida' => null];
    }
    $historial_fichajes[$dia][$f['tipo']] = $f['fecha'];
}
// Orden descendente (día más reciente primero)
krsort($historial_fichajes);

// Mis justificantes
$s_just = $conn->prepare("SELECT descripcion, archivo, fecha FROM justificantes WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 20");
$s_just->bind_param('i', $uid);
$s_just->execute();
$mis_justificantes = $s_just->get_result()->fetch_all(MYSQLI_ASSOC);

$paginaActiva = 'perfil';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Mi perfil</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
  <div class="topbar"><h1>Mi perfil</h1></div>
  <main class="main">
    <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div><?php endif; ?>

    <!-- Datos de cuenta + cambiar contraseña -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
      <div class="panel">
        <div class="panel-header"><h2>Información de cuenta</h2></div>
        <div class="panel-body">
          <table>
            <tr><td class="text-gris" style="padding:8px 0;width:130px;">Nombre</td><td class="fw-600"><?= htmlspecialchars($usuario['nombre'].' '.$usuario['apellidos']) ?></td></tr>
            <tr><td class="text-gris" style="padding:8px 0;">Usuario</td><td><?= htmlspecialchars($usuario['usuario']) ?></td></tr>
            <tr><td class="text-gris" style="padding:8px 0;">Email</td><td><?= htmlspecialchars($usuario['email']) ?></td></tr>
            <tr><td class="text-gris" style="padding:8px 0;">Rol</td><td><span class="badge badge-azul"><?= nombreRol($usuario['rol']) ?></span></td></tr>
          </table>
        </div>
      </div>
      <div class="panel">
        <div class="panel-header"><h2>Cambiar contraseña</h2></div>
        <div class="panel-body">
          <form method="POST" class="form-grid">
            <input type="hidden" name="accion" value="contra">
            <div class="form-grupo full"><label>Contraseña actual</label><input type="password" name="contra_actual" required></div>
            <div class="form-grupo full"><label>Nueva contraseña</label><input type="password" name="contra_nueva" required minlength="8"></div>
            <div class="form-grupo full"><label>Repetir nueva</label><input type="password" name="contra_repetir" required></div>
            <div class="form-grupo full"><button type="submit" class="btn btn-primario">Cambiar contraseña</button></div>
          </form>
        </div>
      </div>
    </div>

    <!-- Historial de fichajes del mes -->
    <div class="panel" style="margin-bottom:20px;">
      <div class="panel-header">
        <h2>Mis fichajes — <?= $titulo_mes ?></h2>
        <a href="dashboard.php" class="btn btn-primario btn-sm">Fichar ahora →</a>
      </div>
      <?php if (!empty($historial_fichajes)): ?>
      <table>
        <thead>
          <tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Horas</th><th>Estado</th></tr>
        </thead>
        <tbody>
        <?php foreach ($historial_fichajes as $dia => $f):
            $e_ts    = $f['entrada'] ? strtotime($f['entrada']) : null;
            $s_ts    = $f['salida']  ? strtotime($f['salida'])  : null;
            $ref_ts  = $s_ts ?? ($dia === date('Y-m-d') ? time() : null);
            $min_trab = ($e_ts && $ref_ts) ? (int)(($ref_ts - $e_ts) / 60) : 0;
            $hh      = intdiv($min_trab, 60);
            $mm      = $min_trab % 60;
            $diff_8h = $min_trab - 480;
        ?>
        <tr>
          <td class="fw-600"><?= date('d/m/Y', strtotime($dia)) ?></td>
          <td>
            <?php if ($f['entrada']): ?>
              <span class="badge badge-verde"><?= date('H:i', strtotime($f['entrada'])) ?></span>
            <?php else: ?><span class="text-gris">—</span><?php endif; ?>
          </td>
          <td>
            <?php if ($f['salida']): ?>
              <span class="badge badge-rojo"><?= date('H:i', strtotime($f['salida'])) ?></span>
            <?php elseif ($f['entrada'] && $dia === date('Y-m-d')): ?>
              <span class="text-gris" style="font-size:11px;">En turno</span>
            <?php else: ?><span class="text-gris">—</span><?php endif; ?>
          </td>
          <td class="fw-600">
            <?php if ($e_ts && $ref_ts): ?>
              <?= $hh ?>h <?= str_pad($mm,2,'0',STR_PAD_LEFT) ?>min
              <?php if (!$s_ts && $dia === date('Y-m-d')): ?>
                <span class="text-gris" style="font-size:11px;">(en curso)</span>
              <?php endif; ?>
            <?php else: ?><span class="text-gris">—</span><?php endif; ?>
          </td>
          <td>
            <?php if (!$e_ts): ?>
              <span class="badge badge-gris">Sin fichar</span>
            <?php elseif (!$s_ts && $dia !== date('Y-m-d')): ?>
              <span class="badge badge-naranja">Sin salida</span>
            <?php elseif ($diff_8h >= 0): ?>
              <span class="badge badge-verde">+<?= intdiv($diff_8h,60) ?>h<?= str_pad($diff_8h%60,2,'0',STR_PAD_LEFT) ?>m extra</span>
            <?php elseif ($diff_8h >= -30): ?>
              <span class="badge badge-verde">✓ Completa</span>
            <?php else: ?>
              <?php $fh=intdiv(abs($diff_8h),60); $fm=abs($diff_8h)%60; ?>
              <span class="badge badge-rojo">–<?= $fh ?>h<?= str_pad($fm,2,'0',STR_PAD_LEFT) ?>m pendientes</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="panel-body"><p class="text-gris">Sin fichajes este mes todavía.</p></div>
      <?php endif; ?>
    </div>

    <!-- Mis justificantes de ausencia -->
    <div class="panel">
      <div class="panel-header"><h2>Mis justificantes de ausencia</h2></div>
      <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" class="form-grid" style="margin-bottom:20px;">
          <input type="hidden" name="accion" value="justificante">
          <div class="form-grupo">
            <label>Descripción (ej: baja médica 3/6)</label>
            <input type="text" name="descripcion" placeholder="Motivo de la ausencia" required>
          </div>
          <div class="form-grupo">
            <label>Archivo (PDF, JPG, PNG)</label>
            <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required>
          </div>
          <div class="form-grupo" style="align-self:flex-end;">
            <button type="submit" class="btn btn-primario">Subir justificante</button>
          </div>
        </form>
        <?php if (!empty($mis_justificantes)): ?>
        <table>
          <thead><tr><th>Descripción</th><th>Archivo</th><th>Fecha</th></tr></thead>
          <tbody>
          <?php foreach ($mis_justificantes as $j): ?>
          <tr>
            <td><?= htmlspecialchars($j['descripcion']) ?></td>
            <td><a href="../uploads/justificantes/<?= htmlspecialchars($j['archivo']) ?>" target="_blank" class="btn btn-gris btn-sm">📄 Ver</a></td>
            <td class="text-gris"><?= date('d/m/Y H:i', strtotime($j['fecha'])) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p class="text-gris">No has subido ningún justificante todavía.</p>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>
</div>

<script src="../js/app.js"></script>
</body></html>
