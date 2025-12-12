<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => '', 'filepath' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil'])) {
    $uploadDir = '../../assets/img/logos/';
    
    // Verificar si el directorio existe, si no crearlo
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['foto_perfil'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // Obtener extensión del archivo
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Extensiones permitidas
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
    
    // Verificar extensión
    if (!in_array($fileExt, $allowedExt)) {
        $response['message'] = 'Solo se permiten archivos JPG, JPEG, PNG y GIF';
        echo json_encode($response);
        exit;
    }
    
    // Verificar tamaño (máximo 2MB)
    if ($fileSize > 2097152) {
        $response['message'] = 'El archivo es demasiado grande (máximo 2MB)';
        echo json_encode($response);
        exit;
    }
    
    // Verificar errores
    if ($fileError !== UPLOAD_ERR_OK) {
        $response['message'] = 'Error al subir el archivo';
        echo json_encode($response);
        exit;
    }
    
    // Generar nombre único para el archivo
    $newFileName = 'cliente_' . $user_id . '_' . time() . '.' . $fileExt;
    $fileDestination = $uploadDir . $newFileName;
    
    // Mover archivo
    if (move_uploaded_file($fileTmpName, $fileDestination)) {
        // Actualizar base de datos
        $stmt = $db->prepare("UPDATE clientes SET foto_perfil = ? WHERE id_cliente = ?");
        if ($stmt->execute([$newFileName, $user_id])) {
            $response['success'] = true;
            $response['message'] = 'Foto de perfil actualizada correctamente';
            $response['filepath'] = '../../assets/img/logos/' . $newFileName . '?t=' . time();
        } else {
            $response['message'] = 'Error al actualizar la base de datos';
            // Eliminar archivo subido si hay error en la BD
            unlink($fileDestination);
        }
    } else {
        $response['message'] = 'Error al guardar el archivo';
    }
} else {
    $response['message'] = 'No se recibió ningún archivo';
}

echo json_encode($response);
?>