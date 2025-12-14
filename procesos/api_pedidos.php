<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Obtener pedidos del cliente
function obtenerPedidosCliente($db, $id_cliente) {
    try {
        $query = "SELECT p.*, 
                  COALESCE(pg.estado, 'sin_pago') as estado_pago,
                  ue.direccion_entrega,
                  ue.latitud as lat_entrega,
                  ue.longitud as lon_entrega
                  FROM pedidos p
                  LEFT JOIN pagos pg ON p.id_pedido = pg.id_pedido
                  LEFT JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
                  WHERE p.id_cliente = ?
                  ORDER BY p.fecha DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id_cliente]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener detalles de cada pedido
        foreach ($pedidos as &$pedido) {
            $detalles = "SELECT pd.*, 
                        CASE 
                            WHEN pd.tipo_producto = 'local' THEN pr.nombre
                            ELSE pd.datos_externos
                        END as nombre_producto,
                        pr.imagen
                        FROM pedido_detalles pd
                        LEFT JOIN productos pr ON pd.id_producto = pr.id_producto
                        WHERE pd.id_pedido = ?";
            
            $stmt = $db->prepare($detalles);
            $stmt->execute([$pedido['id_pedido']]);
            $pedido['detalles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return ['success' => true, 'pedidos' => $pedidos];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerPedidosCliente: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al obtener pedidos'];
    }
}

// Obtener pedidos PAGADOS para empleado (listos para entrega)
function obtenerPedidosPagados($db) {
    try {
        $query = "SELECT DISTINCT p.id_pedido, p.id_cliente, p.total, p.fecha, 
                  p.estado, p.estado_entrega,
                  c.nombre as nombre_cliente,
                  c.telefono as telefono_cliente,
                  ue.direccion_entrega,
                  ue.latitud,
                  ue.longitud,
                  ue.referencia,
                  ue.nombre_receptor,
                  ue.telefono_receptor,
                  pg.estado as estado_pago,
                  pg.fecha_pago,
                  pe.id_empleado,
                  pe.estado as estado_asignacion
                  FROM pedidos p
                  INNER JOIN clientes c ON p.id_cliente = c.id_cliente
                  INNER JOIN pagos pg ON p.id_pedido = pg.id_pedido
                  LEFT JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
                  LEFT JOIN pedido_empleado pe ON p.id_pedido = pe.id_pedido
                  WHERE pg.estado = 'pagado' 
                  AND p.estado != 'entregado'
                  AND p.estado != 'cancelado'
                  ORDER BY p.fecha DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener detalles de cada pedido
        foreach ($pedidos as &$pedido) {
            $detalles = "SELECT pd.*, 
                        CASE 
                            WHEN pd.tipo_producto = 'local' THEN pr.nombre
                            ELSE pd.datos_externos
                        END as nombre_producto,
                        pr.imagen
                        FROM pedido_detalles pd
                        LEFT JOIN productos pr ON pd.id_producto = pr.id_producto
                        WHERE pd.id_pedido = ?";
            
            $stmt = $db->prepare($detalles);
            $stmt->execute([$pedido['id_pedido']]);
            $pedido['detalles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return ['success' => true, 'pedidos' => $pedidos];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerPedidosPagados: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al obtener pedidos'];
    }
}

// Obtener pedidos asignados a un empleado
function obtenerPedidosEmpleado($db, $id_empleado) {
    try {
        $query = "SELECT p.id_pedido, p.id_cliente, p.total, p.fecha, 
                  p.estado, p.estado_entrega,
                  c.nombre as nombre_cliente,
                  c.telefono as telefono_cliente,
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
                  AND p.estado != 'entregado'
                  AND p.estado != 'cancelado'
                  ORDER BY pe.fecha_asignacion DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id_empleado]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener detalles de cada pedido
        foreach ($pedidos as &$pedido) {
            $detalles = "SELECT pd.*, 
                        CASE 
                            WHEN pd.tipo_producto = 'local' THEN pr.nombre
                            ELSE pd.datos_externos
                        END as nombre_producto,
                        pr.imagen
                        FROM pedido_detalles pd
                        LEFT JOIN productos pr ON pd.id_producto = pr.id_producto
                        WHERE pd.id_pedido = ?";
            
            $stmt = $db->prepare($detalles);
            $stmt->execute([$pedido['id_pedido']]);
            $pedido['detalles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return ['success' => true, 'pedidos' => $pedidos];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerPedidosEmpleado: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al obtener pedidos'];
    }
}

// Asignar pedido a empleado
function asignarPedido($db, $id_pedido, $id_empleado) {
    try {
        $db->beginTransaction();
        
        // Verificar que el pedido existe y está pagado
        $check = "SELECT p.id_pedido 
                  FROM pedidos p
                  INNER JOIN pagos pg ON p.id_pedido = pg.id_pedido
                  WHERE p.id_pedido = ? AND pg.estado = 'pagado'";
        $stmt = $db->prepare($check);
        $stmt->execute([$id_pedido]);
        
        if (!$stmt->fetch()) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Pedido no válido o no pagado'];
        }
        
        // Verificar si ya está asignado
        $checkAsignado = "SELECT id FROM pedido_empleado WHERE id_pedido = ?";
        $stmt = $db->prepare($checkAsignado);
        $stmt->execute([$id_pedido]);
        
        if ($stmt->fetch()) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Pedido ya asignado'];
        }
        
        // Asignar pedido
        $insert = "INSERT INTO pedido_empleado (id_pedido, id_empleado, estado) 
                   VALUES (?, ?, 'asignado')";
        $stmt = $db->prepare($insert);
        $stmt->execute([$id_pedido, $id_empleado]);
        
        // Actualizar estado del pedido
        $update = "UPDATE pedidos SET estado_entrega = 'asignado' WHERE id_pedido = ?";
        $stmt = $db->prepare($update);
        $stmt->execute([$id_pedido]);
        
        $db->commit();
        return ['success' => true, 'message' => 'Pedido asignado correctamente'];
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error en asignarPedido: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al asignar pedido'];
    }
}

