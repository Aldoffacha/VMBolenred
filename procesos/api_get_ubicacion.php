<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$data = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';
require_once '../includes/config.php';
require_once '../includes/database.php';

$db = (new Database())->getConnection();
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id_pedido'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

$id_pedido = (int) $input['id_pedido'];

try {
    $query = "
        SELECT 
            ue.direccion_entrega,
            ue.latitud,
            ue.longitud,
            ue.referencia,
            ue.nombre_receptor,
            ue.telefono_receptor,
            c.nombre as cliente_nombre,
            c.telefono as cliente_telefono
        FROM ubicacion_entrega ue
        INNER JOIN pedidos p ON ue.id_pedido = p.id_pedido
        INNER JOIN clientes c ON p.id_cliente = c.id_cliente
        WHERE ue.id_pedido = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$id_pedido]);
    $ubicacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ubicacion) {
        echo json_encode([
            'success' => true,
            'ubicacion' => $ubicacion
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró ubicación para este pedido'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}