<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID no proporcionado']);
    exit;
}

$db = (new Database())->getConnection();
$id = intval($_GET['id']);

$query = "SELECT a.*, 
                 COALESCE(ad.nombre, e.nombre, c.nombre) as nombre_usuario
          FROM auditoria a 
          LEFT JOIN administradores ad ON a.id_usuario = ad.id_admin AND a.tipo_usuario = 'admin'
          LEFT JOIN empleados e ON a.id_usuario = e.id_empleado AND a.tipo_usuario = 'empleado'
          LEFT JOIN clientes c ON a.id_usuario = c.id_cliente AND a.tipo_usuario = 'cliente'
          WHERE a.id_auditoria = ?";

$stmt = $db->prepare($query);
$stmt->execute([$id]);
$detalles = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$detalles) {
    http_response_code(404);
    echo json_encode(['error' => 'Registro no encontrado']);
    exit;
}

header('Content-Type: application/json');
echo json_encode($detalles);