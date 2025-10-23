<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
Auth::checkAuth('empleado');

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener estadísticas REALES para el empleado
$stmt = $db->prepare("SELECT COUNT(*) as total_pedidos FROM pedidos WHERE estado = 'pendiente'");
$stmt->execute();
$total_pedidos = $stmt->fetch(PDO::FETCH_ASSOC)['total_pedidos'];

$stmt = $db->prepare("SELECT COUNT(*) as cotizaciones_hoy FROM cotizaciones WHERE DATE(fecha) = CURDATE()");
$stmt->execute();
$cotizaciones_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['cotizaciones_hoy'];

// Obtener pedidos recientes
$stmt = $db->prepare("
    SELECT p.*, c.nombre as cliente_nombre 
    FROM pedidos p 
    LEFT JOIN clientes c ON p.id_cliente = c.id_cliente 
    WHERE p.estado IN ('pendiente', 'procesando')
    ORDER BY p.fecha DESC 
    LIMIT 10
");
$stmt->execute();
$pedidos_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Panel Empleado"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>👨‍💼 Panel de Empleado</h2>
                <span class="badge bg-primary">Bienvenido, <?php echo $_SESSION['nombre']; ?></span>
            </div>

            <!-- Estadísticas Rápidas -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Pedidos Pendientes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $total_pedidos; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Cotizaciones Hoy</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $cotizaciones_hoy; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Clientes Nuevos (Mes)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM clientes WHERE MONTH(fecha_registro) = MONTH(CURDATE())");
                                        $stmt->execute();
                                        echo $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Ingresos del Mes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        $<?php 
                                        $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM pedidos WHERE MONTH(fecha) = MONTH(CURDATE()) AND estado = 'completado'");
                                        $stmt->execute();
                                        echo number_format($stmt->fetch(PDO::FETCH_ASSOC)['total'], 2);
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">🚀 Acciones Rápidas</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <a href="gestion_pedidos.php" class="btn btn-primary w-100">
                                        <i class="fas fa-shopping-cart me-2"></i>Gestionar Pedidos
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="cotizaciones.php" class="btn btn-success w-100">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>Ver Cotizaciones
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="clientes.php" class="btn btn-info w-100">
                                        <i class="fas fa-users me-2"></i>Gestionar Clientes
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="inventario.php" class="btn btn-warning w-100">
                                        <i class="fas fa-warehouse me-2"></i>Ver Inventario
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pedidos Recientes -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">📦 Pedidos Recientes</h5>
                            <a href="gestion_pedidos.php" class="btn btn-sm btn-primary">Ver Todos</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th># Pedido</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($pedidos_recientes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">
                                                <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                                                No hay pedidos pendientes
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($pedidos_recientes as $pedido): ?>
                                        <tr>
                                            <td><strong>#VM<?php echo $pedido['id_pedido']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($pedido['cliente_nombre']); ?></td>
                                            <td>$<?php echo number_format($pedido['total'], 2); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></td>
                                            <td>
                                                <?php 
                                                $badge_class = [
                                                    'pendiente' => 'bg-warning',
                                                    'procesando' => 'bg-info',
                                                    'completado' => 'bg-success',
                                                    'cancelado' => 'bg-danger'
                                                ][$pedido['estado']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($pedido['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="ver_pedido.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                                       class="btn btn-outline-primary" title="Ver Detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($pedido['estado'] == 'pendiente'): ?>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="procesarPedido(<?php echo $pedido['id_pedido']; ?>)"
                                                            title="Procesar Pedido">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                    <?php elseif ($pedido['estado'] == 'procesando'): ?>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="completarPedido(<?php echo $pedido['id_pedido']; ?>)" 
                                                            title="Completar Pedido">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">📊 Actividad Reciente</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-shopping-cart text-success me-2"></i>
                                        <small>Nuevo pedido #VM1005</small>
                                    </div>
                                    <small class="text-muted">Hace 2 horas</small>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-file-invoice-dollar text-primary me-2"></i>
                                        <small>Cotización aprobada</small>
                                    </div>
                                    <small class="text-muted">Hace 4 horas</small>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user text-info me-2"></i>
                                        <small>Cliente nuevo registrado</small>
                                    </div>
                                    <small class="text-muted">Hace 6 horas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">🎯 Tareas Pendientes</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="tarea1">
                                        <label class="form-check-label" for="tarea1">
                                            Contactar cliente #VM1003
                                        </label>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="tarea2">
                                        <label class="form-check-label" for="tarea2">
                                            Actualizar inventario
                                        </label>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="tarea3">
                                        <label class="form-check-label" for="tarea3">
                                            Enviar cotización pendiente
                                        </label>
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

<script>
function procesarPedido(id) {
    if (confirm('¿Estás seguro de procesar este pedido?')) {
        // Aquí iría la lógica para procesar el pedido
        alert('Pedido #VM' + id + ' en proceso');
        location.reload();
    }
}

function completarPedido(id) {
    if (confirm('¿Marcar este pedido como completado?')) {
        // Aquí iría la lógica para completar el pedido
        alert('Pedido #VM' + id + ' completado');
        location.reload();
    }
}

// Marcar tareas como completadas
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.form-check-input');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                this.parentElement.style.textDecoration = 'line-through';
                this.parentElement.style.color = '#6c757d';
            } else {
                this.parentElement.style.textDecoration = 'none';
                this.parentElement.style.color = '';
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>