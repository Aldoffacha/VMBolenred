<?php
// Corregir las rutas según la ubicación del archivo
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/notificaciones.php';

Auth::checkAuth('cliente'); // O 'admin' según corresponda
$db = (new Database())->getConnection();
$notificaciones = new Notificaciones();

$pageTitle = "Notificaciones";
include '../../includes/header.php';

$todas_notificaciones = $notificaciones->obtener($_SESSION['user_id'], $_SESSION['rol'], 50);
?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>🔔 Mis Notificaciones</h2>
                <button class="btn btn-outline-primary" onclick="marcarTodasLeidas()">
                    Marcar todas como leídas
                </button>
            </div>

            <div class="row">
                <div class="col-12">
                    <?php if (empty($todas_notificaciones)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <h4>No hay notificaciones</h4>
                        <p class="text-muted">Cuando tengas notificaciones, aparecerán aquí.</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($todas_notificaciones as $notif): ?>
                        <div class="list-group-item list-group-item-action <?php echo !$notif['leido'] ? 'list-group-item-primary' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1"><?php echo htmlspecialchars($notif['titulo']); ?></h5>
                                <small class="text-muted">
                                    <?php 
                                    $fecha = new DateTime($notif['fecha_creacion']);
                                    echo $fecha->format('d/m/Y H:i');
                                    ?>
                                </small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($notif['mensaje']); ?></p>
                            <?php if ($notif['enlace']): ?>
                            <a href="<?php echo $notif['enlace']; ?>" class="btn btn-sm btn-outline-primary">
                                Ver más
                            </a>
                            <?php endif; ?>
                            <?php if (!$notif['leido']): ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="marcarLeida(<?php echo $notif['id_notificacion']; ?>)">
                                Marcar como leída
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function marcarLeida(id_notificacion) {
    fetch('../../procesos/marcar_notificacion_leida.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id_notificacion=' + id_notificacion
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function marcarTodasLeidas() {
    fetch('../../procesos/marcar_todas_leidas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>