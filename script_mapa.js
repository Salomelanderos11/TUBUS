/* =====================================================
   script_mapa.js — TuBus
   Mapa Leaflet + funciones de dibujo
   ===================================================== */

/* ── 1. MAPA BASE ── */
const map = L.map('map').setView([24.8090, -107.3940], 14);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

/* ── 2. VARIABLES GLOBALES ── */
let capaCamino      = null;  // Ruta del buscador React
let capasViaje      = [];    // Polilíneas del planificador
let marcadoresViaje = [];    // Marcadores del planificador

// Ubicación GPS del usuario (accesible desde script_busqueda.js)
window.ubicacionUsuario = null;

/* ── 3. LIMPIAR TODO (expuesta globalmente) ── */
window.limpiarTodo = function () {
    if (capaCamino) { map.removeLayer(capaCamino); capaCamino = null; }
    capasViaje.forEach(c => map.removeLayer(c));
    capasViaje = [];
    marcadoresViaje.forEach(m => map.removeLayer(m));
    marcadoresViaje = [];
};

/* ── 4. DIBUJAR RUTA SIMPLE (usado por el buscador React) ── */
window.dibujarCamino = function (puntos) {
    if (!puntos || puntos.length === 0) return;
    window.limpiarTodo();

    const coords = puntos.map(p => [p.lat, p.lng]);

    capaCamino = L.polyline(coords, {
        color:    '#2563eb',
        weight:   6,
        opacity:  0.8,
        lineJoin: 'round'
    }).addTo(map);

    map.fitBounds(capaCamino.getBounds(), { padding: [30, 30] });
};

/* ── 5. COLORES PARA EL PLAN DE VIAJE ── */
const COLORES_PLAN = ['#2563eb', '#dc2626', '#16a34a'];

/* ── 6. DIBUJAR PLAN DE VIAJE ── */
window.dibujarPlanViaje = function (plan, origen, destino, textoDestino) {
    window.limpiarTodo();

    const todosPuntos = [];

    /* Marcador de origen (azul) */
    const iconOrig = L.divIcon({
        html: `<div style="
                 background:#2563eb; width:18px; height:18px;
                 border-radius:50%; border:3px solid white;
                 box-shadow:0 2px 8px rgba(0,0,0,.45);
               "></div>`,
        iconSize:   [18, 18],
        iconAnchor: [9, 9],
        className:  ''
    });

    const mOrig = L.marker([origen.lat, origen.lng], { icon: iconOrig })
        .addTo(map)
        .bindPopup('<strong>📍 Tu ubicación</strong>');
    marcadoresViaje.push(mOrig);
    todosPuntos.push([origen.lat, origen.lng]);

    /* Marcador de destino (verde) */
    const iconDest = L.divIcon({
        html: `<div style="
                 background:#16a34a; width:22px; height:22px;
                 border-radius:50%; border:3px solid white;
                 box-shadow:0 2px 8px rgba(0,0,0,.45);
                 display:flex; align-items:center;
                 justify-content:center; font-size:12px;
               ">🏁</div>`,
        iconSize:   [22, 22],
        iconAnchor: [11, 11],
        className:  ''
    });

    const mDest = L.marker([destino.lat, destino.lng], { icon: iconDest })
        .addTo(map)
        .bindPopup(`<strong>🏁 Destino:</strong><br>${textoDestino}`);
    marcadoresViaje.push(mDest);
    todosPuntos.push([destino.lat, destino.lng]);

    if (plan.tipo === 'sin_ruta') {
        map.setView(
            [(origen.lat + destino.lat) / 2, (origen.lng + destino.lng) / 2],
            13
        );
        return;
    }

    /* Recorrer pasos del plan */
    let rutaIdx = 0;

    (plan.pasos || []).forEach(paso => {

        /* Polilínea de cada ruta */
        if (paso.tipo === 'ruta' && paso.camino && paso.camino.length > 1) {
            const color  = COLORES_PLAN[rutaIdx % COLORES_PLAN.length];
            rutaIdx++;
            const coords = paso.camino.map(p => [p.lat, p.lng]);

            const linea = L.polyline(coords, {
                color,
                weight:   6,
                opacity:  0.85,
                lineJoin: 'round'
            })
            .addTo(map)
            .bindPopup(`
                <div style="min-width:170px;">
                  <strong style="color:${color}">🚌 ${paso.nombre}</strong><br>
                  <em>${paso.descripcion || ''}</em><br>
                  👤 ${paso.conductor || 'N/D'}
                </div>
            `);

            capasViaje.push(linea);
            coords.forEach(c => todosPuntos.push(c));
        }

        /* Marcador de transbordo (naranja) */
        if (paso.tipo === 'transbordo' && paso.punto) {
            const iconTrans = L.divIcon({
                html: `<div style="
                         background:#d97706; width:16px; height:16px;
                         border-radius:50%; border:2px solid white;
                         box-shadow:0 2px 6px rgba(0,0,0,.35);
                       "></div>`,
                iconSize:   [16, 16],
                iconAnchor: [8, 8],
                className:  ''
            });

            const mTrans = L.marker([paso.punto.lat, paso.punto.lng], { icon: iconTrans })
                .addTo(map)
                .bindPopup(
                    `<strong>🚶 Transbordo</strong><br>Camina ~${paso.dist_caminata} m`
                );
            marcadoresViaje.push(mTrans);
            todosPuntos.push([paso.punto.lat, paso.punto.lng]);
        }
    });

    /* Ajustar zoom para ver todo el recorrido */
    if (todosPuntos.length > 1) {
        map.fitBounds(L.latLngBounds(todosPuntos), { padding: [50, 50] });
    }
};

/* ── 7. GEOLOCALIZACIÓN DEL USUARIO ── */
function rastrearUsuario() {
    if (!navigator.geolocation) return;

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const { latitude: lat, longitude: lng } = pos.coords;

            // Guardar globalmente para el planificador
            window.ubicacionUsuario = { lat, lng };

            // Marcador visual en el mapa
            L.circleMarker([lat, lng], {
                radius:      9,
                fillColor:   '#ef4444',
                color:       'white',
                weight:      2.5,
                fillOpacity: 1
            }).addTo(map).bindPopup('<strong>📍 Tu ubicación actual</strong>');
        },
        (err) => console.warn('GPS no disponible:', err.message),
        { enableHighAccuracy: true }
    );
}

rastrearUsuario();