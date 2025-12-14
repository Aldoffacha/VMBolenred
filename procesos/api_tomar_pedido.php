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

    if (!isset($input['id_empleado']) || !isset($input['id_pedido'])) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
        exit;
    }

    $id_empleado = (int) $input['id_empleado'];
    $id_pedido = (int) $input['id_pedido'];

    try {
        // INICIAR TRANSACCIÓN - IMPORTANTE para evitar que dos empleados tomen el mismo pedido
        $db->beginTransaction();
        
        // 1. Verificar que el pedido sigue disponible (LOCK para evitar race conditions)
        $stmt = $db->prepare("
            SELECT p.* 
            FROM pedidos p
            INNER JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
            WHERE p.id_pedido = ?
                AND p.estado = 'pagado'
                AND p.estado_entrega = 'con_ubicacion'
                AND NOT EXISTS (
                    SELECT 1 FROM pedido_empleado 
                    WHERE id_pedido = p.id_pedido 
                    AND estado IN ('aceptado', 'asignado', 'en_camino')
                )
            FOR UPDATE  -- ¡ESTO BLOQUEA EL REGISTRO! Evita que otro empleado lo tome al mismo tiempo
        ");
        $stmt->execute([$id_pedido]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            $db->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'El pedido ya fue tomado por otro empleado o no está disponible'
            ]);
            exit;
        }
        
        // 2. Registrar que este empleado tomó el pedido
        $stmt = $db->prepare("
            INSERT INTO pedido_empleado (id_pedido, id_empleado, estado, fecha_asignacion)
            VALUES (?, ?, 'aceptado', NOW())
        ");
        $stmt->execute([$id_pedido, $id_empleado]);
        
        // 3. Actualizar estado del pedido
        $stmt = $db->prepare("
            UPDATE pedidos 
            SET estado_entrega = 'aceptado' 
            WHERE id_pedido = ?
        ");
        $stmt->execute([$id_pedido]);
        
        // 4. Registrar en auditoría
        $admin_id = 0; // O el ID del sistema
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt = $db->prepare("
            INSERT INTO auditoria 
            (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, tipo_usuario, ip_address)
            VALUES ('pedido_empleado', ?, 'INSERT', ?, ?, 'empleado', ?)
        ");
        $stmt->execute([
            $id_pedido,
            json_encode([
                'id_empleado' => $id_empleado, 
                'id_pedido' => $id_pedido, 
                'estado' => 'aceptado',
                'accion' => 'empleado_tomo_pedido'
            ]),
            $id_empleado,
            $ip
        ]);
        
        // 5. Crear notificación para el cliente
        $stmt = $db->prepare("
            INSERT INTO notificaciones 
            (id_usuario, tipo_usuario, titulo, mensaje, tipo, enlace, metadata)
            VALUES (?, 'cliente', 'Pedido Aceptado', 'Un empleado ha aceptado tu pedido #{$id_pedido} y se dirigirá a tu ubicación', 'success', 'detalle_pedido.php?id_pedido={$id_pedido}', ?)
        ");
        $stmt->execute([
            $pedido['id_cliente'],
            json_encode(['pedido_id' => $id_pedido, 'empleado_id' => $id_empleado])
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '¡Pedido tomado con éxito! Ahora dirígete a la ubicación del cliente.',
            'pedido_id' => $id_pedido,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al tomar pedido: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error al tomar el pedido: ' . $e->getMessage()
        ]);
    }