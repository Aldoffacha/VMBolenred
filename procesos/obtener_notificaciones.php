<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/notificaciones.php';

session_start();

// Verificar autenticación pero sin requerir un rol específico
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $notificaciones = new Notificaciones();
    $notifs = $notificaciones->obtener($_SESSION['user_id'], $_SESSION['rol'], 10);
    $no_leidas = $notificaciones->obtenerNoLeidas($_SESSION['user_id'], $_SESSION['rol']);
    
    echo json_encode([
        'success' => true,
        'notificaciones' => $notifs,
        'no_leidas' => $no_leidas
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>