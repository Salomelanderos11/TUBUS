/* =====================================================
   script_busqueda.js — TuBus
   Planificador de viaje: GPS → Dirección buscada
   ===================================================== */

const COLORES_BADGE = ['#2563eb', '#dc2626', '#16a34a'];

/* ══════════════════════════════════════════
   PLANIFICAR VIAJE — función principal
══════════════════════════════════════════ */
async function planificarViaje() {
    const inputEl      = document.getElementById('inputDireccion');
    const radioEl      = document.getElementById('radioSelect');
    const resultadosEl = document.getElementById('resultadosDireccion');
    const btnLimpiar   = document.getElementById('btnLimpiar');
    const direccion    = inputEl.value.trim();
    const radio        = radioEl.value;

    if (!direccion) {
        mostrarMsg(resultadosEl, 'error', '⚠️ Ingresa la dirección a la que quieres llegar.');
        return;
    }

    /* Verificar que ya tenemos la ubicación GPS */
    if (!window.ubicacionUsuario) {
        mostrarMsg(resultadosEl, 'info', '📍 Obteniendo tu ubicación GPS...');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                window.ubicacionUsuario = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude
                };
                mostrarMsg(resultadosEl, 'success',
                    '✅ Ubicación obtenida. Pulsa "Planificar" de nuevo.');
            },
            () => mostrarMsg(resultadosEl, 'error',
                '❌ No se pudo obtener tu ubicación. Acepta el permiso de localización.')
        );
        return;
    }

    mostrarMsg(resultadosEl, 'info', '🔄 Buscando destino...');

    try {
        /* ── 1. Geocodificar destino con Nominatim ── */
        const q = encodeURIComponent(`${direccion}, Culiacán, Sinaloa, México`);
        const geoRes  = await fetch(
            `https://nominatim.openstreetmap.org/search` +
            `?q=${q}&format=json&limit=1&countrycodes=mx&accept-language=es`
        );
        const geoData = await geoRes.json();

        if (!geoData || geoData.length === 0) {
            mostrarMsg(resultadosEl, 'error',
                '❌ Destino no encontrado. Sé más específico.<br>' +
                'Ej: <em>"Av. Insurgentes 450, Culiacán"</em>');
            btnLimpiar.style.display = 'inline-block';
            return;
        }

        const destLat    = parseFloat(geoData[0].lat);
        const destLng    = parseFloat(geoData[0].lon);
        const destNombre = geoData[0].display_name;

        resultadosEl.innerHTML = `
            <div class="msg success">
                🎯 <strong>Destino encontrado:</strong> ${destNombre}
            </div>
            <div class="empty-state">⏳ Calculando el mejor camino...</div>`;

        /* ── 2. Consultar plan de viaje ── */
        const orig = window.ubicacionUsuario;
        const url  = `planificar_viaje.php`
                   + `?orig_lat=${orig.lat}&orig_lng=${orig.lng}`
                   + `&dest_lat=${destLat}&dest_lng=${destLng}`
                   + `&radio=${radio}`;

        const planRes = await fetch(url);
        const plan    = await planRes.json();

        /* ── 3. Dibujar en el mapa ── */
        if (window.dibujarPlanViaje) {
            window.dibujarPlanViaje(
                plan,
                orig,
                { lat: destLat, lng: destLng },
                direccion
            );
        }

        /* ── 4. Mostrar itinerario en la UI ── */
        renderItinerario(resultadosEl, plan, destNombre);

        document.getElementById('map')
                .scrollIntoView({ behavior: 'smooth', block: 'center' });

    } catch (err) {
        console.error(err);
        mostrarMsg(resultadosEl, 'error',
            '❌ Error de conexión. Verifica tu internet e inténtalo de nuevo.');
    }

    /* Mostrar botón limpiar al terminar cualquier búsqueda */
    btnLimpiar.style.display = 'inline-block';
}

/* ══════════════════════════════════════════
   LIMPIAR BÚSQUEDA
══════════════════════════════════════════ */
function limpiarBusqueda() {
    /* Limpiar input y resultados */
    document.getElementById('inputDireccion').value    = '';
    document.getElementById('resultadosDireccion').innerHTML = '';

    /* Ocultar el botón limpiar */
    document.getElementById('btnLimpiar').style.display = 'none';

    /* Limpiar rutas y marcadores del mapa */
    if (window.limpiarTodo) {
        window.limpiarTodo();
    }
}

