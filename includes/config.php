<?php
// Verificar si las constantes ya están definidas
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/VMBol_en_red/');
}

// Configuración de la base de datos PostgreSQL
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'vmbolenred');  // tu base en PostgreSQL
    define('DB_USER', 'postgres');    // usuario por defecto de PostgreSQL
    define('DB_PASS', 'A12345');      // tu contraseña
}

// Solo iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
