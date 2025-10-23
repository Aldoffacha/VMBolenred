<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = htmlspecialchars(strip_tags(trim($_POST['correo'])));
    $contrasena = $_POST['contrasena'];
    $tipo_usuario = $_POST['tipo_usuario'];
    
    // Usar el método estático simplificado
   if (Auth::quickLogin($correo, $contrasena, $tipo_usuario)) {
    $redirect = '../../index.php'; // por defecto clientes

    if ($tipo_usuario === 'administradores') {
        $redirect = '../paginas/admin/dashboard.php';
    } elseif ($tipo_usuario === 'empleados') {
        $redirect = '../paginas/empleado/index.php';
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Login exitoso',
        'redirect' => $redirect
    ]);
}
else {
        echo json_encode([
            'success' => false, 
            'message' => 'Credenciales incorrectas'
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Método no permitido'
    ]);
}
?>