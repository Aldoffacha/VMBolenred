<aside class="app-sidebar bg-body-secondary shadow">
    <div class="sidebar-brand">
        <a href="/paginas/admin/dashboard.php" class="brand-link">
            <span class="brand-text"><?php echo SITIO_NOMBRE; ?></span>
        </a>
    </div>
    
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column">
                <li class="nav-item">
                    <a href="/paginas/admin/dashboard.php" class="nav-link active">
                        <i class="nav-icon bi bi-speedometer"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/paginas/admin/usuarios.php" class="nav-link">
                        <i class="nav-icon bi bi-people-fill"></i>
                        <p>Gestión de Usuarios</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/paginas/empleado/cotizaciones.php" class="nav-link">
                        <i class="nav-icon bi bi-file-text"></i>
                        <p>Cotizaciones</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/paginas/empleado/inventario.php" class="nav-link">
                        <i class="nav-icon bi bi-box-seam"></i>
                        <p>Inventario</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/procesos/logout.php" class="nav-link text-danger">
                        <i class="nav-icon bi bi-box-arrow-right"></i>
                        <p>Cerrar Sesión</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>