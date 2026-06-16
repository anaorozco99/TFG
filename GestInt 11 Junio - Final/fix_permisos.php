<?php
// Script puntual: aplica permisos faltantes sin reinstalar
// Eliminar este archivo tras ejecutarlo una vez.
$conn = new mysqli('localhost', 'root', '', 'gestint');
if ($conn->connect_error) die('Error: ' . $conn->connect_error);

$grants = [
    "GRANT UPDATE ON gestint.inventario TO 'ventas_gestint'@'localhost'",
];

$ok = []; $err = [];
foreach ($grants as $sql) {
    $conn->query($sql);
    if ($conn->error) $err[] = $sql . ' → ' . $conn->error;
    else              $ok[]  = $sql;
}
$conn->query("FLUSH PRIVILEGES");
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Fix permisos</title></head><body>
<h2>Fix permisos MySQL</h2>
<?php foreach ($ok  as $s): ?><p style="color:green">OK: <?= htmlspecialchars($s) ?></p><?php endforeach; ?>
<?php foreach ($err as $s): ?><p style="color:red">ERROR: <?= htmlspecialchars($s) ?></p><?php endforeach; ?>
<p><strong>Elimina este archivo una vez ejecutado.</strong></p>
</body></html>
