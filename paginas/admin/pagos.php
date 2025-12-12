<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

try { Auth::checkAuth('admin'); } catch (Exception $e) { header('Location: ../public/login.php'); exit; }
$db = (new Database())->getConnection();

// Obtener pagos recientes
$stmt = $db->prepare("SELECT pa.*, pe.id_pedido, pe.id_cliente, pe.total AS pedido_total, c.nombre as cliente_nombre
    FROM pagos pa
    JOIN pedidos pe ON pa.id_pedido = pe.id_pedido
    LEFT JOIN clientes c ON pe.id_cliente = c.id_cliente
    ORDER BY pa.id_pago DESC");
$stmt->execute();
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Pagos - Admin"; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Pagos Pendientes</h1>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID Pago</th>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Comprobante</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pagos)): ?>
                                    <tr><td colspan="8" class="text-center text-muted">No hay pagos</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pagos as $p): ?>
                                        <tr>
                                            <td>#<?php echo $p['id_pago']; ?></td>
                                            <td>#<?php echo $p['id_pedido']; ?></td>
                                            <td><?php echo htmlspecialchars($p['cliente_nombre'] ?? 'Cliente #' . $p['id_cliente']); ?></td>
                                            <td>$<?php echo number_format($p['monto'] ?? $p['pedido_total'],2); ?></td>
                                            <td><?php echo htmlspecialchars(strtoupper($p['metodo'])); ?></td>
                                            <td>
                                                <?php if (!empty($p['comprobante'])): ?>
                                                    <a href="../../uploads/payments/<?php echo htmlspecialchars($p['comprobante']); ?>" target="_blank">
                                                        <img src="../../uploads/payments/<?php echo htmlspecialchars($p['comprobante']); ?>" style="max-width:120px;" alt="comprobante">
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin comprobante</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($p['estado']); ?></td>
                                            <td>
                                                <?php if ($p['estado'] !== 'confirmado'): ?>
                                                    <form method="POST" action="../../procesos/confirmar_pago.php" onsubmit="return confirm('Confirmar pago y marcar pedido como pagado?');">
                                                        <input type="hidden" name="id_pago" value="<?php echo $p['id_pago']; ?>">
                                                        <input type="hidden" name="id_pedido" value="<?php echo $p['id_pedido']; ?>">
                                                        <button class="btn btn-sm btn-success">Confirmar</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-success">Confirmado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
