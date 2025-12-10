<?php if (isset($_SESSION['user_id'])): ?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar">
    <div class="sidebar-content">
        <ul class="nav flex-column">
            <?php if ($_SESSION['rol'] == 'admin'): ?>
            <!-- Menú Admin -->
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'empleados.php' ? 'active' : ''; ?>" href="empleados.php">
        <i class="fas fa-user-tie me-2"></i> Empleados
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'usuarios.php' ? 'active' : ''; ?>" href="usuarios.php">
        <i class="fas fa-users me-2"></i> Clientes
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'productos.php' ? 'active' : ''; ?>" href="productos.php">
        <i class="fas fa-box me-2"></i> Productos
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'pedidos.php' ? 'active' : ''; ?>" href="pedidos.php">
        <i class="fas fa-shopping-cart me-2"></i> Pedidos
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'pagos.php' ? 'active' : ''; ?>" href="pagos.php">
        <i class="fas fa-credit-card me-2"></i> Pagos
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'cotizaciones.php' ? 'active' : ''; ?>" href="cotizaciones.php">
        <i class="fas fa-file-invoice-dollar me-2"></i> Cotizaciones
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'inventario.php' ? 'active' : ''; ?>" href="inventario.php">
        <i class="fas fa-warehouse me-2"></i> Inventario
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'reportes.php' ? 'active' : ''; ?>" href="reportes.php">
        <i class="fas fa-chart-bar me-2"></i> Reportes
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'auditoria.php' ? 'active' : ''; ?>" href="auditoria.php">
        <i class="fas fa-clipboard-list me-2"></i> Auditoría
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'configuracion.php' ? 'active' : ''; ?>" href="configuracion.php">
        <i class="fas fa-cog me-2"></i> Configuración
    </a>
</li>
        
            
            <?php elseif ($_SESSION['rol'] == 'empleado'): ?>
            <!-- Menú Empleado -->
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Panel
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pedidos.php' ? 'active' : ''; ?>" href="pedidos.php">
                    <i class="fas fa-clipboard-list me-2"></i>Gestionar Pedidos
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : ''; ?>" href="clientes.php">
                    <i class="fas fa-headset me-2"></i>Atención al Cliente
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cotizaciones.php' ? 'active' : ''; ?>" href="cotizaciones.php">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Cotizaciones
                </a>
            </li>
            
            <?php else: ?>
            <!-- Menú Cliente -->
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
            <?php endif; ?>
        </ul>
        
       
    </div>
</nav>
<?php endif; ?>