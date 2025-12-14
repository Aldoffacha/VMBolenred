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
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos'
    ]);
    exit;
}

// 🔴 LEER JSON UNA SOLA VEZ
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/* =========================
   FUNCIONES
========================= */

function loginEmpleado($db, $correo, $password) {
    try {
        $sql = "
            SELECT id_empleado, nombre, correo, telefono, contrasena
            FROM empleados
            WHERE correo = ? AND estado = 1
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$correo]);
        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$empleado) {
            return ['success' => false, 'message' => 'Credenciales inválidas'];
        }

        if (!password_verify($password, $empleado['contrasena'])) {
            return ['success' => false, 'message' => 'Credenciales inválidas'];
        }

        unset($empleado['contrasena']);

        return [
            'success' => true,
            'user' => $empleado,
            'tipo' => 'empleado'
        ];

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Error interno del servidor'];
    }
}

/* =========================
   ROUTER
========================= */

switch ($action) {
    case 'login':

        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }

        if (
            empty($data['correo']) ||
            empty($data['password']) ||
            empty($data['tipo'])
        ) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos incompletos'
            ]);
            exit;
        }

        if ($data['tipo'] !== 'empleado') {
            echo json_encode([
                'success' => false,
                'message' => 'Tipo de usuario inválido'
            ]);
            exit;
        }

        $resultado = loginEmpleado(
            $db,
            $data['correo'],
            $data['password']
        );

        echo json_encode($resultado);
        exit;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Acción no válida'
        ]);
        exit;
}
