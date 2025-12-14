<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
$data = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';
require_once '../includes/config.php';
require_once '../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$db = (new Database())->getConnection();
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['correo']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan credenciales']);
    exit;
}

$correo = trim($input['correo']);
$password = trim($input['password']);

try {
    // Buscar empleado
    $stmt = $db->prepare("
        SELECT id_empleado, nombre, correo, contrasena, estado 
        FROM empleados 
        WHERE correo = ? AND estado = 1
    ");
    $stmt->execute([$correo]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado) {
        echo json_encode([
            'success' => false, 
            'message' => 'Empleado no encontrado o inactivo'
        ]);
        exit;
    }
    
    // Verificar contraseña (asumiendo que está hasheada con password_hash)
    if (!password_verify($password, $empleado['contrasena'])) {
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        exit;
    }
    
    // Generar token simple (puedes usar JWT si prefieres)
    $token = bin2hex(random_bytes(32));
    
    // Registrar login
    $stmt = $db->prepare("
        INSERT INTO logins 
        (usuario_id, correo, tipo_usuario, ip_address, equipo, fecha_login)
        VALUES (?, ?, 'empleado', ?, 'App Flutter', NOW())
    ");
    $stmt->execute([
        $empleado['id_empleado'],
        $empleado['correo'],
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
    
    // Retornar datos del empleado (sin password)
    unset($empleado['contrasena']);
    $empleado['token'] = $token;
    
    echo json_encode([
        'success' => true,
        'message' => 'Login exitoso',
        'empleado' => $empleado
    ]);
    
} catch (Exception $e) {
    error_log("Error login empleado: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error en el servidor'
    ]);
}