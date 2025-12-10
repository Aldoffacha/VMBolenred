<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// DEBUG
error_log("=== ACCESO A DASHBOARD ADMIN ===");
error_log("Session ID: " . ($_SESSION['user_id'] ?? 'NO'));
error_log("Rol: " . ($_SESSION['rol'] ?? 'NO'));

// Verificar autenticación
try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    error_log("Error en checkAuth: " . $e->getMessage());
    header('Location: ../../public/login.php');
    exit;
}

$db = (new Database())->getConnection();
$stats = [];

// ======= CLIENTES ACTIVOS =======
$stmt = $db->query("SELECT COUNT(*) as total FROM clientes WHERE estado = 1");
$stats['clientes'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// ======= PRODUCTOS ACTIVOS =======
$stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE estado = 1");
$stats['productos'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// ======= PEDIDOS ACTIVOS =======
$stmt = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado != 'cancelado'");
$stats['pedidos'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// ======= PEDIDOS PENDIENTES =======
$stmt = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'pendiente'");
$stats['pendientes'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// ======= VENTAS MES ACTUAL =======
$stmt = $db->query("
    SELECT SUM(total) AS total 
    FROM pedidos 
    WHERE EXTRACT(MONTH FROM fecha) = EXTRACT(MONTH FROM CURRENT_DATE)
      AND EXTRACT(YEAR FROM fecha) = EXTRACT(YEAR FROM CURRENT_DATE)
      AND estado IN ('pagado', 'enviado')
");
$stats['ventas_mes'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// ======= VENTAS MES ANTERIOR =======
$stmt = $db->query("
    SELECT SUM(total) AS total 
    FROM pedidos 
    WHERE EXTRACT(MONTH FROM fecha) = EXTRACT(MONTH FROM (CURRENT_DATE - INTERVAL '1 month'))
      AND EXTRACT(YEAR FROM fecha) = EXTRACT(YEAR FROM (CURRENT_DATE - INTERVAL '1 month'))
      AND estado IN ('pagado', 'enviado')
");
$ventas_mes_anterior = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// ======= CALCULAR CRECIMIENTO =======
if ($ventas_mes_anterior > 0) {
    $stats['crecimiento'] = (($stats['ventas_mes'] - $ventas_mes_anterior) / $ventas_mes_anterior) * 100;
} else {
    $stats['crecimiento'] = 0;
}

// ======= VENTAS DETALLADAS DEL MES (LIMITADO A 5) =======
$ventas_detalladas = $db->query("
    SELECT p.*, c.nombre AS cliente
    FROM pedidos p
    JOIN clientes c ON p.id_cliente = c.id_cliente
    WHERE EXTRACT(MONTH FROM p.fecha) = EXTRACT(MONTH FROM CURRENT_DATE)
      AND EXTRACT(YEAR FROM p.fecha) = EXTRACT(YEAR FROM CURRENT_DATE)
      AND p.estado IN ('pagado', 'enviado')
    ORDER BY p.fecha DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ======= VENTAS MENSUALES (6 MESES) =======
$ventas_mensuales = $db->query("
    SELECT 
        EXTRACT(YEAR FROM fecha) AS año, 
        EXTRACT(MONTH FROM fecha) AS mes, 
        SUM(total) AS total
    FROM pedidos
    WHERE fecha >= (NOW() - INTERVAL '6 months')
      AND estado IN ('pagado', 'enviado')
    GROUP BY año, mes
    ORDER BY año, mes
")->fetchAll(PDO::FETCH_ASSOC);

// ======= CLIENTES, PEDIDOS Y PRODUCTOS ACTIVOS (LIMITADOS A 5) =======
$clientes_activos = $db->query("SELECT * FROM clientes WHERE estado = 1 ORDER BY fecha_registro DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$pedidos_activos = $db->query("
    SELECT p.*, c.nombre AS cliente
    FROM pedidos p
    JOIN clientes c ON p.id_cliente = c.id_cliente
    WHERE p.estado != 'cancelado'
    ORDER BY p.fecha DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
$productos_activos = $db->query("SELECT * FROM productos WHERE estado = 1 ORDER BY fecha_registro DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// ======= PEDIDOS RECIENTES PARA LA TABLA =======
$pedidos_recientes = $db->query("
    SELECT p.*, c.nombre as cliente 
    FROM pedidos p 
    JOIN clientes c ON p.id_cliente = c.id_cliente 
    WHERE p.estado != 'cancelado'
    ORDER BY p.fecha DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - VMBol en Red</title>
    <!-- Bootstrap PRIMERO -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tus CSS DESPUÉS (para que sobrescriban a Bootstrap) -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>

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
                                        <small class="text-muted">Haz click para ver últimos 5</small>
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
                                        <small class="text-muted">Haz click para ver últimos 5</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido principal reorganizado -->
                <div class="row">
                    <!-- Columna izquierda - Pedidos recientes y Ventas mensuales -->
                    <div class="col-lg-8">
                        <!-- Pedidos Recientes - Siempre expandido -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-shopping-cart me-2"></i>Pedidos Recientes
                                </h6>
                                <a href="pedidos.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt me-1"></i>Ver Todos
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
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
                                            <?php foreach ($pedidos_recientes as $pedido): 
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
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Ventas Mensuales - Siempre expandido -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-line me-2"></i>Ventas Mensuales (Últimos 6 meses)
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Columna derecha - Estado de pedidos -->
                    <div class="col-lg-4">
                        <!-- Estado de Pedidos - Siempre expandido -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-pie me-2"></i>Estado de Pedidos
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="ordersChart" height="250"></canvas>
                                <div class="mt-3 text-center">
                                    <?php
                                    $total_pedidos = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado != 'cancelado'")->fetch(PDO::FETCH_ASSOC)['total'];
                                    $pendientes = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'pendiente'")->fetch(PDO::FETCH_ASSOC)['total'];
                                    $pagados = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'pagado'")->fetch(PDO::FETCH_ASSOC)['total'];
                                    $enviados = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'enviado'")->fetch(PDO::FETCH_ASSOC)['total'];
                                    ?>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-warning">
                                                <i class="fas fa-clock fa-2x mb-2"></i>
                                                <h5><?php echo $pendientes; ?></h5>
                                                <small>Pendientes</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-info">
                                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                                <h5><?php echo $pagados; ?></h5>
                                                <small>Pagados</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-success">
                                                <i class="fas fa-shipping-fast fa-2x mb-2"></i>
                                                <h5><?php echo $enviados; ?></h5>
                                                <small>Enviados</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen rápido -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-tachometer-alt me-2"></i>Resumen Rápido
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-dollar-sign text-success fa-2x mb-2"></i>
                                            <h5>$<?php echo number_format($stats['ventas_mes'], 0); ?></h5>
                                            <small class="text-muted">Ventas Mes</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-shopping-cart text-info fa-2x mb-2"></i>
                                            <h5><?php echo $total_pedidos; ?></h5>
                                            <small class="text-muted">Total Pedidos</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-users text-primary fa-2x mb-2"></i>
                                            <h5><?php echo $stats['clientes']; ?></h5>
                                            <small class="text-muted">Clientes</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-box text-warning fa-2x mb-2"></i>
                                            <h5><?php echo $stats['productos']; ?></h5>
                                            <small class="text-muted">Productos</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para Clientes Activos -->
    <div class="modal fade" id="modalClientes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">👥 Últimos 5 Clientes Activos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($clientes_activos)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Mostrando los últimos 5 clientes registrados
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Fecha Registro</th>
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
                    <a href="usuarios.php" class="btn btn-primary">Gestionar Todos los Clientes</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Ventas del Mes -->
    <div class="modal fade" id="modalVentas" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">💰 Últimas 5 Ventas del Mes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4>$<?php echo number_format($stats['ventas_mes'], 2); ?></h4>
                                    <p class="mb-0">Total Ventas Mes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4>$<?php echo number_format($ventas_mes_anterior, 2); ?></h4>
                                    <p class="mb-0">Mes Anterior</p>
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
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Mostrando las últimas 5 ventas del mes actual
                        </div>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📦 Últimos 5 Pedidos Activos</h5>
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
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Mostrando los últimos 5 pedidos activos
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
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
                    <a href="pedidos.php" class="btn btn-primary">Gestionar Todos los Pedidos</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Productos Activos -->
    <div class="modal fade" id="modalProductos" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📦 Últimos 5 Productos Activos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($productos_activos)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Mostrando los últimos 5 productos registrados
                        </div>
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
                    <a href="productos.php" class="btn btn-primary">Gestionar Todos los Productos</a>
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
                cutout: '60%'
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
    .card-header {
        background: rgba(255,255,255,0.05) !important;
        border-bottom: 1px solid rgba(255,255,255,0.1) !important;
    }
    </style>
</body>
</html>