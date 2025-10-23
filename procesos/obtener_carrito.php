<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] != 'cliente') {
    echo json_encode(['total' => 0, 'items' => []]);
    exit;
}

$id_cliente = $_SESSION['user_id'];

try {
    $db = (new Database())->getConnection();
    
    // Obtener cantidad total de items en carrito
    $stmt = $db->prepare("SELECT SUM(cantidad) as total FROM carrito WHERE id_cliente = ?");
    $stmt->execute([$id_cliente]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Obtener items del carrito con detalles del producto
    $stmt = $db->prepare("
        SELECT c.*, p.nombre, p.precio, p.imagen 
        FROM carrito c 
        JOIN productos p ON c.id_producto = p.id_producto 
        WHERE c.id_cliente = ?
    ");
    $stmt->execute([$id_cliente]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['total' => $total, 'items' => $items]);
    
} catch (Exception $e) {
    echo json_encode(['total' => 0, 'items' => []]);
}
?>