<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

// Verificar que sea una petición GET y tenga ID
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de pedido no especificado']);
    exit;
}

try {
    $pedido_id = intval($_GET['id']);
    
    $db = (new Database())->getConnection();

    // Obtener información del pedido
    $stmt_pedido = $db->prepare("
        SELECT p.*, c.nombre as cliente_nombre, c.correo as cliente_email 
        FROM pedidos p 
        JOIN clientes c ON p.id_cliente = c.id_cliente 
        WHERE p.id_pedido = ?
    ");
    
    $stmt_pedido->execute([$pedido_id]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception('Pedido no encontrado');
    }

    // Obtener detalles del pedido
    $stmt_detalles = $db->prepare("
        SELECT pd.*, p.nombre as producto 
        FROM pedido_detalles pd 
        JOIN productos p ON pd.id_producto = p.id_producto 
        WHERE pd.id_pedido = ?
    ");
    
    $stmt_detalles->execute([$pedido_id]);
    $productos = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'productos' => $productos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>