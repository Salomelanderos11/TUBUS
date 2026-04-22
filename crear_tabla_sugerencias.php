<?php
require "Conexion.php";

$sql = "CREATE TABLE IF NOT EXISTS sugerencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "✅ Tabla 'sugerencias' creada correctamente o ya existía.";
} else {
    echo "❌ Error al crear la tabla: " . $conn->error;
}