<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

Auth::checkAuth('empleado');
$db = (new Database())->getConnection();

// Leer datos JSON del cuerpo de la petición
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$id_empleado = (int) ($input['id_empleado'] ?? 0);

// DEBUG
error_log("API Pedidos: action=$action, id_empleado=$id_empleado");

if ($action === 'obtener_disponibles') {
    try {
        // Obtener pedidos disponibles (pagados, con ubicación, no asignados)
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
                ue.telefono_receptor
            FROM pedidos p
            INNER JOIN clientes c ON p.id_cliente = c.id_cliente
            INNER JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
            LEFT JOIN pedido_empleado pe ON p.id_pedido = pe.id_pedido 
                AND pe.estado NOT IN ('rechazado', 'entregado', 'cancelado')
            LEFT JOIN pagos pg ON p.id_pedido = pg.id_pedido
            WHERE 
                (p.estado = 'pagado' OR pg.estado IN ('pagado', 'confirmado'))
                AND p.estado_entrega = 'con_ubicacion'
                AND (pe.id_pedido IS NULL OR pe.estado IS NULL)
                AND ue.direccion_entrega IS NOT NULL
                AND ue.latitud IS NOT NULL
                AND ue.longitud IS NOT NULL
            ORDER BY p.fecha ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $pedidos_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'pedidos_disponibles' => $pedidos_disponibles,
            'count_disponibles' => count($pedidos_disponibles)
        ]);
        
    } catch (Exception $e) {
        error_log("Error en obtener_disponibles: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener pedidos: ' . $e->getMessage()
        ]);
    }
    
} elseif ($action === 'obtener_mis_pedidos') {
    try {
        // Solo pedidos asignados a este empleado
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
            ORDER BY pe.fecha_asignacion DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id_empleado]);
        $pedidos_mios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'pedidos_mios' => $pedidos_mios
        ]);
        
    } catch (Exception $e) {
        error_log("Error en obtener_mis_pedidos: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener mis pedidos: ' . $e->getMessage()
        ]);
    }
    
} elseif ($action === 'asignar') {
    try {
        $id_pedido = (int) ($input['id_pedido'] ?? 0);
        
        if ($id_pedido <= 0 || $id_empleado <= 0) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit;
        }
        
        // Verificar que el pedido esté disponible
        $stmt = $db->prepare("
            SELECT p.id_pedido 
            FROM pedidos p
            LEFT JOIN pedido_empleado pe ON p.id_pedido = pe.id_pedido 
                AND pe.estado NOT IN ('rechazado', 'entregado', 'cancelado')
            WHERE p.id_pedido = ? 
                AND p.estado_entrega = 'con_ubicacion'
                AND (pe.id_pedido IS NULL OR pe.estado IS NULL)
        ");
        $stmt->execute([$id_pedido]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'El pedido ya no está disponible']);
            exit;
        }
        
        // Asignar pedido al empleado
        $stmt = $db->prepare("
            INSERT INTO pedido_empleado (id_pedido, id_empleado, estado, fecha_asignacion) 
            VALUES (?, ?, 'asignado', CURRENT_TIMESTAMP)
        ");
        
        $success = $stmt->execute([$id_pedido, $id_empleado]);
        
        if ($success) {
            // Actualizar estado del pedido
            $stmt = $db->prepare("UPDATE pedidos SET estado_entrega = 'asignado' WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);
            
            echo json_encode(['success' => true, 'message' => 'Pedido asignado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al asignar el pedido']);
        }
        
    } catch (Exception $e) {
        error_log("Error en asignar: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error al asignar pedido: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida'
    ]);
}