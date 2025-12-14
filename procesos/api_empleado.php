<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

// Iniciar entrega
function iniciarEntrega($db, $data) {
    try {
        $db->beginTransaction();
        
        // Verificar que el pedido está asignado al empleado
        $check = "SELECT id FROM pedido_empleado 
                  WHERE id_pedido = ? AND id_empleado = ? AND estado = 'asignado'";
        $stmt = $db->prepare($check);
        $stmt->execute([$data['id_pedido'], $data['id_empleado']]);
        
        if (!$stmt->fetch()) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Pedido no asignado a este empleado'];
        }
        
        // Actualizar estado del pedido
        $update = "UPDATE pedidos SET estado_entrega = 'en_camino' WHERE id_pedido = ?";
        $stmt = $db->prepare($update);
        $stmt->execute([$data['id_pedido']]);
        
        // Actualizar estado en pedido_empleado
        $updateEmpleado = "UPDATE pedido_empleado SET estado = 'en_camino' WHERE id_pedido = ?";
        $stmt = $db->prepare($updateEmpleado);
        $stmt->execute([$data['id_pedido']]);
        
        // Registrar ubicación inicial
        $insert = "INSERT INTO ubicacion_empleado 
                   (id_empleado, id_pedido, latitud, longitud, activo) 
                   VALUES (?, ?, ?, ?, true)";
        $stmt = $db->prepare($insert);
        $stmt->execute([
            $data['id_empleado'],
            $data['id_pedido'],
            $data['latitud'],
            $data['longitud']
        ]);
        
        $db->commit();
        return ['success' => true, 'message' => 'Entrega iniciada'];
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error en iniciarEntrega: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al iniciar entrega'];
    }
}

// Actualizar ubicación del empleado
function actualizarUbicacion($db, $data) {
    try {
        // Desactivar ubicaciones anteriores
        $deactivate = "UPDATE ubicacion_empleado 
                      SET activo = false 
                      WHERE id_empleado = ? AND id_pedido = ?";
        $stmt = $db->prepare($deactivate);
        $stmt->execute([$data['id_empleado'], $data['id_pedido']]);
        
        // Insertar nueva ubicación
        $insert = "INSERT INTO ubicacion_empleado 
                   (id_empleado, id_pedido, latitud, longitud, activo, velocidad, direccion_movimiento, precision_gps, bateria) 
                   VALUES (?, ?, ?, ?, true, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($insert);
        $stmt->execute([
            $data['id_empleado'],
            $data['id_pedido'],
            $data['latitud'],
            $data['longitud'],
            $data['velocidad'] ?? 0,
            $data['direccion_movimiento'] ?? null,
            $data['precision_gps'] ?? null,
            $data['bateria'] ?? null
        ]);
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Error en actualizarUbicacion: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar ubicación'];
    }
}

// Finalizar entrega
function finalizarEntrega($db, $data) {
    try {
        $db->beginTransaction();
        
        // Verificar que el pedido está en camino
        $check = "SELECT id FROM pedido_empleado 
                  WHERE id_pedido = ? AND id_empleado = ? AND estado = 'en_camino'";
        $stmt = $db->prepare($check);
        $stmt->execute([$data['id_pedido'], $data['id_empleado']]);
        
        if (!$stmt->fetch()) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Pedido no está en camino'];
        }
        
        // Actualizar estado del pedido a 'en_destino' (esperando confirmación del cliente)
        $update = "UPDATE pedidos SET estado_entrega = 'en_destino' WHERE id_pedido = ?";
        $stmt = $db->prepare($update);
        $stmt->execute([$data['id_pedido']]);
        
        // Actualizar estado en pedido_empleado
        $updateEmpleado = "UPDATE pedido_empleado SET estado = 'en_destino' WHERE id_pedido = ?";
        $stmt = $db->prepare($updateEmpleado);
        $stmt->execute([$data['id_pedido']]);
        
        // Desactivar ubicación activa
        $deactivate = "UPDATE ubicacion_empleado 
                      SET activo = false 
                      WHERE id_empleado = ? AND id_pedido = ?";
        $stmt = $db->prepare($deactivate);
        $stmt->execute([$data['id_empleado'], $data['id_pedido']]);
        
        $db->commit();
        return ['success' => true, 'message' => 'Entrega finalizada, esperando confirmación del cliente'];
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error en finalizarEntrega: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al finalizar entrega'];
    }
}

// Obtener ubicación activa del empleado para un pedido
function obtenerUbicacionPedido($db, $id_pedido) {
    try {
        $query = "SELECT ue.latitud, ue.longitud, ue.fecha, ue.velocidad, 
                  e.nombre as nombre_empleado
                  FROM ubicacion_empleado ue
                  INNER JOIN empleados e ON ue.id_empleado = e.id_empleado
                  WHERE ue.id_pedido = ? AND ue.activo = true
                  ORDER BY ue.fecha DESC
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id_pedido]);
        $ubicacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ubicacion) {
            return ['success' => false, 'message' => 'No hay ubicación activa'];
        }
        
        return ['success' => true, 'ubicacion' => $ubicacion];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerUbicacionPedido: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al obtener ubicación'];
    }
}

// Manejo de rutas
switch($action) {
    case 'iniciar_entrega':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['id_pedido', 'id_empleado', 'latitud', 'longitud'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    echo json_encode(['success' => false, 'message' => "Falta el campo: $field"]);
                    exit;
                }
            }
            
            $result = iniciarEntrega($db, $data);
            echo json_encode($result);
        }
        break;
    
    case 'actualizar_ubicacion':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['id_pedido', 'id_empleado', 'latitud', 'longitud'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    echo json_encode(['success' => false, 'message' => "Falta el campo: $field"]);
                    exit;
                }
            }
            
            $result = actualizarUbicacion($db, $data);
            echo json_encode($result);
        }
        break;
    
    case 'finalizar_entrega':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id_pedido']) || !isset($data['id_empleado'])) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                break;
            }
            
            $result = finalizarEntrega($db, $data);
            echo json_encode($result);
        }
        break;
    
    case 'marcar_entregado':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id_pedido']) || !isset($data['id_empleado'])) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                break;
            }
            
            // Alias para finalizar_entrega (mismo comportamiento)
            $result = finalizarEntrega($db, $data);
            echo json_encode($result);
        }
        break;
    
    case 'ubicacion_pedido':
        if ($method === 'GET' && isset($_GET['id_pedido'])) {
            $result = obtenerUbicacionPedido($db, $_GET['id_pedido']);
            echo json_encode($result);
        }
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>