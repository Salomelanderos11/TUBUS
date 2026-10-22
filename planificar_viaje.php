<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración de conexión a la Base de Datos
$host = "localhost";
$user = "tu_usuario";
$pass = "tu_contraseña";
$db   = "tu_base_de_datos";

$conexion = new mysqli($host, $user, $pass, $db);
if ($conexion->connect_error) {
    echo json_encode(["error" => "Error de conexión: " . $conexion->connect_error]);
    exit();
}
$conexion->set_charset("utf8mb4");

// Obtener datos del Request (JSON o POST)
$input = json_decode(file_get_contents('php_input') ?: '{}', true);

$latOrig = isset($input['latOrig']) ? (float)$input['latOrig'] : (isset($_POST['latOrig']) ? (float)$_POST['latOrig'] : null);
$lonOrig = isset($input['lonOrig']) ? (float)$input['lonOrig'] : (isset($_POST['lonOrig']) ? (float)$_POST['lonOrig'] : null);
$latDest = isset($input['latDest']) ? (float)$input['latDest'] : (isset($_POST['latDest']) ? (float)$_POST['latDest'] : null);
$lonDest = isset($input['lonDest']) ? (float)$input['lonDest'] : (isset($_POST['lonDest']) ? (float)$_POST['lonDest'] : null);

// Radios de tolerancia en metros
$radioCaminata  = 800; // Máxima distancia para caminar al origen o destino
$radioTransbordo = 500; // Máxima distancia entre rutas para hacer transbordo

if ($latOrig === null || $lonOrig === null || $latDest === null || $lonDest === null) {
    echo json_encode(["error" => "Parámetros de origen y destino incompletos."]);
    exit();
}

/**
 * Función para calcular la distancia en metros entre dos coordenadas (Fórmula de Haversine)
 */
function calcularDistanciaMetros($lat1, $lon1, $lat2, $lon2) {
    $radioTierra = 6371000; // Radio de la Tierra en metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
         
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $radioTierra * $c;
}

/**
 * Función auxiliar para obtener la distancia mínima de un punto (lat/lon) a una ruta (arreglo de coordenadas)
 */
function obtenerDistanciaMinimaRuta($latPunto, $lonPunto, $camino) {
    $distMinima = PHP_FLOAT_MAX;
    $puntoCercano = null;
    $indiceCercano = 0;

    if (is_array($camino)) {
        foreach ($camino as $index => $coord) {
            $lat = isset($coord['lat']) ? $coord['lat'] : (isset($coord[0]) ? $coord[0] : null);
            $lon = isset($coord['lon']) ? $coord['lon'] : (isset($coord[1]) ? $coord[1] : null);

            if ($lat !== null && $lon !== null) {
                $dist = calcularDistanciaMetros($latPunto, $lonPunto, $lat, $lon);
                if ($dist < $distMinima) {
                    $distMinima = $dist;
                    $puntoCercano = ['lat' => $lat, 'lon' => $lon];
                    $indiceCercano = $index;
                }
            }
        }
    }

    return [
        'distancia' => $distMinima,
        'punto'     => $puntoCercano,
        'indice'    => $indiceCercano
    ];
}

// --------------------------------------------------------------------------
// 1. CARGAR RUTAS Y CALCULAR DISTANCIAS A ORIGEN Y DESTINO
// --------------------------------------------------------------------------
$sql = "SELECT id, nombre, descripcion, conductor, paradas, camino FROM rutas";
$resultado = $conexion->query($sql);

$rutasCandidatas = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $camino = json_decode($row['camino'], true) ?: [];
        $paradasList = !empty($row['paradas']) ? array_map('trim', explode('|', $row['paradas'])) : [];

        $evalOrig = obtenerDistanciaMinimaRuta($latOrig, $lonOrig, $camino);
        $evalDest = obtenerDistanciaMinimaRuta($latDest, $lonDest, $camino);

        $rutasCandidatas[] = [
            'id'          => $row['id'],
            'nombre'      => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'conductor'   => $row['conductor'],
            'paradas'     => $paradasList,
            'camino'      => $camino,
            'dOrig'       => $evalOrig['distancia'],
            'ptOrig'      => $evalOrig['punto'],
            'idxOrig'     => $evalOrig['indice'],
            'dDest'       => $evalDest['distancia'],
            'ptDest'      => $evalDest['punto'],
            'idxDest'     => $evalDest['indice'],
        ];
    }
}

// --------------------------------------------------------------------------
// 2. BUSCAR RUTAS DIRECTAS (0 TRANSBORDOS)
// --------------------------------------------------------------------------
$mejorDirecta = null;
$mejorScoreDirecta = PHP_FLOAT_MAX;

foreach ($rutasCandidatas as $r) {
    if ($r['dOrig'] <= $radioCaminata && $r['dDest'] <= $radioCaminata) {
        $score = $r['dOrig'] + $r['dDest'];
        if ($score < $mejorScoreDirecta) {
            $mejorScoreDirecta = $score;
            $mejorDirecta = [
                'tipo'  => 'directo',
                'pasos' => [
                    [
                        'tipo'        => 'ruta',
                        'numero'      => 1,
                        'nombre'      => $r['nombre'],
                        'descripcion' => $r['descripcion'],
                        'conductor'   => $r['conductor'],
                        'paradas'     => implode(' | ', $r['paradas']),
                        'camino'      => json_encode($r['camino']),
                        'dist_orig'   => (int)round($r['dOrig']),
                        'dist_dest'   => (int)round($r['dDest']),
                        'instruccion' => "Aborda la ruta <strong>{$r['nombre']}</strong> a ~" . (int)round($r['dOrig']) . " m de tu ubicación y bájate a ~" . (int)round($r['dDest']) . " m de tu destino."
                    ]
                ]
            ];
        }
    }
}

