<?php
// Iniciar sesión
session_start();

// Si ya está logueado, redirigir según su rol
if (isset($_SESSION['user_id']) && isset($_SESSION['rol'])) {
    switch ($_SESSION['rol']) {
        case 'admin':
            header('Location: paginas/admin/dashboard.php');
            break;
        case 'empleado':
            header('Location: paginas/empleado/index.php');
            break;
        case 'cliente':
            header('Location: paginas/cliente/index.php');
            break;
        default:
            header('Location: paginas/public/login.php');
    }
    exit;
} else {
    // Si no está logueado, ir al login DIRECTAMENTE
    header('Location: paginas/public/login.php');
    exit;
}
?>