// Marcar pedido como entregado
function marcarEntregado($db, $id_pedido, $id_cliente) {
    try {
        $db->beginTransaction();
        
        // Verificar que el pedido pertenece al cliente
        $check = "SELECT id_pedido FROM pedidos WHERE id_pedido = ? AND id_cliente = ?";
        $stmt = $db->prepare($check);
        $stmt->execute([$id_pedido, $id_cliente]);
        
        if (!$stmt->fetch()) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }
        
        // Actualizar estado del pedido
        $update = "UPDATE pedidos SET estado = 'entregado', estado_entrega = 'completado' 
                   WHERE id_pedido = ?";
        $stmt = $db->prepare($update);
        $stmt->execute([$id_pedido]);
        
        // Actualizar estado en pedido_empleado
        $updateEmpleado = "UPDATE pedido_empleado SET estado = 'completado' 
                          WHERE id_pedido = ?";
        $stmt = $db->prepare($updateEmpleado);
        $stmt->execute([$id_pedido]);
        
        $db->commit();
        return ['success' => true, 'message' => 'Pedido marcado como entregado'];
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error en marcarEntregado: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al marcar como entregado'];
    }
}

// Manejo de rutas
switch($action) {
    case 'cliente':
        if ($method === 'GET' && isset($_GET['id_cliente'])) {
            $result = obtenerPedidosCliente($db, $_GET['id_cliente']);
            echo json_encode($result);
        }
        break;
    
    case 'pagados':
        if ($method === 'GET') {
            $result = obtenerPedidosPagados($db);
            echo json_encode($result);
        }
        break;
    
    case 'empleado':
        if ($method === 'GET' && isset($_GET['id_empleado'])) {
            $result = obtenerPedidosEmpleado($db, $_GET['id_empleado']);
            echo json_encode($result);
        }
        break;
    
    case 'ubicacion':
        if ($method === 'GET' && isset($_GET['id_pedido'])) {
            try {
                $query = "SELECT direccion_entrega, latitud, longitud, referencia, nombre_receptor, telefono_receptor 
                          FROM ubicacion_entrega 
                          WHERE id_pedido = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_GET['id_pedido']]);
                $ubicacion = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ubicacion) {
                    echo json_encode(['success' => true, 'ubicacion' => $ubicacion]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Ubicación no encontrada']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error al obtener ubicación']);
            }
        }
        break;
    
    case 'asignar':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id_pedido']) || !isset($data['id_empleado'])) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                break;
            }
            
            $result = asignarPedido($db, $data['id_pedido'], $data['id_empleado']);
            echo json_encode($result);
        }
        break;
    
    case 'marcar_entregado':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id_pedido']) || !isset($data['id_cliente'])) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                break;
            }
            
            $result = marcarEntregado($db, $data['id_pedido'], $data['id_cliente']);
            echo json_encode($result);
        }
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>