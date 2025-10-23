<?php $rol = get_rol_usuario(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo SITIO_NOMBRE; ?> | Dashboard</title>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>fonts/source-sans-3/source-sans-3.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>css/vendors/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>css/vendors/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>css/custom/main.css">
    <?php if ($rol === 'admin'): ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>css/custom/admin.css">
    <?php elseif ($rol === 'empleado'): ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>css/custom/empleado.css">
    <?php elseif ($rol === 'cliente'): ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>css/custom/cliente.css">
    <?php endif; ?>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand d-lg-none" href="index.php?ruta=dashboard_<?php echo $rol; ?>"><?php echo SITIO_NOMBRE; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse d-none d-lg-block">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0"></ul>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?ruta=logout">
                            <i class="fas fa-sign-out-alt"></i> Salir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="brand-link">
                        <img src="<?php echo URL_ASSETS; ?>img/custom/logos/favicon.ico" alt="Logo" class="brand-image img-circle">
                        <span class="brand-text"><?php echo SITIO_NOMBRE; ?></span>
                    </div>
                    <ul class="nav flex-column mt-3">
                        <?php if ($rol === 'admin'): ?>
                            <li class="nav-item"><a href="index.php?ruta=dashboard_admin" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li class="nav-item"><a href="index.php?ruta=inventario_admin" class="nav-link"><i class="nav-icon fas fa-boxes"></i> Inventario</a></li>
                            <li class="nav-item"><a href="index.php?ruta=cotizaciones_admin" class="nav-link"><i class="nav-icon fas fa-file-invoice-dollar"></i> Cotizaciones</a></li>
                            <li class="nav-item"><a href="index.php?ruta=usuarios_admin" class="nav-link"><i class="nav-icon fas fa-users"></i> Usuarios</a></li>
                        <?php elseif ($rol === 'empleado'): ?>
                            <li class="nav-item"><a href="index.php?ruta=dashboard_empleado" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li class="nav-item"><a href="index.php?ruta=cotizaciones_empleado" class="nav-link"><i class="nav-icon fas fa-file-invoice-dollar"></i> Cotizaciones</a></li>
                            <li class="nav-item"><a href="index.php?ruta=pedidos_empleado" class="nav-link"><i class="nav-icon fas fa-shipping-fast"></i> Pedidos</a></li>
                        <?php elseif ($rol === 'cliente'): ?>
                            <li class="nav-item"><a href="index.php?ruta=dashboard_cliente" class="nav-link"><i class="nav-icon fas fa-home"></i> Inicio</a></li>
                            <li class="nav-item"><a href="index.php?ruta=pedidos_cliente" class="nav-link"><i class="nav-icon fas fa-box"></i> Mis Pedidos</a></li>
                            <li class="nav-item"><a href="index.php?ruta=cotizaciones_cliente" class="nav-link"><i class="nav-icon fas fa-calculator"></i> Mis Cotizaciones</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                </div>