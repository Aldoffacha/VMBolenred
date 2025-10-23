<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = Funciones::sanitizar($_POST['nombre']);
    $correo = Funciones::sanitizar($_POST['correo']);
    $contrasena = $_POST['contrasena'];
    $telefono = Funciones::sanitizar($_POST['telefono'] ?? '');
    $direccion = Funciones::sanitizar($_POST['direccion'] ?? '');
    
    try {
        $db = (new Database())->getConnection();
        
        // Verificar si el correo ya existe
        $stmt = $db->prepare("SELECT id_cliente FROM clientes WHERE correo = ?");
        $stmt->execute([$correo]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Este correo electrónico ya está registrado'
            ]);
            exit;
        }
        
        // Registrar nuevo cliente
        $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO clientes (nombre, correo, contrasena, telefono, direccion) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $correo, $contrasena_hash, $telefono, $direccion]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registro exitoso. Redirigiendo...',
            'redirect' => 'login.php'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error en el registro: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Método no permitido'
    ]);
}
?>