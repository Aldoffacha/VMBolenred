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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_notificacion'])) {
    $notificaciones = new Notificaciones();
    $success = $notificaciones->marcarLeida($_POST['id_notificacion']);
    
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
}
?>