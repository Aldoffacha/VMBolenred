<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
$error = '';

// Función para calcular costo de importación
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

// Verificar que el carrito no esté vacío (local + externo)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM carrito WHERE id_cliente = ?");
$stmt->execute([$user_id]);
$total_local = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM carrito_externo WHERE id_cliente = ? AND estado = 'pendiente'");
$stmt->execute([$user_id]);
$total_externo = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$total_items = $total_local + $total_externo;

if ($total_items == 0) {
    header('Location: carrito.php');
    exit;
}

// Obtener items del carrito LOCAL
$stmt = $db->prepare("
    SELECT c.*, p.nombre, p.precio, p.stock, p.descripcion, p.categoria
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

// Calcular totales
$subtotal_local = 0;
$subtotal_externo = 0;

foreach ($carrito_local as $item) {
    $subtotal_local += $item['precio'] * $item['cantidad'];
}

foreach ($carrito_externo as $item) {
    $costo_importacion = calcularCostoImportacion($item['precio'], $item['peso'], $item['categoria']);
    $subtotal_externo += $costo_importacion['total'] * $item['cantidad'];
}

$subtotal = $subtotal_local + $subtotal_externo;
$impuesto = $subtotal * 0.13;
$envio = $total_items > 0 ? 50.00 : 0;
$total_con_impuestos = $subtotal + $impuesto + $envio;

// Procesar pago
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['procesar_pago'])) {
    try {
        $db->beginTransaction();

        // 1. Verificar stock para productos locales
        foreach ($carrito_local as $item) {
            if ($item['cantidad'] > $item['stock']) {
                throw new Exception("No hay suficiente stock de: " . $item['nombre']);
            }
        }

        // 2. Crear pedido (SIN tipo_pedido por ahora)
        $stmt = $db->prepare("INSERT INTO pedidos (id_cliente, total, estado) VALUES (?, ?, 'pendiente')");
        $stmt->execute([$user_id, $total_con_impuestos]);
        $id_pedido = $db->lastInsertId();

        // 3. Agregar detalles del pedido para productos LOCALES y actualizar stock
        foreach ($carrito_local as $item) {
            $stmt = $db->prepare("INSERT INTO pedido_detalles (id_pedido, id_producto, cantidad, precio) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_pedido, $item['id_producto'], $item['cantidad'], $item['precio']]);
            
            // Actualizar stock
            $stmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id_producto = ?");
            $stmt->execute([$item['cantidad'], $item['id_producto']]);
        }

        // 4. Agregar detalles del pedido para productos EXTERNOS
        foreach ($carrito_externo as $item) {
            $costo_importacion = calcularCostoImportacion($item['precio'], $item['peso'], $item['categoria']);
            
            // Insertar producto externo (id_producto puede ser NULL)
            $stmt = $db->prepare("INSERT INTO pedido_detalles (id_pedido, id_producto, cantidad, precio) VALUES (?, NULL, ?, ?)");
            $stmt->execute([$id_pedido, $item['cantidad'], $costo_importacion['total']]);
            
            // Actualizar estado del carrito externo a "procesado"
            $stmt = $db->prepare("UPDATE carrito_externo SET estado = 'procesado' WHERE id_carrito_externo = ?");
            $stmt->execute([$item['id_carrito_externo']]);
        }

        // 5. Crear pago con QR
        $codigo_qr = 'VMB' . strtoupper(uniqid());
        $stmt = $db->prepare("INSERT INTO pagos (id_pedido, monto, metodo, codigo_qr, estado) VALUES (?, ?, 'qr', ?, 'pendiente')");
        $metodo_pago = $_POST['metodo_pago'] ?? 'qr';
        $stmt->execute([$id_pedido, $total_con_impuestos, $codigo_qr]);

        // 6. Vaciar carritos
        $stmt = $db->prepare("DELETE FROM carrito WHERE id_cliente = ?");
        $stmt->execute([$user_id]);

        // 7. Registrar en auditoría (si existe la tabla)
        try {
            $stmt = $db->prepare("INSERT INTO auditoria (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, tipo_usuario) VALUES (?, ?, 'INSERT', ?, ?, 'cliente')");
            $detalles_pedido = "Pedido #$id_pedido - " . count($carrito_local) . " locales, " . count($carrito_externo) . " externos";
            $stmt->execute(['pedidos', $id_pedido, $detalles_pedido, $user_id]);
        } catch (Exception $e) {
            // Ignorar error si la tabla auditoria no existe
            error_log("Tabla auditoria no disponible: " . $e->getMessage());
        }

        $db->commit();

        header('Location: confirmacion_pago.php?id_pedido=' . $id_pedido . '&qr=' . $codigo_qr);
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Procesar Pago"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>💳 Procesar Pago</h2>
                <div>
                    <?php if ($total_local > 0): ?>
                    <span class="badge bg-primary"><?php echo $total_local; ?> locales</span>
                    <?php endif; ?>
                    <?php if ($total_externo > 0): ?>
                    <span class="badge bg-warning"><?php echo $total_externo; ?> externos</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($error): ?>
            <script>
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        showError('<?php echo addslashes($error); ?>', 5000);
                    });
                } else {
                    showError('<?php echo addslashes($error); ?>', 5000);
                }
            </script>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Resumen de Compra</h5>
                        </div>
                        <div class="card-body">
                            <!-- Productos Locales -->
                            <?php if (!empty($carrito_local)): ?>
                            <div class="mb-4">
                                <h6 class="text-primary">🏪 Productos Locales</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Precio</th>
                                                <th>Cantidad</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($carrito_local as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                                <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                                <td><?php echo $item['cantidad']; ?></td>
                                                <td>$<?php echo number_format($item['precio'] * $item['cantidad'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Productos Externos -->
                            <?php if (!empty($carrito_externo)): ?>
                            <div class="mb-4">
                                <h6 class="text-warning">🌐 Productos Externos (Amazon/eBay)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Plataforma</th>
                                                <th>Precio Base</th>
                                                <th>Cantidad</th>
                                                <th>Total con Importación</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($carrito_externo as $item): 
                                                $costo_importacion = calcularCostoImportacion($item['precio'], $item['peso'], $item['categoria']);
                                                $badge_class = $item['plataforma'] === 'amazon' ? 'bg-warning text-dark' : 'bg-info';
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($item['nombre']); ?>
                                                    <span class="badge <?php echo $badge_class; ?> ms-1">
                                                        <?php echo $item['plataforma']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo ucfirst($item['plataforma']); ?></small>
                                                </td>
                                                <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                                <td><?php echo $item['cantidad']; ?></td>
                                                <td class="text-success">
                                                    <strong>$<?php echo number_format($costo_importacion['total'] * $item['cantidad'], 2); ?></strong>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Resumen de Costos -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <strong>Subtotal Locales:</strong> $<?php echo number_format($subtotal_local, 2); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Subtotal Externos:</strong> $<?php echo number_format($subtotal_externo, 2); ?>
                                </div>
                                <div class="col-md-12 mt-2">
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Subtotal:</strong> $<?php echo number_format($subtotal, 2); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Impuestos (13%):</strong> $<?php echo number_format($impuesto, 2); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Envío:</strong> $<?php echo number_format($envio, 2); ?>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="text-center">
                                        <h4 class="text-success">Total: $<?php echo number_format($total_con_impuestos, 2); ?></h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Información de Importación para productos externos -->
                            <?php if (!empty($carrito_externo)): ?>
                            <div class="alert alert-info mt-3">
                                <h6>📦 Información de Importación</h6>
                                <small class="text-muted">
                                    Los productos externos incluyen todos los costos de importación:
                                    <ul class="mb-0">
                                        <li>Precio original del producto</li>
                                        <li>Flete marítimo internacional</li>
                                        <li>Seguro (2% del valor)</li>
                                        <li>Impuestos de aduana</li>
                                        <li>Costos de almacenaje y manejo</li>
                                    </ul>
                                    <strong>Tiempo estimado de entrega para productos externos: 15-30 días</strong>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Método de Pago</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Seleccionar Método</label>
                                    <select class="form-select" name="metodo_pago" required>
                                        <option value="qr">📱 Pago QR</option>
                                        <option value="efectivo">💵 Efectivo</option>
                                        <option value="transferencia">🏦 Transferencia Bancaria</option>
                                        <option value="tarjeta">💳 Tarjeta de Crédito/Débito</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Información de Envío</label>
                                    <?php
                                    $stmt = $db->prepare("SELECT direccion, telefono FROM clientes WHERE id_cliente = ?");
                                    $stmt->execute([$user_id]);
                                    $cliente = $stmt->fetch();
                                    ?>
                                    <textarea class="form-control" rows="3" readonly><?php echo htmlspecialchars($cliente['direccion'] ?? 'No especificada'); ?></textarea>
                                    <small class="text-muted">
                                        Teléfono: <?php echo htmlspecialchars($cliente['telefono'] ?? 'No especificado'); ?><br>
                                        <a href="perfil.php" class="text-primary">Actualizar información en tu perfil</a>
                                    </small>
                                </div>

                                <!-- Información de Tiempos de Entrega -->
                                <div class="mb-3">
                                    <label class="form-label">📅 Tiempos de Entrega Estimados</label>
                                    <div class="small text-muted">
                                        <?php if (!empty($carrito_local) && !empty($carrito_externo)): ?>
                                        <div class="alert alert-warning p-2">
                                            <strong>Pedido Mixto:</strong><br>
                                            • Productos locales: 3-5 días<br>
                                            • Productos externos: 15-30 días<br>
                                            <em>Los recibirás en envíos separados</em>
                                        </div>
                                        <?php elseif (!empty($carrito_local)): ?>
                                        <div class="alert alert-success p-2">
                                            <strong>Entrega rápida:</strong> 3-5 días hábiles
                                        </div>
                                        <?php elseif (!empty($carrito_externo)): ?>
                                        <div class="alert alert-info p-2">
                                            <strong>Importación internacional:</strong> 15-30 días hábiles
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" required id="terminos">
                                    <label class="form-check-label" for="terminos">
                                        Acepto los <a href="#" class="text-primary">términos y condiciones</a> y 
                                        <a href="#" class="text-primary">políticas de importación</a>
                                    </label>
                                </div>
                                
                                <button type="submit" name="procesar_pago" value="1" class="btn btn-success w-100 btn-lg">
                                    💳 Confirmar y Pagar $<?php echo number_format($total_con_impuestos, 2); ?>
                                </button>

                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-lock me-1"></i>Tu información de pago está protegida
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resumen Rápido -->
                    <div class="card mt-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">📊 Resumen Rápido</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Productos locales:</span>
                                <span><?php echo $total_local; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Productos externos:</span>
                                <span><?php echo $total_externo; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Total productos:</span>
                                <strong><?php echo $total_items; ?></strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total a pagar:</strong>
                                <strong class="text-success">$<?php echo number_format($total_con_impuestos, 2); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>