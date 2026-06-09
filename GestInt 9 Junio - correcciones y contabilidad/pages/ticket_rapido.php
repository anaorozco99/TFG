<?php
// Procesa el envío de un ticket rápido desde el sidebar (usuario autenticado)
require_once '../includes/auth.php';
require_once '../includes/db.php';

$asunto = trim($_POST['t_asunto'] ?? '');
$desc   = trim($_POST['t_desc']   ?? '');
$uid    = (int)$_SESSION['usuario_id'];
$ref    = $_SERVER['HTTP_REFERER'] ?? '../pages/dashboard.php';

if ($asunto !== '' && $desc !== '') {
    // Coge nombre y email del usuario desde la BD
    $s = $conn->prepare("SELECT nombre, apellidos, email FROM usuarios WHERE id = ?");
    $s->bind_param('i', $uid);
    $s->execute();
    $u = $s->get_result()->fetch_assoc();
    $nombre = ($u['nombre'] ?? '') . ' ' . ($u['apellidos'] ?? '');
    $email  = $u['email'] ?? '';

    $ins = $conn->prepare("INSERT INTO tickets (nombre, email, asunto, descripcion, usuario_id) VALUES (?,?,?,?,?)");
    $ins->bind_param('ssssi', $nombre, $email, $asunto, $desc, $uid);
    $ins->execute();
}

// Volver a la página anterior con mensaje
header('Location: ' . $ref . (strpos($ref,'?')!==false ? '&' : '?') . 'ticket_ok=1');
exit;
