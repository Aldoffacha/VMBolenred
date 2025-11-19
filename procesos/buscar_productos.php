<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

Auth::checkAuth('cliente');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['buscar'])) {
    $termino = trim($_GET['buscar']);
    
    if (empty($termino)) {
        echo json_encode(['success' => false, 'message' => 'Término de búsqueda vacío']);
        exit;
    }
    
    $db = (new Database())->getConnection();
    
    try {
        // Buscar productos que coincidan con el término
        $stmt = $db->prepare("
            SELECT * FROM productos 
            WHERE estado = 1 AND (
                nombre ILIKE :termino OR 
                descripcion ILIKE :termino OR 
                categoria ILIKE :termino OR
                REPLACE(LOWER(nombre), ' ', '') LIKE REPLACE(LOWER(:termino_sin_espacios), ' ', '')
            )
            ORDER BY 
                CASE 
                    WHEN nombre ILIKE :termino THEN 1
                    WHEN descripcion ILIKE :termino THEN 2
                    WHEN categoria ILIKE :termino THEN 3
                    ELSE 4
                END,
                nombre ASC
            LIMIT 20
        ");
        
        $termino_like = "%$termino%";
        $termino_sin_espacios = "%" . str_replace(' ', '', $termino) . "%";
        
        $stmt->execute([
            ':termino' => $termino_like,
            ':termino_sin_espacios' => $termino_sin_espacios
        ]);
        
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'productos' => $productos,
            'total' => count($productos)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error en la búsqueda: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>