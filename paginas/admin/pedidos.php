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
                
                // Validar que el estado sea válido para la base de datos
                $estados_validos = ['pendiente', 'pagado', 'enviado', 'cancelado'];
                if (!in_array($estado, $estados_validos)) {
                    $_SESSION['error'] = "Estado no válido";
                    header('Location: pedidos.php' . (isset($_GET['estado']) ? '?estado=' . $_GET['estado'] : ''));
                    exit;
                }
                
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
    header('Location: pedidos.php' . (isset($_GET['estado']) ? '?estado=' . $_GET['estado'] : ''));
    exit;
}

// Obtener filtro de estado
$filtro_estado = $_GET['estado'] ?? 'todos';
$where_condition = "";
$params = [];

if ($filtro_estado !== 'todos' && in_array($filtro_estado, ['pendiente', 'pagado', 'enviado', 'cancelado'])) {
    $where_condition = " WHERE p.estado = ?";
    $params[] = $filtro_estado;
}

// Obtener pedidos con información de cliente
$sql = "
    SELECT p.*, c.nombre as cliente_nombre, c.correo as cliente_email 
    FROM pedidos p 
    JOIN clientes c ON p.id_cliente = c.id_cliente 
    {$where_condition}
    ORDER BY p.fecha DESC, p.id_pedido DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores por estado para las estadísticas
$contadores = [
    'total' => 0,
    'pendiente' => 0,
    'pagado' => 0,
    'enviado' => 0,
    'cancelado' => 0
];

