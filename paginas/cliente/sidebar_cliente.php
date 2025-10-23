<nav class="col-md-3 col-lg-2 d-md-block sidebar">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tienda.php' ? 'active' : ''; ?>" href="tienda.php">
                    <i class="fas fa-store me-2"></i>Tienda
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'carrito.php' ? 'active' : ''; ?>" href="carrito.php">
                    <i class="fas fa-shopping-cart me-2"></i>Mi Carrito
                    <span class="badge bg-primary carrito-badge float-end">0</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pedidos.php' ? 'active' : ''; ?>" href="pedidos.php">
                    <i class="fas fa-clipboard-list me-2"></i>Mis Pedidos
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cotizaciones.php' ? 'active' : ''; ?>" href="cotizaciones.php">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Mis Cotizaciones
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'active' : ''; ?>" href="perfil.php">
                    <i class="fas fa-user me-2"></i>Mi Perfil
                </a>
            </li>
        </ul>
        
        <hr>
        
        <div class="sidebar-footer p-3">
            <small class="text-muted">Soporte 24/7</small><br>
            <small><i class="fas fa-phone me-1"></i>+591 777 12345</small><br>
            <small><i class="fas fa-envelope me-1"></i>soporte@vmbol.com</small>
        </div>
    </div>
</nav>