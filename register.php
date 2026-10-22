<?php
session_start();
require 'Conexion.php';

$exito = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = trim($_POST['password'] ?? '');

    if ($nombre === '' || $email === '' || $pass === '') {
        $error = "Completa todos los campos.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nombre, $email, $hash);
            $stmt->execute();
            $stmt->close();
            $exito = "Registro exitoso. Ya puedes iniciar sesión.";
        } catch (mysqli_sql_exception $e) {
            $error = "No se pudo registrar. ¿Correo ya registrado?";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro - TuBus</title>
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
      <h2 style="font-size: 20px; font-weight: 900; color:#1f2937;">Crear cuenta</h2>
      <p style="color:#6b7280; margin-top:6px;">
        Regístrate para usar el sistema.
      </p>
    </div>

    <?php if ($exito): ?>
      <div class="msg success"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="contact-form">
      <input type="text" name="nombre" placeholder="Nombre" required>
      <input type="email" name="email" placeholder="Correo" required>
      <input type="password" name="password" placeholder="Contraseña" required>

      <button class="view-route-btn" type="submit" style="width:100%;">
        Registrarme
      </button>
    </form>

    <div style="margin-top: 14px; text-align:center; color:#6b7280;">
      ¿Ya tienes cuenta?
      <a href="login.php" style="font-weight:800; color:#2563eb; text-decoration:none;">Inicia sesión</a>
    </div>

  </div>
</div>

</body>
</html>