// Obtener contadores
$stmt_contadores = $db->query("
    SELECT estado, COUNT(*) as cantidad 
    FROM pedidos 
    GROUP BY estado
");
$contadores_db = $stmt_contadores->fetchAll(PDO::FETCH_ASSOC);

foreach ($contadores_db as $contador) {
    $contadores[$contador['estado']] = $contador['cantidad'];
    $contadores['total'] += $contador['cantidad'];
}
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
                    <div class="text-muted">
                        <small>Mostrando <?php echo count($pedidos); ?> pedido(s)</small>
                    </div>
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

                <!-- Estadísticas de Pedidos -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Pedidos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $contadores['total']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pendientes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $contadores['pendiente']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Pagados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $contadores['pagado']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Enviados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $contadores['enviado']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shipping-fast fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Cancelados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $contadores['cancelado']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros de Estado -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Filtrar Pedidos por Estado</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-auto">
                                <a href="pedidos.php" 
                                   class="btn <?php echo $filtro_estado === 'todos' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    <i class="fas fa-list me-1"></i> Todos (<?php echo $contadores['total']; ?>)
                                </a>
                            </div>
                            <div class="col-auto">
                                <a href="pedidos.php?estado=pendiente" 
                                   class="btn <?php echo $filtro_estado === 'pendiente' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                                    <i class="fas fa-clock me-1"></i> Pendientes (<?php echo $contadores['pendiente']; ?>)
                                </a>
                            </div>
                            <div class="col-auto">
                                <a href="pedidos.php?estado=pagado" 
                                   class="btn <?php echo $filtro_estado === 'pagado' ? 'btn-info' : 'btn-outline-info'; ?>">
                                    <i class="fas fa-money-bill-wave me-1"></i> Pagados (<?php echo $contadores['pagado']; ?>)
                                </a>
                            </div>
                            <div class="col-auto">
                                <a href="pedidos.php?estado=enviado" 
                                   class="btn <?php echo $filtro_estado === 'enviado' ? 'btn-success' : 'btn-outline-success'; ?>">
                                    <i class="fas fa-shipping-fast me-1"></i> Enviados (<?php echo $contadores['enviado']; ?>)
                                </a>
                            </div>
                            <div class="col-auto">
                                <a href="pedidos.php?estado=cancelado" 
                                   class="btn <?php echo $filtro_estado === 'cancelado' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                    <i class="fas fa-times-circle me-1"></i> Cancelados (<?php echo $contadores['cancelado']; ?>)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Pedidos -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Lista de Pedidos 
                            <?php if ($filtro_estado !== 'todos'): ?>
                                <span class="badge bg-secondary"><?php echo ucfirst($filtro_estado); ?></span>
                            <?php endif; ?>
                            <small class="text-muted ms-2">(<?php echo count($pedidos); ?> pedidos encontrados)</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pedidos)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">
                                <?php if ($filtro_estado !== 'todos'): ?>
                                No hay pedidos con estado "<?php echo ucfirst($filtro_estado); ?>"
                                <?php else: ?>
                                No hay pedidos registrados
                                <?php endif; ?>
                            </h5>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID Pedido</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Fecha y Hora</th>
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
                                        <td>
                                            <strong>#<?php echo $pedido['id_pedido']; ?></strong>
                                            <?php if ($pedido['id_cliente']): ?>
                                            <br><small class="text-muted">Cliente ID: <?php echo $pedido['id_cliente']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($pedido['cliente_nombre']); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($pedido['cliente_email']); ?></small>
                                        </td>
                                        <td><strong>$<?php echo number_format($pedido['total'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($pedido['estado']); ?></span>
                                        </td>
                                        <td>
                                            <div><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></div>
                                            <small class="text-muted"><?php echo date('H:i:s', strtotime($pedido['fecha'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary ver-detalle" 
                                                        data-pedido-id="<?php echo $pedido['id_pedido']; ?>"
                                                        title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <!-- Botones individuales para cambiar estado -->
                                                <?php if ($pedido['estado'] != 'pagado'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de cambiar el estado a pagado?')">
                                                    <input type="hidden" name="accion" value="actualizar_estado">
                                                    <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                                    <input type="hidden" name="estado" value="pagado">
                                                    <button type="submit" class="btn btn-sm btn-outline-info" title="Marcar como Pagado">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($pedido['estado'] != 'enviado'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de cambiar el estado a enviado?')">
                                                    <input type="hidden" name="accion" value="actualizar_estado">
                                                    <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                                    <input type="hidden" name="estado" value="enviado">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Marcar como Enviado">
                                                        <i class="fas fa-shipping-fast"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($pedido['estado'] != 'cancelado'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de cancelar este pedido?')">
                                                    <input type="hidden" name="accion" value="actualizar_estado">
                                                    <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                                    <input type="hidden" name="estado" value="cancelado">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar Pedido">
                                                        <i class="fas fa-times-circle"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($pedido['estado'] == 'cancelado'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de reactivar este pedido?')">
                                                    <input type="hidden" name="accion" value="actualizar_estado">
                                                    <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                                    <input type="hidden" name="estado" value="pendiente">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Reactivar Pedido">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
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

    <!-- Modal Único para Detalles -->
    <div class="modal fade" id="modalDetallePedido" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle del Pedido <span id="pedido-id-title"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalle-pedido-content">
                    <!-- El contenido se cargará aquí dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Manejar clic en botones de ver detalles
        document.querySelectorAll('.ver-detalle').forEach(button => {
            button.addEventListener('click', function() {
                const pedidoId = this.getAttribute('data-pedido-id');
                cargarDetallePedido(pedidoId);
            });
        });

        // Función para cargar detalles del pedido
        function cargarDetallePedido(pedidoId) {
            // Mostrar loading
            document.getElementById('detalle-pedido-content').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando detalles del pedido...</p>
                </div>
            `;

            // Actualizar título del modal
            document.getElementById('pedido-id-title').textContent = '#' + pedidoId;

            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('modalDetallePedido'));
            modal.show();

            // Hacer petición AJAX para obtener los detalles
            fetch('../../procesos/obtener_detalle_pedido.php?id=' + pedidoId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor: ' + response.status);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            mostrarDetallePedido(data.pedido, data.productos);
                        } else {
                            mostrarError(data.message || 'Error al cargar los detalles del pedido');
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e, 'Response text:', text);
                        mostrarError('Error en el formato de respuesta del servidor');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarError('Error al cargar los detalles del pedido: ' + error.message);
                });
        }

        // Función para mostrar los detalles del pedido
        function mostrarDetallePedido(pedido, productos) {
            const badgeClass = {
                'pendiente': 'bg-warning',
                'pagado': 'bg-info',
                'enviado': 'bg-success',
                'cancelado': 'bg-danger'
            }[pedido.estado] || 'bg-secondary';

            let productosHTML = '';
            
            if (productos && productos.length > 0) {
                productos.forEach(producto => {
                    const subtotal = parseFloat(producto.precio) * parseInt(producto.cantidad);
                    productosHTML += `
                        <tr>
                            <td>${producto.producto}</td>
                            <td>${producto.cantidad}</td>
                            <td>$${parseFloat(producto.precio).toFixed(2)}</td>
                            <td>$${subtotal.toFixed(2)}</td>
                        </tr>
                    `;
                });
            } else {
                productosHTML = `
                    <tr>
                        <td colspan="4" class="text-center">
                            <div class="alert alert-warning mb-0">
                                No se encontraron productos para este pedido.
                            </div>
                        </td>
                    </tr>
                `;
            }

            const contenido = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información del Cliente</h6>
                        <p>
                            <strong>Nombre:</strong> ${pedido.cliente_nombre}<br>
                            <strong>Email:</strong> ${pedido.cliente_email}<br>
                            <strong>ID Cliente:</strong> ${pedido.id_cliente}
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Información del Pedido</h6>
                        <p>
                            <strong>ID Pedido:</strong> #${pedido.id_pedido}<br>
                            <strong>Fecha:</strong> ${formatDate(pedido.fecha)}<br>
                            <strong>Estado:</strong> <span class="badge ${badgeClass}">${pedido.estado.charAt(0).toUpperCase() + pedido.estado.slice(1)}</span><br>
                            <strong>Total:</strong> $${parseFloat(pedido.total).toFixed(2)}
                        </p>
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
                                ${productosHTML}
                                ${productos && productos.length > 0 ? `
                                <tr class="table-primary">
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td><strong>$${parseFloat(pedido.total).toFixed(2)}</strong></td>
                                </tr>
                                ` : ''}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            document.getElementById('detalle-pedido-content').innerHTML = contenido;
        }

        // Función para formatear fecha
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES');
        }

        // Función para mostrar error
        function mostrarError(mensaje) {
            document.getElementById('detalle-pedido-content').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${mensaje}
                </div>
            `;
        }
    });
    </script>
</body>
</html>