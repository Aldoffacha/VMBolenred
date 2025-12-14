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

if (isset($_SESSION['error'])) {
    $mensaje = $_SESSION['error'];
    $mensaje_tipo = 'danger';
    unset($_SESSION['error']);
}

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
    $mensaje = "Error al cargar pedidos";
    $mensaje_tipo = 'danger';
}
?>

<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Mis Pedidos"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="pt-3 pb-2 mb-3 border-bottom">
                <h2>Mis Pedidos</h2>
            </div>

            <?php if ($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    showAlert('<?php echo addslashes($mensaje); ?>', '<?php echo $mensaje_tipo; ?>', 5000);
                });
            </script>
            <?php endif; ?>

            <div class="row">
                <?php if (empty($pedidos)): ?>
                    <div class="col-12 text-center py-5">
                        <h5>No tienes pedidos aún</h5>
                        <p class="text-muted">Realiza tu primer pedido para comenzar</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
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
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></p>
                                <p><strong>Total:</strong> $<?php echo number_format($pedido['total'], 2); ?></p>

                                <?php if ($pedido['direccion_entrega']): ?>
                                <p class="text-muted small">
                                    <strong>Dirección:</strong><br>
                                    <?php echo htmlspecialchars($pedido['direccion_entrega']); ?>
                                </p>
                                <?php endif; ?>
                            </div>

                            <div class="card-footer d-grid gap-2">
                                <?php if ($pedido['estado_pago'] === 'pagado'): ?>
                                    <a href="establecer_ubicacion.php?id_pedido=<?php echo $pedido['id_pedido']; ?>"
                                       class="btn btn-primary btn-sm">
                                        Establecer / Editar Ubicación
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-warning py-1 mb-0 text-center">
                                        Pendiente de pago
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
