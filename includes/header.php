<?php
// Iniciar sesión si no está iniciada - SOLO UNA VEZ
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - VMBol en Red' : 'VMBol en Red - Sistema de Importación'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
<?php if (isset($_SESSION['rol'])): ?>
<link rel="stylesheet" href="../../assets/css/<?php echo $_SESSION['rol']; ?>.css">
<?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="d-flex flex-column h-100 <?php echo isset($_SESSION['rol']) ? $_SESSION['rol'] . '-dashboard' : 'public-page'; ?>">
    <?php if (isset($_SESSION['user_id'])): ?>
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <a class="navbar-brand" href="../index.php">
                    <i class="fas fa-globe-americas me-2"></i>VMBol en Red
                </a>
                
                <div class="navbar-nav ms-auto align-items-center">
                    <span class="navbar-text me-3 d-none d-md-block">
                        <i class="fas fa-user me-1"></i>Hola, <strong><?php echo $_SESSION['nombre']; ?></strong>
                    </span>
                    <!-- RUTA CORRECTA DEL LOGOUT -->
                    <a href="../../procesos/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Salir
                    </a>
                </div>
            </div>
        </nav>
    </header>
    <?php endif; ?>
    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Gestor de temas persistente -->
    <script src="../../assets/js/theme-manager.js"></script>
    
    <!-- Tus otros scripts... -->
<!-- Contenedor principal flex -->
<div class="container-fluid flex-grow-1 d-flex flex-column p-0">
    <div class="row flex-grow-1 m-0">
        </body>
</html>