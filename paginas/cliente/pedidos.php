<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];

// Obtener pedidos del cliente
$stmt = $db->prepare("
    SELECT p.*, e.estado as estado_envio, e.guia_aerea, e.fecha_entrega_cliente
    FROM pedidos p 
    LEFT JOIN envios_importacion e ON p.id_pedido = e.id_pedido 
    WHERE p.id_cliente = ? 
    ORDER BY p.fecha DESC
");
$stmt->execute([$user_id]);
$pedidos = $stmt->fetchAll();

// Procesar cancelación de pedido - VERIFICAR SI EXISTE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancelar_pedido']) && isset($_POST['id_pedido'])) {
    $id_pedido = intval($_POST['id_pedido']);
    
    // Verificar que el pedido sea del cliente y esté pendiente
    $stmt = $db->prepare("SELECT * FROM pedidos WHERE id_pedido = ? AND id_cliente = ? AND estado = 'pendiente'");
    $stmt->execute([$id_pedido, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        $stmt = $db->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id_pedido = ?");
        $stmt->execute([$id_pedido]);
        header('Location: pedidos.php?msg=cancelado');
        exit;
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Mis Pedidos"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>📋 Mis Pedidos</h2>
                <span class="badge bg-primary"><?php echo count($pedidos); ?> pedidos</span>
            </div>

            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'cancelado'): ?>
            <script>
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        showSuccess('Pedido cancelado correctamente', 5000);
                    });
                } else {
                    showSuccess('Pedido cancelado correctamente', 5000);
                }
            </script>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($pedidos)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h5>No tienes pedidos aún</h5>
                        <p class="text-muted">Realiza tu primer pedido desde la tienda</p>
                        <a href="tienda.php" class="btn btn-primary">Ir a la Tienda</a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th># Pedido</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Seguimiento</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td><strong>#VM<?php echo $pedido['id_pedido']; ?></strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></td>
                                    <td>$<?php echo number_format($pedido['total'], 2); ?></td>
                                    <td>
                                        <?php 
                                        $badge_class = [
                                            'pendiente' => 'bg-warning',
                                            'pagado' => 'bg-info',
                                            'enviado' => 'bg-primary', 
                                            'entregado' => 'bg-success',
                                            'cancelado' => 'bg-danger'
                                        ][$pedido['estado']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($pedido['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($pedido['guia_aerea']): ?>
                                        <small>Guía: <?php echo $pedido['guia_aerea']; ?></small>
                                        <?php else: ?>
                                        <small class="text-muted">Sin envío</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="seguimiento.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-sm btn-outline-primary">
                                            👁️ Ver
                                        </a>
                                        <?php if ($pedido['estado'] == 'pendiente'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                            <button type="submit" name="cancelar_pedido" value="1" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('¿Cancelar este pedido?')">
                                                Cancelar
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>