<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/auditoria.class.php';

try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    header('Location: ../../public/login.php');
    exit;
}

$db = (new Database())->getConnection();
$auditoria = new Auditoria($db);

// Obtener información del admin para auditoría
$admin_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;
$ip_address = $_SERVER['REMOTE_ADDR'];

// Procesar cambios de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'actualizar_estado':
                $id = $_POST['id_pedido'];
                $estado = $_POST['estado'];
                
                // DEBUG: Ver qué datos llegan
                error_log("Intentando cambiar pedido #$id a estado: $estado");
                
                // Obtener datos anteriores del pedido
                $stmt_old = $db->prepare("SELECT * FROM pedidos WHERE id_pedido = ?");
                $stmt_old->execute([$id]);
                $pedido_anterior = $stmt_old->fetch(PDO::FETCH_ASSOC);
                
                if (!$pedido_anterior) {
                    $_SESSION['error'] = "Pedido no encontrado";
                    header('Location: pedidos.php');
                    exit;
                }
                
                $stmt = $db->prepare("UPDATE pedidos SET estado = ? WHERE id_pedido = ?");
                if ($stmt->execute([$estado, $id])) {
                    $_SESSION['success'] = "Estado del pedido #$id actualizado a " . ucfirst($estado);
                    
                    // AUDITORÍA: Registrar cambio de estado
                    $datos_anteriores = [
                        'id_pedido' => $pedido_anterior['id_pedido'],
                        'estado_anterior' => $pedido_anterior['estado'],
                        'total' => $pedido_anterior['total'],
                        'id_cliente' => $pedido_anterior['id_cliente']
                    ];
                    
                    $datos_nuevos = [
                        'id_pedido' => $id,
                        'estado_nuevo' => $estado,
                        'estado_anterior' => $pedido_anterior['estado'],
                        'total' => $pedido_anterior['total'],
                        'id_cliente' => $pedido_anterior['id_cliente'],
                        'admin_id' => $admin_id,
                        'ip_address' => $ip_address,
                        'fecha_cambio' => date('Y-m-d H:i:s')
                    ];
                    
                    try {
                        $query = "INSERT INTO auditoria 
                                 (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, tipo_usuario, ip_address) 
                                 VALUES (:tabla, :id_registro, :accion, :datos_anteriores, :datos_nuevos, :id_usuario, :tipo_usuario, :ip_address)";
                        
                        $stmt_audit = $db->prepare($query);
                        $stmt_audit->execute([
                            ':tabla' => 'pedidos',
                            ':id_registro' => $id,
                            ':accion' => 'UPDATE',
                            ':datos_anteriores' => json_encode($datos_anteriores, JSON_PRETTY_PRINT),
                            ':datos_nuevos' => json_encode($datos_nuevos, JSON_PRETTY_PRINT),
                            ':id_usuario' => $admin_id,
                            ':tipo_usuario' => 'admin',
                            ':ip_address' => $ip_address
                        ]);
                        
                        error_log("✅ Auditoría: Estado de pedido #$id cambiado de '{$pedido_anterior['estado']}' a '$estado' por admin ID: $admin_id");
                        
                    } catch (PDOException $e) {
                        error_log("❌ Error en auditoría de pedido: " . $e->getMessage());
                    }
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $_SESSION['error'] = "Error al actualizar el estado del pedido: " . $errorInfo[2];
                    error_log("❌ Error SQL: " . $errorInfo[2]);
                }
                break;
        }
    }
    header('Location: pedidos.php');
    exit;
}

// Obtener pedidos con información de cliente
$pedidos = $db->query("
    SELECT p.*, c.nombre as cliente_nombre, c.correo as cliente_email 
    FROM pedidos p 
    JOIN clientes c ON p.id_cliente = c.id_cliente 
    ORDER BY p.fecha DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - VMBol en Red</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Pedidos</h1>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $pedido): 
                                $badgeClass = [
                                    'pendiente' => 'bg-warning',
                                    'pagado' => 'bg-info',
                                    'enviado' => 'bg-success',
                                    'cancelado' => 'bg-danger'
                                ][$pedido['estado']] ?? 'bg-secondary';
                            ?>
                            <tr>
                                <td>#<?php echo $pedido['id_pedido']; ?></td>
                                <td>
                                    <div><strong><?php echo htmlspecialchars($pedido['cliente_nombre']); ?></strong></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($pedido['cliente_email']); ?></small>
                                </td>
                                <td>$<?php echo number_format($pedido['total'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($pedido['estado']); ?></span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalDetalle<?php echo $pedido['id_pedido']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <!-- Solo mostrar estados válidos según tu ENUM -->
                                                <?php if ($pedido['estado'] != 'pagado'): ?>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="accion" value="actualizar_estado">
                                                        <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                                        <input type="hidden" name="estado" value="pagado">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas fa-money-bill-wave me-1"></i>Marcar como Pagado
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>
                                                
                                                <?php if ($pedido['estado'] != 'enviado'): ?>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="accion" value="actualizar_estado">
                                                        <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                                        <input type="hidden" name="estado" value="enviado">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas fa-shipping-fast me-1"></i>Marcar como Enviado
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>
                                                
                                                <?php if ($pedido['estado'] != 'cancelado'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('¿Estás seguro de cancelar este pedido?')">
                                                        <input type="hidden" name="accion" value="actualizar_estado">
                                                        <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                                        <input type="hidden" name="estado" value="cancelado">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-times-circle me-1"></i>Cancelar Pedido
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>
                                                
                                                <!-- Opción para volver a pendiente si está cancelado -->
                                                <?php if ($pedido['estado'] == 'cancelado'): ?>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="accion" value="actualizar_estado">
                                                        <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                                        <input type="hidden" name="estado" value="pendiente">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas fa-undo me-1"></i>Reactivar Pedido
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal Detalle -->
                            <div class="modal fade" id="modalDetalle<?php echo $pedido['id_pedido']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Detalle del Pedido #<?php echo $pedido['id_pedido']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php
                                            // Obtener detalles del pedido
                                            $detalles = $db->prepare("
                                                SELECT pd.*, p.nombre as producto 
                                                FROM pedido_detalles pd 
                                                JOIN productos p ON pd.id_producto = p.id_producto 
                                                WHERE pd.id_pedido = ?
                                            ");
                                            $detalles->execute([$pedido['id_pedido']]);
                                            $productos = $detalles->fetchAll(PDO::FETCH_ASSOC);
                                            ?>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Información del Cliente</h6>
                                                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pedido['cliente_nombre']); ?><br>
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($pedido['cliente_email']); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Información del Pedido</h6>
                                                    <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?><br>
                                                    <strong>Estado:</strong> <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($pedido['estado']); ?></span><br>
                                                    <strong>Total:</strong> $<?php echo number_format($pedido['total'], 2); ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <h6>Productos del Pedido</h6>
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Producto</th>
                                                                <th>Cantidad</th>
                                                                <th>Precio Unitario</th>
                                                                <th>Subtotal</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($productos as $producto): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($producto['producto']); ?></td>
                                                                <td><?php echo $producto['cantidad']; ?></td>
                                                                <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                                                                <td>$<?php echo number_format($producto['precio'] * $producto['cantidad'], 2); ?></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    
    <!-- SOLO AGREGAR ESTE SCRIPT PARA INICIALIZAR LOS DROPDOWNS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar todos los dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>
</html>