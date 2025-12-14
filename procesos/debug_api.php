<?php
// Archivo: procesos/debug_api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../includes/config.php';
require_once '../includes/database.php';

$db = (new Database())->getConnection();

// Función para depurar
function logDebug($message) {
    error_log("DEBUG: " . $message);
    echo "<!-- " . htmlspecialchars($message) . " -->\n";
}

try {
    echo json_encode([
        'success' => true,
        'server_info' => [
            'time' => date('Y-m-d H:i:s'),
            'php_version' => phpversion(),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ],
        
        // 1. PEDIDOS PAGADOS
        'pedidos_pagados' => getPedidosPagados($db),
        
        // 2. PEDIDOS CON UBICACIÓN
        'pedidos_con_ubicacion' => getPedidosConUbicacion($db),
        
        // 3. PEDIDOS DISPONIBLES (sin asignar)
        'pedidos_disponibles' => getPedidosDisponibles($db),
        
        // 4. ASIGNACIONES A EMPLEADOS
        'asignaciones' => getAsignaciones($db),
        
        // 5. TABLAS RELEVANTES
        'counts' => [
            'total_pedidos' => getCount($db, 'pedidos'),
            'pedidos_pagados_count' => getCount($db, 'pedidos', "estado = 'pagado'"),
            'ubicaciones_count' => getCount($db, 'ubicacion_entrega'),
            'empleados_count' => getCount($db, 'empleados'),
            'asignaciones_count' => getCount($db, 'pedido_empleado')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

// FUNCIONES AUXILIARES
function getPedidosPagados($db) {
    $sql = "
        SELECT 
            p.id_pedido,
            p.estado,
            p.estado_entrega,
            p.total,
            p.fecha,
            c.nombre as cliente_nombre
        FROM pedidos p
        LEFT JOIN clientes c ON p.id_cliente = c.id_cliente
        WHERE p.estado = 'pagado'
        ORDER BY p.id_pedido DESC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPedidosConUbicacion($db) {
    $sql = "
        SELECT 
            p.id_pedido,
            p.estado,
            p.estado_entrega,
            ue.direccion_entrega,
            ue.latitud,
            ue.longitud,
            ue.fecha_creacion
        FROM pedidos p
        INNER JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
        WHERE p.estado = 'pagado'
            AND ue.direccion_entrega IS NOT NULL
        ORDER BY ue.fecha_creacion DESC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPedidosDisponibles($db) {
    $sql = "
        SELECT 
            p.id_pedido,
            p.estado,
            p.estado_entrega,
            p.total,
            ue.direccion_entrega,
            COUNT(pe.id) as asignaciones_count
        FROM pedidos p
        LEFT JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
        LEFT JOIN pedido_empleado pe ON p.id_pedido = pe.id_pedido 
            AND pe.estado IN ('aceptado', 'asignado', 'en_camino')
        WHERE p.estado = 'pagado'
            AND (p.estado_entrega = 'con_ubicacion' OR p.estado_entrega = 'sin_asignar')
            AND ue.direccion_entrega IS NOT NULL
        GROUP BY p.id_pedido
        HAVING asignaciones_count = 0
        ORDER BY p.fecha ASC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAsignaciones($db) {
    $sql = "
        SELECT 
            pe.id_pedido,
            pe.id_empleado,
            pe.estado,
            pe.fecha_asignacion,
            p.estado_entrega,
            e.nombre as empleado_nombre
        FROM pedido_empleado pe
        LEFT JOIN pedidos p ON pe.id_pedido = p.id_pedido
        LEFT JOIN empleados e ON pe.id_empleado = e.id_empleado
        ORDER BY pe.fecha_asignacion DESC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCount($db, $table, $condition = '1=1') {
    $sql = "SELECT COUNT(*) as total FROM $table WHERE $condition";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}
?>