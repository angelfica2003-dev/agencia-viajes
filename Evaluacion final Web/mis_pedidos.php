<?php
session_start();

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit();
}

require 'conexion.php';

$mensaje = $_SESSION['mensaje_pedido'] ?? '';
unset($_SESSION['mensaje_pedido']);

try {
    $stmt = $conexion->prepare(
        'SELECT ID, DIRECCION_ENVIO, MONTO_TOTAL, ESTADO, FECHA_CREACION
         FROM PEDIDOS
         WHERE ID_USUARIO = :usuario
         ORDER BY FECHA_CREACION DESC, ID DESC'
    );
    $stmt->execute([':usuario' => (int) $_SESSION['id_usuario']]);
    $pedidos = $stmt->fetchAll();

    $detalleStmt = $conexion->prepare(
        'SELECT TITULO_LIBRO, PRECIO_UNITARIO, CANTIDAD, SUBTOTAL
         FROM DETALLE_PEDIDO
         WHERE ID_PEDIDO = :pedido
         ORDER BY ID'
    );
} catch (PDOException $e) {
    error_log('Error al consultar pedidos: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudieron cargar tus pedidos. Intenta nuevamente más tarde.');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis pedidos - Librería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="estilos.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <p class="text-muted mb-1">Hola, <?php echo htmlspecialchars($_SESSION['usuario'], ENT_QUOTES, 'UTF-8'); ?></p>
                <h1 class="h2 mb-0">Mis pedidos</h1>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">Volver al catálogo</a>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (empty($pedidos)): ?>
            <div class="card formulario-tarjeta">
                <div class="card-body text-center py-5">
                    <p class="text-muted mb-3">Todavía no tienes pedidos.</p>
                    <a href="index.php#novedades" class="btn btn-primary">Explorar libros</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="col-12">
                        <article class="gestion-tarjeta card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                    <div>
                                        <h2 class="h5 mb-1">Pedido #<?php echo (int) $pedido['ID']; ?></h2>
                                        <p class="small text-muted mb-0"><?php echo htmlspecialchars($pedido['FECHA_CREACION'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                    <span class="badge text-bg-primary align-self-start"><?php echo htmlspecialchars($pedido['ESTADO'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <p><strong>Dirección de envío:</strong> <?php echo htmlspecialchars($pedido['DIRECCION_ENVIO'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php
                                    $detalleStmt->execute([':pedido' => (int) $pedido['ID']]);
                                    $detalles = $detalleStmt->fetchAll();
                                ?>
                                <ul class="list-group list-group-flush mb-3">
                                    <?php foreach ($detalles as $detalle): ?>
                                        <li class="list-group-item px-0 d-flex justify-content-between gap-2">
                                            <span><?php echo htmlspecialchars($detalle['TITULO_LIBRO'], ENT_QUOTES, 'UTF-8'); ?> × <?php echo (int) $detalle['CANTIDAD']; ?></span>
                                            <span>$<?php echo number_format((float) $detalle['SUBTOTAL'], 2); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="text-end fw-bold mb-0">Total: $<?php echo number_format((float) $pedido['MONTO_TOTAL'], 2); ?></p>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
