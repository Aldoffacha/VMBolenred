<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

Auth::checkAuth('empleado');
$db = (new Database())->getConnection();

$action = $_GET['action'] ?? '';
$empleado_id = (int) ($_GET['empleado_id'] ?? 0);

if ($action === 'mis_pedidos') {
    try {
        $query = "
            SELECT 
                p.id_pedido,
                p.total,
                p.fecha,
                p.estado as estado_pedido,
                p.estado_entrega,
                c.nombre as cliente_nombre,
                c.telefono as cliente_telefono,
                ue.direccion_entrega,
                ue.latitud,
                ue.longitud,
                ue.referencia,
                ue.nombre_receptor,
                ue.telefono_receptor,
                pe.estado as estado_asignacion,
                pe.fecha_asignacion
            FROM pedido_empleado pe
            INNER JOIN pedidos p ON pe.id_pedido = p.id_pedido
            INNER JOIN clientes c ON p.id_cliente = c.id_cliente
            LEFT JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
            WHERE pe.id_empleado = ?
                AND pe.estado NOT IN ('rechazado', 'entregado', 'cancelado')
            ORDER BY 
                CASE pe.estado 
                    WHEN 'asignado' THEN 1
                    WHEN 'aceptado' THEN 2
                    WHEN 'en_camino' THEN 3
                    WHEN 'entregado' THEN 4
                    ELSE 5
                END,
                pe.fecha_asignacion DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$empleado_id]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'pedidos' => $pedidos
        ]);
        
    } catch (Exception $e) {
        error_log("Error en mis_pedidos: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener pedidos'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida'
    ]);
}