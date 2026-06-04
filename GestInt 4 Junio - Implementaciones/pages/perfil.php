<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$msg = ''; $tipo = '';
$uid = $_SESSION['usuario_id'];

// datos del usuario actual
$s = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$s->bind_param('i', $uid);
$s->execute();
$usuario = $s->get_result()->fetch_assoc();

// handle acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // cambiar contraseña
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

    // fichar entrada/salida (máx 1 entrada y 1 salida por día)
    if ($accion === 'fichar') {
        $sc = $conn->prepare("SELECT tipo FROM fichajes WHERE usuario_id = ? AND DATE(fecha) = CURDATE() ORDER BY fecha ASC");
        $sc->bind_param('i', $uid);
        $sc->execute();
        $hoy_rows = $sc->get_result()->fetch_all(MYSQLI_ASSOC);
        $n_hoy = count($hoy_rows);

        if ($n_hoy >= 2) {
            $msg = 'Ya has completado el fichaje de hoy (entrada + salida).'; $tipo = 'aviso';
        } elseif ($n_hoy === 0) {
            $ins = $conn->prepare("INSERT INTO fichajes (usuario_id, tipo) VALUES (?, 'entrada')");
            $ins->bind_param('i', $uid);
            $ins->execute();
            $msg = 'Entrada registrada.'; $tipo = 'ok';
        } elseif ($n_hoy === 1 && $hoy_rows[0]['tipo'] === 'entrada') {
            $ins = $conn->prepare("INSERT INTO fichajes (usuario_id, tipo) VALUES (?, 'salida')");
            $ins->bind_param('i', $uid);
            $ins->execute();
            $msg = 'Salida registrada.'; $tipo = 'ok';
        } else {
            $msg = 'Estado de fichaje inconsistente. Contacta con RRHH.'; $tipo = 'error';
        }
    }

    // subir justificante
    if ($accion === 'justificante') {
        $desc = trim($_POST['descripcion'] ?? '');
        $file = $_FILES['archivo'] ?? null;
        if ($desc === '' || !$file || $file['error'] !== 0) {
            $msg = 'Rellena la descripción y selecciona un archivo.'; $tipo = 'error';
        } else {
            $ext_ok  = ['pdf','jpg','jpeg','png'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
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

// fichajes de HOY para el usuario actual
$s_hoy = $conn->prepare("SELECT tipo, fecha FROM fichajes WHERE usuario_id = ? AND DATE(fecha) = CURDATE() ORDER BY fecha ASC");
$s_hoy->bind_param('i', $uid);
$s_hoy->execute();
$fichajes_hoy = $s_hoy->get_result()->fetch_all(MYSQLI_ASSOC);

$n_hoy = count($fichajes_hoy);
$puede_entrada = $n_hoy === 0;
$puede_salida  = $n_hoy === 1 && $fichajes_hoy[0]['tipo'] === 'entrada';
$completado    = $n_hoy >= 2;

// propios justificantes
$s_just = $conn->prepare("SELECT id, descripcion, archivo, fecha FROM justificantes WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 20");
$s_just->bind_param('i', $uid);
$s_just->execute();
$mis_justificantes = $s_just->get_result()->fetch_all(MYSQLI_ASSOC);

// todos los justificantes (solo admin/rrhh)
$todos_justificantes = null;
if (esRRHH()) {
    $todos_justificantes = $conn->query("
        SELECT j.id, j.descripcion, j.archivo, j.fecha, u.nombre, u.apellidos
        FROM justificantes j
        JOIN usuarios u ON u.id = j.usuario_id
        ORDER BY j.fecha DESC
        LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);
}

$paginaActiva = 'perfil';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Mi perfil</title>
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
  <div class="topbar"><h1>Mi perfil</h1></div>
  <main class="main">
    <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div><?php endif; ?>

    <!-- Fila 1: datos cuenta + cambiar contraseña -->
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

    <!-- Fila 2: fichaje -->
    <div class="panel" style="margin-bottom:20px;">
      <div class="panel-header">
        <h2>Fichaje — <?= date('d/m/Y') ?></h2>
        <?php if ($completado): ?>
          <span class="badge badge-verde">Jornada completa ✓</span>
        <?php endif; ?>
      </div>
      <div class="panel-body">
        <div style="display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:center;">
          <form method="POST">
            <input type="hidden" name="accion" value="fichar">
            <?php if ($completado): ?>
              <button type="submit" class="btn btn-gris" disabled style="font-size:15px;padding:12px 28px;opacity:.5;cursor:not-allowed;">
                Fichaje completado
              </button>
            <?php elseif ($puede_entrada): ?>
              <button type="submit" class="btn btn-verde" style="font-size:15px;padding:12px 28px;">
                ✅ Registrar entrada
              </button>
            <?php else: ?>
              <button type="submit" class="btn btn-rojo" style="font-size:15px;padding:12px 28px;">
                🚪 Registrar salida
              </button>
            <?php endif; ?>
          </form>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php foreach ($fichajes_hoy as $f): ?>
              <span class="badge <?= $f['tipo']==='entrada' ? 'badge-verde' : 'badge-rojo' ?>" style="font-size:13px;padding:4px 12px;">
                <?= ucfirst($f['tipo']) ?> — <?= date('H:i', strtotime($f['fecha'])) ?>
              </span>
            <?php endforeach; ?>
            <?php if (empty($fichajes_hoy)): ?>
              <span class="text-gris">Sin fichajes hoy todavía.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Fila 3: mis justificantes -->
    <div class="panel" style="margin-bottom:20px;">
      <div class="panel-header">
        <h2>Mis justificantes de ausencia</h2>
      </div>
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
            <td><a href="../uploads/justificantes/<?= $j['archivo'] ?>" target="_blank" class="btn btn-gris btn-sm">📄 Ver</a></td>
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

    <!-- Fila 4: todos los justificantes (solo admin/rrhh) -->
    <?php if (esRRHH() && $todos_justificantes !== null): ?>
    <div class="panel">
      <div class="panel-header">
        <h2>🔒 Justificantes de todos los empleados</h2>
        <span class="text-gris">Solo visible para Administración y RRHH</span>
      </div>
      <?php if (!empty($todos_justificantes)): ?>
      <table>
        <thead><tr><th>Empleado</th><th>Descripción</th><th>Archivo</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($todos_justificantes as $j): ?>
        <tr>
          <td class="fw-600"><?= htmlspecialchars($j['nombre'].' '.$j['apellidos']) ?></td>
          <td><?= htmlspecialchars($j['descripcion']) ?></td>
          <td><a href="../uploads/justificantes/<?= $j['archivo'] ?>" target="_blank" class="btn btn-gris btn-sm">📄 Ver</a></td>
          <td class="text-gris"><?= date('d/m/Y H:i', strtotime($j['fecha'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="panel-body"><p class="text-gris">Ningún empleado ha subido justificantes.</p></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </main>
</div>
</div>
<script src="../js/app.js"></script>
</body></html>
