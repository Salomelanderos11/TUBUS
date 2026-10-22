<?php
$host = "sql104.infinityfree.com";
$user = "if0_43231628";
$pass = "gKtUBVmZZSc";
$db   = "if0_43231628_tubus_db";
$charset = 'utf8mb4';

// Crear conexión usando MySQLi
$conn = new mysqli($host, $user, $pass, $db);

// Comprobar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer charset UTF-8
$conn->set_charset("utf8mb4");
