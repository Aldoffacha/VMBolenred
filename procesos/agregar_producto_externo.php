<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Verificar autenticación como admin
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos
$nombre = $_POST['nombre'] ?? null;
$precio = $_POST['precio'] ?? null;
$descripcion = $_POST['descripcion'] ?? null;
$categoria = $_POST['categoria'] ?? null;
$peso = $_POST['peso'] ?? 0.50;
$enlace = $_POST['enlace'] ?? null;
$imagen = $_POST['imagen'] ?? null;
$plataforma = $_POST['plataforma'] ?? 'amazon';
$destacado = $_POST['destacado'] ?? 1;

// Validar datos requeridos
if (!$nombre || !$precio || !$descripcion || !$categoria || !$enlace) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
    exit;
}

// Detectar plataforma por URL
if (stripos($enlace, 'ebay') !== false) {
    $plataforma = 'ebay';
} else if (stripos($enlace, 'amazon') !== false) {
    $plataforma = 'amazon';
} else {
    echo json_encode(['success' => false, 'message' => 'El enlace debe ser de Amazon o eBay']);
    exit;
}

$db = (new Database())->getConnection();

try {
    $stmt = $db->prepare(
        "INSERT INTO productos_exterior (nombre, descripcion, precio, categoria, peso, enlace, imagen, plataforma, destacado) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    $stmt->execute([
        $nombre,
        $descripcion,
        $precio,
        $categoria,
        $peso,
        $enlace,
        $imagen,
        $plataforma,
        $destacado
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Producto externo agregado correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}
?>
