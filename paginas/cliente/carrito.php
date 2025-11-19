<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
$mensaje = '';

// Procesar acciones del carrito - PARA PRODUCTOS LOCALES
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
        // Vaciar tanto carrito local como externo
        $stmt = $db->prepare("DELETE FROM carrito WHERE id_cliente = ?");
        $stmt->execute([$user_id]);
        
        $stmt = $db->prepare("DELETE FROM carrito_externo WHERE id_cliente = ?");
        $stmt->execute([$user_id]);
        
        $mensaje = "✅ Carrito vaciado completamente";
    }
    // Procesar acciones para productos externos
    elseif (isset($_POST['actualizar_cantidad_externo']) && isset($_POST['id_carrito_externo'])) {
        $id_carrito_externo = intval($_POST['id_carrito_externo']);
        $cantidad = intval($_POST['cantidad']);
        
        if ($cantidad > 0) {
            $stmt = $db->prepare("UPDATE carrito_externo SET cantidad = ? WHERE id_carrito_externo = ? AND id_cliente = ?");
            $stmt->execute([$cantidad, $id_carrito_externo, $user_id]);
            $mensaje = "✅ Cantidad actualizada";
        } else {
            $stmt = $db->prepare("DELETE FROM carrito_externo WHERE id_carrito_externo = ? AND id_cliente = ?");
            $stmt->execute([$id_carrito_externo, $user_id]);
            $mensaje = "✅ Producto externo eliminado del carrito";
        }
    }
    elseif (isset($_POST['eliminar_item_externo']) && isset($_POST['id_carrito_externo'])) {
        $id_carrito_externo = intval($_POST['id_carrito_externo']);
        $stmt = $db->prepare("DELETE FROM carrito_externo WHERE id_carrito_externo = ? AND id_cliente = ?");
        $stmt->execute([$id_carrito_externo, $user_id]);
        $mensaje = "✅ Producto externo eliminado del carrito";
    }
}

