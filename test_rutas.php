<?php
echo "<h1>Verificación de Rutas</h1>";

// Verificar si los archivos existen
$archivos = [
    'procesos/logout.php' => 'Logout',
    'includes/auth.php' => 'Auth',
    'includes/config.php' => 'Config',
    'includes/database.php' => 'Database',
    'paginas/public/login.php' => 'Login',
];

foreach ($archivos as $ruta => $nombre) {
    if (file_exists($ruta)) {
        echo "<p style='color: green;'>✅ $nombre encontrado en: $ruta</p>";
    } else {
        echo "<p style='color: red;'>❌ $nombre NO encontrado en: $ruta</p>";
    }
}

// Verificar la estructura de carpetas
echo "<h2>Estructura de carpetas:</h2>";
function listarDirectorio($dir, $nivel = 0) {
    $ignorar = ['.', '..', '.git', 'node_modules'];
    $archivos = scandir($dir);
    
    foreach ($archivos as $archivo) {
        if (!in_array($archivo, $ignorar)) {
            $ruta = $dir . '/' . $archivo;
            $espacios = str_repeat('&nbsp;', $nivel * 4);
            
            if (is_dir($ruta)) {
                echo "<p>{$espacios}📁 $archivo/</p>";
                listarDirectorio($ruta, $nivel + 1);
            } else {
                echo "<p>{$espacios}📄 $archivo</p>";
            }
        }
    }
}

echo "<div style='font-family: monospace;'>";
listarDirectorio('.');
echo "</div>";
?>