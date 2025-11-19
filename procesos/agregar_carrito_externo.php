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

$id_cliente = $_SESSION['user_id'];
$db = (new Database())->getConnection();

try {
    // Verificar qué tipo de producto externo estamos agregando
    if (isset($_POST['id_producto_externo'])) {
        // Caso 1: Producto externo existente (del carrusel)
        $id_producto_externo = $_POST['id_producto_externo'];
        $plataforma = $_POST['plataforma'] ?? 'amazon';
        
        // Determinar si es un ID numérico (real) o string (simulado)
        $es_id_numerico = is_numeric($id_producto_externo);
        
        if ($es_id_numerico) {
            // Es un producto externo real de la base de datos
            $id_producto_externo = intval($id_producto_externo);
            
            // Verificar si el producto externo existe
            $stmt = $db->prepare("SELECT * FROM productos_externos WHERE id_producto_externo = ? AND id_cliente = ?");
            $stmt->execute([$id_producto_externo, $id_cliente]);
            $producto_externo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$producto_externo) {
                echo json_encode(['success' => false, 'message' => 'Producto externo no encontrado']);
                exit;
            }
            
            $nombre = $producto_externo['nombre'];
            $precio = $producto_externo['precio'];
            $peso = $producto_externo['peso'] ?? 0.5;
            $categoria = $producto_externo['categoria'];
            $url = $producto_externo['enlace'];
            
        } else {
            // Es un producto simulado - obtener datos del formulario o usar valores por defecto
            $nombre = $_POST['nombre'] ?? 'Producto Externo';
            $precio = floatval($_POST['precio'] ?? 0);
            $peso = floatval($_POST['peso'] ?? 0.5);
            $categoria = $_POST['categoria'] ?? 'otros';
            $url = $_POST['url'] ?? '';
            $plataforma = $_POST['plataforma'] ?? 'amazon';
        }
        
        // Verificar si ya está en el carrito externo
        $stmt = $db->prepare("SELECT * FROM carrito_externo WHERE id_cliente = ? AND id_producto_externo = ?");
        $stmt->execute([$id_cliente, $id_producto_externo]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existe) {
            // Actualizar cantidad
            $nueva_cantidad = $existe['cantidad'] + 1;
            $stmt = $db->prepare("UPDATE carrito_externo SET cantidad = ? WHERE id_carrito_externo = ?");
            $stmt->execute([$nueva_cantidad, $existe['id_carrito_externo']]);
            $mensaje = "Cantidad actualizada en el carrito";
        } else {
            // Insertar nuevo registro en carrito_externo
            $stmt = $db->prepare("INSERT INTO carrito_externo (id_cliente, id_producto_externo, nombre, precio, peso, categoria, plataforma, url, cantidad, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'pendiente')");
            $stmt->execute([$id_cliente, $id_producto_externo, $nombre, $precio, $peso, $categoria, $plataforma, $url]);
            $mensaje = "Producto externo agregado al carrito";
        }
        
    } else if (isset($_POST['url'])) {
        // Caso 2: Nuevo producto externo (desde el modal de agregar)
        $url = $_POST['url'];
        $precio = floatval($_POST['precio']);
        $peso = floatval($_POST['peso'] ?? 0.5);
        $categoria = $_POST['categoria'] ?? 'otros';
        $plataforma = $_POST['plataforma'] ?? (strpos($url, 'amazon') !== false ? 'amazon' : 'ebay');
        $nombre = $_POST['nombre'] ?? 'Producto Externo';
        
        // Generar un ID único para productos simulados
        $id_producto_externo = 'ext_' . uniqid();
        
        // Insertar directamente en carrito_externo (sin pasar por productos_externos)
        $stmt = $db->prepare("INSERT INTO carrito_externo (id_cliente, id_producto_externo, nombre, precio, peso, categoria, plataforma, url, cantidad, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'pendiente')");
        $stmt->execute([$id_cliente, $id_producto_externo, $nombre, $precio, $peso, $categoria, $plataforma, $url]);
        
        $mensaje = "Producto externo agregado al carrito";
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Datos insuficientes']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => $mensaje]);
    
} catch (Exception $e) {
    error_log("Error en agregar_carrito_externo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>