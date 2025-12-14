<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
$data = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';
require_once '../includes/config.php';
require_once '../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$db = (new Database())->getConnection();
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id_empleado']) || !isset($input['id_pedido']) || !isset($input['estado'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

$id_empleado = (int) $input['id_empleado'];
$id_pedido = (int) $input['id_pedido'];
$estado = $input['estado']; // 'en_camino', 'entregado', 'problema', 'cancelado'

// Estados válidos
$estados_validos = ['en_camino', 'entregado', 'problema', 'cancelado'];

if (!in_array($estado, $estados_validos)) {
    echo json_encode(['success' => false, 'message' => 'Estado no válido']);
    exit;
}

try {
    $db->beginTransaction();
    
    // 1. Verificar que el pedido es de este empleado
    $stmt = $db->prepare("
        SELECT * FROM pedido_empleado 
        WHERE id_pedido = ? AND id_empleado = ? AND estado = 'aceptado'
        FOR UPDATE
    ");
    $stmt->execute([$id_pedido, $id_empleado]);
    $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$asignacion) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'No tienes este pedido asignado']);
        exit;
    }
    
    // 2. Actualizar pedido_empleado
    $stmt = $db->prepare("
        UPDATE pedido_empleado 
        SET estado = ?, fecha_actualizacion = NOW()
        WHERE id_pedido = ? AND id_empleado = ?
    ");
    $stmt->execute([$estado, $id_pedido, $id_empleado]);
    
    // 3. Actualizar estado_entrega en pedidos
    $nuevo_estado_pedido = 'en_camino';
    
    if ($estado === 'en_camino') {
        $nuevo_estado_pedido = 'en_camino';
        $mensaje_cliente = 'El empleado está en camino a tu ubicación';
    } elseif ($estado === 'entregado') {
        $nuevo_estado_pedido = 'entregado';
        $mensaje_cliente = '¡Tu pedido ha sido entregado exitosamente!';
        
        // Actualizar estado principal del pedido también
        $stmt2 = $db->prepare("UPDATE pedidos SET estado = 'entregado' WHERE id_pedido = ?");
        $stmt2->execute([$id_pedido]);
    } elseif ($estado === 'problema') {
        $nuevo_estado_pedido = 'problema';
        $mensaje_cliente = 'Hay un problema con la entrega de tu pedido';
    } elseif ($estado === 'cancelado') {
        $nuevo_estado_pedido = 'con_ubicacion'; // Vuelve a estar disponible
        $mensaje_cliente = 'La entrega de tu pedido fue cancelada por el empleado';
        
        // Liberar el pedido para otros empleados
        $stmt2 = $db->prepare("DELETE FROM pedido_empleado WHERE id_pedido = ? AND id_empleado = ?");
        $stmt2->execute([$id_pedido, $id_empleado]);
    }
    
    $stmt = $db->prepare("
        UPDATE pedidos 
        SET estado_entrega = ?
        WHERE id_pedido = ?
    ");
    $stmt->execute([$nuevo_estado_pedido, $id_pedido]);
    
    // 4. Notificar al cliente
    $stmt = $db->prepare("
        SELECT id_cliente FROM pedidos WHERE id_pedido = ?
    ");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pedido) {
        $stmt = $db->prepare("
            INSERT INTO notificaciones 
            (id_usuario, tipo_usuario, titulo, mensaje, tipo, enlace, metadata)
            VALUES (?, 'cliente', 'Estado de Entrega Actualizado', ?, 'info', 'detalle_pedido.php?id_pedido={$id_pedido}', ?)
        ");
        $stmt->execute([
            $pedido['id_cliente'],
            $mensaje_cliente,
            json_encode(['pedido_id' => $id_pedido, 'estado' => $estado])
        ]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente',
        'estado' => $estado,
        'mensaje_cliente' => $mensaje_cliente
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error actualizar estado: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error al actualizar estado: ' . $e->getMessage()
    ]);
}