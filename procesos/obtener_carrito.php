<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['total' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];
$db = (new Database())->getConnection();

try {
    // Contar productos locales
    $stmt = $db->prepare("SELECT SUM(cantidad) as total FROM carrito WHERE id_cliente = ?");
    $stmt->execute([$user_id]);
    $total_local = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Contar productos externos
    $stmt = $db->prepare("SELECT SUM(cantidad) as total FROM carrito_externo WHERE id_cliente = ?");
    $stmt->execute([$user_id]);
    $total_externo = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $total = intval($total_local) + intval($total_externo);
    
    echo json_encode(['total' => $total]);
    
} catch (Exception $e) {
    echo json_encode(['total' => 0]);
}
?>