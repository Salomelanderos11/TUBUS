
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
                            onClick: () => {
                                setSelectedBus(bus); // Muestra el detalle en la UI de React
                                
                                // El nombre debe coincidir con el de la tabla 'trazado_rutas'
                                // Usamos bus.nombre_ruta que viene de tu tabla 'rutas'
                                const nombreParaBusqueda = bus.name; 

                                fetch(`obtener_camino.php?nombre=${encodeURIComponent(nombreParaBusqueda)}`)
                                    .then(res => res.json())
                                    .then(data => {
                                        if (data.error) {
                                            console.warn(data.error);
                                            alert("Aún no tenemos el mapa trazado para esta ruta.");
                                        } else if (window.dibujarCamino) {
                                            // Ejecuta la función de Leaflet en script_mapa.js
                                            window.dibujarCamino(data);
                                        }
                                    })
                                    .catch(err => console.error("Error en el servidor:", err));
                            },
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