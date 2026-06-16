<?php
// Descarga segura de justificantes
// Verifica sesión activa y que el usuario tenga permiso antes de servir el archivo

require_once '../includes/auth.php';
require_once '../includes/db.php';

$archivo = basename($_GET['f'] ?? ''); // basename() elimina cualquier intento de path traversal

if (!$archivo) {
    http_response_code(400);
    die('Archivo no especificado.');
}

// Solo letras, números, guión bajo, guión y extensión — sin rutas
if (!preg_match('/^[\w\-]+\.(pdf|jpg|jpeg|png)$/i', $archivo)) {
    http_response_code(400);
    die('Nombre de archivo no válido.');
}

$ruta = __DIR__ . '/../uploads/justificantes/' . $archivo;

if (!file_exists($ruta)) {
    http_response_code(404);
    die('Archivo no encontrado.');
}

$uid = (int)$_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? '';

// Admin y RRHH pueden ver cualquier justificante
// El resto solo puede ver los suyos propios
if (!in_array($rol, ['sistema', 'it', 'rrhh', 'direccion'])) {
    $chk = $conn->prepare("SELECT id FROM justificantes WHERE archivo = ? AND usuario_id = ?");
    $chk->bind_param('si', $archivo, $uid);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        http_response_code(403);
        die('No tienes permiso para acceder a este archivo.');
    }
}

// Determinar tipo MIME
$ext  = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
$mime = match($ext) {
    'pdf'        => 'application/pdf',
    'jpg','jpeg' => 'image/jpeg',
    'png'        => 'image/png',
    default      => 'application/octet-stream',
};

// Servir el archivo
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
