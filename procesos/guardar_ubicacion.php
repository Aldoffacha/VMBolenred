<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

// Verificar sesión de manera más robusta
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['usuario']['id_cliente'])) {
    $user_id = $_SESSION['usuario']['id_cliente'];
} else {
    echo json_encode(['success' => false, 'message' => 'No autorizado - Sesión no válida']);
    exit;
}

$action = $_POST['action'] ?? '';
$id_pedido = (int) ($_POST['id_pedido'] ?? 0);
$id_cliente = (int) ($_POST['id_cliente'] ?? 0);

// DEBUG LOG
error_log("DEBUG guardar_ubicacion: user_id=$user_id, id_cliente=$id_cliente, id_pedido=$id_pedido, action=$action");

// Validar que el pedido pertenece al cliente
if ($id_cliente !== $user_id) {
    error_log("ERROR: Intento de acceso no autorizado. user_id=$user_id, id_cliente=$id_cliente");
    echo json_encode(['success' => false, 'message' => 'No autorizado - Cliente no coincide']);
    exit;
}

// Función para verificar si el pedido está pagado
function estaPagado($estado_pedido, $estado_pago) {
    $estado_pedido = strtolower(trim($estado_pedido ?? ''));
    $estado_pago = strtolower(trim($estado_pago ?? ''));
    
    return ($estado_pedido === 'pagado' || $estado_pago === 'pagado' || $estado_pago === 'confirmado');
}

if ($action === 'guardar') {
    try {
        $direccion_entrega = trim($_POST['direccion_entrega'] ?? '');
        $latitud = floatval($_POST['latitud'] ?? 0);
        $longitud = floatval($_POST['longitud'] ?? 0);
        $referencia = trim($_POST['referencia'] ?? '');
        $nombre_receptor = trim($_POST['nombre_receptor'] ?? '');
        $telefono_receptor = trim($_POST['telefono_receptor'] ?? '');
        
        // Validar datos requeridos
        if (empty($direccion_entrega) || $latitud === 0 || $longitud === 0) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
            exit;
        }
        
        // Validar que id_pedido sea válido
        if ($id_pedido <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
            exit;
        }
        
        // Verificar que el pedido existe y está pagado
        $stmt = $db->prepare("
            SELECT p.estado as estado_pedido, 
                   COALESCE(pg.estado, 'sin_pago') as estado_pago 
            FROM pedidos p
            LEFT JOIN pagos pg ON p.id_pedido = pg.id_pedido
            WHERE p.id_pedido = ? AND p.id_cliente = ?
        ");
        $stmt->execute([$id_pedido, $user_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resultado) {
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o no pertenece al cliente']);
            exit;
        }
        
        if (!estaPagado($resultado['estado_pedido'], $resultado['estado_pago'])) {
            echo json_encode([
                'success' => false, 
                'message' => 'El pedido debe estar pagado. Estado actual: ' . 
                           $resultado['estado_pedido'] . ' (pedido) / ' . 
                           $resultado['estado_pago'] . ' (pago)'
            ]);
            exit;
        }
        
        // Iniciar transacción para evitar inconsistencias
        $db->beginTransaction();
        
        // Verificar si ya existe una ubicación para este pedido específico
        $stmt = $db->prepare("SELECT id FROM ubicacion_entrega WHERE id_pedido = ?");
        $stmt->execute([$id_pedido]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Actualizar ubicación existente SOLO para este pedido específico
            $sql = "UPDATE ubicacion_entrega SET 
                    direccion_entrega = ?,
                    latitud = ?,
                    longitud = ?,
                    referencia = ?,
                    nombre_receptor = ?,
                    telefono_receptor = ?,
                    fecha_creacion = CURRENT_TIMESTAMP
                    WHERE id_pedido = ? AND id_cliente = ?";
            
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $direccion_entrega,
                $latitud,
                $longitud,
                $referencia,
                $nombre_receptor,
                $telefono_receptor,
                $id_pedido,
                $user_id  // Añadido para mayor seguridad
            ]);
            
            $tipo = 'actualizada';
        } else {
            // Insertar nueva ubicación SOLO para este pedido
            $sql = "INSERT INTO ubicacion_entrega 
                    (id_pedido, id_cliente, direccion_entrega, latitud, longitud, referencia, nombre_receptor, telefono_receptor, fecha_creacion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $id_pedido,
                $user_id,
                $direccion_entrega,
                $latitud,
                $longitud,
                $referencia,
                $nombre_receptor,
                $telefono_receptor
            ]);
            
            $tipo = 'guardada';
        }
        
        if ($success) {
            // Actualizar estado del pedido
            $stmt = $db->prepare("UPDATE pedidos SET estado_entrega = 'con_ubicacion' WHERE id_pedido = ? AND id_cliente = ?");
            $stmt->execute([$id_pedido, $user_id]);
            
            $db->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => "Ubicación $tipo correctamente",
                'debug' => [
                    'id_pedido' => $id_pedido,
                    'id_cliente' => $user_id,
                    'lat' => $latitud,
                    'lng' => $longitud
                ]
            ]);
        } else {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos']);
        }
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error guardando ubicación: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al guardar la ubicación: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}