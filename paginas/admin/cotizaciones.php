<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    header('Location: ../public/login.php');
    exit;
}

$db = (new Database())->getConnection();

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'actualizar_estado':
                $id = $_POST['id_cotizacion'];
                $estado = $_POST['estado'];
                
                $stmt = $db->prepare("UPDATE cotizaciones SET estado=? WHERE id_cotizacion=?");
                $stmt->execute([$estado, $id]);
                break;
                
            case 'eliminar':
                $id = $_POST['id_cotizacion'];
                $stmt = $db->prepare("DELETE FROM cotizaciones WHERE id_cotizacion=?");
                $stmt->execute([$id]);
                break;
        }
    }
}

// Obtener cotizaciones con información de cliente
$cotizaciones = $db->query("
    SELECT c.*, cl.nombre as cliente_nombre, cl.correo as cliente_email 
    FROM cotizaciones c 
    JOIN clientes cl ON c.id_cliente = cl.id_cliente 
    ORDER BY c.fecha DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cotizaciones - VMBol en Red</title>
    <!-- Bootstrap PRIMERO -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tus CSS DESPUÉS (para que sobrescriban a Bootstrap) -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-dashboard">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Cotizaciones</h1>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Producto</th>
                                <th>Precio Base</th>
                                <th>Costos Adicionales</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cotizaciones as $cotizacion): 
                                $badgeClass = [
                                    'pendiente' => 'bg-warning',
                                    'aprobada' => 'bg-success',
                                    'rechazada' => 'bg-danger'
                                ][$cotizacion['estado']] ?? 'bg-secondary';
                            ?>
                            <tr>
                                <td>#<?php echo $cotizacion['id_cotizacion']; ?></td>
                                <td>
                                    <div><strong><?php echo htmlspecialchars($cotizacion['cliente_nombre']); ?></strong></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($cotizacion['cliente_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($cotizacion['nombre_producto']); ?></td>
                                <td>$<?php echo number_format($cotizacion['precio_base'], 2); ?></td>
                                <td>
                                    <small>Flete: $<?php echo number_format($cotizacion['costo_flete'], 2); ?><br>
                                    Aduana: $<?php echo number_format($cotizacion['costo_aduana'], 2); ?><br>
                                    Seguro: $<?php echo number_format($cotizacion['costo_seguro'], 2); ?></small>
                                </td>
                                <td><strong>$<?php echo number_format($cotizacion['costo_total'], 2); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($cotizacion['estado']); ?></span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($cotizacion['fecha'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalDetalle<?php echo $cotizacion['id_cotizacion']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success btn-estado" 
                                                data-id="<?php echo $cotizacion['id_cotizacion']; ?>"
                                                data-estado="aprobada">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-estado" 
                                                data-id="<?php echo $cotizacion['id_cotizacion']; ?>"
                                                data-estado="rechazada">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-dark btn-eliminar" 
                                                data-id="<?php echo $cotizacion['id_cotizacion']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal Detalle -->
                            <div class="modal fade" id="modalDetalle<?php echo $cotizacion['id_cotizacion']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Detalle de Cotización #<?php echo $cotizacion['id_cotizacion']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Información del Cliente</h6>
                                                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($cotizacion['cliente_nombre']); ?><br>
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($cotizacion['cliente_email']); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Información del Producto</h6>
                                                    <p><strong>Producto:</strong> <?php echo htmlspecialchars($cotizacion['nombre_producto']); ?><br>
                                                    <strong>Categoría:</strong> <?php echo htmlspecialchars($cotizacion['categoria']); ?><br>
                                                    <strong>Tamaño:</strong> <?php echo htmlspecialchars($cotizacion['tamano']); ?><br>
                                                    <strong>Peso:</strong> <?php echo $cotizacion['peso']; ?> kg</p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <h6>Desglose de Costos</h6>
                                                    <table class="table table-sm">
                                                        <tr>
                                                            <td>Precio Base del Producto</td>
                                                            <td class="text-end">$<?php echo number_format($cotizacion['precio_base'], 2); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td>Costo de Flete</td>
                                                            <td class="text-end">$<?php echo number_format($cotizacion['costo_flete'], 2); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td>Costo de Aduana</td>
                                                            <td class="text-end">$<?php echo number_format($cotizacion['costo_aduana'], 2); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td>Costo de Almacén</td>
                                                            <td class="text-end">$<?php echo number_format($cotizacion['costo_almacen'], 2); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td>Costo de Seguro</td>
                                                            <td class="text-end">$<?php echo number_format($cotizacion['costo_seguro'], 2); ?></td>
                                                        </tr>
                                                        <tr class="table-active">
                                                            <td><strong>TOTAL</strong></td>
                                                            <td class="text-end"><strong>$<?php echo number_format($cotizacion['costo_total'], 2); ?></strong></td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        // Cambiar estado de cotización
        document.querySelectorAll('.btn-estado').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('¿Estás seguro de cambiar el estado de esta cotización?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="accion" value="actualizar_estado">
                        <input type="hidden" name="id_cotizacion" value="${this.dataset.id}">
                        <input type="hidden" name="estado" value="${this.dataset.estado}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        // Eliminar cotización
        document.querySelectorAll('.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('¿Estás seguro de eliminar esta cotización?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id_cotizacion" value="${this.dataset.id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    </script>
</body>
</html>