<?php
// script de un solo uso: ajusta fechas de vacaciones que caen en fin de semana
// y recalcula los días laborables correctamente
// ejecutar desde el navegador una sola vez y luego borrar el archivo

require_once 'includes/db.php';

// calcula días laborables entre dos fechas (ambas inclusive)
function dias_laborables(string $ini, string $fin): int {
    $d   = new DateTime($ini);
    $end = new DateTime($fin);
    $n   = 0;
    while ($d <= $end) {
        $dow = (int)$d->format('N'); // 1=lun ... 7=dom
        if ($dow < 6) $n++;
        $d->modify('+1 day');
    }
    return $n;
}

// mueve la fecha al siguiente lunes si cae en finde
function siguiente_lunes(string $fecha): string {
    $d   = new DateTime($fecha);
    $dow = (int)$d->format('N');
    if ($dow === 6) $d->modify('+2 days'); // sábado → lunes
    if ($dow === 7) $d->modify('+1 day');  // domingo → lunes
    return $d->format('Y-m-d');
}

// mueve la fecha al viernes anterior si cae en finde
function viernes_anterior(string $fecha): string {
    $d   = new DateTime($fecha);
    $dow = (int)$d->format('N');
    if ($dow === 6) $d->modify('-1 day');  // sábado → viernes
    if ($dow === 7) $d->modify('-2 days'); // domingo → viernes
    return $d->format('Y-m-d');
}

$vacaciones = $conn->query("SELECT id, usuario_id, fecha_ini, fecha_fin, dias FROM vacaciones")->fetch_all(MYSQLI_ASSOC);

$cambios = [];

foreach ($vacaciones as $v) {
    $ini_orig = $v['fecha_ini'];
    $fin_orig = $v['fecha_fin'];

    $ini_nuevo = siguiente_lunes($ini_orig);
    $fin_nuevo  = viernes_anterior($fin_orig);

    // si tras ajustar el fin queda antes que el ini, usamos el ini como fin (1 día)
    if ($fin_nuevo < $ini_nuevo) $fin_nuevo = $ini_nuevo;

    $dias_nuevos = dias_laborables($ini_nuevo, $fin_nuevo);

    $hubo_cambio = ($ini_nuevo !== $ini_orig || $fin_nuevo !== $fin_orig || $dias_nuevos !== (int)$v['dias']);

    if ($hubo_cambio) {
        $stmt = $conn->prepare("UPDATE vacaciones SET fecha_ini=?, fecha_fin=?, dias=? WHERE id=?");
        $stmt->bind_param('ssii', $ini_nuevo, $fin_nuevo, $dias_nuevos, $v['id']);
        $stmt->execute();
        $cambios[] = [
            'id'      => $v['id'],
            'antes'   => "$ini_orig → $fin_orig ({$v['dias']} días)",
            'despues' => "$ini_nuevo → $fin_nuevo ($dias_nuevos días laborables)",
        ];
    }
}

echo '<style>body{font-family:sans-serif;padding:30px;} table{border-collapse:collapse;} td,th{border:1px solid #ccc;padding:8px 12px;} .ok{color:green;}</style>';
echo '<h2>Corrección de vacaciones — fechas que caían en fin de semana</h2>';

if (empty($cambios)) {
    echo '<p class="ok">No había registros que corregir.</p>';
} else {
    echo '<table><thead><tr><th>ID</th><th>Antes</th><th>Después</th></tr></thead><tbody>';
    foreach ($cambios as $c) {
        echo "<tr><td>{$c['id']}</td><td>{$c['antes']}</td><td>{$c['despues']}</td></tr>";
    }
    echo '</tbody></table>';
    echo '<p class="ok" style="margin-top:16px;">Correcciones aplicadas: ' . count($cambios) . '</p>';
}

echo '<p style="margin-top:20px;color:#999;font-size:13px;">Ya puedes borrar este archivo.</p>';
