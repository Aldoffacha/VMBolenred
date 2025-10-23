<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// DEBUG
error_log("=== ACCESO A DASHBOARD ADMIN ===");
error_log("Session ID: " . ($_SESSION['user_id'] ?? 'NO'));
error_log("Rol: " . ($_SESSION['rol'] ?? 'NO'));

// Verificar autenticación con manejo de errores
try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    error_log("Error en checkAuth: " . $e->getMessage());
    header('Location: ../../public/login.php');
    exit;
}

$db = (new Database())->getConnection();
$stats = [];

// Total de clientes activos
$stmt = $db->query("SELECT COUNT(*) as total FROM clientes WHERE estado = 1");
$stats['clientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de productos activos
$stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE estado = 1");
$stats['productos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de pedidos (excluyendo cancelados)
$stmt = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado != 'cancelado'");
$stats['pedidos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// CORRECCIÓN: Ventas del mes solo pagadas o enviadas
$stmt = $db->query("SELECT SUM(total) as total FROM pedidos WHERE MONTH(fecha) = MONTH(CURRENT_DATE()) AND YEAR(fecha) = YEAR(CURRENT_DATE()) AND estado IN ('pagado', 'enviado')");
$stats['ventas_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Ventas del mes anterior para comparación (solo pagadas o enviadas)
$stmt = $db->query("SELECT SUM(total) as total FROM pedidos WHERE MONTH(fecha) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(fecha) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH) AND estado IN ('pagado', 'enviado')");
$ventas_mes_anterior = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Calcular porcentaje de crecimiento
if ($ventas_mes_anterior > 0) {
    $stats['crecimiento'] = (($stats['ventas_mes'] - $ventas_mes_anterior) / $ventas_mes_anterior) * 100;
} else {
    $stats['crecimiento'] = $stats['ventas_mes'] > 0 ? 100 : 0;
}

// Pedidos pendientes
$stmt = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'pendiente'");
$stats['pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Obtener datos para los modales
// Clientes activos
$clientes_activos = $db->query("SELECT * FROM clientes WHERE estado = 1 ORDER BY fecha_registro DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// Productos activos
$productos_activos = $db->query("SELECT * FROM productos WHERE estado = 1 ORDER BY fecha_registro DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// Pedidos activos
$pedidos_activos = $db->query("
    SELECT p.*, c.nombre as cliente 
    FROM pedidos p 
    JOIN clientes c ON p.id_cliente = c.id_cliente 
    WHERE p.estado != 'cancelado'
    ORDER BY p.fecha DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Ventas del mes detalladas
$ventas_detalladas = $db->query("
    SELECT p.*, c.nombre as cliente 
    FROM pedidos p 
    JOIN clientes c ON p.id_cliente = c.id_cliente 
    WHERE MONTH(p.fecha) = MONTH(CURRENT_DATE()) 
    AND YEAR(p.fecha) = YEAR(CURRENT_DATE()) 
    AND p.estado IN ('pagado', 'enviado')
    ORDER BY p.fecha DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Ventas de los últimos 6 meses para el gráfico (solo pagadas o enviadas)
$ventas_mensuales = $db->query("
    SELECT YEAR(fecha) as año, MONTH(fecha) as mes, SUM(total) as total 
    FROM pedidos 
    WHERE fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
    AND estado IN ('pagado', 'enviado')
    GROUP BY YEAR(fecha), MONTH(fecha) 
    ORDER BY año, mes
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - VMBol en Red</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<style>
/* Forzar texto blanco en modales */
.modal-body,
.modal-body *:not(.text-muted):not(.text-success):not(.text-danger):not(.text-warning):not(.text-info) {
    color: #d9d9d9 !important;
}

.modal-body .table,
.modal-body .table * {
    color: #d9d9d9 !important;
}

.modal-body .text-muted {
    color: #a0a0a0 !important;
}

.modal-body .text-success {
    color: #10b981 !important;
}

.modal-body .text-danger {
    color: #ef4444 !important;
}

.modal-body .text-warning {
    color: #f59e0b !important;
}

.modal-body .text-info {
    color: #3b82f6 !important;
}

/* Cards con colores de fondo */
.modal-body .card.bg-primary,
.modal-body .card.bg-success, 
.modal-body .card.bg-info,
.modal-body .card.bg-warning,
.modal-body .card.bg-danger {
    color: white !important;
}

.modal-body .card.bg-primary *,
.modal-body .card.bg-success *,
.modal-body .card.bg-info *,
.modal-body .card.bg-warning *,
.modal-body .card.bg-danger * {
    color: white !important;
}
</style>
<body class="admin-dashboard" style="background: #121418 !important;">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard Admin</h1>
                    <span class="text-muted"><?php echo date('d/m/Y'); ?></span>
                </div>

                <!-- Estadísticas rápidas -->
                <div class="row">
                    <!-- Clientes Activos - Modal -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2 clickable-card" onclick="mostrarClientes()" style="cursor: pointer;">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Clientes Activos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['clientes']; ?></div>
                                        <small class="text-muted">Haz click para ver detalles</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ventas del Mes - Modal -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2 clickable-card" onclick="mostrarVentas()" style="cursor: pointer;">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Ventas del Mes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['ventas_mes'], 2); ?></div>
                                        <div class="text-xs mt-1">
                                            <span class="<?php echo $stats['crecimiento'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <i class="fas fa-arrow-<?php echo $stats['crecimiento'] >= 0 ? 'up' : 'down'; ?>"></i>
                                                <?php echo number_format(abs($stats['crecimiento']), 1); ?>%
                                            </span>
                                            vs mes anterior
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pedidos Activos - Modal -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2 clickable-card" onclick="mostrarPedidos()" style="cursor: pointer;">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Pedidos Activos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pedidos']; ?></div>
                                        <div class="text-xs mt-1">
                                            <span class="text-warning">
                                                <?php echo $stats['pendientes']; ?> pendientes
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Productos Activos - Modal -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2 clickable-card" onclick="mostrarProductos()" style="cursor: pointer;">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Productos Activos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['productos']; ?></div>
                                        <small class="text-muted">Haz click para ver detalles</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Ventas Mensuales (Últimos 6 meses)</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Estado de Pedidos</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="ordersChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actividad reciente -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pedidos Recientes</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $db->query("
                                        SELECT p.*, c.nombre as cliente 
                                        FROM pedidos p 
                                        JOIN clientes c ON p.id_cliente = c.id_cliente 
                                        WHERE p.estado != 'cancelado'
                                        ORDER BY p.fecha DESC LIMIT 10
                                    ");
                                    while ($pedido = $stmt->fetch(PDO::FETCH_ASSOC)):
                                        $badgeClass = [
                                            'pendiente' => 'warning',
                                            'pagado' => 'info',
                                            'enviado' => 'success',
                                            'cancelado' => 'danger'
                                        ][$pedido['estado']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td>#<?php echo $pedido['id_pedido']; ?></td>
                                        <td><?php echo htmlspecialchars($pedido['cliente']); ?></td>
                                        <td>$<?php echo number_format($pedido['total'], 2); ?></td>
                                        <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($pedido['estado']); ?></span></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para Clientes Activos -->
    <div class="modal fade" id="modalClientes" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">👥 Clientes Activos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($clientes_activos)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Fecha Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientes_activos as $cliente): ?>
                                    <tr>
                                        <td><?php echo $cliente['id_cliente']; ?></td>
                                        <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['correo']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['telefono'] ?? 'No especificado'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></td>
                                        <td>
                                            <a href="usuarios.php?accion=editar&id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No hay clientes activos</h5>
                            <p class="text-muted">No se encontraron clientes registrados en el sistema</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="usuarios.php" class="btn btn-primary">Gestionar Clientes</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Ventas del Mes -->
    <div class="modal fade" id="modalVentas" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">💰 Ventas del Mes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4>$<?php echo number_format($stats['ventas_mes'], 2); ?></h4>
                                    <p class="mb-0">Total Ventas Mes Actual</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4>$<?php echo number_format($ventas_mes_anterior, 2); ?></h4>
                                    <p class="mb-0">Ventas Mes Anterior</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card <?php echo $stats['crecimiento'] >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo number_format($stats['crecimiento'], 1); ?>%</h4>
                                    <p class="mb-0">Crecimiento</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($ventas_detalladas)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Pedido</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ventas_detalladas as $venta): 
                                        $badgeClass = [
                                            'pendiente' => 'warning',
                                            'pagado' => 'info',
                                            'enviado' => 'success'
                                        ][$venta['estado']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td>#<?php echo $venta['id_pedido']; ?></td>
                                        <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                                        <td>$<?php echo number_format($venta['total'], 2); ?></td>
                                        <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($venta['estado']); ?></span></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h5>No hay ventas este mes</h5>
                            <p class="text-muted">No se registraron ventas pagadas o enviadas en el mes actual</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="reportes.php" class="btn btn-primary">Ver Reportes Completos</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Pedidos Activos -->
    <div class="modal fade" id="modalPedidos" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📦 Pedidos Activos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo $stats['pedidos']; ?></h4>
                                    <p class="mb-0">Total Activos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo $stats['pendientes']; ?></h4>
                                    <p class="mb-0">Pendientes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo $pagados = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'pagado'")->fetch(PDO::FETCH_ASSOC)['total']; ?></h4>
                                    <p class="mb-0">Pagados</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo $enviados = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'enviado'")->fetch(PDO::FETCH_ASSOC)['total']; ?></h4>
                                    <p class="mb-0">Enviados</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($pedidos_activos)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pedidos_activos as $pedido): 
                                        $badgeClass = [
                                            'pendiente' => 'warning',
                                            'pagado' => 'info',
                                            'enviado' => 'success'
                                        ][$pedido['estado']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td>#<?php echo $pedido['id_pedido']; ?></td>
                                        <td><?php echo htmlspecialchars($pedido['cliente']); ?></td>
                                        <td>$<?php echo number_format($pedido['total'], 2); ?></td>
                                        <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($pedido['estado']); ?></span></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></td>
                                        <td>
                                            <a href="pedidos.php?accion=ver&id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <h5>No hay pedidos activos</h5>
                            <p class="text-muted">No se encontraron pedidos en el sistema</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="pedidos.php" class="btn btn-primary">Gestionar Pedidos</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Productos Activos -->
    <div class="modal fade" id="modalProductos" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📦 Productos Activos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($productos_activos)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Imagen</th>
                                        <th>Nombre</th>
                                        <th>Precio</th>
                                        <th>Stock</th>
                                        <th>Fecha Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos_activos as $producto): ?>
                                    <tr>
                                        <td><?php echo $producto['id_producto']; ?></td>
                                        <td>
                                            <?php if (!empty($producto['imagen'])): ?>
                                                <img src="../../assets/img/productos/<?php echo $producto['imagen']; ?>" 
                                                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                                     style="width: 50px; height: 50px; object-fit: cover;"
                                                     onerror="this.src='https://via.placeholder.com/50x50/2c7be5/ffffff?text=IMG'">
                                            <?php else: ?>
                                                <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                                                     style="width: 50px; height: 50px;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                        <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                                        <td>
                                            <span class="<?php echo $producto['stock'] <= 5 ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo $producto['stock']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($producto['fecha_registro'])); ?></td>
                                        <td>
                                            <a href="productos.php?accion=editar&id=<?php echo $producto['id_producto']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-box fa-3x text-muted mb-3"></i>
                            <h5>No hay productos activos</h5>
                            <p class="text-muted">No se encontraron productos en el sistema</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="productos.php" class="btn btn-primary">Gestionar Productos</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Funciones para mostrar modales
        function mostrarClientes() {
            const modal = new bootstrap.Modal(document.getElementById('modalClientes'));
            modal.show();
        }

        function mostrarVentas() {
            const modal = new bootstrap.Modal(document.getElementById('modalVentas'));
            modal.show();
        }

        function mostrarPedidos() {
            const modal = new bootstrap.Modal(document.getElementById('modalPedidos'));
            modal.show();
        }

        function mostrarProductos() {
            const modal = new bootstrap.Modal(document.getElementById('modalProductos'));
            modal.show();
        }

        // Gráfico de ventas mensuales
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: [<?php 
                    $meses = [];
                    foreach ($ventas_mensuales as $venta) {
                        $meses[] = "'" . date('M Y', mktime(0, 0, 0, $venta['mes'], 1, $venta['año'])) . "'";
                    }
                    echo implode(', ', $meses);
                ?>],
                datasets: [{
                    label: 'Ventas ($)',
                    data: [<?php echo implode(', ', array_column($ventas_mensuales, 'total')); ?>],
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de estado de pedidos
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        const ordersChart = new Chart(ordersCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pendientes', 'Pagados', 'Enviados'],
                datasets: [{
                    data: [
                        <?php 
                        $pendientes = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'pendiente'")->fetch(PDO::FETCH_ASSOC)['total'];
                        $pagados = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'pagado'")->fetch(PDO::FETCH_ASSOC)['total'];
                        $enviados = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'enviado'")->fetch(PDO::FETCH_ASSOC)['total'];
                        echo "$pendientes, $pagados, $enviados";
                        ?>
                    ],
                    backgroundColor: ['#f6c23e', '#36b9cc', '#1cc88a'],
                    hoverBackgroundColor: ['#f8d375', '#5acddb', '#3dd5a1'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                cutout: '70%'
            }
        });

        // Agregar efecto hover a las tarjetas clickeables
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.clickable-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.transition = 'all 0.2s ease';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>

    <style>
    .clickable-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    }
    </style>
</body>
</html>