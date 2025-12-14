<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = $_POST['id_producto_exterior'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no especificado']);
    exit;
}

$db = (new Database())->getConnection();

try {
    $stmt = $db->prepare("DELETE FROM productos_exterior WHERE id_producto_exterior = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Producto eliminado correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
?>
