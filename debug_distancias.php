<?php
/*
 * debug_distancias.php
 * --------------------
 * Herramienta de diagnóstico: muestra las distancias reales
 * que calcula el sistema desde un punto a todas las rutas.
 * Uso: debug_distancias.php?lat=24.805&lng=-107.394
 */
require 'Conexion.php';
header('Content-Type: application/json');

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0;

if (!$lat || !$lng) {
    echo json_encode(['error' => 'Falta lat y lng. Ej: ?lat=24.805&lng=-107.394']);
    exit;
}

function distPuntoSeg($lat, $lng, $lat1, $lng1, $lat2, $lng2) {
    $m   = deg2rad(($lat1 + $lat2) / 2);
    $sLt = 111320;
    $sLn = 111320 * cos($m);
    $px  = ($lng  - $lng1) * $sLn;  $py = ($lat  - $lat1) * $sLt;
    $dx  = ($lng2 - $lng1) * $sLn;  $dy = ($lat2 - $lat1) * $sLt;
    $l2  = $dx * $dx + $dy * $dy;
    if ($l2 == 0) return sqrt($px * $px + $py * $py);
    $t = max(0, min(1, ($px * $dx + $py * $dy) / $l2));
    return sqrt(($px - $t * $dx) ** 2 + ($py - $t * $dy) ** 2);
}

$sql = "SELECT tr.nombre, ST_AsText(tr.camino) AS wkt FROM trazado_rutas tr";
$res = $conn->query($sql);
$resultados = [];

while ($f = $res->fetch_assoc()) {
    $wkt    = str_replace(['LINESTRING(', ')'], '', $f['wkt']);
    $coords = [];
    foreach (explode(',', $wkt) as $p) {
        $xy = explode(' ', trim($p));
        if (count($xy) >= 2)
            $coords[] = ['lat' => (float)$xy[1], 'lng' => (float)$xy[0]];
    }

    $minDist  = PHP_FLOAT_MAX;
    $segMasCercano = 0;
    $n = count($coords);

    for ($i = 0; $i < $n - 1; $i++) {
        $d = distPuntoSeg(
            $lat, $lng,
            $coords[$i]['lat'],   $coords[$i]['lng'],
            $coords[$i+1]['lat'], $coords[$i+1]['lng']
        );
        if ($d < $minDist) {
            $minDist = $d;
            $segMasCercano = $i;
        }
    }

    $resultados[] = [
        'ruta'              => $f['nombre'],
        'distancia_metros'  => (int)round($minDist),
        'segmento_cercano'  => $segMasCercano,
        'total_segmentos'   => $n - 1,
        'punto_mas_cercano' => [
            'lat' => $coords[$segMasCercano]['lat'],
            'lng' => $coords[$segMasCercano]['lng']
        ]
    ];
}

usort($resultados, fn($a, $b) => $a['distancia_metros'] - $b['distancia_metros']);

echo json_encode([
    'punto_consultado' => ['lat' => $lat, 'lng' => $lng],
    'rutas'            => $resultados
], JSON_PRETTY_PRINT);