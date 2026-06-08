<?php
session_start();
require_once 'includes/db.php';
if (isset($_SESSION['usuario_id'])) { header('Location: pages/dashboard.php'); exit; }

$error       = '';
$ticket_ok   = false;
$ticket_err  = '';
$vista       = $_GET['vista'] ?? 'login'; // 'login' o 'ticket'

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_login'])) {
    $usuario = trim($_POST['usuario'] ?? '');
    $contra  = $_POST['contra'] ?? '';
    if ($usuario === '' || $contra === '') {
        $error = 'Rellena todos los campos.';
    } else {
        $stmt = $conn->prepare("SELECT id,nombre,apellidos,password,rol FROM usuarios WHERE usuario=? AND activo=1 LIMIT 1");
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        if ($fila && password_verify($contra, $fila['password'])) {
            $_SESSION['usuario_id'] = $fila['id'];
            $_SESSION['usuario']    = $usuario;
            $_SESSION['nombre']     = $fila['nombre'].' '.$fila['apellidos'];
            $_SESSION['rol']        = $fila['rol'];
            $ip = $_SERVER['REMOTE_ADDR'];
            $log = $conn->prepare("INSERT INTO logs_acceso (usuario_id,ip,fecha) VALUES (?,?,NOW())");
            $log->bind_param('is', $fila['id'], $ip);
            $log->execute();
            header('Location: pages/dashboard.php'); exit;
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
}

// Procesar nuevo ticket desde la pantalla de login (sin autenticación)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_ticket'])) {
    $tnombre = trim($_POST['t_nombre'] ?? '');
    $temail  = trim($_POST['t_email']  ?? '');
    $tasunto = trim($_POST['t_asunto'] ?? '');
    $tdesc   = trim($_POST['t_desc']   ?? '');
    if ($tnombre === '' || $temail === '' || $tasunto === '' || $tdesc === '') {
        $ticket_err = 'Rellena todos los campos del ticket.';
        $vista = 'ticket';
    } elseif (!filter_var($temail, FILTER_VALIDATE_EMAIL)) {
        $ticket_err = 'El email no es válido.';
        $vista = 'ticket';
    } else {
        $ins = $conn->prepare("INSERT INTO tickets (nombre, email, asunto, descripcion) VALUES (?,?,?,?)");
        $ins->bind_param('ssss', $tnombre, $temail, $tasunto, $tdesc);
        $ins->execute();
        $ticket_ok = true;
        $vista = 'login';
    }
}
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Acceso</title>
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="css/style.css">
</head><body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="logo-icon">DM</div>
      <h1>DUNDER MIFFLIN</h1>
      <p>Paper Company · Scranton Branch</p>
      <p style="font-size:11px;margin-top:4px;color:#94a3b8;">Sistema de Gestión Interna</p>
    </div>

    <?php if ($ticket_ok): ?>
      <div class="alerta alerta-ok">Ticket enviado correctamente. El equipo de soporte se pondrá en contacto contigo.</div>
    <?php endif; ?>

    <?php if ($vista === 'ticket'): ?>
      <!-- Formulario de ticket de soporte (sin login) -->
      <?php if ($ticket_err): ?><div class="alerta alerta-error"><?=htmlspecialchars($ticket_err)?></div><?php endif; ?>
      <p style="font-size:13px;color:#475569;margin-bottom:14px;">¿Tienes un problema o has olvidado tu contraseña? Describe tu incidencia y el equipo de soporte te ayudará.</p>
      <form method="POST" novalidate>
        <input type="hidden" name="accion_ticket" value="1">
        <div class="form-grupo">
          <label>Tu nombre *</label>
          <input type="text" name="t_nombre" required value="<?=htmlspecialchars($_POST['t_nombre']??'')?>">
        </div>
        <div class="form-grupo">
          <label>Email de contacto *</label>
          <input type="email" name="t_email" required value="<?=htmlspecialchars($_POST['t_email']??'')?>">
        </div>
        <div class="form-grupo">
          <label>Asunto *</label>
          <input type="text" name="t_asunto" required value="<?=htmlspecialchars($_POST['t_asunto']??'')?>" placeholder="Ej: No recuerdo mi contraseña">
        </div>
        <div class="form-grupo">
          <label>Descripción *</label>
          <textarea name="t_desc" rows="4" required placeholder="Describe el problema con detalle..."
            style="width:100%;padding:8px 12px;border:1px solid var(--gris-borde);border-radius:6px;font-size:14px;resize:vertical;"><?=htmlspecialchars($_POST['t_desc']??'')?></textarea>
        </div>
        <div class="mt-16"><button type="submit" class="btn btn-primario w-100" style="justify-content:center;padding:11px;">Enviar ticket</button></div>
      </form>
      <p class="text-gris text-center mt-16"><a href="login.php" style="color:var(--azul-medio);">← Volver al acceso</a></p>

    <?php else: ?>
      <!-- Formulario de login normal -->
      <?php if($error): ?><div class="alerta alerta-error"><?=htmlspecialchars($error)?></div><?php endif; ?>
      <form method="POST" novalidate>
        <input type="hidden" name="accion_login" value="1">
        <div class="form-grupo">
          <label for="usuario">Usuario</label>
          <input type="text" id="usuario" name="usuario" value="<?=htmlspecialchars($_POST['usuario']??'')?>" placeholder="Nombre de usuario" autocomplete="username" required>
        </div>
        <div class="form-grupo">
          <label for="contra">Contraseña</label>
          <input type="password" id="contra" name="contra" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <div class="mt-16"><button type="submit" class="btn btn-primario w-100" style="justify-content:center;padding:11px;">Entrar</button></div>
      </form>
      <!-- Enlace para abrir un ticket de soporte desde la pantalla de login -->
      <p class="text-gris text-center mt-16">
        Acceso restringido al personal autorizado<br>
        <a href="?vista=ticket" style="color:var(--azul-medio);font-size:13px;margin-top:6px;display:inline-block;">
          ¿Problemas para acceder? Abre un ticket
        </a>
      </p>
    <?php endif; ?>
  </div>
</div>
</body></html>
