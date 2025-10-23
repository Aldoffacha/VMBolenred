<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

// Verificar que sea POST y tenga datos
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar que tenemos session activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] != 'cliente') {
    echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión como cliente']);
    exit;
}

// Verificar que tenemos el id_producto
if (!isset($_POST['id_producto'])) {
    echo json_encode(['success' => false, 'message' => 'ID de producto no especificado']);
    exit;
}

$id_cliente = $_SESSION['user_id'];
$id_producto = intval($_POST['id_producto']);
$cantidad = intval($_POST['cantidad'] ?? 1);

// Validar que la cantidad sea positiva
if ($cantidad <= 0) {
    $cantidad = 1;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$user_id = $_SESSION['user_id'];
$id_producto = $_POST['id_producto'] ?? null;
$cantidad = $_POST['cantidad'] ?? 1;

if (!$id_producto) {
    echo json_encode(['success' => false, 'message' => 'Producto no especificado']);
    exit;
}

$db = (new Database())->getConnection();

try {
    // Verificar si el producto ya está en el carrito
    $stmt = $db->prepare("SELECT * FROM carrito WHERE id_cliente = ? AND id_producto = ?");
    $stmt->execute([$user_id, $id_producto]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existe) {
        // Actualizar cantidad si ya existe
        $nueva_cantidad = $existe['cantidad'] + $cantidad;
        $stmt = $db->prepare("UPDATE carrito SET cantidad = ? WHERE id_carrito = ?");
        $stmt->execute([$nueva_cantidad, $existe['id_carrito']]);
        $mensaje = "Cantidad actualizada en el carrito";
    } else {
        // Insertar nuevo registro
        $stmt = $db->prepare("INSERT INTO carrito (id_cliente, id_producto, cantidad) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $id_producto, $cantidad]);
        $mensaje = "Producto agregado al carrito";
    }
    
    echo json_encode(['success' => true, 'message' => $mensaje]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al agregar al carrito: ' . $e->getMessage()]);
}
?>