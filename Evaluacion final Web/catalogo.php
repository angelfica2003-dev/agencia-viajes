<?php
session_start();

// Control de acceso: Si no ha iniciado sesión, redirigir al login
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Inicializar el carrito en la sesión si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Simular agregar un libro al carrito mediante POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agregar'])) {
    $libro = $_POST['libro'];
    $precio = $_POST['precio'];
    
    // Si ya está en el carrito, aumentar cantidad; si no, agregarlo
    if (isset($_SESSION['carrito'][$libro])) {
        $_SESSION['carrito'][$libro]['cantidad']++;
    } else {
        $_SESSION['carrito'][$libro] = [
            'precio' => $precio,
            'cantidad' => 1
        ];
    }
}

// Vaciar o cerrar sesión
if (isset($_GET['accion']) && $_GET['accion'] == 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Catálogo y Carrito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?></h2>
        <a href="catalogo.php?accion=logout" class="btn btn-outline-danger">Cerrar Sesión</a>
    </div>

    <div class="row">
        <!-- Listado de Libros Disponibles -->
        <div class="col-md-8">
            <h3>Catálogo de Libros</h3>
            <div class="list-group">
                <form method="POST" class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Programación en PHP</h5>
                        <small>Autor: Juan Pérez - Precio: $20.00</small>
                    </div>
                    <input type="hidden" name="libro" value="Programación en PHP">
                    <input type="hidden" name="precio" value="20.00">
                    <button type="submit" name="agregar" class="btn btn-sm btn-success">Agregar al Carrito</button>
                </form>
                <form method="POST" class="list-group-item d-flex justify-content-between align-items-center mt-2">
                    <div>
                        <h5 class="mb-1">Bases de Datos MySQL</h5>
                        <small>Autor: María Gómez - Precio: $25.00</small>
                    </div>
                    <input type="hidden" name="libro" value="Bases de Datos MySQL">
                    <input type="hidden" name="precio" value="25.00">
                    <button type="submit" name="agregar" class="btn btn-sm btn-success">Agregar al Carrito</button>
                </form>
            </div>
        </div>

        <!-- Resumen del Carrito en Sesión -->
        <div class="col-md-4">
            <h3>Tu Carrito</h3>
            <ul class="list-group mb-3">
                <?php if (empty($_SESSION['carrito'])): ?>
                    <li class="list-group-item">El carrito está vacío.</li>
                <?php else: ?>
                    <?php 
                    $totalGeneral = 0;
                    foreach ($_SESSION['carrito'] as $titulo => $detalle): 
                        $subtotal = $detalle['precio'] * $detalle['cantidad'];
                        $totalGeneral += $subtotal;
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="my-0"><?php echo $titulo; ?></h6>
                                <small class="text-muted">Cant: <?php echo $detalle['cantidad']; ?> x $<?php echo $detalle['precio']; ?></small>
                            </div>
                            <span>$<?php echo number_format($subtotal, 2); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center fw-bold">
                        Total a Pagar:
                        <span>$<?php echo number_format($totalGeneral, 2); ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>
</html>