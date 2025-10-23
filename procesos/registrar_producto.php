<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

if ($_POST) {
    $db = (new Database())->getConnection();
    $nombre = Funciones::sanitizar($_POST['nombre']);
    $descripcion = Funciones::sanitizar($_POST['descripcion']);
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    
    try {
        $stmt = $db->prepare("INSERT INTO productos (nombre, descripcion, precio, stock) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $descripcion, $precio, $stock]);
        
        echo json_encode(['success' => true, 'message' => 'Producto registrado correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al registrar producto']);
    }
}