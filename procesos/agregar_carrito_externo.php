<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

session_start();
Auth::checkAuth('cliente');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$user_id = $_SESSION['user_id'];
$url = $_POST['url'] ?? '';
$precio = floatval($_POST['precio'] ?? 0);
$peso = floatval($_POST['peso'] ?? 0.5);
$categoria = $_POST['categoria'] ?? 'electronico';
$plataforma = $_POST['plataforma'] ?? 'amazon';
$nombre = $_POST['nombre'] ?? 'Producto Externo';

if (empty($url) || $precio <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos del producto incompletos']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    // Insertar en el carrito normal con flag de externo
    $stmt = $db->prepare("
        INSERT INTO carrito 
        (id_cliente, id_producto, cantidad, precio, es_externo, url_externo, nombre_externo) 
        VALUES (?, ?, 1, ?, 1, ?, ?)
    ");
    
    $id_externo = 'ext_' . uniqid();
    
    $stmt->execute([
        $user_id, 
        $id_externo,
        $precio,
        $url,
        $nombre
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Producto externo agregado al carrito correctamente. Nuestro equipo revisará el enlace para procesar tu pedido.'
    ]);
    
} catch (Exception $e) {
    error_log("Error al agregar producto externo: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al agregar producto al carrito: ' . $e->getMessage()
    ]);
}
?>