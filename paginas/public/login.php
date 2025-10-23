<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/auditoria.class.php';

if (Auth::isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$auditoria = new Auditoria($db);

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = htmlspecialchars(strip_tags(trim($_POST['correo'])));
    $contrasena = $_POST['contrasena'];
    $tipo_usuario = $_POST['tipo_usuario'];
    
    error_log("Login attempt: $correo - Tipo: $tipo_usuario");
    
    if (Auth::quickLogin($correo, $contrasena, $tipo_usuario)) {
        error_log("Login successful for: $correo - Tipo: $tipo_usuario");

        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $equipo = "Desconocido";
        if (preg_match('/Windows/i', $user_agent)) {
            $equipo = "Windows PC";
        } elseif (preg_match('/Macintosh|Mac OS/i', $user_agent)) {
            $equipo = "Mac";
        } elseif (preg_match('/Linux/i', $user_agent)) {
            $equipo = "Linux";
        } elseif (preg_match('/Android/i', $user_agent)) {
            $equipo = "Android";
        } elseif (preg_match('/iPhone|iPad/i', $user_agent)) {
            $equipo = "iOS";
        }
        
        $navegador = "Desconocido";
        if (preg_match('/Chrome/i', $user_agent) && !preg_match('/Edg/i', $user_agent)) {
            $navegador = "Chrome";
        } elseif (preg_match('/Firefox/i', $user_agent)) {
            $navegador = "Firefox";
        } elseif (preg_match('/Safari/i', $user_agent) && !preg_match('/Chrome/i', $user_agent)) {
            $navegador = "Safari";
        } elseif (preg_match('/Edg/i', $user_agent)) {
            $navegador = "Edge";
        }
        
        $info_equipo = "$equipo - $navegador";
        
        $nombre_usuario = "Desconocido";
        $id_usuario = 0;
        $tipo_auditoria = 'cliente'; 
        
        try {
            if ($tipo_usuario === 'administradores') {
                $query = "SELECT id_admin, nombre FROM administradores WHERE correo = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$correo]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($usuario) {
                    $id_usuario = $usuario['id_admin'];
                    $nombre_usuario = $usuario['nombre'];
                    $tipo_auditoria = 'admin';
                }
            } elseif ($tipo_usuario === 'empleados') {
                $query = "SELECT id_empleado, nombre FROM empleados WHERE correo = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$correo]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($usuario) {
                    $id_usuario = $usuario['id_empleado'];
                    $nombre_usuario = $usuario['nombre'];
                    $tipo_auditoria = 'empleado';
                }
            } else { // clientes
                $query = "SELECT id_cliente, nombre FROM clientes WHERE correo = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$correo]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($usuario) {
                    $id_usuario = $usuario['id_cliente'];
                    $nombre_usuario = $usuario['nombre'];
                    $tipo_auditoria = 'cliente';
                }
            }
        } catch (PDOException $e) {
            error_log("Error obteniendo datos del usuario: " . $e->getMessage());
        }
        
        
        error_log("Datos para auditoría - ID: $id_usuario, Nombre: $nombre_usuario, Tipo: $tipo_auditoria");
        
        $datos_login = [
            'usuario_id' => $id_usuario,
            'nombre_usuario' => $nombre_usuario,
            'correo' => $correo,
            'tipo_usuario' => $tipo_auditoria,
            'ip_address' => $ip_address,
            'equipo' => $info_equipo,
            'user_agent' => $user_agent,
            'fecha_login' => date('Y-m-d H:i:s')
        ];
        
        try {
            $query = "INSERT INTO auditoria 
                     (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, tipo_usuario, ip_address) 
                     VALUES (:tabla, :id_registro, :accion, :datos_nuevos, :id_usuario, :tipo_usuario, :ip_address)";
            
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                ':tabla' => 'logins',
                ':id_registro' => $id_usuario, 
                ':accion' => 'INSERT',
                ':datos_nuevos' => json_encode($datos_login, JSON_PRETTY_PRINT),
                ':id_usuario' => $id_usuario,
                ':tipo_usuario' => $tipo_auditoria, 
                ':ip_address' => $ip_address
            ]);
            
            if ($result) {
                error_log("✅ Auditoría de login registrada exitosamente para: $nombre_usuario ($correo) - Tipo: $tipo_auditoria");
            } else {
                error_log("❌ Error al registrar auditoría de login");
            }
            
        } catch (PDOException $e) {
            error_log("❌ Error en auditoría de login: " . $e->getMessage());
        }

        if ($tipo_usuario === 'administradores') {
            header('Location: ../admin/dashboard.php'); 
        } elseif ($tipo_usuario === 'empleados') {
            header('Location: ../empleado/index.php'); 
        } else {
            header('Location: ../../index.php'); 
        }
        exit;
    } else {
        $error = "❌ Credenciales incorrectas o usuario inactivo";
        error_log("Login failed for: $correo");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VMBol en Red</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/login.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <h2 class="text-primary">🔐 VMBol en Red</h2>
                <p class="text-muted">Sistema de Importación a Bolivia</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">👤 Tipo de Usuario</label>
                    <select name="tipo_usuario" class="form-select" required>
                        <option value="clientes">Cliente</option>
                        <option value="empleados">Empleado</option>
                        <option value="administradores">Administrador</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">📧 Correo Electrónico</label>
                    <input type="email" name="correo" class="form-control" required 
                           value="admin@test.com">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">🔒 Contraseña</label>
                    <input type="password" name="contrasena" class="form-control" required 
                           value="password">
                </div>
                
                <button type="submit" class="btn btn-primary w-100 mb-3">🚀 Iniciar Sesión</button>
                
                <div class="text-center">
                    <a href="registro.php" class="text-primary">📝 ¿No tienes cuenta? Regístrate aquí</a>
                </div>
            </form>
            
        </div>
    </div>
</body>
</html>