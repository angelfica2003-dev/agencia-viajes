<?php
$username = "Libros";
$password = "123456";
$db = "//192.168.1.91:1521/XEPDB1"; 

try {
    $conexion = new PDO("oci:dbname=$db;charset=UTF8", $username, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a Oracle: " . $e->getMessage());
}
?>