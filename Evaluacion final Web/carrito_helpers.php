<?php
function cargarCarritoPersistente(PDO $conexion, int $idUsuario): array
{
    $stmt = $conexion->prepare('SELECT ID_LIBRO, CANTIDAD FROM CARRITO WHERE ID_USUARIO = :id_usuario');
    $stmt->execute([':id_usuario' => $idUsuario]);

    $carrito = [];
    foreach ($stmt->fetchAll() as $item) {
        $carrito[(int) $item['ID_LIBRO']] = ['cantidad' => (int) $item['CANTIDAD']];
    }

    return $carrito;
}

function guardarCarritoPersistente(PDO $conexion, int $idUsuario, array $carrito): void
{
    $conexion->beginTransaction();

    try {
        $delete = $conexion->prepare('DELETE FROM CARRITO WHERE ID_USUARIO = :id_usuario');
        $delete->execute([':id_usuario' => $idUsuario]);

        $buscarLibro = $conexion->prepare(
            'SELECT PRECIO, CANTIDAD_INVENTARIO FROM LIBROS WHERE ID = :id_libro'
        );
        $insertar = $conexion->prepare(
            'INSERT INTO CARRITO (ID_USUARIO, ID_LIBRO, CANTIDAD, MONTO_TOTAL)
             VALUES (:id_usuario, :id_libro, :cantidad, :monto_total)'
        );

        foreach ($carrito as $idLibro => $item) {
            $idLibro = (int) $idLibro;
            $cantidad = (int) $item['cantidad'];
            if ($idLibro < 1 || $cantidad < 1) {
                continue;
            }

            $buscarLibro->execute([':id_libro' => $idLibro]);
            $libro = $buscarLibro->fetch();
            if (!$libro || $cantidad > (int) $libro['CANTIDAD_INVENTARIO']) {
                throw new RuntimeException('El stock disponible cambió. Actualiza tu carrito.');
            }

            $total = number_format((float) $libro['PRECIO'] * $cantidad, 2, '.', '');
            $insertar->execute([
                ':id_usuario' => $idUsuario,
                ':id_libro' => $idLibro,
                ':cantidad' => $cantidad,
                ':monto_total' => $total,
            ]);
        }

        $conexion->commit();
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $e;
    }
}
