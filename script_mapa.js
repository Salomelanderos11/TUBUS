/**
     * 1. CONFIGURACIÓN DEL MAPA
     */
    const map = L.map('map').setView([24.8090, -107.3940], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

  

    /**
     * 3. FUNCIÓN PARA DIBUJAR EL CAMINO (SIN ETIQUETAS DE ORDEN)
     */
    // Variable global para controlar la línea actual
    let capaCamino = null;

    window.dibujarCamino = function(puntos) {
        if (!puntos || puntos.length === 0) return;

        // Limpiar trazado anterior si existe
        if (capaCamino) {
            map.removeLayer(capaCamino);
        }

        // Convertir el array de objetos a un formato que Leaflet entienda [[lat, lng], ...]
        const coordenadas = puntos.map(p => [p.lat, p.lng]);

        // Dibujar la línea (Polyline)
        capaCamino = L.polyline(coordenadas, {
            color: '#2563eb', // Azul profesional
            weight: 6,        // Grosor de la línea
            opacity: 0.8,     // Transparencia
            lineJoin: 'round' // Bordes redondeados en las esquinas de las calles
        }).addTo(map);

        // Ajustar el zoom automáticamente para que se vea toda la ruta
        map.fitBounds(capaCamino.getBounds(), { padding: [30, 30] });
    };
    /**
     * 4. GEOLOCALIZACIÓN DEL USUARIO
     */
    function rastrearUsuario() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((p) => {
                const { latitude, longitude } = p.coords;

                // Círculo rojo para la ubicación del usuario
                L.circleMarker([latitude, longitude], {
                    radius: 8,
                    fillColor: "#ef4444",
                    color: "white",
                    weight: 2,
                    fillOpacity: 1
                }).addTo(map).bindPopup("Tu ubicación actual");

            }, (error) => {
                console.warn("Ubicación no disponible: ", error.message);
            });
        }
    }

    /**
     * 5. EJECUCIÓN INICIAL
     */
    rastrearUsuario();
