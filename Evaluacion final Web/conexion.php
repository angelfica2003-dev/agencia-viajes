<?php
$host = getenv('LIBRERIA_DB_HOST') ?: '127.0.0.1';
$port = getenv('LIBRERIA_DB_PORT') ?: '3307';
$database = getenv('LIBRERIA_DB_NAME') ?: 'LIBRERIA';
$username = getenv('LIBRERIA_DB_USER') ?: 'root';
$password = getenv('LIBRERIA_DB_PASSWORD') ?: '';

$dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

try {
    $conexion = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Error de conexión a MySQL: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo conectar con la base de datos. Verifica la configuración de MySQL.');
}
