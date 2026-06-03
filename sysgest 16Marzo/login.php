<?php
// ============================================================
// GestInt — Login
// ============================================================
session_start();
require_once 'includes/db.php';

// Si ya hay sesión, redirige al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $contra  = $_POST['contra'] ?? '';

    if ($usuario === '' || $contra === '') {
        $error = 'Por favor, rellena todos los campos.';
    } else {
        // Busca el usuario en la BD
        $stmt = $conn->prepare(
            "SELECT id, nombre, apellidos, password, rol FROM usuarios WHERE usuario = ? AND activo = 1 LIMIT 1"
        );
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($fila = $res->fetch_assoc()) {
            if (password_verify($contra, $fila['password'])) {
                // Sesión iniciada correctamente
                $_SESSION['usuario_id'] = $fila['id'];
                $_SESSION['usuario']    = $usuario;
                $_SESSION['nombre']     = $fila['nombre'] . ' ' . $fila['apellidos'];
                $_SESSION['rol']        = $fila['rol'];

                // Registro de auditoría
                $ip = $_SERVER['REMOTE_ADDR'];
                $conn->query("INSERT INTO logs_acceso (usuario_id, ip, fecha)
                              VALUES ({$fila['id']}, '$ip', NOW())");

                header('Location: pages/dashboard.php');
                exit;
            }
        }
        // Usuario o contraseña incorrectos (mismo mensaje por seguridad)
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
    <title>GestInt — Iniciar sesión</title>
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
                       placeholder="Tu nombre de usuario"
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

        <p class="text-gris text-center mt-16">
            Sistema de uso interno — acceso restringido
        </p>
    </div>
</div>
</body>
</html>
