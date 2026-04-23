<?php
/*
 * tibus-app.php
 * -------------
 * Página principal (protegida por login) del proyecto TuBus.
 * - React: buscador y detalles de rutas (cargadas desde MySQL)
 * - PHP: formulario de sugerencias + mostrar últimas sugerencias
 */

require 'auth.php';
require 'Conexion.php';

$mensajeExito = "";
$mensajeError = "";

/* ------------------------------------------------------
   PROCESAR FORMULARIO DE SUGERENCIAS
------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_sugerencia'])) {
    $nombre  = trim($_POST['nombre'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');

    if ($nombre === '' || $email === '' || $mensaje === '') {
        $mensajeError = "Por favor completa todos los campos.";
    } else {
        $stmt = $conn->prepare("INSERT INTO sugerencias (nombre, email, mensaje) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $email, $mensaje);

        if ($stmt->execute()) {
            $mensajeExito = "¡Gracias por tu sugerencia!";
        } else {
            $mensajeError = "Error al guardar sugerencia.";
        }

        $stmt->close();
    }
}

/* ------------------------------------------------------
   CONSULTAR ÚLTIMAS SUGERENCIAS
------------------------------------------------------ */
$ultimasSugerencias = [];
$res = $conn->query("SELECT nombre, mensaje, fecha FROM sugerencias ORDER BY fecha DESC LIMIT 5");
if ($res && $res->num_rows > 0) {
    while ($f = $res->fetch_assoc()) {
        $ultimasSugerencias[] = $f;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TuBus - Buscador de Rutas</title>

    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>

<!-- HEADER SUPERIOR -->
<div class="top-header">
    <div class="top-header-left">
        <strong>TuBus</strong>
    </div>

    <div class="top-header-right">
        <a href="tubus-app.php" class="top-link">Inicio</a>

        <?php if (isset($_SESSION['usuario'])): ?>
            <?php if (($_SESSION['usuario']['rol'] ?? 'usuario') === 'admin'): ?>
                <a href="admin_rutas.php" class="top-link">Panel Admin</a>
            <?php endif; ?>

            <span class="top-user">
                <?= htmlspecialchars($_SESSION['usuario']['nombre']) ?>
            </span>

            <a href="logout.php" class="top-link logout">Cerrar sesión</a>
        <?php endif; ?>
    </div>
</div>

<!-- APP REACT -->
<div id="root"></div>
<div class="app-container" style="margin-top: 20px;">
    <div class="glass-card">
        <h2 style="margin-bottom:10px;"><i class="fa-solid fa-map-location-dot"></i> Rutas en Culiacán</h2>
        <div id="map" style="width: 100%; height: 450px; border-radius: 12px; border: 1px solid #e2e8f0; z-index: 1;">
            <p style="text-align:center; padding-top:200px; color:#9ca3af;">Cargando mapa de Culiacán...</p>
        </div>
    </div>
</div>

    
<!-- SUGERENCIAS -->
<div class="app-container" style="margin-top: 20px;">
    <div class="glass-card">

        <h2 style="margin-bottom:6px;">¿No encuentras tu ruta?</h2>
        <p style="color:#6b7280; margin-bottom:14px;">
            Envíanos una sugerencia para agregar nuevas rutas.
        </p>

        <?php if ($mensajeExito): ?>
            <div class="msg success"><?= htmlspecialchars($mensajeExito) ?></div>
        <?php endif; ?>

        <?php if ($mensajeError): ?>
            <div class="msg error"><?= htmlspecialchars($mensajeError) ?></div>
        <?php endif; ?>

        <form method="POST" class="contact-form">
            <input type="hidden" name="enviar_sugerencia" value="1">

            <input type="text" name="nombre" placeholder="Tu nombre" required>
            <input type="email" name="email" placeholder="Correo" required>
            <textarea name="mensaje" placeholder="Describe la ruta sugerida..." required></textarea>

            <button type="submit" class="view-route-btn">Enviar sugerencia</button>
        </form>

        <div style="margin-top:18px;">
            <h3 style="margin-bottom:10px;">Últimas sugerencias:</h3>

            <?php if (count($ultimasSugerencias) === 0): ?>
                <div class="empty-state">Aún no hay sugerencias.</div>
            <?php else: ?>
                <?php foreach($ultimasSugerencias as $s): ?>
                    <div style="padding:10px 12px; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:10px; background:#fff;">
                        <strong><?= htmlspecialchars($s['nombre']) ?>:</strong>
                        <div style="margin-top:6px; color:#374151;">
                            <?= nl2br(htmlspecialchars($s['mensaje'])) ?>
                        </div>
                        <small style="display:block; margin-top:6px; color:#6b7280;">
                            <?= htmlspecialchars($s['fecha']) ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>


<script src="script_mapa.js"></script>
<script src="script_react.js"></script>
</body>
</html>