<?php
// Vacaciones: solicitar, aprobar (RRHH) y calendario
require_once '../includes/auth.php';
require_once '../includes/db.php';

$msg = ''; $tipo = '';
$uid = (int)$_SESSION['usuario_id'];

// Pestaña activa
$tab = $_GET['tab'] ?? 'calendario';
$tab_map = ['calendario'=>'tab_cal','solicitudes'=>'tab_sol','aprobadas'=>'tab_aprobadas','gestion'=>'vacaciones'];
$paginaActiva = $tab_map[$tab] ?? 'tab_cal';

// RRHH: aprobar o rechazar
if (esRRHH() && isset($_GET['accion']) && isset($_GET['id'])) {
    $vid    = (int)$_GET['id'];
    $accion = $_GET['accion'];
    if (in_array($accion, ['aprobar','rechazar'])) {
        $estado = $accion === 'aprobar' ? 'aprobada' : 'rechazada';
        $upd = $conn->prepare("UPDATE vacaciones SET estado = ? WHERE id = ?");
        $upd->bind_param('si', $estado, $vid);
        $upd->execute();
        $msg = $accion === 'aprobar' ? 'Solicitud aprobada.' : 'Solicitud rechazada.';
        $tipo = $accion === 'aprobar' ? 'ok' : 'aviso';
    }
}

// Enviar solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'solicitar') {
    $fi = $_POST['fecha_ini'] ?? ''; $ff = $_POST['fecha_fin'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    if (!$fi || !$ff) { $msg = 'Indica las fechas.'; $tipo = 'error'; }
    else {
        $dias = (int)(new DateTime($fi))->diff(new DateTime($ff))->days + 1;
        if ($dias <= 0) { $msg = 'Fecha fin debe ser posterior al inicio.'; $tipo = 'error'; }
        elseif ($dias > 15) { $msg = 'Máximo 15 días por solicitud.'; $tipo = 'error'; }
        else {
            $ins = $conn->prepare("INSERT INTO vacaciones (usuario_id,fecha_ini,fecha_fin,dias,motivo) VALUES (?,?,?,?,?)");
            $ins->bind_param('isssi', $uid, $fi, $ff, $dias, $motivo);
            $ins->execute();
            $msg = "Solicitud enviada ($dias días). RRHH la revisará pronto."; $tipo = 'ok';
        }
    }
}

// Navegación del calendario
$cal_year  = (int)($_GET['year']  ?? date('Y'));
$cal_month = (int)($_GET['month'] ?? date('n'));
if ($cal_month < 1)  { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1;  $cal_year++; }
$prev_m = $cal_month-1; $prev_y = $cal_year; if($prev_m<1){$prev_m=12;$prev_y--;}
$next_m = $cal_month+1; $next_y = $cal_year; if($next_m>12){$next_m=1;$next_y++;}
$meses_es = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

// Departamento del usuario
$sd = $conn->prepare("SELECT e.departamento FROM empleados e WHERE e.id_usuario = ? AND e.activo=1 LIMIT 1");
$sd->bind_param('i', $uid); $sd->execute();
$depto_usuario = $sd->get_result()->fetch_row()[0] ?? null;

// Colores por persona
$colores = ['#069DE0','#16a34a','#d97706','#9333ea','#dc2626','#0891b2','#65a30d','#ea580c','#7c3aed','#0f766e'];

// Vacaciones aprobadas para el calendario
$vac_where = esRRHH()
    ? "WHERE v.estado='aprobada'"
    : ($depto_usuario ? "WHERE v.estado='aprobada' AND e.departamento='" . $conn->real_escape_string($depto_usuario) . "'" : "WHERE v.estado='aprobada' AND v.usuario_id=$uid");

