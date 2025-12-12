<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
$id_pedido = intval($_GET['id_pedido']);
$codigo_qr = $_GET['qr'] ?? '';

// Verificar que el pedido pertenezca al cliente
$stmt = $db->prepare("SELECT * FROM pedidos WHERE id_pedido = ? AND id_cliente = ?");
$stmt->execute([$id_pedido, $user_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    header('Location: pedidos.php');
    exit;
}

// Obtener detalles del pedido
$stmt = $db->prepare("
    SELECT pd.*, p.nombre 
    FROM pedido_detalles pd 
    JOIN productos p ON pd.id_producto = p.id_producto 
    WHERE pd.id_pedido = ?
");
$stmt->execute([$id_pedido]);
$detalles = $stmt->fetchAll();

// Obtener información del pago
$stmt = $db->prepare("SELECT * FROM pagos WHERE id_pedido = ?");
$stmt->execute([$id_pedido]);
$pago = $stmt->fetch();
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Confirmación de Pago"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="text-center py-5">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h2>¡Pago Procesado Exitosamente!</h2>
                    <p class="lead">Tu pedido ha sido confirmado y está siendo procesado</p>
                </div>

                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">📦 Detalles del Pedido</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Número de Pedido:</strong> #VM<?php echo $pedido['id_pedido']; ?></p>
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></p>
                                <p><strong>Total:</strong> $<?php echo number_format($pedido['total'], 2); ?></p>
                                <p><strong>Estado:</strong> <span class="badge bg-warning"><?php echo ucfirst($pedido['estado']); ?></span></p>
                                
                                <?php if ($pago && $pago['metodo'] == 'qr'): ?>
                                <div class="mt-3 text-center">
                                    <h6>📱 Código QR para Pago</h6>
                                    <div class="p-3 rounded text-center">
                                        <!-- Mostrar imagen QR general -->
                                        <img src="../../assets/img/qr_general.jpg" alt="QR Banco" class="img-fluid" style="max-width:260px;">
                                        <div class="mt-2">
                                            <strong>Monto a pagar:</strong> $<?php echo number_format($pedido['total'],2); ?>
                                        </div>
                                        <small class="text-muted d-block mt-2">Escanea el QR con tu app bancaria y realiza el pago.</small>
                                    </div>

                                    <?php if ($pago['estado'] == 'pendiente' || empty($pago['comprobante'])): ?>
                                    <div class="mt-3">
                                        <form method="POST" action="../../procesos/subir_comprobante_pago.php" enctype="multipart/form-data">
                                            <input type="hidden" name="id_pago" value="<?php echo $pago['id_pago']; ?>">
                                            <input type="hidden" name="id_pedido" value="<?php echo $id_pedido; ?>">
                                            <div class="mb-3">
                                                <label for="comprobante" class="form-label">Subir comprobante de pago (imagen)</label>
                                                <input type="file" name="comprobante" id="comprobante" accept="image/*" class="form-control" required>
                                            </div>
                                            <button type="submit" name="subir" class="btn btn-primary">📤 Subir comprobante</button>
                                        </form>
                                    </div>
                                    <?php elseif (!empty($pago['comprobante'])): ?>
                                    <div class="mt-3">
                                        <h6>Comprobante subido</h6>
                                        <a href="../../uploads/payments/<?php echo htmlspecialchars($pago['comprobante']); ?>" target="_blank">
                                            <img src="../../uploads/payments/<?php echo htmlspecialchars($pago['comprobante']); ?>" alt="Comprobante" class="img-fluid" style="max-width:260px;">
                                        </a>
                                        <p class="text-muted mt-2">Esperando confirmación del admin.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">📋 Productos</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($detalles as $detalle): ?>
                                <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                    <span><?php echo $detalle['nombre']; ?> (x<?php echo $detalle['cantidad']; ?>)</span>
                                    <span>$<?php echo number_format($detalle['precio'] * $detalle['cantidad'], 2); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="pedidos.php" class="btn btn-primary me-2">👀 Ver Mis Pedidos</a>
                            <a href="tienda.php" class="btn btn-outline-secondary">🛍️ Seguir Comprando</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>