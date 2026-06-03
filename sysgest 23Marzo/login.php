<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $contra  = $_POST['contra'] ?? '';

    if ($usuario === '' || $contra === '') {
        $error = 'Rellena todos los campos.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, nombre, apellidos, password, rol FROM usuarios WHERE usuario = ? AND activo = 1 LIMIT 1"
        );
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        if ($fila && password_verify($contra, $fila['password'])) {
            $_SESSION['usuario_id'] = $fila['id'];
            $_SESSION['usuario']    = $usuario;
            $_SESSION['nombre']     = $fila['nombre'] . ' ' . $fila['apellidos'];
            $_SESSION['rol']        = $fila['rol'];

            $ip = $_SERVER['REMOTE_ADDR'];
            $log = $conn->prepare("INSERT INTO logs_acceso (usuario_id, ip, fecha) VALUES (?, ?, NOW())");
            $log->bind_param('is', $fila['id'], $ip);
            $log->execute();

            header('Location: pages/dashboard.php');
            exit;
        }

        $error = 'Usuario o contraseña incorrectos.';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GestInt — Acceso</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div class="logo-icon">GI</div>
            <h1>GestInt</h1>
            <p>Sistema de Gestión Empresarial</p>
        </div>

        <?php if ($error): ?>
            <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <div class="form-grupo">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario"
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                       placeholder="Nombre de usuario"
                       autocomplete="username" required>
            </div>
            <div class="form-grupo">
                <label for="contra">Contraseña</label>
                <input type="password" id="contra" name="contra"
                       placeholder="••••••••"
                       autocomplete="current-password" required>
            </div>
            <div class="mt-16">
                <button type="submit" class="btn btn-primario">Entrar</button>
            </div>
        </form>

        <p class="text-gris text-center mt-16">Acceso restringido al personal autorizado</p>
    </div>
</div>
</body>
</html>
