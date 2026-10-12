<?php
session_start();

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

require 'conexion.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje = $_SESSION['mensaje_admin_libros'] ?? '';
unset($_SESSION['mensaje_admin_libros']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('La solicitud expiró o no es válida. Recarga la página e inténtalo de nuevo.');
    }

    $accion = $_POST['accion'] ?? '';
    $id = filter_var($_POST['id_libro'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        $_SESSION['mensaje_admin_libros'] = 'El identificador del libro no es válido.';
    } elseif ($accion === 'editar') {
        $titulo = trim($_POST['titulo'] ?? '');
        $autor = trim($_POST['autor'] ?? '');
        $precio = filter_var($_POST['precio'] ?? null, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);

        if ($titulo === '' || $autor === '' || $precio === false || $precio <= 0 || $precio > 99999999.99 || $stock === false || $stock < 0) {
            $_SESSION['mensaje_admin_libros'] = 'Revisa los datos: título y autor son obligatorios, el precio debe ser mayor que cero y el stock no puede ser negativo.';
        } else {
            try {
                $stmt = $conexion->prepare(
                    "UPDATE libros
                     SET titulo = :titulo,
                         autor = :autor,
                         precio = TO_NUMBER(:precio, '99999999D99', 'NLS_NUMERIC_CHARACTERS=''.,'''),
                         stock = TO_NUMBER(:stock, '99999999999999999999', 'NLS_NUMERIC_CHARACTERS=''.,''')
                     WHERE id_libro = :id"
                );
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':autor' => $autor,
                    ':precio' => number_format($precio, 2, '.', ''),
                    ':stock' => (string) $stock,
                    ':id' => $id
                ]);
                $_SESSION['mensaje_admin_libros'] = $stmt->rowCount() > 0
                    ? 'Los cambios del libro se guardaron correctamente.'
                    : 'No se encontró el libro o no hubo cambios para guardar.';
            } catch (PDOException $e) {
                error_log('Error al modificar un libro: ' . $e->getMessage());
                $_SESSION['mensaje_admin_libros'] = 'No se pudo modificar el libro. Verifica los datos y la base de datos.';
            }
        }
    } elseif ($accion === 'eliminar') {
        try {
            $stmt = $conexion->prepare('DELETE FROM libros WHERE id_libro = :id');
            $stmt->execute([':id' => $id]);
            $_SESSION['mensaje_admin_libros'] = $stmt->rowCount() > 0
                ? 'El libro se eliminó correctamente.'
                : 'No se encontró el libro que intentabas eliminar.';
        } catch (PDOException $e) {
            error_log('Error al eliminar un libro: ' . $e->getMessage());
            $_SESSION['mensaje_admin_libros'] = 'No se pudo eliminar el libro. Puede estar asociado a un carrito registrado.';
        }
    } else {
        $_SESSION['mensaje_admin_libros'] = 'La acción solicitada no es válida.';
    }

    header('Location: administrar_libros.php');
    exit();
}

try {
    $stmt = $conexion->query('SELECT id_libro, titulo, autor, precio, stock FROM libros ORDER BY titulo');
    $libros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar libros para administración: ' . $e->getMessage());
    $libros = [];
    $mensaje = 'No se pudo cargar la lista de libros. Verifica la conexión con la base de datos.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar libros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="estilos.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">Gestionar libros</h1>
            <div>
                <a href="registro_libro.php" class="btn btn-success">Agregar libro</a>
                <a href="index.php" class="btn btn-outline-secondary">Volver al catálogo</a>
            </div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (empty($libros)): ?>
            <p class="text-muted">No hay libros para gestionar.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($libros as $libro): ?>
                    <div class="col-12">
                        <div class="gestion-tarjeta card">
                            <div class="card-body">
                                <form method="POST" class="row g-3 align-items-end">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="accion" value="editar">
                                    <input type="hidden" name="id_libro" value="<?php echo (int) $libro['ID_LIBRO']; ?>">
                                    <div class="col-md-4">
                                        <label class="form-label" for="titulo-<?php echo (int) $libro['ID_LIBRO']; ?>">Título</label>
                                        <input class="form-control" id="titulo-<?php echo (int) $libro['ID_LIBRO']; ?>" name="titulo" type="text" maxlength="255" value="<?php echo htmlspecialchars($libro['TITULO'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="autor-<?php echo (int) $libro['ID_LIBRO']; ?>">Autor</label>
                                        <input class="form-control" id="autor-<?php echo (int) $libro['ID_LIBRO']; ?>" name="autor" type="text" maxlength="100" value="<?php echo htmlspecialchars($libro['AUTOR'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label" for="precio-<?php echo (int) $libro['ID_LIBRO']; ?>">Precio ($)</label>
                                        <input class="form-control" id="precio-<?php echo (int) $libro['ID_LIBRO']; ?>" name="precio" type="number" min="0.01" max="99999999.99" step="0.01" value="<?php echo htmlspecialchars((string) $libro['PRECIO'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label" for="stock-<?php echo (int) $libro['ID_LIBRO']; ?>">Stock</label>
                                        <input class="form-control" id="stock-<?php echo (int) $libro['ID_LIBRO']; ?>" name="stock" type="number" min="0" step="1" value="<?php echo (int) $libro['STOCK']; ?>" required>
                                    </div>
                                    <div class="col-md-2 d-grid">
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    </div>
                                </form>
                                <form method="POST" class="mt-2" onsubmit="return confirm('¿Eliminar este libro del catálogo?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_libro" value="<?php echo (int) $libro['ID_LIBRO']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar libro</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
