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

<script>




    
/*
 * React:
 * - Carga rutas desde obtener_rutas.php
 * - Busca por nombre, descripción y paradas
 * - Muestra detalle con conductor
 */

const BusDotIcon = () => React.createElement('svg', {
    width: '12',
    height: '12',
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: '2'
},
    React.createElement('path', { d: 'M4 16V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10' }),
    React.createElement('path', { d: 'M4 16h16' }),
    React.createElement('circle', { cx: '7', cy: '18', r: '2' }),
    React.createElement('circle', { cx: '17', cy: '18', r: '2' })
);

function App() {
    const [allBuses, setAllBuses] = React.useState([]);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState(null);

    const [searchTerm, setSearchTerm] = React.useState('');
    const [searchResults, setSearchResults] = React.useState([]);
    const [selectedBus, setSelectedBus] = React.useState(null);

    React.useEffect(() => {
        fetch('obtener_rutas.php')
            .then(res => {
                if (!res.ok) {
                    throw new Error("No se pudieron cargar las rutas.");
                }
                return res.json();
            })
            .then(data => {
                setAllBuses(data || []);
                setLoading(false);
            })
            .catch(() => {
                setError("No se pudieron cargar las rutas. Revisa obtener_rutas.php y la base de datos.");
                setLoading(false);
            });
    }, []);

    const handleSearch = (e) => {
        const value = e.target.value;
        setSearchTerm(value);

        if (value.length === 0) {
            setSearchResults([]);
            return;
        }

        const lower = value.toLowerCase();

        const filtered = allBuses.filter(bus =>
            (bus.name || '').toLowerCase().includes(lower) ||
            (bus.description || '').toLowerCase().includes(lower) ||
            (bus.stops || []).some(stop => (stop || '').toLowerCase().includes(lower))
        );

        setSearchResults(filtered);
    };

    if (loading) {
        return React.createElement('div', { className: 'app-container fade-in' },
            React.createElement('div', { className: 'glass-card' },
                React.createElement('h1', null, 'TuBus'),
                React.createElement('div', { className: 'empty-state' }, 'Cargando rutas...')
            )
        );
    }

    if (error) {
        return React.createElement('div', { className: 'app-container fade-in' },
            React.createElement('div', { className: 'glass-card' },
                React.createElement('h1', null, 'TuBus'),
                React.createElement('div', { className: 'no-results' }, error)
            )
        );
    }

    if (selectedBus) {
        return React.createElement('div', { className: 'app-container fade-in' },
            React.createElement('div', { className: 'glass-card' },
                React.createElement('h1', null, 'TuBus'),

                React.createElement('button', {
                    onClick: () => setSelectedBus(null),
                    className: 'back-button'
                }, '← Volver'),

                React.createElement('div', { className: 'route-header' },
                    React.createElement('h2', null, selectedBus.name),
                    React.createElement('p', { className: 'route-description' }, selectedBus.description),
                    React.createElement('p', {
                        style: {
                            fontWeight: '700',
                            color: '#1e40af',
                            marginTop: '8px'
                        }
                    }, 'Conductor: ' + (selectedBus.conductor || 'No disponible'))
                ),

                React.createElement('div', { className: 'stops-title' }, 'Paradas de la Ruta:'),

                React.createElement('div', { className: 'stops-list' },
                    React.createElement('ul', { className: 'space-y-4' },
                        (selectedBus.stops || []).map((stop, index) =>
                            React.createElement('li', {
                                key: index,
                                className: 'stop-item'
                            },
                                React.createElement('div', { className: 'stop-marker' },
                                    React.createElement(BusDotIcon)
                                ),
                                React.createElement('div', { className: 'stop-content' }, stop)
                            )
                        )
                    )
                )
            )
        );
    }

    return React.createElement('div', { className: 'app-container fade-in' },
        React.createElement('div', { className: 'glass-card' },
            React.createElement('h1', null, 'TuBus'),

            React.createElement('div', { className: 'search-container' },
                React.createElement('input', {
                    type: 'text',
                    placeholder: 'Busca tu ruta o parada...',
                    className: 'search-input',
                    value: searchTerm,
                    onChange: handleSearch
                })
            ),

            searchTerm.length > 0 && searchResults.length > 0 &&
            React.createElement('div', { className: 'results-container slide-up' },
                React.createElement('h3', { className: 'results-title' }, 'Resultados de la búsqueda:'),
                React.createElement('div', { className: 'space-y-4' },
                    searchResults.map(bus =>
                        React.createElement('div', {
                            key: bus.id,
                            className: 'bus-card'
                        },
                            React.createElement('div', { className: 'bus-info' },
                                React.createElement('h3', null, bus.name),
                                React.createElement('p', null, bus.description)
                            ),
                            React.createElement('button', {
                                onClick: () => setSelectedBus(bus),
                                className: 'view-route-btn'
                            }, 'Ver Ruta')
                        )
                    )
                )
            ),

            searchTerm.length > 0 && searchResults.length === 0 &&
            React.createElement('div', { className: 'no-results slide-up' },
                React.createElement('p', null, 'No encontramos rutas con ese término.')
            ),

            searchTerm.length === 0 &&
            React.createElement('div', { className: 'empty-state' },
                React.createElement('p', null, 'Empieza a escribir para encontrar tu autobús ideal.')
            )
        )
    );
}

const rootElement = document.getElementById('root');
const root = ReactDOM.createRoot(rootElement);
root.render(React.createElement(App));
</script>
<script>
    // 1. Inicializar el mapa (Culiacán por defecto)
    const map = L.map('map').setView([24.8090, -107.3940], 13);

    // 2. Capa de OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // --- FUNCIÓN DE GEOLOCALIZACIÓN ---
    function encontrarMiUbicacion() {
        if (!navigator.geolocation) {
            alert("Tu navegador no soporta geolocalización");
            return;
        }

        navigator.geolocation.getCurrentPosition((position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            // Centrar mapa en tu ubicación
            map.setView([lat, lng], 15);

            // Agregar un marcador especial para el usuario (Círculo azul)
            const miUbicacionMarker = L.circleMarker([lat, lng], {
                radius: 10,
                fillColor: "#1e40af", // Azul fuerte como tu tema
                color: "#fff",
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map);

            miUbicacionMarker.bindPopup("<b>Estás aquí</b>").openPopup();

        }, (error) => {
            console.warn("Error de geolocalización: ", error.message);
            alert("No se pudo obtener tu ubicación. Asegúrate de dar permisos.");
        });
    }

    // --- FUNCIÓN DE OVERPASS API ---
    async function cargarParadas() {
        const bounds = map.getBounds();
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();

        const query = `[out:json];node["highway"="bus_stop"](${sw.lat},${sw.lng},${ne.lat},${ne.lng});out;`;
        const url = `https://overpass-api.de/api/interpreter?data=${encodeURIComponent(query)}`;

        try {
            const response = await fetch(url);
            const data = await response.json();

            data.elements.forEach(element => {
                L.marker([element.lat, element.lon])
                 .addTo(map)
                 .bindPopup(`<b>Parada de TuBus</b><br>${element.tags.name || 'Parada s/n'}`);
            });
        } catch (e) {
            console.error("Error en Overpass", e);
        }
    }

    // Ejecutar funciones al cargar
    map.on('moveend', cargarParadas);
    cargarParadas();
    
    // Llamar a la ubicación actual automáticamente al iniciar
    encontrarMiUbicacion();
</script>

</body>
</html>