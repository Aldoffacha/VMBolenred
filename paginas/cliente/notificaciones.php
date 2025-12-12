<?php
// Corregir las rutas según la ubicación del archivo
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/notificaciones.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente'); // O 'admin' según corresponda
$db = (new Database())->getConnection();
$notificaciones = new Notificaciones();

$pageTitle = "Notificaciones";
include '../../includes/header.php';

$todas_notificaciones = $notificaciones->obtener($_SESSION['user_id'], $_SESSION['rol'], 50);
?>

<style>
    :root {
        --notif-bg: #ffffff;
        --notif-text: #000000;
        --notif-border: #dee2e6;
        --notif-unread-bg: #e7f3ff;
        --notif-unread-text: #004085;
        --notif-hover-bg: #f8f9fa;
    }

    [data-theme="dark"] {
        --notif-bg: #1e1e1e;
        --notif-text: #e0e0e0;
        --notif-border: #333333;
        --notif-unread-bg: #1a3a52;
        --notif-unread-text: #81d4fa;
        --notif-hover-bg: #2a2a2a;
    }

    .notif-container {
        background-color: var(--notif-bg);
        color: var(--notif-text);
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    .notif-item {
        background-color: var(--notif-bg);
        border: 1px solid var(--notif-border);
        color: var(--notif-text);
        transition: all 0.3s ease;
    }

    .notif-item:hover {
        background-color: var(--notif-hover-bg);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    [data-theme="dark"] .notif-item:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    .notif-item.unread {
        background-color: var(--notif-unread-bg);
        color: var(--notif-unread-text);
        font-weight: 500;
        border-left: 4px solid var(--notif-unread-text);
    }

    .notif-header {
        color: var(--notif-text);
    }

    .notif-title {
        color: var(--notif-text);
        font-weight: 600;
    }

    .notif-timestamp {
        color: var(--notif-text);
        opacity: 0.7;
    }

    [data-theme="dark"] .notif-timestamp {
        color: #a0a0a0;
    }

    .notif-message {
        color: var(--notif-text);
    }

    .btn-notif {
        transition: all 0.3s ease;
    }

    [data-theme="dark"] .btn-outline-primary {
        border-color: #667eea;
        color: #667eea;
    }

    [data-theme="dark"] .btn-outline-primary:hover {
        background-color: #667eea;
        color: white;
    }

    [data-theme="dark"] .btn-outline-secondary {
        border-color: #999;
        color: #ccc;
    }

    [data-theme="dark"] .btn-outline-secondary:hover {
        background-color: #999;
        color: white;
    }

    .empty-state {
        color: var(--notif-text);
    }

    [data-theme="dark"] .empty-state i {
        color: #666;
    }
</style>

<div class="container-fluid notif-container">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom notif-header">
                <h2 class="notif-title">
                    <i class="fas fa-bell me-2"></i>Mis Notificaciones
                </h2>
                <button class="btn btn-outline-primary btn-notif" onclick="marcarTodasLeidas()">
                    <i class="fas fa-check-double me-1"></i>Marcar todas como leídas
                </button>
            </div>

            <div class="row">
                <div class="col-12">
                    <?php if (empty($todas_notificaciones)): ?>
                    <div class="text-center py-5 empty-state">
                        <i class="fas fa-bell-slash fa-3x mb-3" style="opacity: 0.5;"></i>
                        <h4>No hay notificaciones</h4>
                        <p class="text-muted">Cuando tengas notificaciones, aparecerán aquí.</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($todas_notificaciones as $notif): ?>
                        <div class="list-group-item notif-item <?php echo !$notif['leido'] ? 'unread' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-start mb-2">
                                <h5 class="mb-0 notif-title">
                                    <?php if (!$notif['leido']): ?>
                                        <span class="badge bg-info me-2" style="width: 10px; height: 10px; padding: 0; border-radius: 50%;"></span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($notif['titulo']); ?>
                                </h5>
                                <small class="notif-timestamp">
                                    <?php 
                                    $fecha = new DateTime($notif['fecha_creacion']);
                                    echo $fecha->format('d/m/Y H:i');
                                    ?>
                                </small>
                            </div>
                            <p class="mb-3 notif-message">
                                <?php echo htmlspecialchars($notif['mensaje']); ?>
                            </p>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if ($notif['enlace']): ?>
                                <a href="<?php echo $notif['enlace']; ?>" class="btn btn-sm btn-outline-primary btn-notif">
                                    <i class="fas fa-arrow-right me-1"></i>Ver más
                                </a>
                                <?php endif; ?>
                                <?php if (!$notif['leido']): ?>
                                <button class="btn btn-sm btn-outline-secondary btn-notif" onclick="marcarLeida(<?php echo $notif['id_notificacion']; ?>)">
                                    <i class="fas fa-check me-1"></i>Marcar como leída
                                </button>
                                <?php endif; ?>
                            </div>
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
            showSuccess('Notificación marcada como leída');
            setTimeout(() => location.reload(), 1000);
        } else {
            showError('Error al marcar la notificación');
        }
    })
    .catch(error => {
        showError('Error de conexión: ' + error.message);
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
            showSuccess('Todas las notificaciones marcadas como leídas');
            setTimeout(() => location.reload(), 1000);
        } else {
            showError('Error al marcar las notificaciones');
        }
    })
    .catch(error => {
        showError('Error de conexión: ' + error.message);
    });
}

// Aplicar tema al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
});

// Observar cambios de tema
const observer = new MutationObserver(function() {
    const theme = document.documentElement.getAttribute('data-theme');
    if (theme) {
        localStorage.setItem('theme', theme);
    }
});

observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme']
});
</script>

<?php include '../../includes/footer.php'; ?>