<?php
session_start();
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require 'conexion.php';
    require 'carrito_helpers.php';

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $conexion->prepare(
            'SELECT ID, NOMBRE, EMAIL, CONTRASENA, DIRECCION, ROL
             FROM USUARIOS
             WHERE EMAIL = :correo'
        );
        $stmt->execute([':correo' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['CONTRASENA'])) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = $usuario['NOMBRE'];
            $_SESSION['id_usuario'] = (int) $usuario['ID'];
            $_SESSION['email'] = $usuario['EMAIL'];
            $_SESSION['direccion'] = $usuario['DIRECCION'];
            $_SESSION['rol'] = $usuario['ROL'];
            $_SESSION['carrito'] = cargarCarritoPersistente($conexion, (int) $usuario['ID']);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: index.php");
            exit();
        }

        $error = "Correo o contraseña incorrectos.";
    } catch (PDOException $e) {
        error_log('Error al autenticar usuario: ' . $e->getMessage());
        $error = "No se pudo iniciar sesión. Verifica la configuración de la base de datos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="estilos.css" rel="stylesheet">
</head>
<body class="pagina-formulario">
    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="formulario-contenedor w-100">
            <h1 class="h2 mb-4 text-center">Acceso a la Librería</h1>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST" id="loginForm" class="formulario-tarjeta p-4">
                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" aria-describedby="ayudaContrasena" required>
                    <div id="ayudaContrasena" class="form-text">Usa al menos 8 caracteres, con mayúscula, minúscula y número.</div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Ingresar</button>
            </form>
            <p class="text-center mt-3">
                ¿No tienes cuenta? <a href="Formularios_Usuario.php">Regístrate</a>
            </p>
        </div>
    </main>
    <script src="script.js"></script>
</body>
</html>