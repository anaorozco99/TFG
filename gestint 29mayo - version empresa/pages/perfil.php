<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$msg  = '';
$tipo = '';

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $_SESSION['usuario_id']);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual  = $_POST['contra_actual']  ?? '';
    $nueva   = $_POST['contra_nueva']   ?? '';
    $repetir = $_POST['contra_repetir'] ?? '';
    $errores = [];

    if (!password_verify($actual, $usuario['password'])) $errores[] = 'La contraseña actual es incorrecta.';
    if (strlen($nueva) < 8)   $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    if ($nueva !== $repetir)  $errores[] = 'Las contraseñas no coinciden.';

    if (empty($errores)) {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $upd  = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $upd->bind_param('si', $hash, $_SESSION['usuario_id']);
        $upd->execute();
        $msg  = 'Contraseña actualizada.';
        $tipo = 'ok';
    } else {
        $msg  = implode(' ', $errores);
        $tipo = 'error';
    }
}

$paginaActiva = 'perfil';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dunder Mifflin — Mi perfil</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="layout">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="contenido">
        <div class="topbar"><h1>Mi perfil</h1></div>
        <main class="main">
            <?php if ($msg): ?>
                <div class="alerta alerta-<?= $tipo ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div class="panel">
                    <div class="panel-header"><h2>Información de cuenta</h2></div>
                    <div class="panel-body">
                        <table>
                            <tr>
                                <td class="text-gris" style="padding:8px 0;width:130px;">Nombre</td>
                                <td class="fw-600"><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-gris" style="padding:8px 0;">Usuario</td>
                                <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-gris" style="padding:8px 0;">Email</td>
                                <td><?= htmlspecialchars($usuario['email']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-gris" style="padding:8px 0;">Rol</td>
                                <td><span class="badge badge-azul"><?= nombreRol($usuario['rol']) ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><h2>Cambiar contraseña</h2></div>
                    <div class="panel-body">
                        <form method="POST" class="form-grid">
                            <div class="form-grupo full">
                                <label>Contraseña actual</label>
                                <input type="password" name="contra_actual" required>
                            </div>
                            <div class="form-grupo full">
                                <label>Nueva contraseña</label>
                                <input type="password" name="contra_nueva" required minlength="8">
                            </div>
                            <div class="form-grupo full">
                                <label>Repetir nueva contraseña</label>
                                <input type="password" name="contra_repetir" required>
                            </div>
                            <div class="form-grupo full">
                                <button type="submit" class="btn btn-primario">Cambiar contraseña</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="../js/app.js"></script>
</body>
</html>
