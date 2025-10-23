<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

if ($_POST) {
    $db = (new Database())->getConnection();
    $nombre = Funciones::sanitizar($_POST['nombre']);
    $correo = Funciones::sanitizar($_POST['correo']);
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $tipo = $_POST['tipo'];
    
    $tabla = $tipo . 's'; // clientes, empleados, etc.
    
    try {
        $stmt = $db->prepare("INSERT INTO $tabla (nombre, correo, contrasena) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $correo, $contrasena]);
        
        echo json_encode(['success' => true, 'message' => 'Usuario registrado correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al registrar usuario']);
    }
}