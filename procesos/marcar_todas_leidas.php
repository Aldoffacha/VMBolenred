<?php
session_start();

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/notificaciones.php';

// Verificar autenticación pero sin requerir un rol específico
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificaciones = new Notificaciones();
    $success = $notificaciones->marcarTodasLeidas($_SESSION['user_id'], $_SESSION['rol']);
    
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>