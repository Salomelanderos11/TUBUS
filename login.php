<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'Conexion.php';

$error = "";

if (isset($_SESSION['usuario'])) {
    header("Location: tubus-app.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        $error = "Completa correo y contraseña.";
    } else {
        $stmt = $conn->prepare("SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $u = $res->fetch_assoc();
        $stmt->close();

        if ($u && password_verify($pass, $u['password_hash'])) {
            $_SESSION['usuario'] = [
                'id' => $u['id'],
                'nombre' => $u['nombre'],
                'email' => $u['email'],
                'rol' => $u['rol']
            ];
            header("Location: tubus-app.php");
            exit;
        } else {
            $error = "Correo o contraseña incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión - TuBus</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- Header simple (sin sesión) -->
<div class="top-header">
  <div class="top-header-left">
    <strong>TuBus</strong>
  </div>
  <div class="top-header-right">
    <a href="login.php" class="top-link">Iniciar sesión</a>
    <a href="register.php" class="top-link">Registro</a>
  </div>
</div>

<div class="app-container" style="margin-top: 18px;">
  <div class="glass-card">

    <h1 style="margin-bottom: 10px;">TuBus</h1>

    <div style="text-align:center; margin-bottom: 14px;">
      <h2 style="font-size: 20px; font-weight: 900; color:#1f2937;">Iniciar sesión</h2>
      <p style="color:#6b7280; margin-top:6px;">
        Accede para buscar rutas y ver detalles.
      </p>
    </div>

    <?php if ($error): ?>
      <div class="msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="contact-form">
      <input type="email" name="email" placeholder="Correo" required>
      <input type="password" name="password" placeholder="Contraseña" required>

      <button class="view-route-btn" type="submit" style="width:100%;">
        Entrar
      </button>
    </form>

    <div style="margin-top: 14px; text-align:center; color:#6b7280;">
      ¿No tienes cuenta?
      <a href="register.php" style="font-weight:800; color:#2563eb; text-decoration:none;">Regístrate</a>
    </div>

  </div>
</div>

</body>
</html>