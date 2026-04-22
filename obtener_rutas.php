<?php
require 'Conexion.php';

$rutas = [];

$res = $conn->query("SELECT * FROM rutas");

while ($r = $res->fetch_assoc()) {
    $rutas[] = [
        "id"=>$r['id'],
        "name"=>$r['nombre_ruta'],
        "description"=>$r['descripcion'],
        "conductor"=>$r['conductor'],
        "stops"=>explode("|",$r['paradas'])
    ];
}

echo json_encode($rutas);