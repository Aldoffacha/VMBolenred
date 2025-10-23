<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$url = $data['url'] ?? '';

if (empty($url)) {
    echo json_encode(['success' => false, 'message' => 'URL no proporcionada']);
    exit;
}

// Función para extraer información básica del producto desde la URL
function obtenerInfoProducto($url) {
    $producto = [
        'nombre' => 'Producto de ' . (strpos($url, 'amazon') !== false ? 'Amazon' : 'eBay'),
        'precio' => rand(20, 500),
        'categoria' => 'electronico',
        'peso' => 0.5,
        'disponible' => true
    ];

    // Simulación de scraping
    if (strpos($url, 'amazon.com') !== false) {
        $producto['plataforma'] = 'amazon';
        $producto['nombre'] = 'Producto Premium Amazon';
        $producto['precio'] = rand(50, 500);
        $producto['imagen'] = 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&h=300&fit=crop';
    } elseif (strpos($url, 'ebay.com') !== false) {
        $producto['plataforma'] = 'ebay';
        $producto['nombre'] = 'Producto Exclusivo eBay';
        $producto['precio'] = rand(30, 400);
        $producto['imagen'] = 'https://images.unsplash.com/photo-1556656793-08538906a9f8?w=400&h=300&fit=crop';
    } else {
        $producto['plataforma'] = 'otro';
        $producto['nombre'] = 'Producto Externo';
        $producto['precio'] = rand(20, 300);
        $producto['imagen'] = 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=400&h=300&fit=crop';
    }

    return $producto;
}

try {
    $info = obtenerInfoProducto($url);
    echo json_encode([
        'success' => true,
        'producto' => $info
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener información del producto: ' . $e->getMessage()
    ]);
}
?>