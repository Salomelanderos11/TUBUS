<?php
require 'Conexion.php';

// Recibimos el nombre de la ruta (ej: "Zapata - Centro")
$nombre_ruta = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';

if ($nombre_ruta !== '') {
    // Buscamos por la columna 'nombre'
    $query = "SELECT ST_AsText(camino) as texto_camino FROM trazado_rutas WHERE nombre = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("s", $nombre_ruta); // 's' porque es un string
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $rawText = $row['texto_camino']; 
            
            // Limpiar el formato LINESTRING(lng lat, lng lat...)
            $clean = str_replace(['LINESTRING(', ')'], '', $rawText);
            $puntos = explode(',', $clean);
            
            $json_final = [];
            foreach ($puntos as $index => $p) {
                $coords = explode(' ', trim($p));
                if(count($coords) >= 2) {
                    // Invertimos: MySQL (LNG LAT) -> Leaflet (LAT LNG)
                    $json_final[] = [
                        "lat" => (float)$coords[1],
                        "lng" => (float)$coords[0],
                        "orden" => $index + 1
                    ];
                }
            }
            header('Content-Type: application/json');
            echo json_encode($json_final);
        } else {
            echo json_encode(["error" => "No se encontró trazado para: " . $nombre_ruta]);
        }
        $stmt->close();
    }
}