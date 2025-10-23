<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
$error = '';

// Verificar que el carrito no esté vacío
$stmt = $db->prepare("SELECT COUNT(*) as total FROM carrito WHERE id_cliente = ?");
$stmt->execute([$user_id]);
$total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

if ($total_items == 0) {
    header('Location: carrito.php');
    exit;
}

// Obtener items del carrito
$stmt = $db->prepare("
    SELECT c.*, p.nombre, p.precio, p.stock 
    FROM carrito c 
    JOIN productos p ON c.id_producto = p.id_producto 
    WHERE c.id_cliente = ?
");
$stmt->execute([$user_id]);
$carrito = $stmt->fetchAll();

// Calcular total
$total = 0;
foreach ($carrito as $item) {
    $total += $item['precio'] * $item['cantidad'];
}
$total_con_impuestos = $total * 1.13;

// Procesar pago - VERIFICAR SI EXISTE LA CLAVE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['procesar_pago'])) {
    try {
        $db->beginTransaction();

        // 1. Verificar stock
        foreach ($carrito as $item) {
            if ($item['cantidad'] > $item['stock']) {
                throw new Exception("No hay suficiente stock de: " . $item['nombre']);
            }
        }

        // 2. Crear pedido
        $stmt = $db->prepare("INSERT INTO pedidos (id_cliente, total, estado) VALUES (?, ?, 'pendiente')");
        $stmt->execute([$user_id, $total_con_impuestos]);
        $id_pedido = $db->lastInsertId();

        // 3. Agregar detalles del pedido y actualizar stock
        foreach ($carrito as $item) {
            $stmt = $db->prepare("INSERT INTO pedido_detalles (id_pedido, id_producto, cantidad, precio) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_pedido, $item['id_producto'], $item['cantidad'], $item['precio']]);
            
            // Actualizar stock
            $stmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id_producto = ?");
            $stmt->execute([$item['cantidad'], $item['id_producto']]);
        }

        // 4. Crear pago con QR
        $codigo_qr = 'VMB' . strtoupper(uniqid());
        $stmt = $db->prepare("INSERT INTO pagos (id_pedido, monto, metodo, codigo_qr, estado) VALUES (?, ?, 'qr', ?, 'pendiente')");
        $stmt->execute([$id_pedido, $total_con_impuestos, $codigo_qr]);

        // 5. Vaciar carrito
        $stmt = $db->prepare("DELETE FROM carrito WHERE id_cliente = ?");
        $stmt->execute([$user_id]);

        // 6. Registrar en auditoría
        $stmt = $db->prepare("INSERT INTO auditoria (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, tipo_usuario) VALUES (?, ?, 'INSERT', ?, ?, 'cliente')");
        $stmt->execute(['pedidos', $id_pedido, "Pedido creado #$id_pedido", $user_id]);

        $db->commit();

        header('Location: confirmacion_pago.php?id_pedido=' . $id_pedido . '&qr=' . $codigo_qr);
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = "❌ Error: " . $e->getMessage();
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
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Resumen de Compra</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Precio</th>
                                            <th>Cantidad</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($carrito as $item): ?>
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
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <strong>Subtotal:</strong> $<?php echo number_format($total, 2); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Impuestos (13%):</strong> $<?php echo number_format($total * 0.13, 2); ?>
                                </div>
                                <div class="col-md-12 mt-2">
                                    <h4 class="text-success">Total: $<?php echo number_format($total_con_impuestos, 2); ?></h4>
                                </div>
                            </div>
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
                                        <option value="transferencia">🏦 Transferencia</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Información de Envío</label>
                                    <?php
                                    $stmt = $db->prepare("SELECT direccion FROM clientes WHERE id_cliente = ?");
                                    $stmt->execute([$user_id]);
                                    $cliente = $stmt->fetch();
                                    ?>
                                    <textarea class="form-control" rows="3" readonly><?php echo htmlspecialchars($cliente['direccion'] ?? 'No especificada'); ?></textarea>
                                    <small class="text-muted">Actualiza tu dirección en tu perfil</small>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" required id="terminos">
                                    <label class="form-check-label" for="terminos">
                                        Acepto los términos y condiciones
                                    </label>
                                </div>
                                
                                <button type="submit" name="procesar_pago" value="1" class="btn btn-success w-100 btn-lg">
                                    💳 Confirmar y Pagar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>