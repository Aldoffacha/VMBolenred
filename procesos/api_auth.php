<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/config.php';
require_once '../includes/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$request = isset($_GET['action']) ? $_GET['action'] : '';

function loginCliente($db, $correo, $password) {
    try {
        $query = "SELECT id_cliente, nombre, correo, telefono, direccion, foto_perfil, contrasena, estado
                  FROM clientes 
                  WHERE correo = ? AND estado = 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$correo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Credenciales inválidas'];
        }
        
        // Verificar contraseña
        if (!password_verify($password, $user['contrasena'])) {
            return ['success' => false, 'message' => 'Credenciales inválidas'];
        }
        
        // Registrar login
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $log_query = "INSERT INTO logins (usuario_id, correo, tipo_usuario, ip_address, user_agent, fecha_login)
                      VALUES (?, ?, 'cliente', ?, ?, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$user['id_cliente'], $correo, $ip, $user_agent]);
        
        // Eliminar datos sensibles
        unset($user['contrasena']);
        unset($user['estado']);
        
        return ['success' => true, 'user' => $user, 'tipo' => 'cliente'];
        
    } catch (PDOException $e) {
        error_log("Error en loginCliente: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error en el servidor'];
    }
}

function loginEmpleado($db, $correo, $password) {
    try {
        $query = "SELECT id_empleado, nombre, correo, telefono, contrasena, estado
                  FROM empleados 
                  WHERE correo = ? AND estado = 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$correo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Credenciales inválidas'];
        }
        
        // Verificar contraseña
        if (!password_verify($password, $user['contrasena'])) {
            return ['success' => false, 'message' => 'Credenciales inválidas'];
        }
        
        // Registrar login
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $log_query = "INSERT INTO logins (usuario_id, correo, tipo_usuario, ip_address, user_agent, fecha_login)
                      VALUES (?, ?, 'empleado', ?, ?, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$user['id_empleado'], $correo, $ip, $user_agent]);
        
        // Eliminar datos sensibles
        unset($user['contrasena']);
        unset($user['estado']);
        
        return ['success' => true, 'user' => $user, 'tipo' => 'empleado'];
        
    } catch (PDOException $e) {
        error_log("Error en loginEmpleado: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error en el servidor'];
    }
}

// Manejo de rutas
switch($request) {
    case 'login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['correo']) || !isset($data['password']) || !isset($data['tipo'])) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                break;
            }
            
            if ($data['tipo'] === 'cliente') {
                $result = loginCliente($db, $data['correo'], $data['password']);
            } elseif ($data['tipo'] === 'empleado') {
                $result = loginEmpleado($db, $data['correo'], $data['password']);
            } else {
                $result = ['success' => false, 'message' => 'Tipo de usuario inválido'];
            }
            
            echo json_encode($result);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>