$vac_cal = $conn->query("
    SELECT v.fecha_ini, v.fecha_fin, v.dias, u.nombre, u.apellidos, u.id AS uid, e.departamento
    FROM vacaciones v
    JOIN usuarios u ON u.id = v.usuario_id
    LEFT JOIN empleados e ON e.id_usuario = u.id AND e.activo=1
    $vac_where ORDER BY v.fecha_ini ASC
")->fetch_all(MYSQLI_ASSOC);

$color_map = []; $ci = 0;
foreach ($vac_cal as $v) {
    if (!isset($color_map[$v['uid']])) { $color_map[$v['uid']] = $colores[$ci++ % count($colores)]; }
}
$dias_cal = [];
foreach ($vac_cal as $v) {
    $d = new DateTime($v['fecha_ini']); $fin = new DateTime($v['fecha_fin']);
    while ($d <= $fin) {
        $key = $d->format('Y-m-d');
        $dias_cal[$key][] = ['label'=>$v['nombre'].' '.$v['apellidos'], 'color'=>$color_map[$v['uid']]];
        $d->modify('+1 day');
    }
}

// Mis solicitudes
$sq = $conn->prepare("SELECT * FROM vacaciones WHERE usuario_id=? ORDER BY created_at DESC LIMIT 30");
$sq->bind_param('i',$uid); $sq->execute();
$mis_solicitudes = $sq->get_result()->fetch_all(MYSQLI_ASSOC);

// Vacaciones aprobadas (lista) para RRHH
$aprobadas = [];
if (esRRHH()) {
    $aprobadas = $conn->query("
        SELECT v.*, u.nombre, u.apellidos, e.departamento
        FROM vacaciones v JOIN usuarios u ON u.id=v.usuario_id
        LEFT JOIN empleados e ON e.id_usuario=u.id AND e.activo=1
        WHERE v.estado='aprobada' ORDER BY v.fecha_ini DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

// Gestión RRHH: pendientes y todas
$filtro_gestion = $_GET['fg'] ?? 'pendientes';
$todas_solicitudes = [];
if (esRRHH()) {
    $wg = $filtro_gestion==='todos' ? "WHERE 1=1" : "WHERE v.estado='pendiente'";
    $todas_solicitudes = $conn->query("
        SELECT v.*, u.nombre, u.apellidos
        FROM vacaciones v JOIN usuarios u ON u.id=v.usuario_id
        $wg ORDER BY v.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Vacaciones</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="../css/style.css">
<style>
.cal-grid { display:grid;grid-template-columns:repeat(7,1fr);gap:2px; }
.cal-header-day { background:var(--azul);color:#fff;text-align:center;padding:6px 0;font-size:12px;font-weight:700;border-radius:4px; }
.cal-day { min-height:72px;background:#fff;border:1px solid var(--gris-borde);border-radius:4px;padding:4px;vertical-align:top; }
.cal-day.otro-mes { background:#f8fafc;opacity:.5; }
.cal-day.hoy { border:2px solid var(--azul-medio); }
.cal-num { font-size:12px;font-weight:700;margin-bottom:3px; }
.cal-evento { font-size:10px;color:#fff;border-radius:3px;padding:1px 4px;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.leyenda-item { display:flex;align-items:center;gap:6px;font-size:12px; }
.leyenda-color { width:12px;height:12px;border-radius:3px;flex-shrink:0; }
</style>
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
    <div class="topbar">
        <h1>Vacaciones</h1>
        <div class="topbar-right text-gris"><?= esRRHH() ? 'Vista RRHH' : ($depto_usuario ?? 'Mi equipo') ?></div>
    </div>
    <main class="main">
        <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- Tabs de navegación -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-body" style="padding:8px 16px;">
                <div class="barra-filtros">
                    <a href="vacaciones.php?tab=calendario"  class="btn <?= $tab==='calendario'  ?'btn-primario':'btn-gris' ?> btn-sm">📅 Calendario</a>
                    <a href="vacaciones.php?tab=solicitudes" class="btn <?= $tab==='solicitudes' ?'btn-primario':'btn-gris' ?> btn-sm">📝 Mis solicitudes</a>
                    <?php if (esRRHH()): ?>
                    <a href="vacaciones.php?tab=aprobadas"   class="btn <?= $tab==='aprobadas'   ?'btn-primario':'btn-gris' ?> btn-sm">✅ Aprobadas</a>
                    <a href="vacaciones.php?tab=gestion"     class="btn <?= $tab==='gestion'     ?'btn-primario':'btn-gris' ?> btn-sm">⚙ Gestión RRHH</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== TAB: CALENDARIO ===== -->
        <?php if ($tab === 'calendario'): ?>
        <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
            <div>
                <div class="panel" style="margin-bottom:20px;">
                    <div class="panel-header">
                        <h2>📅 <?= ucfirst($meses_es[$cal_month]).' '.$cal_year ?></h2>
                        <div class="d-flex gap-8">
                            <a href="?tab=calendario&year=<?=$prev_y?>&month=<?=$prev_m?>" class="btn btn-gris btn-sm">‹</a>
                            <a href="vacaciones.php?tab=calendario" class="btn btn-gris btn-sm">Hoy</a>
                            <a href="?tab=calendario&year=<?=$next_y?>&month=<?=$next_m?>" class="btn btn-gris btn-sm">›</a>
                        </div>
                    </div>
                    <div style="padding:16px;">
                        <div class="cal-grid" style="margin-bottom:4px;">
                            <?php foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $dn): ?>
                            <div class="cal-header-day"><?=$dn?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php
                        $primer = new DateTime("$cal_year-$cal_month-01");
                        $dow    = (int)$primer->format('N');
                        $n_dias = (int)$primer->format('t');
                        $hoy_s  = date('Y-m-d');
                        $prev_last = (clone $primer)->modify('last day of previous month');
                        ?>
                        <div class="cal-grid">
                            <?php for($i=$dow-1;$i>0;$i--):
                                $ds=(clone $prev_last)->modify('-'.($i-1).' days')->format('j'); ?>
                                <div class="cal-day otro-mes"><div class="cal-num"><?=$ds?></div></div>
                            <?php endfor; ?>
                            <?php for($d=1;$d<=$n_dias;$d++):
                                $ds=sprintf('%04d-%02d-%02d',$cal_year,$cal_month,$d);
                                $clase='cal-day'.($ds===$hoy_s?' hoy':'');
                                echo "<div class='$clase'>";
                                echo "<div class='cal-num'>".($ds===$hoy_s?"● $d":$d)."</div>";
                                if(isset($dias_cal[$ds])) foreach($dias_cal[$ds] as $ev) {
                                    $nc=explode(' ',$ev['label'])[0];
                                    echo "<div class='cal-evento' style='background:{$ev['color']};' title='{$ev['label']}'>{$nc}</div>";
                                }
                                echo "</div>";
                            endfor; ?>
                            <?php $resto=(7-($dow-1+$n_dias)%7)%7; for($d=1;$d<=$resto;$d++): ?>
                                <div class="cal-day otro-mes"><div class="cal-num"><?=$d?></div></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php if(!empty($color_map)): ?>
                <div class="panel"><div class="panel-header"><h2>Leyenda</h2></div>
                    <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:12px;">
                    <?php $shown=[];foreach($vac_cal as $v):if(in_array($v['uid'],$shown))continue;$shown[]=$v['uid']; ?>
                        <div class="leyenda-item">
                            <div class="leyenda-color" style="background:<?=$color_map[$v['uid']]?>;"></div>
                            <span><?=htmlspecialchars($v['nombre'].' '.$v['apellidos'])?></span>
                            <?php if(esRRHH()&&$v['departamento']): ?><span class="badge badge-azul" style="font-size:10px;"><?=htmlspecialchars($v['departamento'])?></span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- Columna derecha: solicitar -->
            <div class="panel">
                <div class="panel-header"><h2>Solicitar vacaciones</h2></div>
                <div class="panel-body">
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="accion" value="solicitar">
                        <div class="form-grupo full"><label>Fecha inicio *</label><input type="date" name="fecha_ini" required min="<?=date('Y-m-d')?>"></div>
                        <div class="form-grupo full"><label>Fecha fin *</label><input type="date" name="fecha_fin" required min="<?=date('Y-m-d')?>"></div>
                        <div class="form-grupo full"><label>Motivo (opcional)</label><input type="text" name="motivo" placeholder="Vacaciones verano..."></div>
                        <div class="form-grupo full"><p class="text-gris" style="font-size:12px;margin-bottom:8px;">Máx. 15 días · RRHH aprobará</p>
                            <button type="submit" class="btn btn-primario w-100">Enviar solicitud</button></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===== TAB: MIS SOLICITUDES ===== -->
        <?php elseif ($tab === 'solicitudes'): ?>
        <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
            <div class="panel">
                <div class="panel-header"><h2>Mis solicitudes de vacaciones</h2></div>
                <?php if (!empty($mis_solicitudes)): ?>
                <table>
                    <thead><tr><th>Período</th><th>Días</th><th>Motivo</th><th>Estado</th><th>Respuesta</th></tr></thead>
                    <tbody>
                    <?php foreach ($mis_solicitudes as $v): ?>
                    <tr>
                        <td><?=date('d/m/Y',strtotime($v['fecha_ini']))?> → <?=date('d/m/Y',strtotime($v['fecha_fin']))?></td>
                        <td class="fw-600"><?=$v['dias']?></td>
                        <td class="text-gris"><?=htmlspecialchars($v['motivo']?:'—')?></td>
                        <td>
                            <?php if($v['estado']==='aprobada'): ?><span class="badge badge-verde">Aprobada</span>
                            <?php elseif($v['estado']==='rechazada'): ?><span class="badge badge-rojo">Rechazada</span>
                            <?php else: ?><span class="badge badge-naranja">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-gris"><?=htmlspecialchars($v['respuesta']?:'—')?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="panel-body"><p class="text-gris">No tienes solicitudes todavía.</p></div>
                <?php endif; ?>
            </div>
            <!-- Formulario derecha -->
            <div class="panel">
                <div class="panel-header"><h2>Nueva solicitud</h2></div>
                <div class="panel-body">
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="accion" value="solicitar">
                        <div class="form-grupo full"><label>Fecha inicio *</label><input type="date" name="fecha_ini" required min="<?=date('Y-m-d')?>"></div>
                        <div class="form-grupo full"><label>Fecha fin *</label><input type="date" name="fecha_fin" required min="<?=date('Y-m-d')?>"></div>
                        <div class="form-grupo full"><label>Motivo</label><input type="text" name="motivo"></div>
                        <div class="form-grupo full"><button type="submit" class="btn btn-primario w-100">Enviar solicitud</button></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===== TAB: APROBADAS (RRHH) ===== -->
        <?php elseif ($tab === 'aprobadas' && esRRHH()): ?>
        <div class="panel">
            <div class="panel-header"><h2>✅ Vacaciones aprobadas</h2></div>
            <?php if (!empty($aprobadas)): ?>
            <table>
                <thead><tr><th>Empleado</th><th>Departamento</th><th>Período</th><th>Días</th><th>Motivo</th></tr></thead>
                <tbody>
                <?php foreach ($aprobadas as $v): ?>
                <tr>
                    <td class="fw-600"><?=htmlspecialchars($v['nombre'].' '.$v['apellidos'])?></td>
                    <td class="text-gris"><?=htmlspecialchars($v['departamento']??'—')?></td>
                    <td><?=date('d/m/Y',strtotime($v['fecha_ini']))?> — <?=date('d/m/Y',strtotime($v['fecha_fin']))?></td>
                    <td class="fw-600"><?=$v['dias']?></td>
                    <td class="text-gris"><?=htmlspecialchars($v['motivo']?:'—')?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="panel-body"><p class="text-gris">No hay vacaciones aprobadas todavía.</p></div>
            <?php endif; ?>
        </div>

        <!-- ===== TAB: GESTIÓN RRHH ===== -->
        <?php elseif ($tab === 'gestion' && esRRHH()): ?>
        <div class="panel">
            <div class="panel-header">
                <h2>⚙ Gestión de solicitudes</h2>
                <div class="d-flex gap-8">
                    <a href="?tab=gestion&fg=pendientes" class="btn <?=$filtro_gestion==='pendientes'?'btn-primario':'btn-gris'?> btn-sm">Pendientes</a>
                    <a href="?tab=gestion&fg=todos"      class="btn <?=$filtro_gestion==='todos'?'btn-primario':'btn-gris'?> btn-sm">Todas</a>
                </div>
            </div>
            <?php if (!empty($todas_solicitudes)): ?>
            <table>
                <thead><tr><th>Empleado</th><th>Período</th><th>Días</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($todas_solicitudes as $v): ?>
                <tr>
                    <td class="fw-600"><?=htmlspecialchars($v['nombre'].' '.$v['apellidos'])?></td>
                    <td><?=date('d/m/Y',strtotime($v['fecha_ini']))?> — <?=date('d/m/Y',strtotime($v['fecha_fin']))?></td>
                    <td><?=$v['dias']?></td>
                    <td class="text-gris"><?=htmlspecialchars($v['motivo']?:'—')?></td>
                    <td>
                        <?php if($v['estado']==='aprobada'): ?><span class="badge badge-verde">Aprobada</span>
                        <?php elseif($v['estado']==='rechazada'): ?><span class="badge badge-rojo">Rechazada</span>
                        <?php else: ?><span class="badge badge-naranja">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($v['estado']==='pendiente'): ?>
                        <div class="d-flex gap-8">
                            <a href="?tab=gestion&accion=aprobar&id=<?=$v['id']?>&fg=<?=$filtro_gestion?>"
                               class="btn btn-verde btn-sm"
                               onclick="return confirm('¿Aprobar?')">Aprobar</a>
                            <a href="?tab=gestion&accion=rechazar&id=<?=$v['id']?>&fg=<?=$filtro_gestion?>"
                               class="btn btn-rojo btn-sm"
                               onclick="return confirm('¿Rechazar?')">Rechazar</a>
                        </div>
                        <?php else: ?><span class="text-gris" style="font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="panel-body"><p class="text-gris">No hay solicitudes <?=$filtro_gestion==='pendientes'?'pendientes':'registradas'?>.</p></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
</div>
<script src="../js/app.js"></script>
</body></html>
