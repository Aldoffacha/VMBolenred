<?php
// Ruta corregida para la estructura principal/procesos/
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

$stmt = $db->prepare("SELECT * FROM empleados WHERE id_empleado = ?");
$stmt->execute([$id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    http_response_code(404);
    echo json_encode(['error' => 'Empleado no encontrado']);
    exit;
}

header('Content-Type: application/json');
echo json_encode($empleado);