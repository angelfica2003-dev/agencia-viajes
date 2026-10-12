<?php
session_start();

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

require 'conexion.php';

$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = trim($_POST['titulo'] ?? '');
    $autor = trim($_POST['autor'] ?? '');
    $precio = filter_var($_POST['precio'] ?? null, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);

    if ($titulo === '' || $autor === '' || $precio === false || $precio <= 0 || $precio > 99999999.99 || $stock === false || $stock < 0) {
        $mensaje = '<div class="alert alert-danger">Completa los campos correctamente. El precio debe ser mayor que cero y el stock no puede ser negativo.</div>';
    } else {
        try {
            $sql = "INSERT INTO libros (titulo, autor, precio, stock)
                    VALUES (
                        :titulo,
                        :autor,
                        TO_NUMBER(:precio, '99999999D99', 'NLS_NUMERIC_CHARACTERS=''.,'''),
                        TO_NUMBER(:stock, '99999999999999999999', 'NLS_NUMERIC_CHARACTERS=''.,''')
                    )";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':titulo' => $titulo,
                ':autor' => $autor,
                ':precio' => number_format($precio, 2, '.', ''),
                ':stock' => (string) $stock
            ]);
            $mensaje = '<div class="alert alert-success">Libro "' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '" registrado correctamente.</div>';
        } catch (PDOException $e) {
            error_log('Error al registrar un libro: ' . $e->getMessage());
            $mensaje = '<div class="alert alert-danger">No se pudo registrar el libro. Verifica la base de datos.</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Libros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="estilos.css" rel="stylesheet">
</head>
<body class="pagina-formulario">
    <main class="container py-5">
    <div class="formulario-contenedor mx-auto">
    <h1 class="h2 mb-4">Registro de Nuevo Libro</h1>
    
    <?php if ($mensaje !== '') echo $mensaje; ?>

    <form action="registro_libro.php" method="POST" class="formulario-tarjeta p-4 mt-3">
        <div class="mb-3">
            <label for="titulo" class="form-label">Título del libro</label>
            <input type="text" class="form-control" id="titulo" name="titulo" required>
        </div>
        <div class="mb-3">
            <label for="autor" class="form-label">Autor</label>
            <input type="text" class="form-control" id="autor" name="autor" required>
        </div>
        <div class="mb-3">
            <label for="precio" class="form-label">Precio ($)</label>
            <input type="number" min="0.01" max="99999999.99" step="0.01" class="form-control" id="precio" name="precio" required>
        </div>
        <div class="mb-3">
            <label for="stock" class="form-label">Cantidad en Inventario (Stock)</label>
            <input type="number" min="0" step="1" class="form-control" id="stock" name="stock" required>
        </div>
        <button type="submit" class="btn btn-success">Guardar Libro</button>
        <a href="administrar_libros.php" class="btn btn-secondary ms-2">Gestionar libros</a>
        <a href="index.php" class="btn btn-link ms-2">Volver al inicio</a>
    </form>
    </div>
    </main>
</body>
</html>