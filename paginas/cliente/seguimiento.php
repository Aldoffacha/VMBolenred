<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
$id_pedido = intval($_GET['id'] ?? 0);

// Obtener información de envío
$stmt = $db->prepare("
    SELECT p.*, e.*, d.nombre_deposito, d.direccion as direccion_deposito
    FROM pedidos p 
    LEFT JOIN envios_importacion e ON p.id_pedido = e.id_pedido 
    LEFT JOIN depositos_miami d ON e.id_deposito = d.id_deposito 
    WHERE p.id_pedido = ? AND p.id_cliente = ?
");
$stmt->execute([$id_pedido, $user_id]);
$envio = $stmt->fetch();

if (!$envio) {
    header('Location: pedidos.php');
    exit;
}
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Seguimiento de Envío"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>🚚 Seguimiento de Envío #VM<?php echo $id_pedido; ?></h2>
                <span class="badge bg-<?php echo [
                    'en_miami' => 'warning',
                    'en_transito' => 'info', 
                    'en_aduanas' => 'primary',
                    'entregado' => 'success'
                ][$envio['estado']] ?? 'secondary'; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $envio['estado'])); ?>
                </span>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Timeline de envío -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">📅 Progreso del Envío</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php
                                $etapas = [
                                    'en_miami' => ['icon' => '🏢', 'text' => 'En depósito Miami', 'fecha' => $envio['fecha_salida_miami']],
                                    'en_transito' => ['icon' => '✈️', 'text' => 'En tránsito a Bolivia', 'fecha' => $envio['fecha_llegada_bolivia']],
                                    'en_aduanas' => ['icon' => '🛃', 'text' => 'En aduanas bolivianas', 'fecha' => null],
                                    'entregado' => ['icon' => '<i class="fas fa-check text-success"></i>', 'text' => 'Entregado', 'fecha' => $envio['fecha_entrega_cliente']]
                                ];
                                
                                $estado_actual = $envio['estado'];
                                $etapas_completadas = array_slice($etapas, 0, array_search($estado_actual, array_keys($etapas)) + 1);
                                
                                foreach ($etapas as $etapa => $info):
                                    $completada = in_array($etapa, array_keys($etapas_completadas));
                                    $activa = $etapa == $estado_actual;
                                ?>
                                <div class="timeline-item <?php echo $completada ? 'completed' : ''; ?> <?php echo $activa ? 'active' : ''; ?>">
                                    <div class="timeline-marker">
                                        <?php echo $info['icon']; ?>
                                    </div>
                                    <div class="timeline-content">
                                        <h6><?php echo $info['text']; ?></h6>
                                        <?php if ($completada && $info['fecha']): ?>
                                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($info['fecha'])); ?></small>
                                        <?php elseif ($activa): ?>
                                        <span class="badge bg-info">En progreso</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Información de guía -->
                    <?php if ($envio['guia_aerea']): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">📦 Información de Guía</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Guía Aérea:</strong> <?php echo $envio['guia_aerea']; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Aerolínea:</strong> <?php echo $envio['aerolinea'] ?? 'Por asignar'; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Peso Total:</strong> <?php echo $envio['peso_total'] ?? '0'; ?> kg
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <!-- Información de depósito -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">🏢 Depósito Miami</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($envio['nombre_deposito']): ?>
                            <h6><?php echo $envio['nombre_deposito']; ?></h6>
                            <p class="small"><?php echo $envio['direccion_deposito']; ?></p>
                            <p class="small">📞 <?php echo $envio['telefono'] ?? 'N/A'; ?></p>
                            <p class="small">👤 <?php echo $envio['contacto'] ?? 'N/A'; ?></p>
                            <?php else: ?>
                            <p class="text-muted">Depósito por asignar</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contacto de soporte -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">📞 Soporte</h5>
                        </div>
                        <div class="card-body">
                            <p class="small">¿Necesitas ayuda con tu envío?</p>
                            <div class="d-grid gap-2">
                                <a href="https://wa.me/59177712345" class="btn btn-success btn-sm" target="_blank">
                                    💬 WhatsApp
                                </a>
                                <a href="tel:+59177712345" class="btn btn-primary btn-sm">
                                    📞 Llamar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}
.timeline-item.completed .timeline-marker {
    background: #28a745;
    color: white;
}
.timeline-item.active .timeline-marker {
    background: #007bff;
    color: white;
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}
</style>

<?php include '../../includes/footer.php'; ?>