<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/funciones.php';
require_once '../../includes/auditoria.class.php'; // <-- AÑADIR ESTA LÍNEA

if (isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

// Inicializar conexión a la base de datos y auditoría
$database = new Database();
$db = $database->getConnection();
$auditoria = new Auditoria($db); // <-- AÑADIR ESTA LÍNEA

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = htmlspecialchars(strip_tags(trim($_POST['nombre'])));
    $correo = htmlspecialchars(strip_tags(trim($_POST['correo'])));
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];
    $telefono = htmlspecialchars(strip_tags(trim($_POST['telefono'])));
    $direccion = htmlspecialchars(strip_tags(trim($_POST['direccion'])));
    
    // Obtener información del equipo para auditoría
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Determinar el tipo de equipo
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
    
    // Detectar navegador
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
    
    // Validaciones
    if ($contrasena !== $confirmar_contrasena) {
        $error = "Las contraseñas no coinciden";
    } elseif (strlen($contrasena) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo electrónico no es válido";
    } else {
        try {
            $db = (new Database())->getConnection();
            
            // Verificar si el correo ya existe
            $stmt = $db->prepare("SELECT id_cliente FROM clientes WHERE correo = ?");
            $stmt->execute([$correo]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Este correo electrónico ya está registrado";
                
                // Auditoría: Intento de registro con correo existente
                $datos_intento = [
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'telefono' => $telefono,
                    'ip_address' => $ip_address,
                    'equipo' => $info_equipo,
                    'user_agent' => $user_agent,
                    'razon' => 'correo_ya_registrado',
                    'fecha_intento' => date('Y-m-d H:i:s')
                ];
                
                $auditoria->registrarInsercion('registros_fallidos', null, $datos_intento);
                
            } else {
                // Registrar nuevo cliente
                $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO clientes (nombre, correo, contrasena, telefono, direccion) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $correo, $contrasena_hash, $telefono, $direccion]);
                
                $id_cliente = $db->lastInsertId();
                $success = "¡Registro exitoso! Ya puedes iniciar sesión.";
                
                // Auditoría: Registro exitoso
                $datos_registro = [
                    'id_cliente' => $id_cliente,
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'telefono' => $telefono,
                    'direccion' => $direccion,
                    'ip_address' => $ip_address,
                    'equipo' => $info_equipo,
                    'user_agent' => $user_agent,
                    'fecha_registro' => date('Y-m-d H:i:s')
                ];
                
                // Registrar en auditoría usando inserción directa
                try {
                    $query = "INSERT INTO auditoria 
                             (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, tipo_usuario, ip_address) 
                             VALUES (:tabla, :id_registro, :accion, :datos_nuevos, :id_usuario, :tipo_usuario, :ip_address)";
                    
                    $stmt_audit = $db->prepare($query);
                    $result = $stmt_audit->execute([
                        ':tabla' => 'clientes',
                        ':id_registro' => $id_cliente,
                        ':accion' => 'INSERT',
                        ':datos_nuevos' => json_encode($datos_registro, JSON_PRETTY_PRINT),
                        ':id_usuario' => $id_cliente,
                        ':tipo_usuario' => 'cliente',
                        ':ip_address' => $ip_address
                    ]);
                    
                    if ($result) {
                        error_log("✅ Auditoría de registro exitoso para: $nombre ($correo)");
                    } else {
                        error_log("❌ Error al registrar auditoría de registro");
                    }
                    
                } catch (PDOException $e) {
                    error_log("❌ Error en auditoría de registro: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $error = "Error en el registro: " . $e->getMessage();
            
            // Auditoría: Error en el registro
            $datos_error = [
                'nombre' => $nombre,
                'correo' => $correo,
                'telefono' => $telefono,
                'ip_address' => $ip_address,
                'equipo' => $info_equipo,
                'user_agent' => $user_agent,
                'error' => $e->getMessage(),
                'fecha_error' => date('Y-m-d H:i:s')
            ];
            
            try {
                $query = "INSERT INTO auditoria 
                         (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, tipo_usuario, ip_address) 
                         VALUES (:tabla, :id_registro, :accion, :datos_nuevos, :id_usuario, :tipo_usuario, :ip_address)";
                
                $stmt_audit = $db->prepare($query);
                $stmt_audit->execute([
                    ':tabla' => 'clientes',
                    ':id_registro' => 0,
                    ':accion' => 'INSERT',
                    ':datos_nuevos' => json_encode($datos_error, JSON_PRETTY_PRINT),
                    ':id_usuario' => 0,
                    ':tipo_usuario' => 'cliente',
                    ':ip_address' => $ip_address
                ]);
            } catch (PDOException $audit_error) {
                error_log("❌ Error en auditoría de error: " . $audit_error->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - VMBol en Red</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/login.css">
    <style>
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
        }
        .weak { background-color: #dc3545; width: 33%; }
        .medium { background-color: #ffc107; width: 66%; }
        .strong { background-color: #28a745; width: 100%; }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <h2 class="text-primary">🌐 Crear Cuenta</h2>
                <p class="text-muted">Únete a VMBol en Red</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?>
                    <br><a href="login.php" class="alert-link">👉 Ir al login</a>
                </div>
            <?php else: ?>
            
            <form method="POST" id="registroForm">
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">👤 Nombre Completo *</label>
                            <input type="text" name="nombre" class="form-control" required 
                                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
                                   placeholder="Ej: Juan Pérez">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">📧 Correo Electrónico *</label>
                    <input type="email" name="correo" class="form-control" required
                           value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>"
                           placeholder="ejemplo@correo.com">
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">🔒 Contraseña *</label>
                            <input type="password" name="contrasena" class="form-control" required 
                                   minlength="6" id="password" placeholder="Mínimo 6 caracteres">
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">🔑 Confirmar Contraseña *</label>
                            <input type="password" name="confirmar_contrasena" class="form-control" required
                                   id="confirmPassword" placeholder="Repite tu contraseña">
                            <div class="form-text" id="passwordMatch"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">📞 Teléfono</label>
                    <input type="tel" name="telefono" class="form-control"
                           value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>"
                           placeholder="Ej: 77712345">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">🏠 Dirección (Para envíos)</label>
                    <textarea name="direccion" class="form-control" rows="2" placeholder="Dirección completa para entregas"><?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?></textarea>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terminos" required>
                    <label class="form-check-label" for="terminos">
                        ✅ Acepto los <a href="#" class="text-primary">términos y condiciones</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 mb-3" style="padding: 12px;">
                    🚀 Registrarse
                </button>
                
                <div class="text-center">
                    <span class="text-muted">¿Ya tienes cuenta? </span>
                    <a href="login.php" class="text-primary fw-bold">👉 Inicia sesión aquí</a>
                </div>
            </form>
            
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Validación de fortaleza de contraseña
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            strengthBar.className = 'password-strength ';
            if (password.length === 0) {
                strengthBar.style.width = '0';
            } else if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength === 3) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        });

        // Validación de coincidencia de contraseñas
        document.getElementById('registroForm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchText.innerHTML = '✅ Las contraseñas coinciden';
                    matchText.style.color = 'green';
                } else {
                    matchText.innerHTML = '❌ Las contraseñas no coinciden';
                    matchText.style.color = 'red';
                }
            } else {
                matchText.innerHTML = '';
            }
        });

        // Validación antes de enviar
        document.getElementById('registroForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const terminos = document.getElementById('terminos').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('❌ Las contraseñas no coinciden');
                return false;
            }
            
            if (!terminos) {
                e.preventDefault();
                alert('❌ Debes aceptar los términos y condiciones');
                return false;
            }
        });
    </script>
</body>
</html>