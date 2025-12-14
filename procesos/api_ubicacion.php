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

// Guardar ubicación de entrega para un pedido
function guardarUbicacionEntrega($db, $data) {
    try {
        // Validar que el pedido existe y pertenece al cliente
        $query = "SELECT id_pedido, estado FROM pedidos WHERE id_pedido = ? AND id_cliente = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$data['id_pedido'], $data['id_cliente']]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }
        
        // Verificar si ya existe ubicación para este pedido
        $check = "SELECT id FROM ubicacion_entrega WHERE id_pedido = ?";
        $stmt = $db->prepare($check);
        $stmt->execute([$data['id_pedido']]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existe) {
            // Actualizar ubicación existente
            $update = "UPDATE ubicacion_entrega SET 
                      direccion_entrega = ?,
                      latitud = ?,
                      longitud = ?,
                      referencia = ?,
                      nombre_receptor = ?,
                      telefono_receptor = ?
                      WHERE id_pedido = ?";
            
            $stmt = $db->prepare($update);
            $stmt->execute([
                $data['direccion_entrega'],
                $data['latitud'],
                $data['longitud'],
                $data['referencia'] ?? null,
                $data['nombre_receptor'] ?? null,
                $data['telefono_receptor'] ?? null,
                $data['id_pedido']
            ]);
        } else {
            // Insertar nueva ubicación
            $insert = "INSERT INTO ubicacion_entrega 
                      (id_pedido, id_cliente, direccion_entrega, latitud, longitud, referencia, nombre_receptor, telefono_receptor)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($insert);
            $stmt->execute([
                $data['id_pedido'],
                $data['id_cliente'],
                $data['direccion_entrega'],
                $data['latitud'],
                $data['longitud'],
                $data['referencia'] ?? null,
                $data['nombre_receptor'] ?? null,
                $data['telefono_receptor'] ?? null
            ]);
        }
        
        // Actualizar estado del pedido a 'listo para entrega'
        $updatePedido = "UPDATE pedidos SET estado_entrega = 'pendiente_asignacion' WHERE id_pedido = ?";
        $stmt = $db->prepare($updatePedido);
        $stmt->execute([$data['id_pedido']]);
        
        return ['success' => true, 'message' => 'Ubicación guardada correctamente'];
        
    } catch (PDOException $e) {
        error_log("Error en guardarUbicacionEntrega: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al guardar ubicación'];
    }
}

// Obtener ubicación de entrega de un pedido
function obtenerUbicacionEntrega($db, $id_pedido, $id_cliente) {
    try {
        $query = "SELECT ue.*, p.estado, p.estado_entrega 
                  FROM ubicacion_entrega ue
                  INNER JOIN pedidos p ON ue.id_pedido = p.id_pedido
                  WHERE ue.id_pedido = ? AND ue.id_cliente = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id_pedido, $id_cliente]);
        $ubicacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ubicacion) {
            return ['success' => false, 'message' => 'Ubicación no encontrada'];
        }
        
        return ['success' => true, 'ubicacion' => $ubicacion];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerUbicacionEntrega: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al obtener ubicación'];
    }
}

// Manejo de rutas
switch($action) {
    case 'guardar':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['id_pedido', 'id_cliente', 'direccion_entrega', 'latitud', 'longitud'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    echo json_encode(['success' => false, 'message' => "Falta el campo: $field"]);
                    exit;
                }
            }
            
            $result = guardarUbicacionEntrega($db, $data);
            echo json_encode($result);
        }
        break;
    
    case 'obtener':
        if ($method === 'GET') {
            if (!isset($_GET['id_pedido']) || !isset($_GET['id_cliente'])) {
                echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
                break;
            }
            
            $result = obtenerUbicacionEntrega($db, $_GET['id_pedido'], $_GET['id_cliente']);
            echo json_encode($result);
        }
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>