/* ══════════════════════════════════════════
   RENDERIZAR ITINERARIO
══════════════════════════════════════════ */
function renderItinerario(el, plan, destNombre) {

    if (plan.tipo === 'sin_ruta') {
        el.innerHTML = `
            <div class="msg error">
                ⚠️ ${plan.error || 'No encontramos rutas para ese trayecto.'}<br><br>
                💡 <strong>Sugerencias:</strong> aumenta el radio de búsqueda
                o ajusta la dirección de destino.
            </div>`;
        return;
    }

    const pasos      = plan.pasos || [];
    const pasosRuta  = pasos.filter(p => p.tipo === 'ruta');
    const totalRutas = pasosRuta.length;

    const etiquetas = {
        directo:      '✅ Ruta directa',
        transbordo_1: '🔄 1 transbordo necesario',
        transbordo_2: '🔄 2 transbordos necesarios',
    };

    let html = `
        <div class="msg success">
            🎯 <strong>Destino:</strong> ${destNombre}
        </div>
        <div style="background:#f0fdf4; border:1px solid #86efac;
                    border-radius:12px; padding:16px; margin-top:10px;">
            <p style="font-weight:800; font-size:1rem; color:#166534; margin-bottom:14px;">
                ${etiquetas[plan.tipo] || ''} &nbsp;·&nbsp; ${totalRutas} autobús(es)
            </p>`;

    let rutaIdx = 0;

    pasos.forEach((paso) => {

        /* ── Paso de ruta ── */
        if (paso.tipo === 'ruta') {
            const color = COLORES_BADGE[rutaIdx % COLORES_BADGE.length];
            rutaIdx++;

            html += `
                <div style="border-left:5px solid ${color}; padding:12px 14px;
                            margin-bottom:10px; background:white;
                            border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.07);">
                    <p style="font-size:.75rem; color:#9ca3af; font-weight:700;
                               letter-spacing:.05em; margin-bottom:4px;">
                        PASO ${paso.numero}
                    </p>
                    <h3 style="color:${color}; margin-bottom:6px;">
                        🚌 ${paso.nombre}
                    </h3>
                    <p style="color:#374151; font-size:.9rem; line-height:1.5;">
                        ${paso.instruccion}
                    </p>
                    <div style="margin-top:8px; font-size:.82rem; color:#6b7280;
                                display:flex; gap:14px; flex-wrap:wrap;">
                        ${paso.dist_orig != null
                            ? `<span>📍 Abordas a <strong>~${paso.dist_orig} m</strong> de ti</span>`
                            : ''}
                        ${paso.dist_dest != null
                            ? `<span>🏁 Bajas a <strong>~${paso.dist_dest} m</strong> del destino</span>`
                            : ''}
                        <span>👤 ${paso.conductor || 'N/D'}</span>
                    </div>
                </div>`;
        }

        /* ── Paso de transbordo ── */
        if (paso.tipo === 'transbordo') {
            html += `
                <div style="text-align:center; padding:10px 14px; margin-bottom:10px;
                            background:#fffbeb; border:1px dashed #fbbf24;
                            border-radius:10px; color:#92400e; font-size:.88rem;">
                    🚶 ${paso.instruccion}
                </div>`;
        }
    });

    html += `
            <div style="text-align:center; padding:10px; background:#dcfce7;
                        border-radius:10px; font-weight:700;
                        color:#166534; margin-top:4px;">
                🏁 ¡Llegaste a tu destino!
            </div>
        </div>`;

    el.innerHTML = html;
}

/* ══════════════════════════════════════════
   HELPER — mostrar mensaje
══════════════════════════════════════════ */
function mostrarMsg(el, tipo, texto) {
    const cls = { error: 'error', success: 'success', info: '' };
    el.innerHTML = `<div class="msg ${cls[tipo] || ''}">${texto}</div>`;
}

/* ══════════════════════════════════════════
   ENTER en el input
══════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('inputDireccion');
    if (input) {
        input.addEventListener('keypress', e => {
            if (e.key === 'Enter') planificarViaje();
        });
    }
});