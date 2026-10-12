<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

$libros = [];
$errorCatalogo = '';
$mensajeCarrito = '';
$tipoMensajeCarrito = 'success';
try {
    $stmt = $conexion->query("SELECT titulo, autor, precio, stock FROM libros ORDER BY titulo");
    $libros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar el catálogo: ' . $e->getMessage());
    $errorCatalogo = 'No se pudo cargar el catálogo. Intenta nuevamente más tarde.';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['vaciar'])) {
    $_SESSION['carrito'] = [];
    header('Location: index.php');
    exit;
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agregar'])) {
    $tituloSolicitado = $_POST['titulo'] ?? '';
    $libroSolicitado = null;
    foreach ($libros as $libro) {
        if ($libro['TITULO'] === $tituloSolicitado) {
            $libroSolicitado = $libro;
            break;
        }
    }

    if ($libroSolicitado !== null) {
        $titulo = $libroSolicitado['TITULO'];
        $precio = (float) $libroSolicitado['PRECIO'];
        $stock = (int) $libroSolicitado['STOCK'];
        $cantidadEnCarrito = (int) ($_SESSION['carrito'][$titulo]['cantidad'] ?? 0);

        if ($cantidadEnCarrito >= $stock) {
            $mensajeCarrito = 'No puedes agregar más unidades de "' . $titulo . '". Stock disponible: ' . $stock . '.';
            $tipoMensajeCarrito = 'warning';
        } elseif (isset($_SESSION['carrito'][$titulo])) {
            $_SESSION['carrito'][$titulo]['cantidad']++;
        } else {
            $_SESSION['carrito'][$titulo] = [
                'precio' => $precio,
                'cantidad' => 1
            ];
        }
        if ($cantidadEnCarrito < $stock) {
            $mensajeCarrito = 'Libro agregado al carrito.';
        }
    } else {
        $mensajeCarrito = 'No se pudo agregar el libro. Actualiza el catálogo e inténtalo nuevamente.';
        $tipoMensajeCarrito = 'warning';
    }
}
?>

<!DOCTYPE html> 
<html lang="es"> 
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Librería en Línea - Proyecto</title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="estilos.css" rel="stylesheet">
</head> 
<body class="bg-light"> 

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#inicio">📚 Librería</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuLibreria" aria-controls="menuLibreria" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuLibreria">
                <ul class="navbar-nav ms-auto"> 
                    <li class="nav-item"><a class="nav-link" href="#inicio">Inicio</a></li> 
                    <li class="nav-item"><a class="nav-link" href="#novedades">Catálogo</a></li> 
                    <?php if (isset($_SESSION['usuario'])): ?>
                        <li class="nav-item"><span class="nav-link text-warning">Hola, <?php echo htmlspecialchars($_SESSION['usuario'], ENT_QUOTES, 'UTF-8'); ?></span></li>
                        <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="administrar_libros.php">Gestionar libros</a></li>
                            <li class="nav-item"><a class="nav-link" href="registro_libro.php">Agregar libro</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link btn btn-outline-danger btn-sm text-white ms-2 px-3" href="logout.php">Salir</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link btn btn-primary btn-sm text-white ms-2 px-3" href="login.php">Acceso</a></li> 
                    <?php endif; ?>
                </ul> 
            </div>
        </div>
    </nav>

    <main class="container my-5">
        <section id="inicio" class="hero-libreria p-4 p-md-5 mb-5 rounded-4 shadow-sm border">
            <div class="py-3">
                <h1 class="display-5 fw-bold text-dark">Bienvenido a nuestra Librería en Línea</h1>
                <p class="col-md-8 fs-5 text-secondary">Encuentra tus títulos favoritos, descubre las últimas novedades y realiza tus compras de forma rápida, segura y sencilla.</p>
            </div>
        </section>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <h3 id="novedades" class="mb-4 fw-bold">Libros Disponibles</h3>
                <?php if ($mensajeCarrito !== ''): ?>
                    <div class="alert alert-<?php echo $tipoMensajeCarrito; ?>" role="status">
                        <?php echo htmlspecialchars($mensajeCarrito, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($errorCatalogo !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorCatalogo, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php elseif (empty($libros)): ?>
                    <p class="text-muted">Todavía no hay libros registrados.</p>
                <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php foreach ($libros as $libro): ?>
                    <div class="col">
                        <div class="card libro-card h-100 shadow-sm border-0">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title fw-bold"><?php echo htmlspecialchars($libro['TITULO'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="card-text text-muted small mb-2">Autor: <?php echo htmlspecialchars($libro['AUTOR'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="card-text fw-bold text-success fs-5 mb-3">$<?php echo number_format((float) $libro['PRECIO'], 2); ?></p>
                                <?php
                                    $stockDisponible = (int) $libro['STOCK'];
                                    $cantidadEnCarrito = (int) ($_SESSION['carrito'][$libro['TITULO']]['cantidad'] ?? 0);
                                    $unidadesRestantes = max(0, $stockDisponible - $cantidadEnCarrito);
                                ?>
                                <p class="card-text small text-muted mb-3">
                                    <?php echo $unidadesRestantes > 0 ? 'Disponibles para agregar: ' . $unidadesRestantes : 'Stock máximo en el carrito'; ?>
                                </p>
                                <form method="POST" class="mt-auto">
                                    <input type="hidden" name="titulo" value="<?php echo htmlspecialchars($libro['TITULO'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" name="agregar" class="btn btn-primary w-100" <?php echo $unidadesRestantes === 0 ? 'disabled' : ''; ?>>
                                        <?php echo $unidadesRestantes === 0 ? 'Stock agotado' : 'Agregar al carrito'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 p-3">
                    <h3 class="mb-3 fw-bold h4">🛒 Tu Carrito</h3>
                    <ul class="list-group list-group-flush mb-3">
                        <?php if (empty($_SESSION['carrito'])): ?>
                            <li class="list-group-item text-muted text-center py-3">El carrito está vacío.</li>
                        <?php else: ?>
                            <?php 
                            $totalGeneral = 0;
                            foreach ($_SESSION['carrito'] as $titulo => $detalle): 
                                $subtotal = $detalle['precio'] * $detalle['cantidad'];
                                $totalGeneral += $subtotal;
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <h6 class="my-0 fw-semibold"><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></h6>
                                        <small class="text-muted">Cant: <?php echo $detalle['cantidad']; ?> x $<?php echo $detalle['precio']; ?></small>
                                    </div>
                                    <span class="fw-bold text-success">$<?php echo number_format($subtotal, 2); ?></span>
                                </li>
                            <?php endforeach; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 pt-3 fw-bold fs-5">
                                Total:
                                <span class="text-dark">$<?php echo number_format($totalGeneral, 2); ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <?php if (!empty($_SESSION['carrito'])): ?>
                        <button class="btn btn-success w-100 py-2 fw-bold">Proceder al Pago</button>
                        <form method="POST" class="mt-2">
                            <button type="submit" name="vaciar" class="btn btn-outline-danger w-100">Vaciar carrito</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const scrollGuardado = sessionStorage.getItem('posicionScrollTienda');
        if (scrollGuardado !== null) {
            sessionStorage.removeItem('posicionScrollTienda');
            window.scrollTo(0, Number(scrollGuardado));
        }

        document.querySelectorAll('form[method="POST"]').forEach((formulario) => {
            formulario.addEventListener('submit', () => {
                sessionStorage.setItem('posicionScrollTienda', String(window.scrollY));
            });
        });
    </script>
</body> 
</html>
