<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] != 'cliente') {
    echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión como cliente']);
    exit;
}

// Obtener datos
$id_cliente = $_SESSION['user_id'];
$id_producto = $_POST['id_producto'] ?? null;
$plataforma = $_POST['plataforma'] ?? 'local';
$cantidad = intval($_POST['cantidad'] ?? 1);

if (!$id_producto || $cantidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$db = (new Database())->getConnection();

try {
    // Si es producto externo (Amazon o eBay)
    if ($plataforma !== 'local') {
        // Productos externos - usar carrito_externo
        // Obtener datos del producto externo basado en el id
        
        // Primero buscar en productos_exterior
        $stmt_ext = $db->prepare("SELECT * FROM productos_exterior WHERE id_producto_exterior = ?");
        $stmt_ext->execute([intval(str_replace('ext_', '', $id_producto))]);
        $producto_bd = $stmt_ext->fetch(PDO::FETCH_ASSOC);
        
        if ($producto_bd) {
            // Usar datos de la BD
            $producto = $producto_bd;
            $id_producto_real = 'ext_' . $producto_bd['id_producto_exterior'];
        } else {
            // Usar datos hardcodeados
            $productos_externos = [
                'amz001' => ['nombre' => 'Razer DeathAdder Essential - Mouse Gaming', 'precio' => 29.99, 'categoria' => 'electronico', 'peso' => 0.3],
                'amz002' => ['nombre' => 'Sony WH-1000XM4 - Audífonos Inalámbricos', 'precio' => 348.00, 'categoria' => 'electronico', 'peso' => 0.5],
                'eby001' => ['nombre' => 'Logitech G Pro X - Headset Gaming', 'precio' => 89.99, 'categoria' => 'electronico', 'peso' => 0.4],
                'eby002' => ['nombre' => 'SteelSeries Apex Pro - Teclado Mecánico', 'precio' => 179.99, 'categoria' => 'electronico', 'peso' => 0.8],
            ];
            
            if (!isset($productos_externos[$id_producto])) {
                echo json_encode(['success' => false, 'message' => 'Producto externo no encontrado']);
                exit;
            }
            
            $producto = $productos_externos[$id_producto];
            $id_producto_real = $id_producto;
        }
        
        // Verificar si ya existe en carrito_externo
        $stmt = $db->prepare("SELECT * FROM carrito_externo WHERE id_cliente = ? AND id_producto_externo = ? AND estado = 'pendiente'");
        $stmt->execute([$id_cliente, $id_producto_real]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existe) {
            // Actualizar cantidad
            $nueva_cantidad = $existe['cantidad'] + $cantidad;
            $stmt = $db->prepare("UPDATE carrito_externo SET cantidad = ? WHERE id_carrito_externo = ?");
            $stmt->execute([$nueva_cantidad, $existe['id_carrito_externo']]);
            $mensaje = "Cantidad actualizada en el carrito";
        } else {
            // Insertar nuevo registro
            $stmt = $db->prepare(
                "INSERT INTO carrito_externo 
                (id_cliente, id_producto_externo, nombre, precio, peso, categoria, plataforma, url, cantidad, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')"
            );
            $stmt->execute([
                $id_cliente,
                $id_producto_real,
                $producto['nombre'],
                $producto['precio'],
                $producto['peso'] ?? 0.5,
                $producto['categoria'],
                $plataforma,
                $producto['enlace'] ?? 'https://' . ($plataforma === 'amazon' ? 'amazon.com' : 'ebay.com'),
                $cantidad
            ]);
            $mensaje = "Producto agregado al carrito";
        }
    } else {
        // Producto local - usar carrito
        $id_producto_local = intval($id_producto);
        
        // Verificar si existe en carrito
        $stmt = $db->prepare("SELECT * FROM carrito WHERE id_cliente = ? AND id_producto = ?");
        $stmt->execute([$id_cliente, $id_producto_local]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existe) {
            // Actualizar cantidad
            $nueva_cantidad = $existe['cantidad'] + $cantidad;
            $stmt = $db->prepare("UPDATE carrito SET cantidad = ? WHERE id_carrito = ?");
            $stmt->execute([$nueva_cantidad, $existe['id_carrito']]);
            $mensaje = "Cantidad actualizada en el carrito";
        } else {
            // Insertar nuevo registro
            $stmt = $db->prepare("INSERT INTO carrito (id_cliente, id_producto, cantidad) VALUES (?, ?, ?)");
            $stmt->execute([$id_cliente, $id_producto_local, $cantidad]);
            $mensaje = "Producto agregado al carrito";
        }
    }
    
    echo json_encode(['success' => true, 'message' => $mensaje]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al agregar al carrito: ' . $e->getMessage()]);
}
?>