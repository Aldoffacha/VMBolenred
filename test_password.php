<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

// Cambia por tu correo real
$correo = 'tu_correo@ejemplo.com';

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT * FROM clientes WHERE correo = ?");
    $stmt->execute([$correo]);
    
    if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Usuario encontrado:<br>";
        echo "Nombre: " . $user['nombre'] . "<br>";
        echo "Correo: " . $user['correo'] . "<br>";
        echo "Contraseña hash: " . $user['contrasena'] . "<br>";
        echo "Estado: " . $user['estado'] . "<br>";
        
        // Probar contraseña
        $password = 'tu_password'; // Cambia por tu password real
        if (password_verify($password, $user['contrasena'])) {
            echo "✅ Contraseña CORRECTA<br>";
        } else {
            echo "❌ Contraseña INCORRECTA<br>";
        }
    } else {
        echo "❌ Usuario no encontrado";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>