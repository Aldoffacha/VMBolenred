<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

if (isset($_SESSION['user_id'])) {
    $id_cliente = $_SESSION['user_id'];
} elseif (isset($_SESSION['usuario']['id_cliente'])) {
    $id_cliente = $_SESSION['usuario']['id_cliente'];
} else {
    header('Location: ../../public/login.php');
    exit;
}

if (!isset($_GET['id_pedido'])) {
    header('Location: mis_pedidos.php');
    exit;
}

$id_pedido = (int) $_GET['id_pedido'];

/* =========================
   VALIDAR PEDIDO DEL CLIENTE - CORREGIDO
========================= */
$queryPedido = "
    SELECT p.*, 
           COALESCE(pg.estado, 'sin_pago') AS estado_pago,
           p.estado AS estado_pedido,  -- ¡ESTA ES LA CLAVE!
           ue.direccion_entrega,
           ue.latitud,
           ue.longitud
    FROM pedidos p
    LEFT JOIN pagos pg ON p.id_pedido = pg.id_pedido
    LEFT JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
    WHERE p.id_pedido = ? AND p.id_cliente = ?
";

$stmt = $db->prepare($queryPedido);
$stmt->execute([$id_pedido, $id_cliente]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    $_SESSION['error'] = 'Pedido no encontrado';
    header('Location: mis_pedidos.php');
    exit;
}

// DEBUG: Verificar qué estados están llegando
error_log("DEBUG - Estado pedido: " . $pedido['estado_pedido'] . " | Estado pago: " . $pedido['estado_pago']);

/* =========================
   PRODUCTOS DEL PEDIDO
========================= */
$queryProductos = "
    SELECT d.cantidad,
           d.precio,
           p.nombre,
           p.imagen
    FROM pedido_detalles d
    LEFT JOIN productos p ON d.id_producto = p.id_producto
    WHERE d.id_pedido = ?
";

$stmt = $db->prepare($queryProductos);
$stmt->execute([$id_pedido]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para verificar si el pedido está pagado (verifica AMBAS tablas)
function estaPagado($pedido) {
    // Verificar estado en tabla pedidos (lo que actualiza el admin)
    $estado_pedido = strtolower(trim($pedido['estado_pedido'] ?? ''));
    
    // Verificar estado en tabla pagos (si existe)
    $estado_pago = strtolower(trim($pedido['estado_pago'] ?? ''));
    
    // Si el estado del pedido es 'pagado' O el estado del pago es 'pagado'
    return ($estado_pedido === 'pagado' || $estado_pago === 'pagado' || $estado_pago === 'confirmado');
}

$esta_pagado = estaPagado($pedido);
?>

<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Detalle del Pedido"; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="pt-3 pb-2 mb-3 border-bottom">
                <h3>🧾 Detalle del Pedido #<?php echo $id_pedido; ?></h3>
            </div>

            <!-- INFO GENERAL -->
            <div class="card mb-4">
                <div class="card-body">
                    <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></p>
                    <p><strong>Total:</strong> $<?php echo number_format($pedido['total'], 2); ?></p>
                    <p>
                        <strong>Estado del pedido:</strong>
                        <span class="badge bg-<?php echo strtolower($pedido['estado_pedido']) === 'pagado' ? 'success' : (strtolower($pedido['estado_pedido']) === 'pendiente' ? 'warning' : 'info'); ?>">
                            <?php echo strtoupper($pedido['estado_pedido']); ?>
                        </span>
                    </p>
                    <?php if ($pedido['estado_pago'] && $pedido['estado_pago'] !== 'sin_pago'): ?>
                    <p>
                        <strong>Estado de pago:</strong>
                        <span class="badge bg-<?php echo strtolower($pedido['estado_pago']) === 'pagado' || strtolower($pedido['estado_pago']) === 'confirmado' ? 'success' : 'warning'; ?>">
                            <?php echo strtoupper($pedido['estado_pago']); ?>
                        </span>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PRODUCTOS -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Productos del pedido</strong>
                </div>
                <div class="card-body">
                    <?php if (empty($productos)): ?>
                        <p class="text-muted">No hay productos registrados.</p>
                    <?php else: ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $prod): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prod['nombre'] ?? 'Producto sin nombre'); ?></td>
                                    <td><?php echo (int)$prod['cantidad']; ?></td>
                                    <td>$<?php echo number_format($prod['precio'], 2); ?></td>
                                    <td>$<?php echo number_format(($prod['cantidad'] ?? 0) * ($prod['precio'] ?? 0), 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- UBICACIÓN - VERIFICA CON LA NUEVA FUNCIÓN -->
            <?php if ($esta_pagado): ?>
            <div class="card mb-4">
                <div class="card-body text-center">
                    <?php if (!empty($pedido['direccion_entrega']) && !empty($pedido['latitud']) && !empty($pedido['longitud'])): ?>
                        <p class="text-success">
                            ✅ Ubicación registrada correctamente
                        </p>
                        <div class="mt-2">
                            <strong>Dirección:</strong><br>
                            <small><?php echo htmlspecialchars($pedido['direccion_entrega']); ?></small>
                        </div>
                        <div class="mt-3">
                            <a href="establecer_ubicacion.php?id_pedido=<?php echo $id_pedido; ?>"
                               class="btn btn-outline-primary">
                                Editar ubicación
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-warning">
                            ⚠️ Aún no has registrado la ubicación de entrega. Esto es necesario para que el empleado pueda entregarlo.
                        </p>
                        <a href="establecer_ubicacion.php?id_pedido=<?php echo $id_pedido; ?>"
                           class="btn btn-primary">
                            Establecer ubicación
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                ℹ️ Para establecer la ubicación de entrega, primero debes completar el pago del pedido.<br>
                <small>
                    Estado del pedido: <strong><?php echo $pedido['estado_pedido']; ?></strong> | 
                    Estado de pago: <strong><?php echo $pedido['estado_pago']; ?></strong>
                </small>
            </div>
            <?php endif; ?>

            <a href="mis_pedidos.php" class="btn btn-secondary">
                ← Volver a mis pedidos
            </a>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>