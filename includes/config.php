<?php
// Verificar si las constantes ya están definidas
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/VMBol_en_red/');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'vmbolenred');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Solo iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>