// Si se encontró una ruta directa óptima, la devolvemos inmediatamente
if ($mejorDirecta !== null) {
    echo json_encode(["status" => "success", "plan" => $mejorDirecta], JSON_UNESCAPED_UNICODE);
    $conexion->close();
    exit();
}

// --------------------------------------------------------------------------
// 3. BUSCAR ALTERNATIVA CON 1 TRANSBORDO
// --------------------------------------------------------------------------
$mejorScore = PHP_FLOAT_MAX;
$mejorPlan  = null;

$totalRutas = count($rutasCandidatas);

for ($i = 0; $i < $totalRutas; $i++) {
    $rA = $rutasCandidatas[$i];

    // La Ruta A debe estar cerca del punto de origen
    if ($rA['dOrig'] > $radioCaminata) {
        continue;
    }

    for ($j = 0; $j < $totalRutas; $j++) {
        if ($i === $j) continue; // No comparar la misma ruta

        $rB = $rutasCandidatas[$j];

        // La Ruta B debe estar cerca del punto de destino
        if ($rB['dDest'] > $radioCaminata) {
            continue;
        }

        // Buscar el punto de cruce/intersección más cercano entre la Ruta A y la Ruta B
        $minTrans  = PHP_FLOAT_MAX;
        $ptTrans   = null;
        $idxTransA = 0;
        $idxTransB = 0;

        foreach ($rA['camino'] as $idxA => $ptA) {
            $latA = isset($ptA['lat']) ? $ptA['lat'] : (isset($ptA[0]) ? $ptA[0] : null);
            $lonA = isset($ptA['lon']) ? $ptA['lon'] : (isset($ptA[1]) ? $ptA[1] : null);

            if ($latA === null || $lonA === null) continue;

            foreach ($rB['camino'] as $idxB => $ptB) {
                $latB = isset($ptB['lat']) ? $ptB['lat'] : (isset($ptB[0]) ? $ptB[0] : null);
                $lonB = isset($ptB['lon']) ? $ptB['lon'] : (isset($ptB[1]) ? $ptB[1] : null);

                if ($latB === null || $lonB === null) continue;

                $dist = calcularDistanciaMetros($latA, $lonA, $latB, $lonB);

                if ($dist < $minTrans) {
                    $minTrans  = $dist;
                    $ptTrans   = ['lat' => $latA, 'lon' => $lonA];
                    $idxTransA = $idxA;
                    $idxTransB = $idxB;
                }
            }
        }

        // Evaluar si el punto de conexión está dentro del radio permitido
        if ($minTrans <= $radioTransbordo) {
            $score = $rA['dOrig'] + $minTrans + $rB['dDest'];

            if ($score < $mejorScore) {
                $mejorScore = $score;

                // Determinar el nombre exacto de la calle / parada de bajada en la Ruta A
                $paradaBajada = "la zona de transbordo";
                if (!empty($rA['paradas'])) {
                    $cantParadas = count($rA['paradas']);
                    $cantPuntos  = max(1, count($rA['camino']));
                    
                    // Proporción relativa del índice del punto de transbordo respecto al listado de paradas
                    $indiceCalculado = (int)floor(($idxTransA / $cantPuntos) * $cantParadas);
                    $indiceParada    = min($cantParadas - 1, max(0, $indiceCalculado));

                    $paradaBajada = $rA['paradas'][$indiceParada] ?? "el punto de transferencia";
                }

                $mejorPlan = [
                    'tipo'  => 'transbordo_1',
                    'pasos' => [
                        [
                            'tipo'        => 'ruta',
                            'numero'      => 1,
                            'nombre'      => $rA['nombre'],
                            'descripcion' => $rA['descripcion'],
                            'conductor'   => $rA['conductor'],
                            'paradas'     => implode(' | ', $rA['paradas']),
                            'camino'      => json_encode($rA['camino']),
                            'dist_orig'   => (int)round($rA['dOrig']),
                            'dist_dest'   => null,
                            'instruccion' => "1. Aborda la ruta <strong>{$rA['nombre']}</strong> a ~" . (int)round($rA['dOrig']) . " m de tu ubicación.",
                        ],
                        [
                            'tipo'          => 'transbordo',
                            'numero'        => 0,
                            'dist_caminata' => (int)round($minTrans),
                            'punto'         => $ptTrans,
                            'instruccion'   => "2. Bájate en la calle / parada próxima a <strong>{$paradaBajada}</strong> y camina ~" . (int)round($minTrans) . " m para tomar el siguiente camión.",
                        ],
                        [
                            'tipo'        => 'ruta',
                            'numero'      => 2,
                            'nombre'      => $rB['nombre'],
                            'descripcion' => $rB['descripcion'],
                            'conductor'   => $rB['conductor'],
                            'paradas'     => implode(' | ', $rB['paradas']),
                            'camino'      => json_encode($rB['camino']),
                            'dist_orig'   => null,
                            'dist_dest'   => (int)round($rB['dDest']),
                            'instruccion' => "3. Aborda la ruta <strong>{$rB['nombre']}</strong> y bájate a ~" . (int)round($rB['dDest']) . " m de tu destino final.",
                        ],
                    ]
                ];
            }
        }
    }
}

// --------------------------------------------------------------------------
// 4. RESPUESTA FINAL JSON
// --------------------------------------------------------------------------
if ($mejorPlan !== null) {
    echo json_encode(["status" => "success", "plan" => $mejorPlan], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "status"  => "no_route",
        "message" => "No se encontraron rutas directas ni con un transbordo dentro del rango permitido."
    ], JSON_UNESCAPED_UNICODE);
}

$conexion->close();
?>