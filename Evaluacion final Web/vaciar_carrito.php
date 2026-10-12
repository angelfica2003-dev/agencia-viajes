<?php
session_start();
// Destruye o limpia la variable del carrito
unset($_SESSION['carrito']);

// Redirige de regreso a la tienda
header("Location: index.php");
exit();
?>