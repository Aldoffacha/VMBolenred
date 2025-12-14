<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

Auth::checkAuth('empleado');
$db = (new Database())->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$empleado_id = $_SESSION['user_id'] ?? 0;

switch($action) {

    case 'pedidos_pendientes':
        try {
            $stmt = $db->prepare("
                SELECT 
                    p.id_pedido,
                    p.total,
                    p.fecha,
                    p.estado,
                    p.estado_entrega,
                    c.nombre AS cliente_nombre,
                    c.telefono AS cliente_telefono,
                    ue.direccion_entrega,
                    ue.latitud,
                    ue.longitud,
                    ue.referencia,
                    ue.nombre_receptor,
                    ue.telefono_receptor
                FROM pedidos p
                INNER JOIN clientes c ON p.id_cliente = c.id_cliente
                INNER JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
                WHERE p.estado = 'pagado'
                  AND p.estado_entrega = 'sin_asignar'
                  AND NOT EXISTS (
                        SELECT 1 FROM pedido_empleado pe 
                        WHERE pe.id_pedido = p.id_pedido 
                        AND pe.estado IN ('aceptado', 'en_camino')
                    )
                ORDER BY p.fecha ASC
            ");
            $stmt->execute();
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'pedidos_disponibles' => $pedidos
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener pedidos: ' . $e->getMessage()
            ]);
        }
        break;

    case 'aceptar_pedido':
        $id_pedido = (int) ($_POST['id_pedido'] ?? 0);

        if (!$id_pedido) {
            echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
            exit;
        }

        // Verificar que nadie más haya tomado el pedido
        $stmt = $db->prepare("SELECT * FROM pedido_empleado WHERE id_pedido = ? AND estado IN ('aceptado', 'en_camino')");
        $stmt->execute([$id_pedido]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'El pedido ya fue tomado por otro empleado']);
            exit;
        }

        // Registrar asignación
        $stmt = $db->prepare("INSERT INTO pedido_empleado (id_pedido, id_empleado, estado, fecha_asignacion) VALUES (?, ?, 'aceptado', NOW())");
        $success = $stmt->execute([$id_pedido, $empleado_id]);

        if ($success) {
            // Actualizar estado del pedido
            $stmt = $db->prepare("UPDATE pedidos SET estado_entrega = 'aceptado' WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);

            echo json_encode(['success' => true, 'message' => 'Pedido tomado con éxito']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al tomar el pedido']);
        }

        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
