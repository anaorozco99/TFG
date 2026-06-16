<?php
// backup.php — Genera un volcado SQL de la BD gestint usando PHP puro (sin mysqldump)
// Solo accesible para admin y soporte IT
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['sistema','it'])) {
    http_response_code(403); die('Acceso denegado.');
}

$tablas = ['usuarios','empleados','inventario','pedidos','pedidos_lineas',
           'logs_acceso','clientes','pedidos_ventas','pedidos_ventas_lineas',
           'fichajes','justificantes','tickets','vacaciones'];

$sql  = "-- GestInt Backup — " . date('Y-m-d H:i:s') . "\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tablas as $tabla) {
    // Estructura
    $res = $conn->query("SHOW CREATE TABLE `$tabla`");
    if (!$res) continue;
    $row  = $res->fetch_row();
    $sql .= "DROP TABLE IF EXISTS `$tabla`;\n";
    $sql .= $row[1] . ";\n\n";

    // Datos
    $res2 = $conn->query("SELECT * FROM `$tabla`");
    if (!$res2 || $res2->num_rows === 0) continue;
    $sql .= "INSERT INTO `$tabla` VALUES\n";
    $filas = [];
    while ($fila = $res2->fetch_row()) {
        $vals = array_map(function($v) use ($conn) {
            return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
        }, $fila);
        $filas[] = '(' . implode(',', $vals) . ')';
    }
    $sql .= implode(",\n", $filas) . ";\n\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

// Descargar como archivo
$archivo = 'gestint_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . strlen($sql));
echo $sql;
exit;
