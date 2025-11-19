<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido no válido']);
    exit;
}

$pedido_id = intval($_GET['id']);
$db = (new Database())->getConnection();

try {
    // Obtener información del pedido y cliente
    $stmt = $db->prepare("
        SELECT p.*, c.nombre as cliente_nombre, c.correo as cliente_email, c.direccion, c.telefono
        FROM pedidos p 
        JOIN clientes c ON p.id_cliente = c.id_cliente 
        WHERE p.id_pedido = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    // Obtener productos locales del pedido
    $stmt = $db->prepare("
        SELECT 
            pd.*,
            p.nombre as producto_nombre,
            p.descripcion as producto_descripcion,
            p.imagen as producto_imagen,
            'local' as tipo_producto
        FROM pedido_detalles pd
        JOIN productos p ON pd.id_producto = p.id_producto
        WHERE pd.id_pedido = ?
    ");
    $stmt->execute([$pedido_id]);
    $productos_locales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener productos externos del carrito_externo (que fueron procesados en este pedido)
    $stmt = $db->prepare("
        SELECT 
            ce.*,
            'externo' as tipo_producto
        FROM carrito_externo ce
        WHERE ce.id_cliente = ? AND ce.estado = 'procesado'
        AND EXISTS (
            SELECT 1 FROM pedido_detalles pd 
            WHERE pd.id_pedido = ? 
            AND pd.precio = (
                SELECT (ce.precio + 
                       GREATEST(15, ce.peso * 3) + 
                       (ce.precio * 0.02) + 
                       (ce.precio * CASE 
                           WHEN ce.categoria = 'electronico' THEN 0.30
                           WHEN ce.categoria = 'ropa' THEN 0.20
                           WHEN ce.categoria = 'hogar' THEN 0.15
                           WHEN ce.categoria = 'deportes' THEN 0.25
                           ELSE 0.18
                       END) + 25)
            )
            AND pd.cantidad = ce.cantidad
        )
    ");
    $stmt->execute([$pedido['id_cliente'], $pedido_id]);
    $productos_externos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Función para calcular costo de importación (consistente con el frontend)
    function calcularCostoImportacion($precio, $peso, $categoria) {
        $impuestos = [
            'electronico' => 0.30, 'ropa' => 0.20, 'hogar' => 0.15, 
            'deportes' => 0.25, 'otros' => 0.18
        ];
        
        $impuesto = $impuestos[$categoria] ?? 0.18;
        $flete_maritimo = max(15, $peso * 3);
        $seguro = $precio * 0.02;
        $aduana = $precio * $impuesto;
        $costo_almacen = 25; 
        
        $costo_total = $precio + $flete_maritimo + $seguro + $aduana + $costo_almacen;
        
        return [
            'total' => $costo_total,
            'desglose' => [
                'producto' => $precio, 
                'flete' => $flete_maritimo, 
                'seguro' => $seguro, 
                'aduana' => $aduana, 
                'almacen' => $costo_almacen
            ]
        ];
    }

    // Combinar y formatear productos
    $productos_combinados = [];

    // Procesar productos locales
    foreach ($productos_locales as $producto) {
        $costo_importacion = calcularCostoImportacion($producto['precio'], 0.5, 'electronico');
        $productos_combinados[] = [
            'tipo' => 'local',
            'nombre' => $producto['producto_nombre'],
            'descripcion' => $producto['producto_descripcion'],
            'cantidad' => $producto['cantidad'],
            'precio_base' => $producto['precio'],
            'precio_final' => $producto['precio'],
            'precio_importacion' => $costo_importacion['total'],
            'imagen' => $producto['producto_imagen'],
            'costo_importacion' => $costo_importacion
        ];
    }

    // Procesar productos externos
    foreach ($productos_externos as $producto) {
        $costo_importacion = calcularCostoImportacion($producto['precio'], $producto['peso'], $producto['categoria']);
        $productos_combinados[] = [
            'tipo' => 'externo',
            'nombre' => $producto['nombre'],
            'plataforma' => $producto['plataforma'],
            'cantidad' => $producto['cantidad'],
            'precio_base' => $producto['precio'],
            'precio_final' => $costo_importacion['total'],
            'peso' => $producto['peso'],
            'categoria' => $producto['categoria'],
            'url' => $producto['url'],
            'costo_importacion' => $costo_importacion,
            'badge_class' => $producto['plataforma'] === 'amazon' ? 'bg-warning text-dark' : 'bg-info'
        ];
    }

    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'productos' => $productos_combinados,
        'estadisticas' => [
            'total_locales' => count($productos_locales),
            'total_externos' => count($productos_externos),
            'total_productos' => count($productos_combinados)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en obtener_detalle_pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>