<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/auditoria.class.php';
require_once '../../includes/notificaciones.php';
require_once '../../includes/swift-alerts-helper.php';

try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    header('Location: ../../public/login.php');
    exit;
}

$db = (new Database())->getConnection();
$auditoria = new Auditoria($db);
$notificaciones = new Notificaciones(); // AÑADIR ESTA LÍNEA

// Obtener información del admin para auditoría
$admin_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;
$ip_address = $_SERVER['REMOTE_ADDR'];

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
                    
                    // NOTIFICAR AL CLIENTE SOBRE EL CAMBIO DE ESTADO - AÑADIR ESTO
                    try {
                        $notificaciones->notificarEstadoPedido(
                            $pedido_anterior['id_cliente'], 
                            $id, 
                            $estado
                        );
                        error_log("Notificación enviada al cliente ID: {$pedido_anterior['id_cliente']} sobre cambio de estado del pedido #$id a: $estado");
                    } catch (Exception $e) {
                        error_log("Error al enviar notificación: " . $e->getMessage());
                    }
                    
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
                        
                        error_log("Auditoría: Estado de pedido #$id cambiado de '{$pedido_anterior['estado']}' a '$estado' por admin ID: $admin_id");
                        
                    } catch (PDOException $e) {
                        error_log("Error en auditoría de pedido: " . $e->getMessage());
                    }
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $_SESSION['error'] = "Error al actualizar el estado del pedido: " . $errorInfo[2];
                    error_log("Error SQL: " . $errorInfo[2]);
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
                    <h1 class="h2">Gestión de Pedidos</h1>
                    <div class="text-muted">
                        <small>Mostrando <?php echo count($pedidos); ?> pedido(s)</small>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <script>
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', function() {
                                showSuccess('<?php echo addslashes($_SESSION['success']); ?>', 5000);
                            });
                        } else {
                            showSuccess('<?php echo addslashes($_SESSION['success']); ?>', 5000);
                        }
                    </script>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <script>
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', function() {
                                showError('<?php echo addslashes($_SESSION['error']); ?>', 5000);
                            });
                        } else {
                            showError('<?php echo addslashes($_SESSION['error']); ?>', 5000);
                        }
                    </script>
                    <?php unset($_SESSION['error']); ?>
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
                                                
                                                <!-- LÓGICA DE ESTADOS CORRECTA -->
                                                <!-- PENDIENTE: Puede ir a PAGADO o CANCELADO -->
                                                <?php if ($pedido['estado'] == 'pendiente'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-info" title="Marcar como Pagado"
                                                            onclick="mostrarConfirmacion('¿Estás seguro de cambiar el estado a pagado?', <?php echo $pedido['id_pedido']; ?>, 'pagado')">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Cancelar Pedido"
                                                            onclick="mostrarConfirmacion('¿Estás seguro de cancelar este pedido?', <?php echo $pedido['id_pedido']; ?>, 'cancelado')">
                                                        <i class="fas fa-times-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- PAGADO: Solo puede ir a ENVIADO -->
                                                <?php if ($pedido['estado'] == 'pagado'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="Marcar como Enviado"
                                                            onclick="mostrarConfirmacion('¿Estás seguro de cambiar el estado a enviado?', <?php echo $pedido['id_pedido']; ?>, 'enviado')">
                                                        <i class="fas fa-shipping-fast"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- ENVIADO: No permite cambios (solo ver detalles) -->
                                                <!-- No hay botones de cambio de estado -->
                                                
                                                <!-- CANCELADO: Solo puede reactivar a PENDIENTE -->
                                                <?php if ($pedido['estado'] == 'cancelado'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Reactivar Pedido"
                                                            onclick="mostrarConfirmacion('¿Estás seguro de reactivar este pedido?', <?php echo $pedido['id_pedido']; ?>, 'pendiente')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
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

    <!-- Modal Flotante de Confirmación -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header bg-warning bg-opacity-10 border-bottom-0">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Confirmación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMensaje" class="mb-0"></p>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarAccion" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="fas fa-check me-1"></i> Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para enviar cambios de estado -->
    <form id="formCambioEstado" method="POST" style="display: none;">
        <input type="hidden" name="accion" value="actualizar_estado">
        <input type="hidden" name="id_pedido" id="hiddenPedidoId" value="">
        <input type="hidden" name="estado" id="hiddenEstado" value="">
    </form>

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
    let pedidoIdPendiente = null;
    let estadoPendiente = null;

    function mostrarConfirmacion(mensaje, pedidoId, estado) {
        // Actualizar el mensaje del modal
        document.getElementById('confirmMensaje').textContent = mensaje;
        
        // Guardar los valores para usar cuando se confirme
        pedidoIdPendiente = pedidoId;
        estadoPendiente = estado;
        
        // Mostrar el modal
        const modal = new bootstrap.Modal(document.getElementById('modalConfirmacion'));
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Manejar clic en botón confirmar del modal
        document.getElementById('btnConfirmarAccion').addEventListener('click', function() {
            if (pedidoIdPendiente && estadoPendiente) {
                // Llenar el formulario oculto
                document.getElementById('hiddenPedidoId').value = pedidoIdPendiente;
                document.getElementById('hiddenEstado').value = estadoPendiente;
                
                // Enviar el formulario
                document.getElementById('formCambioEstado').submit();
            }
        });

        // Manejar cierre del modal
        document.getElementById('modalConfirmacion').addEventListener('hidden.bs.modal', function() {
            pedidoIdPendiente = null;
            estadoPendiente = null;
        });

        // Manejar clic en botones de ver detalles
        document.querySelectorAll('.ver-detalle').forEach(button => {
            button.addEventListener('click', function() {
                const pedidoId = this.getAttribute('data-pedido-id');
                cargarDetallePedido(pedidoId);
            });
        });

        // Función para cargar detalles del pedido
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
            return response.json();
        })
        .then(data => {
            if (data.success) {
                mostrarDetallePedido(data.pedido, data.productos);
            } else {
                mostrarError(data.message || 'Error al cargar los detalles del pedido');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarError('Error al cargar los detalles del pedido: ' + error.message);
        });
}

        // Función para mostrar los detalles del pedido
        // Función para mostrar los detalles del pedido
function mostrarDetallePedido(pedido, productos) {
    const badgeClass = {
        'pendiente': 'bg-warning',
        'pagado': 'bg-info',
        'enviado': 'bg-success',
        'cancelado': 'bg-danger'
    }[pedido.estado] || 'bg-secondary';

    let productosHTML = '';
    let totalPedido = 0;
    
    if (productos && productos.length > 0) {
        productos.forEach(producto => {
            const subtotal = parseFloat(producto.precio_final) * parseInt(producto.cantidad);
            totalPedido += subtotal;

            if (producto.tipo === 'local') {
                // Producto local
                productosHTML += `
                    <tr>
                        <td>
                            <strong>${producto.nombre}</strong>
                            <br><small class="text-muted">Producto local</small>
                        </td>
                        <td>${producto.cantidad}</td>
                        <td>
                            <div>$${parseFloat(producto.precio_base).toFixed(2)}</div>
                            <small class="text-success">Total: $${parseFloat(producto.precio_final).toFixed(2)}</small>
                        </td>
                        <td>$${subtotal.toFixed(2)}</td>
                    </tr>
                `;
            } else {
                // Producto externo
                const plataformaText = producto.plataforma === 'amazon' ? 'Amazon' : 'eBay';
                productosHTML += `
                    <tr>
                        <td>
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>${producto.nombre}</strong>
                                    <br>
                                    <span class="badge ${producto.badge_class}">${plataformaText}</span>
                                    <small class="text-muted d-block">Peso: ${producto.peso} kg | ${producto.categoria}</small>
                                </div>
                                ${producto.url ? `
                                <a href="${producto.url}" target="_blank" class="btn btn-sm btn-outline-primary ms-2" 
                                   title="Ver producto en ${plataformaText}">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                ` : ''}
                            </div>
                        </td>
                        <td>${producto.cantidad}</td>
                        <td>
                            <div class="small">
                                <div>Base: $${parseFloat(producto.precio_base).toFixed(2)}</div>
                                <div>+ Flete: $${parseFloat(producto.costo_importacion.desglose.flete).toFixed(2)}</div>
                                <div>+ Seguro: $${parseFloat(producto.costo_importacion.desglose.seguro).toFixed(2)}</div>
                                <div>+ Aduana: $${parseFloat(producto.costo_importacion.desglose.aduana).toFixed(2)}</div>
                                <div>+ Almacén: $${parseFloat(producto.costo_importacion.desglose.almacen).toFixed(2)}</div>
                                <strong class="text-success">Total: $${parseFloat(producto.precio_final).toFixed(2)}</strong>
                            </div>
                        </td>
                        <td>
                            <strong>$${subtotal.toFixed(2)}</strong>
                        </td>
                    </tr>
                `;
            }
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

    // Información de estadísticas
    const stats = productos ? {
        locales: productos.filter(p => p.tipo === 'local').length,
        externos: productos.filter(p => p.tipo === 'externo').length,
        total: productos.length
    } : { locales: 0, externos: 0, total: 0 };

    const contenido = `
        <div class="row">
            <div class="col-md-6">
                <h6>👤 Información del Cliente</h6>
                <div class="card bg-light">
                    <div class="card-body">
                        <p class="mb-1"><strong>Nombre:</strong> ${pedido.cliente_nombre}</p>
                        <p class="mb-1"><strong>Email:</strong> ${pedido.cliente_email}</p>
                        <p class="mb-1"><strong>Teléfono:</strong> ${pedido.telefono || 'No especificado'}</p>
                        <p class="mb-0"><strong>Dirección:</strong> ${pedido.direccion || 'No especificada'}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <h6>📦 Información del Pedido</h6>
                <div class="card bg-light">
                    <div class="card-body">
                        <p class="mb-1"><strong>ID Pedido:</strong> #${pedido.id_pedido}</p>
                        <p class="mb-1"><strong>Fecha:</strong> ${formatDate(pedido.fecha)}</p>
                        <p class="mb-1">
                            <strong>Estado:</strong> 
                            <span class="badge ${badgeClass}">
                                ${pedido.estado.charAt(0).toUpperCase() + pedido.estado.slice(1)}
                            </span>
                        </p>
                        <p class="mb-0"><strong>Total:</strong> $${parseFloat(pedido.total).toFixed(2)}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6>🛍️ Productos del Pedido</h6>
                    <div>
                        ${stats.locales > 0 ? `<span class="badge bg-primary me-1">${stats.locales} locales</span>` : ''}
                        ${stats.externos > 0 ? `<span class="badge bg-warning">${stats.externos} externos</span>` : ''}
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th width="40%">Producto</th>
                                <th width="15%">Cantidad</th>
                                <th width="25%">Precio (Detallado)</th>
                                <th width="20%">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${productosHTML}
                            ${productos && productos.length > 0 ? `
                            <tr class="table-success">
                                <td colspan="3" class="text-end"><strong>Total del Pedido:</strong></td>
                                <td><strong>$${parseFloat(pedido.total).toFixed(2)}</strong></td>
                            </tr>
                            ` : ''}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        ${stats.externos > 0 ? `
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <h6>🌐 Información de Productos Externos</h6>
                    <small class="text-muted">
                        <strong>Los productos externos incluyen todos los costos de importación:</strong><br>
                        • Precio original del producto<br>
                        • Flete marítimo internacional<br>
                        • Seguro (2% del valor)<br>
                        • Impuestos de aduana (varía por categoría)<br>
                        • Costos de almacenaje y manejo<br><br>
                        <strong>Tiempo estimado de entrega:</strong> 15-30 días hábiles
                    </small>
                </div>
            </div>
        </div>
        ` : ''}
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