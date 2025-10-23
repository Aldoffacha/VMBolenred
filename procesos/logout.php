<?php
session_start();
session_destroy();

// Redirigir al login CORRECTAMENTE
header('Location: ../paginas/public/login.php');
exit;
?>