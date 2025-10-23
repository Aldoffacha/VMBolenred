<?php
require_once 'database.php';

class Auth {
    private $db;
    private $tipo_usuario;

    // Constructor corregido
    public function __construct($tipo_usuario = '') {
        $this->db = (new Database())->getConnection();
        $this->tipo_usuario = $tipo_usuario;
    }

    // Método login corregido
    public function login($correo, $contrasena, $tipo_usuario = '') {
        // Usar el tipo de usuario del constructor o del parámetro
        $tipo = !empty($tipo_usuario) ? $tipo_usuario : $this->tipo_usuario;
        
        if (empty($tipo)) {
            error_log("Error: Tipo de usuario no especificado");
            return false;
        }

        try {
            // Determinar la tabla y campo ID según el tipo de usuario
           // Mapeo según tipo de usuario
$map = [
    'clientes' => ['tabla' => 'clientes', 'id' => 'id_cliente', 'rol' => 'cliente'],
    'empleados' => ['tabla' => 'empleados', 'id' => 'id_empleado', 'rol' => 'empleado'],
    'administradores' => ['tabla' => 'administradores', 'id' => 'id_admin', 'rol' => 'admin']
];

if (!isset($map[$tipo])) {
    error_log("Tipo de usuario inválido: $tipo");
    return false;
}

$tabla = $map[$tipo]['tabla'];
$id_field = $map[$tipo]['id'];
$rol = $map[$tipo]['rol'];

            
            $query = "SELECT * FROM $tabla WHERE correo = :correo AND estado = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":correo", $correo);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($contrasena, $user['contrasena'])) {
                    // Configurar sesión
                    $_SESSION['user_id'] = $user[$id_field];
                    $_SESSION['nombre'] = $user['nombre'];
                    $_SESSION['correo'] = $user['correo'];
                    $_SESSION['rol'] = $rol;
                    error_log("Login exitoso: " . $_SESSION['nombre'] . " - Rol: " . $_SESSION['rol']);
                    return true;
                } else {
                    error_log("Contraseña incorrecta para: $correo");
                }
            } else {
                error_log("Usuario no encontrado: $correo en tabla $tabla");
            }
            return false;
            
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return false;
        }
    }

    // Método estático simplificado para login rápido
    public static function quickLogin($correo, $contrasena, $tipo_usuario) {
        $auth = new Auth($tipo_usuario);
        return $auth->login($correo, $contrasena, $tipo_usuario);
    }

    public static function logout() {
        session_destroy();
        header('Location: ../paginas/public/login.php');
        exit;
    }

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public static function checkAuth($rol_requerido) {
    if (!self::isLoggedIn()) {
        // Solo redirigir si no estamos ya en la página de login
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page != 'login.php') {
            header('Location: ../paginas/public/login.php');
            exit;
        }
        return;
    }
    
    // Si el rol no coincide, redirigir al dashboard correspondiente
    if ($_SESSION['rol'] != $rol_requerido) {
        error_log("Redirección: Rol actual: " . $_SESSION['rol'] . ", Rol requerido: " . $rol_requerido);
        
        $dashboard_pages = [
            'admin' => '../paginas/admin/dashboard.php',
            'empleado' => '../paginas/empleado/index.php', 
            'cliente' => '../paginas/cliente/index.php'
        ];
        
        $redirect_url = $dashboard_pages[$_SESSION['rol']] ?? '../paginas/public/login.php';
        
        // Prevenir bucle: solo redirigir si no estamos ya en la página destino
        $current_page = basename($_SERVER['PHP_SELF']);
        $target_page = basename($redirect_url);
        
        if ($current_page != $target_page) {
            header('Location: ' . $redirect_url);
            exit;
        }
    }
}

    // Verificar si el usuario tiene un rol específico
    public static function hasRole($rol) {
        return isset($_SESSION['rol']) && $_SESSION['rol'] == $rol;
    }

    // Obtener información del usuario actual
    public static function getUser() {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'nombre' => $_SESSION['nombre'],
                'correo' => $_SESSION['correo'],
                'rol' => $_SESSION['rol']
            ];
        }
        return null;
    }
}

// Inicializar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>