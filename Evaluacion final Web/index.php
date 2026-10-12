<?php
session_start();
require 'conexion.php';
require 'carrito_helpers.php';

if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

foreach (array_keys($_SESSION['carrito']) as $idLibro) {
    if (!ctype_digit((string) $idLibro) || !isset($_SESSION['carrito'][$idLibro]['cantidad'])) {
        unset($_SESSION['carrito'][$idLibro]);
    } else {
        $_SESSION['carrito'][$idLibro]['cantidad'] = max(1, (int) $_SESSION['carrito'][$idLibro]['cantidad']);
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensajeCarrito = $_SESSION['mensaje_carrito'] ?? '';
$tipoMensajeCarrito = $_SESSION['tipo_mensaje_carrito'] ?? 'success';
unset($_SESSION['mensaje_carrito'], $_SESSION['tipo_mensaje_carrito']);

$guardarMensaje = static function (string $mensaje, string $tipo = 'success'): void {
    $_SESSION['mensaje_carrito'] = $mensaje;
    $_SESSION['tipo_mensaje_carrito'] = $tipo;
};

$volverAlCarrito = static function (): void {
    header('Location: index.php#carrito');
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        exit('La solicitud expiró o no es válida. Recarga la página e inténtalo de nuevo.');
    }

    $accion = $_POST['accion'] ?? '';
    $idLibro = filter_var($_POST['id_libro'] ?? null, FILTER_VALIDATE_INT);

    try {
        if ($accion === 'agregar' && $idLibro !== false && $idLibro > 0) {
            $stmt = $conexion->prepare(
                'SELECT CANTIDAD_INVENTARIO FROM LIBROS WHERE ID = :id'
            );
            $stmt->execute([':id' => $idLibro]);
            $stock = $stmt->fetchColumn();
            $cantidadActual = (int) ($_SESSION['carrito'][$idLibro]['cantidad'] ?? 0);

            if ($stock === false) {
                $guardarMensaje('Ese libro ya no está disponible en el catálogo.', 'warning');
            } elseif ($cantidadActual >= (int) $stock) {
                $guardarMensaje('No puedes agregar más unidades: ya alcanzaste el stock disponible.', 'warning');
            } else {
                $_SESSION['carrito'][$idLibro] = ['cantidad' => $cantidadActual + 1];
                if (isset($_SESSION['id_usuario'])) {
                    guardarCarritoPersistente($conexion, (int) $_SESSION['id_usuario'], $_SESSION['carrito']);
                }
                $guardarMensaje('Libro agregado al carrito.');
            }
            header('Location: index.php#libro-' . $idLibro);
            exit();
        }

        if ($accion === 'actualizar' && $idLibro !== false && $idLibro > 0) {
            $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
            $stmt = $conexion->prepare(
                'SELECT CANTIDAD_INVENTARIO FROM LIBROS WHERE ID = :id'
            );
            $stmt->execute([':id' => $idLibro]);
            $stock = $stmt->fetchColumn();

            if ($cantidad === false || $cantidad < 1 || $stock === false || $cantidad > (int) $stock) {
                $guardarMensaje('La cantidad debe ser mayor que cero y no superar el stock actual.', 'warning');
            } else {
                $_SESSION['carrito'][$idLibro] = ['cantidad' => $cantidad];
                if (isset($_SESSION['id_usuario'])) {
                    guardarCarritoPersistente($conexion, (int) $_SESSION['id_usuario'], $_SESSION['carrito']);
                }
                $guardarMensaje('Cantidad actualizada.');
            }
            $volverAlCarrito();
        }

        if ($accion === 'eliminar' && $idLibro !== false && $idLibro > 0) {
            unset($_SESSION['carrito'][$idLibro]);
            if (isset($_SESSION['id_usuario'])) {
                guardarCarritoPersistente($conexion, (int) $_SESSION['id_usuario'], $_SESSION['carrito']);
            }
            $guardarMensaje('Libro eliminado del carrito.');
            $volverAlCarrito();
        }

        if ($accion === 'vaciar') {
            $_SESSION['carrito'] = [];
            if (isset($_SESSION['id_usuario'])) {
                guardarCarritoPersistente($conexion, (int) $_SESSION['id_usuario'], []);
            }
            $guardarMensaje('Se vació el carrito.');
            $volverAlCarrito();
        }

        if ($accion === 'confirmar') {
            if (empty($_SESSION['id_usuario'])) {
                $guardarMensaje('Inicia sesión para confirmar el pedido.', 'warning');
                header('Location: login.php');
                exit();
            }
            if (empty($_SESSION['carrito'])) {
                $guardarMensaje('Tu carrito está vacío.', 'warning');
                $volverAlCarrito();
            }

            $conexion->beginTransaction();
            try {
                $usuarioStmt = $conexion->prepare('SELECT DIRECCION FROM USUARIOS WHERE ID = :id');
                $usuarioStmt->execute([':id' => (int) $_SESSION['id_usuario']]);
                $direccion = $usuarioStmt->fetchColumn();
                if ($direccion === false || trim($direccion) === '') {
                    throw new RuntimeException('Completa una dirección en tu cuenta antes de realizar el pedido.');
                }

                $idsLibros = array_map('intval', array_keys($_SESSION['carrito']));
                sort($idsLibros, SORT_NUMERIC);
                $libroStmt = $conexion->prepare(
                    'SELECT ID, TITULO, PRECIO, CANTIDAD_INVENTARIO
                     FROM LIBROS WHERE ID = :id FOR UPDATE'
                );
                $itemsPedido = [];
                $totalPedido = 0.0;

                foreach ($idsLibros as $id) {
                    $cantidad = (int) $_SESSION['carrito'][$id]['cantidad'];
                    $libroStmt->execute([':id' => $id]);
                    $libro = $libroStmt->fetch();
                    if (!$libro || $cantidad < 1 || $cantidad > (int) $libro['CANTIDAD_INVENTARIO']) {
                        throw new RuntimeException('El stock cambió para uno de los libros. Revisa el carrito e inténtalo otra vez.');
                    }

                    $subtotal = round((float) $libro['PRECIO'] * $cantidad, 2);
                    $totalPedido += $subtotal;
                    $itemsPedido[] = [
                        'id' => $id,
                        'titulo' => $libro['TITULO'],
                        'precio' => (float) $libro['PRECIO'],
                        'cantidad' => $cantidad,
                        'subtotal' => $subtotal,
                    ];
                }

                $pedidoStmt = $conexion->prepare(
                    "INSERT INTO PEDIDOS (ID_USUARIO, DIRECCION_ENVIO, MONTO_TOTAL, ESTADO)
                     VALUES (:usuario, :direccion, :total, 'Pendiente')"
                );
                $pedidoStmt->execute([
                    ':usuario' => (int) $_SESSION['id_usuario'],
                    ':direccion' => $direccion,
                    ':total' => number_format($totalPedido, 2, '.', ''),
                ]);
                $idPedido = (int) $conexion->lastInsertId();

                $detalleStmt = $conexion->prepare(
                    'INSERT INTO DETALLE_PEDIDO
                     (ID_PEDIDO, ID_LIBRO, TITULO_LIBRO, PRECIO_UNITARIO, CANTIDAD, SUBTOTAL)
                     VALUES (:pedido, :libro, :titulo, :precio, :cantidad, :subtotal)'
                );
                $stockStmt = $conexion->prepare(
                    'UPDATE LIBROS
                     SET CANTIDAD_INVENTARIO = CANTIDAD_INVENTARIO - :cantidad_restar
                     WHERE ID = :id AND CANTIDAD_INVENTARIO >= :cantidad_minima'
                );

                foreach ($itemsPedido as $item) {
                    $detalleStmt->execute([
                        ':pedido' => $idPedido,
                        ':libro' => $item['id'],
                        ':titulo' => $item['titulo'],
                        ':precio' => number_format($item['precio'], 2, '.', ''),
                        ':cantidad' => $item['cantidad'],
                        ':subtotal' => number_format($item['subtotal'], 2, '.', ''),
                    ]);
                    $stockStmt->execute([
                        ':cantidad_restar' => $item['cantidad'],
                        ':id' => $item['id'],
                        ':cantidad_minima' => $item['cantidad'],
                    ]);
                    if ($stockStmt->rowCount() !== 1) {
                        throw new RuntimeException('No se pudo reservar el stock. Revisa el carrito e inténtalo otra vez.');
                    }
                }

                $borrarCarrito = $conexion->prepare('DELETE FROM CARRITO WHERE ID_USUARIO = :usuario');
                $borrarCarrito->execute([':usuario' => (int) $_SESSION['id_usuario']]);
                $conexion->commit();
                $_SESSION['carrito'] = [];
                $_SESSION['mensaje_pedido'] = '¡Pedido #' . $idPedido . ' realizado correctamente!';
                header('Location: mis_pedidos.php');
                exit();
            } catch (Throwable $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                if ($e instanceof PDOException) {
                    error_log('Error al confirmar pedido: ' . $e->getMessage());
                    $guardarMensaje('No se pudo completar el pedido por un error de base de datos.', 'danger');
                } else {
                    $guardarMensaje($e->getMessage(), 'warning');
                }
                $volverAlCarrito();
            }
        }

        $guardarMensaje('La acción solicitada no es válida.', 'warning');
    } catch (Throwable $e) {
        if ($e instanceof PDOException) {
            error_log('Error al procesar el carrito: ' . $e->getMessage());
            $guardarMensaje('No se pudo actualizar el carrito. Intenta nuevamente.', 'danger');
        } else {
            $guardarMensaje($e->getMessage(), 'warning');
        }
        $volverAlCarrito();
    }
}

$errorCatalogo = '';
try {
    $stmt = $conexion->query(
        'SELECT ID, TITULO, AUTOR, PRECIO, CANTIDAD_INVENTARIO FROM LIBROS ORDER BY TITULO'
    );
    $libros = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error al cargar el catálogo: ' . $e->getMessage());
    $libros = [];
    $errorCatalogo = 'No se pudo cargar el catálogo. Intenta nuevamente más tarde.';
}

$librosPorId = [];
foreach ($libros as $libro) {
    $librosPorId[(int) $libro['ID']] = $libro;
}

$totalGeneral = 0.0;
foreach ($_SESSION['carrito'] as $id => $item) {
    if (!isset($librosPorId[(int) $id])) {
        unset($_SESSION['carrito'][$id]);
        continue;
    }
    $libro = $librosPorId[(int) $id];
    $totalGeneral += (float) $libro['PRECIO'] * (int) $item['cantidad'];
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
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="#inicio">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#novedades">Catálogo</a></li>
                    <?php if (isset($_SESSION['usuario'])): ?>
                        <li class="nav-item"><span class="nav-link text-warning">Hola, <?php echo htmlspecialchars($_SESSION['usuario'], ENT_QUOTES, 'UTF-8'); ?></span></li>
                        <li class="nav-item"><a class="nav-link" href="mis_pedidos.php">Mis pedidos</a></li>
                        <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="administrar_libros.php">Gestionar libros</a></li>
                            <li class="nav-item"><a class="nav-link" href="registro_libro.php">Agregar libro</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link btn btn-outline-danger btn-sm text-white ms-lg-2 px-3" href="logout.php">Salir</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="Formularios_Usuario.php">Crear cuenta</a></li>
                        <li class="nav-item"><a class="nav-link btn btn-primary btn-sm text-white ms-lg-2 px-3" href="login.php">Acceso</a></li>
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
            <section class="col-lg-8 mb-4" aria-labelledby="novedades">
                <h2 id="novedades" class="h3 mb-4 fw-bold">Libros Disponibles</h2>
                <?php if ($mensajeCarrito !== ''): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($tipoMensajeCarrito, ENT_QUOTES, 'UTF-8'); ?>" role="status">
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
                            <?php
                                $id = (int) $libro['ID'];
                                $stock = (int) $libro['CANTIDAD_INVENTARIO'];
                                $cantidadEnCarrito = (int) ($_SESSION['carrito'][$id]['cantidad'] ?? 0);
                                $unidadesRestantes = max(0, $stock - $cantidadEnCarrito);
                            ?>
                            <div class="col" id="libro-<?php echo $id; ?>">
                                <div class="card libro-card h-100 shadow-sm border-0">
                                    <div class="card-body d-flex flex-column">
                                        <h3 class="card-title h5 fw-bold"><?php echo htmlspecialchars($libro['TITULO'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="card-text text-muted small mb-2">Autor: <?php echo htmlspecialchars($libro['AUTOR'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p class="card-text fw-bold text-success fs-5 mb-2">$<?php echo number_format((float) $libro['PRECIO'], 2); ?></p>
                                        <p class="card-text small text-muted mb-3">
                                            <?php echo $unidadesRestantes > 0 ? 'Disponibles para agregar: ' . $unidadesRestantes : 'Stock máximo en el carrito'; ?>
                                        </p>
                                        <form method="POST" class="mt-auto">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="accion" value="agregar">
                                            <input type="hidden" name="id_libro" value="<?php echo $id; ?>">
                                            <button type="submit" class="btn btn-primary w-100" <?php echo $unidadesRestantes === 0 ? 'disabled' : ''; ?>>
                                                <?php echo $unidadesRestantes === 0 ? 'Stock agotado' : 'Agregar al carrito'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="col-lg-4" id="carrito" aria-labelledby="tituloCarrito">
                <div class="card shadow-sm border-0 p-3">
                    <h2 id="tituloCarrito" class="mb-3 fw-bold h4">🛒 Tu Carrito</h2>
                    <?php if (empty($_SESSION['carrito'])): ?>
                        <p class="list-group-item text-muted text-center py-3 mb-0">El carrito está vacío.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush mb-3">
                            <?php foreach ($_SESSION['carrito'] as $id => $item): ?>
                                <?php
                                    $libro = $librosPorId[(int) $id];
                                    $cantidad = (int) $item['cantidad'];
                                    $subtotal = (float) $libro['PRECIO'] * $cantidad;
                                ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between gap-2 mb-2">
                                        <div>
                                            <h3 class="h6 my-0 fw-semibold"><?php echo htmlspecialchars($libro['TITULO'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <small class="text-muted">$<?php echo number_format((float) $libro['PRECIO'], 2); ?> c/u</small>
                                        </div>
                                        <span class="fw-bold text-success">$<?php echo number_format($subtotal, 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end gap-2">
                                        <form method="POST" class="d-flex align-items-end gap-2">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="accion" value="actualizar">
                                            <input type="hidden" name="id_libro" value="<?php echo (int) $id; ?>">
                                            <div>
                                                <label class="form-label small mb-1" for="cantidad-<?php echo (int) $id; ?>">Cantidad</label>
                                                <input class="form-control form-control-sm" id="cantidad-<?php echo (int) $id; ?>" name="cantidad" type="number" min="1" max="<?php echo (int) $libro['CANTIDAD_INVENTARIO']; ?>" value="<?php echo $cantidad; ?>" required>
                                            </div>
                                            <button type="submit" class="btn btn-outline-primary btn-sm">Actualizar</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id_libro" value="<?php echo (int) $id; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="Eliminar <?php echo htmlspecialchars($libro['TITULO'], ENT_QUOTES, 'UTF-8'); ?>">Quitar</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center fw-bold fs-5 mb-3">
                            <span>Total:</span>
                            <span>$<?php echo number_format($totalGeneral, 2); ?></span>
                        </div>
                        <form method="POST" class="mb-2">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="accion" value="confirmar">
                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Confirmar pedido</button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="accion" value="vaciar">
                            <button type="submit" class="btn btn-outline-danger w-100">Vaciar carrito</button>
                        </form>
                        <?php if (empty($_SESSION['id_usuario'])): ?>
                            <p class="small text-muted mt-2 mb-0">Para confirmar el pedido, <a href="login.php">inicia sesión</a> o <a href="Formularios_Usuario.php">crea una cuenta</a>.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