// Obtener items del carrito LOCAL
$stmt = $db->prepare("
    SELECT c.*, p.nombre, p.descripcion, p.precio, p.imagen, p.stock 
    FROM carrito c 
    JOIN productos p ON c.id_producto = p.id_producto 
    WHERE c.id_cliente = ?
");
$stmt->execute([$user_id]);
$carrito_local = $stmt->fetchAll();

// Obtener items del carrito EXTERNO
$stmt = $db->prepare("
    SELECT * FROM carrito_externo 
    WHERE id_cliente = ? AND estado = 'pendiente'
");
$stmt->execute([$user_id]);
$carrito_externo = $stmt->fetchAll();

// Combinar contadores
$total_productos = count($carrito_local) + count($carrito_externo);

// Calcular totales para productos LOCALES
$subtotal_local = 0;
foreach ($carrito_local as $item) {
    $subtotal_local += $item['precio'] * $item['cantidad'];
}

// Calcular totales para productos EXTERNOS
$subtotal_externo = 0;
foreach ($carrito_externo as $item) {
    // Para productos externos, calcular costo de importación
    $costo_importacion = calcularCostoImportacion($item['precio'], $item['peso'], $item['categoria']);
    $subtotal_externo += $costo_importacion['total'] * $item['cantidad'];
}

// Totales combinados
$subtotal = $subtotal_local + $subtotal_externo;
$impuesto = $subtotal * 0.13;
$envio = $total_productos > 0 ? 50.00 : 0;
$total = $subtotal + $impuesto + $envio;

// Función para calcular costo de importación (debe estar definida o incluirla)
function calcularCostoImportacion($precio, $peso, $categoria) {
    $impuestos = [
        'electronico' => 0.30, 'ropa' => 0.20, 'hogar' => 0.15, 
        'deportes' => 0.25, 'otros' => 0.18
    ];
    
    $impuesto = $impuestos[$categoria] ?? 0.18;
    $flete_maritimo = max(15, $peso * 3);
    $seguro = $precio * 0.02;
    $aduana = $precio * $impuesto;
    $costo_almacen = 25; 
    
    $costo_total = $precio + $flete_maritimo + $seguro + $aduana + $costo_almacen;
    
    return [
        'total' => $costo_total,
        'desglose' => [
            'producto' => $precio, 'flete' => $flete_maritimo, 
            'seguro' => $seguro, 'aduana' => $aduana, 'almacen' => $costo_almacen
        ]
    ];
}
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Mi Carrito"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>🛒 Mi Carrito de Compras</h2>
                <div>
                    <span class="badge bg-primary"><?php echo count($carrito_local); ?> locales</span>
                    <span class="badge bg-warning"><?php echo count($carrito_externo); ?> externos</span>
                    <span class="badge bg-success"><?php echo $total_productos; ?> total</span>
                </div>
            </div>

            <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <!-- PRODUCTOS LOCALES -->
                    <?php if (!empty($carrito_local)): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                            <h5 class="mb-0">🏪 Productos Locales</h5>
                            <span class="badge bg-light text-dark"><?php echo count($carrito_local); ?> productos</span>
                        </div>
                        <div class="card-body">
                            <?php foreach ($carrito_local as $item): 
                                $subtotal_item = $item['precio'] * $item['cantidad'];
                                $costo_importacion = calcularCostoImportacion($item['precio'], 0.5, 'electronico');
                            ?>
                            <div class="row align-items-center mb-3 border-bottom pb-3">
                                <div class="col-md-2">
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                        <i class="fas fa-box text-primary fa-2x"></i>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['nombre']); ?></h6>
                                    <small class="text-muted"><?php echo substr($item['descripcion'] ?? 'Sin descripción', 0, 50); ?>...</small>
                                    <div class="mt-1">
                                        <strong class="text-primary">$<?php echo number_format($item['precio'], 2); ?></strong>
                                        <small class="text-muted d-block">Total importación: $<?php echo number_format($costo_importacion['total'], 2); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <form method="POST" class="d-flex align-items-center">
                                        <input type="hidden" name="id_carrito" value="<?php echo $item['id_carrito']; ?>">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                onclick="actualizarCantidad(<?php echo $item['id_carrito']; ?>, -1, 'local')">-</button>
                                        <input type="number" name="cantidad" value="<?php echo $item['cantidad']; ?>" 
                                               min="1" max="<?php echo $item['stock']; ?>" 
                                               class="form-control form-control-sm mx-2 text-center" 
                                               id="cantidad_local_<?php echo $item['id_carrito']; ?>">
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                                onclick="actualizarCantidad(<?php echo $item['id_carrito']; ?>, 1, 'local')">+</button>
                                        <input type="hidden" name="actualizar_cantidad" value="1">
                                    </form>
                                    <small class="text-muted">Stock: <?php echo $item['stock']; ?></small>
                                </div>
                                <div class="col-md-3 text-end">
                                    <strong class="text-primary">$<?php echo number_format($subtotal_item, 2); ?></strong>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="id_carrito" value="<?php echo $item['id_carrito']; ?>">
                                        <button type="submit" name="eliminar_item" value="1" class="btn btn-sm btn-outline-danger ms-2"
                                                onclick="return confirm('¿Eliminar este producto local?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- PRODUCTOS EXTERNOS -->
                    <?php if (!empty($carrito_externo)): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center bg-warning text-dark">
                            <h5 class="mb-0">🌐 Productos Externos (Amazon/eBay)</h5>
                            <span class="badge bg-dark"><?php echo count($carrito_externo); ?> productos</span>
                        </div>
                        <div class="card-body">
                            <?php foreach ($carrito_externo as $item): 
                                $costo_importacion = calcularCostoImportacion($item['precio'], $item['peso'], $item['categoria']);
                                $subtotal_item = $costo_importacion['total'] * $item['cantidad'];
                                $badge_class = $item['plataforma'] === 'amazon' ? 'bg-warning text-dark' : 'bg-info';
                                $plataforma_text = $item['plataforma'] === 'amazon' ? 'Amazon' : 'eBay';
                            ?>
                            <div class="row align-items-center mb-3 border-bottom pb-3">
                                <div class="col-md-2">
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                        <i class="fab fa-<?php echo $item['plataforma']; ?> fa-2x text-<?php echo $item['plataforma'] === 'amazon' ? 'warning' : 'info'; ?>"></i>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['nombre']); ?></h6>
                                    <span class="badge <?php echo $badge_class; ?> mb-1"><?php echo $plataforma_text; ?></span>
                                    <div class="mt-1">
                                        <strong class="text-primary">Precio: $<?php echo number_format($item['precio'], 2); ?></strong>
                                        <small class="text-muted d-block">Peso: <?php echo $item['peso']; ?> kg</small>
                                        <small class="text-success d-block">
                                            <strong>Total con importación: $<?php echo number_format($costo_importacion['total'], 2); ?></strong>
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <form method="POST" class="d-flex align-items-center">
                                        <input type="hidden" name="id_carrito_externo" value="<?php echo $item['id_carrito_externo']; ?>">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                onclick="actualizarCantidad(<?php echo $item['id_carrito_externo']; ?>, -1, 'externo')">-</button>
                                        <input type="number" name="cantidad" value="<?php echo $item['cantidad']; ?>" 
                                               min="1" max="10" 
                                               class="form-control form-control-sm mx-2 text-center" 
                                               id="cantidad_externo_<?php echo $item['id_carrito_externo']; ?>">
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                                onclick="actualizarCantidad(<?php echo $item['id_carrito_externo']; ?>, 1, 'externo')">+</button>
                                        <input type="hidden" name="actualizar_cantidad_externo" value="1">
                                    </form>
                                    <small class="text-muted">Máximo: 10 unidades</small>
                                </div>
                                <div class="col-md-3 text-end">
                                    <strong class="text-success">$<?php echo number_format($subtotal_item, 2); ?></strong>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="id_carrito_externo" value="<?php echo $item['id_carrito_externo']; ?>">
                                        <button type="submit" name="eliminar_item_externo" value="1" class="btn btn-sm btn-outline-danger ms-2"
                                                onclick="return confirm('¿Eliminar este producto externo?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php if (!empty($item['url'])): ?>
                                    <a href="<?php echo $item['url']; ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-1" title="Ver en <?php echo $plataforma_text; ?>">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- CARRITO VACÍO -->
                    <?php if (empty($carrito_local) && empty($carrito_externo)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <h5>Tu carrito está vacío</h5>
                            <p class="text-muted">Agrega productos desde la tienda o importa productos de Amazon/eBay</p>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="tienda.php" class="btn btn-primary">Ir a la Tienda Local</a>
                                <button class="btn btn-warning" onclick="window.location.href='index.php'">
                                    <i class="fas fa-globe me-1"></i>Importar Productos
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- BOTÓN VACIAR CARRITO -->
                    <?php if (!empty($carrito_local) || !empty($carrito_externo)): ?>
                    <div class="text-end mt-3">
                        <form method="POST">
                            <button type="submit" name="vaciar_carrito" value="1" class="btn btn-outline-danger"
                                    onclick="return confirm('¿Vaciar todo el carrito? Esto eliminará tanto productos locales como externos.')">
                                🗑️ Vaciar Carrito Completo
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Resumen del Pedido</h5>
                        </div>
                        <div class="card-body">
                            <!-- Desglose de productos locales -->
                            <?php if (!empty($carrito_local)): ?>
                            <div class="mb-3">
                                <h6>🏪 Productos Locales</h6>
                                <?php foreach ($carrito_local as $item): ?>
                                <div class="d-flex justify-content-between small">
                                    <span><?php echo htmlspecialchars($item['nombre']); ?> x<?php echo $item['cantidad']; ?></span>
                                    <span>$<?php echo number_format($item['precio'] * $item['cantidad'], 2); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Desglose de productos externos -->
                            <?php if (!empty($carrito_externo)): ?>
                            <div class="mb-3">
                                <h6>🌐 Productos Externos</h6>
                                <?php foreach ($carrito_externo as $item): 
                                    $costo_importacion = calcularCostoImportacion($item['precio'], $item['peso'], $item['categoria']);
                                ?>
                                <div class="d-flex justify-content-between small">
                                    <span><?php echo htmlspecialchars($item['nombre']); ?> x<?php echo $item['cantidad']; ?></span>
                                    <span>$<?php echo number_format($costo_importacion['total'] * $item['cantidad'], 2); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <hr>
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
                            
                            <?php if ($total_productos > 0): ?>
                            <a href="pagar.php" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-credit-card me-1"></i>Proceder al Pago
                            </a>
                            <?php endif; ?>
                            <div class="d-flex gap-2">
                                <a href="tienda.php" class="btn btn-outline-primary flex-fill">
                                    <i class="fas fa-store me-1"></i>Tienda Local
                                </a>
                                <a href="index.php" class="btn btn-outline-warning flex-fill">
                                    <i class="fas fa-globe me-1"></i>Importar
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Importación -->
                    <?php if (!empty($carrito_externo)): ?>
                    <div class="card mt-3">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">📦 Información de Importación</h6>
                        </div>
                        <div class="card-body">
                            <small class="text-muted">
                                <strong>Los productos externos incluyen:</strong><br>
                                • Precio original del producto<br>
                                • Flete marítimo internacional<br>
                                • Seguro (2% del valor)<br>
                                • Impuestos de aduana<br>
                                • Costos de almacenaje<br><br>
                                <strong>Tiempo estimado de entrega:</strong> 15-30 días
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function actualizarCantidad(id, cambio, tipo) {
    const input = document.getElementById('cantidad_' + tipo + '_' + id);
    let nuevaCantidad = parseInt(input.value) + cambio;
    
    if (nuevaCantidad < 1) nuevaCantidad = 1;
    
    // Límites diferentes según el tipo
    if (tipo === 'local') {
        const maxStock = parseInt(input.getAttribute('max'));
        if (nuevaCantidad > maxStock) nuevaCantidad = maxStock;
    } else {
        // Externo: máximo 10 unidades
        if (nuevaCantidad > 10) nuevaCantidad = 10;
    }
    
    input.value = nuevaCantidad;
    
    // Enviar formulario automáticamente
    const form = input.closest('form');
    form.submit();
}

// Agregar evento para cambios manuales en los inputs
document.addEventListener('DOMContentLoaded', function() {
    // Para productos locales
    document.querySelectorAll('input[name="cantidad"]').forEach(input => {
        input.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) form.submit();
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>