<?php
require 'Conexion.php';
header('Content-Type: application/json');

$lat         = isset($_GET['lat'])   ? (float)$_GET['lat']   : 0;
$lng         = isset($_GET['lng'])   ? (float)$_GET['lng']   : 0;
$radio_metros = isset($_GET['radio']) ? (int)$_GET['radio']   : 350;

if ($lat == 0 && $lng == 0) {
    echo json_encode(["error" => "Coordenadas inválidas"]);
    exit;
}

/* --------------------------------------------------
   Distancia de un punto P al segmento AB (en metros)
   Usa proyección plana local (válida para áreas pequeñas como Culiacán)
   -------------------------------------------------- */
function distanciaPuntoSegmento($lat, $lng, $lat1, $lng1, $lat2, $lng2) {
    $latMedia   = deg2rad(($lat1 + $lat2) / 2);
    $escalaLat  = 111320;
    $escalaLng  = 111320 * cos($latMedia);

    $px = ($lng  - $lng1) * $escalaLng;
    $py = ($lat  - $lat1) * $escalaLat;
    $dx = ($lng2 - $lng1) * $escalaLng;
    $dy = ($lat2 - $lat1) * $escalaLat;

    $lenSq = $dx * $dx + $dy * $dy;

    if ($lenSq == 0) {
        return sqrt($px * $px + $py * $py);
    }

    $t  = max(0, min(1, ($px * $dx + $py * $dy) / $lenSq));
    $cx = $t * $dx;
    $cy = $t * $dy;

    return sqrt(($px - $cx) ** 2 + ($py - $cy) ** 2);
}

/* --------------------------------------------------
   Distancia mínima de un punto a toda una polilínea
   -------------------------------------------------- */
function distanciaMinARuta($lat, $lng, $coordenadas) {
    $distMin = PHP_FLOAT_MAX;
    $n       = count($coordenadas);

    for ($i = 0; $i < $n - 1; $i++) {
        $d = distanciaPuntoSegmento(
            $lat, $lng,
            $coordenadas[$i]['lat'],   $coordenadas[$i]['lng'],
            $coordenadas[$i+1]['lat'], $coordenadas[$i+1]['lng']
        );
        if ($d < $distMin) $distMin = $d;
    }
    return $distMin;
}

/* --------------------------------------------------
   Cargar todas las rutas con su trazado geométrico
   -------------------------------------------------- */
$sql = "
    SELECT tr.nombre,
           ST_AsText(tr.camino) AS camino_wkt,
           r.id,
           r.descripcion,
           r.conductor,
           r.paradas
    FROM   trazado_rutas tr
    LEFT JOIN rutas r ON r.nombre_ruta = tr.nombre
";

$res              = $conn->query($sql);
$rutas_encontradas = [];

while ($fila = $res->fetch_assoc()) {

    /* Parsear WKT → array de {lat, lng} */
    $wkt    = $fila['camino_wkt'];
    $limpio = str_replace(['LINESTRING(', ')'], '', $wkt);
    $puntos = explode(',', $limpio);

    $coordenadas = [];
    foreach ($puntos as $p) {
        $partes = explode(' ', trim($p));
        if (count($partes) >= 2) {
            $coordenadas[] = [
                'lat' => (float)$partes[1],
                'lng' => (float)$partes[0]
            ];
        }
    }

    if (empty($coordenadas)) continue;

    /* Calcular distancia mínima */
    $distancia = distanciaMinARuta($lat, $lng, $coordenadas);

    if ($distancia <= $radio_metros) {
        $rutas_encontradas[] = [
            'nombre'           => $fila['nombre'],
            'id'               => $fila['id'],
            'descripcion'      => $fila['descripcion'] ?? '',
            'conductor'        => $fila['conductor']   ?? '',
            'paradas'          => array_values(
                                     array_filter(
                                         explode('|', $fila['paradas'] ?? '')
                                     )
                                  ),
            'camino'           => $coordenadas,
            'distancia_metros' => (int)round($distancia)
        ];
    }
}

/* Ordenar de más cercana a más lejana */
usort($rutas_encontradas, fn($a, $b) => $a['distancia_metros'] - $b['distancia_metros']);

echo json_encode($rutas_encontradas);