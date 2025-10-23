<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block">
                <a href="/paginas/<?php echo $usuario['rol']; ?>/dashboard.php" class="nav-link">Home</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ms-auto">
            <!-- Notifications -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-bs-toggle="dropdown" href="#">
                    <i class="bi bi-bell-fill"></i>
                    <span class="navbar-badge badge text-bg-warning">3</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <span class="dropdown-item dropdown-header">3 Notificaciones</span>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="bi bi-file-text me-2"></i> 2 cotizaciones nuevas
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="bi bi-box me-2"></i> Stock bajo en 5 productos
                    </a>
                </div>
            </li>

            <!-- User Menu -->
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="/assets/img/vendors/adminlte/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User Image">
                    <span class="d-none d-md-inline"><?php echo $usuario['nombre']; ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <li class="user-header text-bg-primary">
                        <img src="/assets/img/vendors/adminlte/user2-160x160.jpg" class="rounded-circle shadow" alt="User Image">
                        <p>
                            <?php echo $usuario['nombre']; ?> - <?php echo ucfirst($usuario['rol']); ?>
                            <small>Miembro desde <?php echo date('M. Y'); ?></small>
                        </p>
                    </li>
                    <li class="user-footer">
                        <a href="/paginas/<?php echo $usuario['rol']; ?>/perfil.php" class="btn btn-default btn-flat">Perfil</a>
                        <a href="/procesos/logout.php" class="btn btn-default btn-flat float-end">Cerrar Sesión</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>