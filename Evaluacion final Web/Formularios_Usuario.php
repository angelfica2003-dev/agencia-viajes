<?php
require 'conexion.php';

$mensaje = '';
$valores = [
    'nombre' => '',
    'email' => '',
    'direccion' => '',
    'telefono' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valores as $campo => $valor) {
        $valores[$campo] = trim($_POST[$campo] ?? $valor);
    }
    $contrasena = $_POST['contrasena'] ?? '';

    if (in_array('', $valores, true) || $contrasena === '') {
        $mensaje = '<div class="alert alert-danger">Completa todos los campos.</div>';
    } elseif (!filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $mensaje = '<div class="alert alert-danger">Ingresa un correo electrónico válido.</div>';
    } else {
        try {
            $stmt = $conexion->prepare(
                'INSERT INTO Usuario (nombre, correo_electronico, contrasena, direccion, telefono)
                 VALUES (:nombre, :correo, :contrasena, :direccion, :telefono)'
            );
            $stmt->execute([
                ':nombre' => $valores['nombre'],
                ':correo' => $valores['email'],
                ':contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
                ':direccion' => $valores['direccion'],
                ':telefono' => $valores['telefono']
            ]);
            $mensaje = '<div class="alert alert-success">Cuenta creada. Ya puedes <a href="login.php">iniciar sesión</a>.</div>';
            $valores = ['nombre' => '', 'email' => '', 'direccion' => '', 'telefono' => ''];
        } catch (PDOException $e) {
            error_log('Error al registrar usuario: ' . $e->getMessage());
            $mensaje = '<div class="alert alert-danger">No se pudo crear la cuenta. El correo podría estar registrado o existe un problema con la base de datos.</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="estilos.css" rel="stylesheet">
</head>
<body class="pagina-formulario">
    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="formulario-contenedor w-100">
            <h1 class="h2 mb-4 text-center">Crear cuenta de cliente</h1>
            <?php if ($mensaje !== '') echo $mensaje; ?>
            <form action="Formularios_Usuario.php" method="POST" class="formulario-tarjeta p-4">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($valores['nombre'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($valores['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="contrasena" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                </div>
                <div class="mb-3">
                    <label for="direccion" class="form-label">Dirección</label>
                    <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($valores['direccion'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($valores['telefono'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Crear cuenta</button>
                <a href="login.php" class="btn btn-link mt-2">Volver al inicio de sesión</a>
            </form>
        </div>
    </main>
</body>
</html>
