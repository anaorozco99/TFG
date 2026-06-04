<?php
// backup.php — Genera un volcado SQL de la base de datos gestint
// Solo accesible para administradores autenticados
session_start();
require_once 'includes/db.php';

// Comprobar que es admin (acceso directo protegido)
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    die('Acceso denegado.');
}

// Nombre del archivo de backup con fecha y hora
$archivo = 'gestint_backup_' . date('Ymd_His') . '.sql';

// Generar el volcado con mysqldump (requiere que esté en el PATH de XAMPP)
$comando = sprintf(
    'mysqldump --user=root --password="" --host=localhost gestint > "%s"',
    sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archivo
);
exec($comando, $salida, $codigo);

if ($codigo !== 0) {
    die('Error al generar el backup. Asegúrate de que mysqldump está disponible.');
}

// Descargar el archivo generado
$ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archivo;
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
unlink($ruta); // borrar el temporal tras la descarga
exit;
