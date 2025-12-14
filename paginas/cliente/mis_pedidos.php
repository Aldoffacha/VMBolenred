<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
$mensaje = '';
$mensaje_tipo = '';

// Verificar si hay mensajes de error en sesión
if (isset($_SESSION['error'])) {
    $mensaje = $_SESSION['error'];
    $mensaje_tipo = 'danger';
    unset($_SESSION['error']);
}

// Obtener pedidos del cliente
try {
    $query = "SELECT p.*, 
              COALESCE(pg.estado, 'sin_pago') as estado_pago,
              pg.fecha_pago,
              ue.direccion_entrega,
              ue.latitud,
              ue.longitud
              FROM pedidos p
              LEFT JOIN pagos pg ON p.id_pedido = pg.id_pedido
              LEFT JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
              WHERE p.id_cliente = ?
              ORDER BY p.fecha DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $mensaje = "Error al cargar pedidos: " . $e->getMessage();
    $mensaje_tipo = 'danger';
}
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Mis Pedidos"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>📦 Mis Pedidos</h2>
            </div>

            <?php if ($mensaje): ?>
            <script>
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        showAlert('<?php echo addslashes($mensaje); ?>', '<?php echo $mensaje_tipo; ?>', 5000);
                    });
                } else {
                    showAlert('<?php echo addslashes($mensaje); ?>', '<?php echo $mensaje_tipo; ?>', 5000);
                }
            </script>
            <?php endif; ?>

            <div class="row">
                <?php if (empty($pedidos)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h5>No tienes pedidos aún</h5>
                        <p class="text-muted">Realiza tu primer pedido para comenzar</p>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($pedidos as $pedido): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Pedido #<?php echo $pedido['id_pedido']; ?></strong>
                            <span class="badge bg-<?php echo [
                                'sin_pago' => 'danger',
                                'pagado' => 'success',
                                'pendiente' => 'warning',
                                'en_camino' => 'info',
                                'entregado' => 'secondary'
                            ][$pedido['estado_pago']] ?? 'secondary'; ?>">
                                <?php echo strtoupper(str_replace('_', ' ', $pedido['estado_pago'])); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted">Fecha:</small>
                                <strong><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></strong>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">Total:</small>
                                <strong class="text-success">$<?php echo number_format($pedido['total'], 2); ?></strong>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">Estado de Entrega:</small>
                                <span class="badge bg-info">
                                    <?php echo ucfirst(str_replace('_', ' ', $pedido['estado_entrega'])); ?>
                                </span>
                            </div>

                            <?php if ($pedido['direccion_entrega']): ?>
                            <div class="mb-3">
                                <small class="text-muted">Dirección:</small>
                                <p class="mb-0 text-muted small"><?php echo htmlspecialchars($pedido['direccion_entrega']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($pedido['estado_pago'] === 'sin_pago'): ?>
                            <div class="alert alert-warning alert-sm py-1 mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Este pedido requiere pago
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <div class="d-grid gap-2">
                                <?php if ($pedido['estado_pago'] === 'pagado' && !$pedido['direccion_entrega']): ?>
                                <a href="establecer_ubicacion.php?id_pedido=<?php echo $pedido['id_pedido']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-map-marked-alt me-1"></i> Establecer Ubicación
                                </a>
                                <?php elseif ($pedido['estado_pago'] === 'pagado' && $pedido['direccion_entrega']): ?>
                                <div class="btn-group w-100" role="group">
                                    <a href="establecer_ubicacion.php?id_pedido=<?php echo $pedido['id_pedido']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit me-1"></i> Editar Ubicación
                                    </a>
                                    <?php if ($pedido['estado_entrega'] === 'en_destino'): ?>
                                    <button onclick="marcarEntregado(<?php echo $pedido['id_pedido']; ?>)" 
                                            class="btn btn-success btn-sm">
                                        <i class="fas fa-check me-1"></i> Marcar Entregado
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <a href="detalle_pedido.php?id_pedido=<?php echo $pedido['id_pedido']; ?>" 
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-eye me-1"></i> Ver Detalle
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
async function marcarEntregado(idPedido) {
    if (!confirm('¿Confirmas que has recibido el pedido?')) {
        return;
    }

    try {
        const response = await fetch('../procesos/api_pedidos.php?action=marcar_entregado', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_pedido: idPedido,
                id_cliente: <?php echo $user_id; ?>
            })
        });

        const result = await response.json();

        if (result.success) {
            showAlert('Pedido marcado como entregado', 'success', 3000);
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('Error: ' + result.message, 'danger', 5000);
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error al marcar como entregado', 'danger', 5000);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>