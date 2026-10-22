<?php
/*
 * tubus-app.php
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

<!-- ══════════════════════════════════════════
     PLANIFICADOR DE VIAJE
══════════════════════════════════════════ -->
<div class="app-container" style="margin-top: 16px;">
    <div class="glass-card">

        <h2 style="margin-bottom:6px;">🗺️ ¿Cómo llego a mi destino?</h2>
        <p style="color:#6b7280; margin-bottom:14px;">
            Tu ubicación GPS se usa como <strong>punto de partida</strong>.
            Escribe a dónde quieres ir y te mostramos qué rutas tomar.
        </p>

        <!-- Indicador de estado GPS -->
        <div id="estadoGPS"
             style="font-size:.85rem; color:#6b7280;
                    margin-bottom:12px; display:flex;
                    align-items:center; gap:8px;">
            <span id="gpsIcono">📡</span>
            <span id="gpsTexto">Detectando tu ubicación GPS...</span>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">

            <input
                type="text"
                id="inputDireccion"
                placeholder="¿A dónde quieres ir? Ej: Plaza Sendero, Culiacán"
                style="flex:1; padding:12px; border-radius:10px;
                       border:1px solid #ccc; min-width:200px; font-size:1rem;"
            >

            <select id="radioSelect"
                    style="padding:12px; border-radius:10px;
                           border:1px solid #ccc; font-size:1rem;">
                <option value="300">300 m</option>
                <option value="500" selected>500 m</option>
                <option value="800">800 m</option>
                <option value="1200">1.2 km</option>
            </select>

            <button onclick="planificarViaje()" class="view-route-btn"
                    style="padding:12px 22px; font-size:1rem;">
                🗺️ Planificar
            </button>

            <!-- BOTÓN LIMPIAR -->
            <button onclick="limpiarBusqueda()"
                    id="btnLimpiar"
                    style="display:none; padding:12px 22px; font-size:1rem;
                           background:#6b7280; color:white; border:none;
                           border-radius:10px; cursor:pointer;">
                🗑️ Limpiar
            </button>

        </div>

        <div id="resultadosDireccion" style="margin-top:14px;"></div>

    </div>
</div>

<!-- APP REACT -->
<div id="root"></div>

<!-- MAPA -->
<div class="app-container" style="margin-top: 20px;">
    <div class="glass-card">
        <h2 style="margin-bottom:10px;">
            <i class="fa-solid fa-map-location-dot"></i> Rutas en Culiacán
        </h2>
        <div id="map" style="width:100%; height:450px; border-radius:12px;
                              border:1px solid #e2e8f0; z-index:1;">
            <p style="text-align:center; padding-top:200px; color:#9ca3af;">
                Cargando mapa de Culiacán...
            </p>
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
            <input type="text"  name="nombre"  placeholder="Tu nombre" required>
            <input type="email" name="email"   placeholder="Correo"    required>
            <textarea name="mensaje" placeholder="Describe la ruta sugerida..." required></textarea>
            <button type="submit" class="view-route-btn">Enviar sugerencia</button>
        </form>

        <div style="margin-top:18px;">
            <h3 style="margin-bottom:10px;">Últimas sugerencias:</h3>

            <?php if (count($ultimasSugerencias) === 0): ?>
                <div class="empty-state">Aún no hay sugerencias.</div>
            <?php else: ?>
                <?php foreach ($ultimasSugerencias as $s): ?>
                    <div style="padding:10px 12px; border:1px solid #e2e8f0;
                                border-radius:12px; margin-bottom:10px; background:#fff;">
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

<!-- SCRIPTS -->
<script src="script_mapa.js"></script>
<script src="script_react.js"></script>
<script src="script_busqueda.js"></script>

<!-- Indicador visual del estado GPS -->
<script>
(function esperarGPS() {
    const icono = document.getElementById('gpsIcono');
    const texto = document.getElementById('gpsTexto');
    if (!icono || !texto) return;

    const check = setInterval(() => {
        if (window.ubicacionUsuario) {
            icono.textContent = '✅';
            texto.textContent = 'Ubicación detectada — listo para planificar.';
            texto.style.color      = '#16a34a';
            texto.style.fontWeight = '700';
            clearInterval(check);
        }
    }, 600);

    setTimeout(() => {
        if (!window.ubicacionUsuario) {
            icono.textContent = '⚠️';
            texto.textContent = 'No se detectó GPS. Acepta el permiso de ubicación y recarga la página.';
            texto.style.color = '#dc2626';
            clearInterval(check);
        }
    }, 10000);
})();
</script>

</body>
</html>