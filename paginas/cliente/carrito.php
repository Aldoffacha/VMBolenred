<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
$mensaje = '';

// Procesar acciones del carrito - VERIFICAR SI EXISTEN LAS CLAVES
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['actualizar_cantidad']) && isset($_POST['id_carrito'])) {
        $id_carrito = intval($_POST['id_carrito']);
        $cantidad = intval($_POST['cantidad']);
        
        if ($cantidad > 0) {
            $stmt = $db->prepare("UPDATE carrito SET cantidad = ? WHERE id_carrito = ? AND id_cliente = ?");
            $stmt->execute([$cantidad, $id_carrito, $user_id]);
            $mensaje = "✅ Cantidad actualizada";
        } else {
            $stmt = $db->prepare("DELETE FROM carrito WHERE id_carrito = ? AND id_cliente = ?");
            $stmt->execute([$id_carrito, $user_id]);
            $mensaje = "✅ Producto eliminado del carrito";
        }
    }
    elseif (isset($_POST['eliminar_item']) && isset($_POST['id_carrito'])) {
        $id_carrito = intval($_POST['id_carrito']);
        $stmt = $db->prepare("DELETE FROM carrito WHERE id_carrito = ? AND id_cliente = ?");
        $stmt->execute([$id_carrito, $user_id]);
        $mensaje = "✅ Producto eliminado del carrito";
    }
    elseif (isset($_POST['vaciar_carrito'])) {
        $stmt = $db->prepare("DELETE FROM carrito WHERE id_cliente = ?");
        $stmt->execute([$user_id]);
        $mensaje = "✅ Carrito vaciado";
    }
}

// Obtener items del carrito
$stmt = $db->prepare("
    SELECT c.*, p.nombre, p.descripcion, p.precio, p.imagen, p.stock 
    FROM carrito c 
    JOIN productos p ON c.id_producto = p.id_producto 
    WHERE c.id_cliente = ?
");
$stmt->execute([$user_id]);
$carrito = $stmt->fetchAll();

// Calcular totales
$subtotal = 0;
foreach ($carrito as $item) {
    $subtotal += $item['precio'] * $item['cantidad'];
}
$impuesto = $subtotal * 0.13;
$envio = count($carrito) > 0 ? 50.00 : 0;
$total = $subtotal + $impuesto + $envio;
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Mi Carrito"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>🛒 Mi Carrito de Compras</h2>
                <span class="badge bg-primary"><?php echo count($carrito); ?> productos</span>
            </div>

            <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Productos en el Carrito</h5>
                            <?php if (!empty($carrito)): ?>
                            <form method="POST">
                                <button type="submit" name="vaciar_carrito" value="1" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('¿Vaciar todo el carrito?')">
                                    🗑️ Vaciar Carrito
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($carrito)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <h5>Tu carrito está vacío</h5>
                                <p class="text-muted">Agrega productos desde la tienda</p>
                                <a href="tienda.php" class="btn btn-primary">Ir a la Tienda</a>
                            </div>
                            <?php else: ?>
                            <?php foreach ($carrito as $item): ?>
                            <div class="row align-items-center mb-3 border-bottom pb-3">
                                <div class="col-md-2">
                                    <!-- IMAGEN MEJORADA - Sin placeholder que se carga eternamente -->
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                        <i class="fas fa-box text-muted fa-2x"></i>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['nombre']); ?></h6>
                                    <small class="text-muted"><?php echo substr($item['descripcion'] ?? 'Sin descripción', 0, 50); ?>...</small>
                                    <div class="mt-1">
                                        <strong class="text-primary">$<?php echo number_format($item['precio'], 2); ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <form method="POST" class="d-flex align-items-center">
                                        <input type="hidden" name="id_carrito" value="<?php echo $item['id_carrito']; ?>">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                onclick="actualizarCantidad(<?php echo $item['id_carrito']; ?>, -1)">-</button>
                                        <input type="number" name="cantidad" value="<?php echo $item['cantidad']; ?>" 
                                               min="1" max="<?php echo $item['stock']; ?>" 
                                               class="form-control form-control-sm mx-2 text-center" 
                                               id="cantidad_<?php echo $item['id_carrito']; ?>">
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                                onclick="actualizarCantidad(<?php echo $item['id_carrito']; ?>, 1)">+</button>
                                        <input type="hidden" name="actualizar_cantidad" value="1">
                                    </form>
                                    <small class="text-muted">Stock: <?php echo $item['stock']; ?></small>
                                </div>
                                <div class="col-md-3 text-end">
                                    <strong class="text-primary">$<?php echo number_format($item['precio'] * $item['cantidad'], 2); ?></strong>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="id_carrito" value="<?php echo $item['id_carrito']; ?>">
                                        <button type="submit" name="eliminar_item" value="1" class="btn btn-sm btn-outline-danger ms-2"
                                                onclick="return confirm('¿Eliminar este producto?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Resumen del Pedido</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Impuestos (13%):</span>
                                <span>$<?php echo number_format($impuesto, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Envío:</span>
                                <span>$<?php echo number_format($envio, 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Total:</strong>
                                <strong class="text-success">$<?php echo number_format($total, 2); ?></strong>
                            </div>
                            
                            <?php if (!empty($carrito)): ?>
                            <a href="pagar.php" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-credit-card me-1"></i>Proceder al Pago
                            </a>
                            <?php endif; ?>
                            <a href="tienda.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left me-1"></i>Seguir Comprando
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function actualizarCantidad(idCarrito, cambio) {
    const input = document.getElementById('cantidad_' + idCarrito);
    let nuevaCantidad = parseInt(input.value) + cambio;
    
    if (nuevaCantidad < 1) nuevaCantidad = 1;
    
    // Enviar formulario automáticamente
    const form = input.closest('form');
    input.value = nuevaCantidad;
    form.submit();
}
</script>

<?php include '../../includes/footer.php'; ?>