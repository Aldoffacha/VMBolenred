<?php
// Iniciar sesión si no está iniciada - SOLO UNA VEZ
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cargar sistema de notificaciones
require_once '../../includes/notificaciones.php';
?>
<!DOCTYPE html>
<html lang="es" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - VMBol en Red' : 'VMBol en Red - Sistema de Importación'; ?></title>
    <!-- Bootstrap PRIMERO -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tus CSS DESPUÉS (para que sobrescriban a Bootstrap) -->
    <link rel="stylesheet" href="../../assets/css/main.css">
<?php if (isset($_SESSION['rol'])): ?>
    <link rel="stylesheet" href="../../assets/css/<?php echo $_SESSION['rol']; ?>.css">
<?php endif; ?>
    <!-- Estilos para dropdown notificaciones en modo oscuro -->
    <style>
        :root {
            --dropdown-bg: #ffffff;
            --dropdown-text: #000000;
            --dropdown-border: #dee2e6;
            --dropdown-hover: #f8f9fa;
            --dropdown-muted: #6c757d;
        }

        [data-theme="dark"] {
            --dropdown-bg: #2c333a;
            --dropdown-text: #e0e0e0;
            --dropdown-border: #444c56;
            --dropdown-hover: #3a4350;
            --dropdown-muted: #a0a0a0;
        }

        .dropdown-menu {
            background-color: var(--dropdown-bg) !important;
            color: var(--dropdown-text) !important;
            border-color: var(--dropdown-border) !important;
        }

        .dropdown-item {
            color: var(--dropdown-text) !important;
        }

        .dropdown-item:hover {
            background-color: var(--dropdown-hover) !important;
            color: var(--dropdown-text) !important;
        }

        .dropdown-header {
            color: var(--dropdown-text) !important;
            background-color: var(--dropdown-bg) !important;
            border-bottom-color: var(--dropdown-border) !important;
        }

        .dropdown-divider {
            border-color: var(--dropdown-border) !important;
        }

        .dropdown-item.text-muted {
            color: var(--dropdown-muted) !important;
        }

        [data-theme="dark"] .btn-outline-secondary {
            border-color: #999 !important;
            color: #ccc !important;
        }

        [data-theme="dark"] .btn-outline-secondary:hover {
            background-color: #999 !important;
            color: white !important;
        }

        [data-theme="dark"] .btn-outline-primary {
            border-color: #667eea !important;
            color: #667eea !important;
        }

        [data-theme="dark"] .btn-outline-primary:hover {
            background-color: #667eea !important;
            color: white !important;
        }
    </style>
    <!-- Script para aplicar tema ANTES de renderizar (evita flash) -->
    <script>
        (function() {
            const theme = localStorage.getItem('vmbolenred_theme') || 'dark';
            const html = document.documentElement;
            html.setAttribute('data-theme', theme);
            
            // También preparar la clase para cuando se cargue el body
            if (theme === 'light') {
                html.classList.add('theme-light-pending');
            }
        })();
    </script>
</head>
<body class="d-flex flex-column h-100 <?php echo isset($_SESSION['rol']) ? $_SESSION['rol'] . '-dashboard' : 'public-page'; ?>">
    <?php if (isset($_SESSION['user_id'])): 
        // Obtener notificaciones
        $notificaciones = new Notificaciones();
        $todas_notifs = $notificaciones->obtener($_SESSION['user_id'], $_SESSION['rol'], 50);
        $no_leidas = $notificaciones->obtenerNoLeidas($_SESSION['user_id'], $_SESSION['rol']);
        
        // Separar leídas y no leídas
        $notifs_no_leidas = [];
        $notifs_leidas = [];
        
        foreach ($todas_notifs as $notif) {
            if (!$notif['leido']) {
                $notifs_no_leidas[] = $notif;
            } else {
                $notifs_leidas[] = $notif;
            }
        }
        
        // Combinar: primero no leídas, luego leídas (máximo 2 total)
        $notifs = array_merge($notifs_no_leidas, $notifs_leidas);
        $notifs = array_slice($notifs, 0, 2);
    ?>
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <a class="navbar-brand" href="../index.php">
                    <i class="fas fa-globe-americas me-2"></i>VMBol en Red
                </a>
                
                <div class="navbar-nav ms-auto align-items-center">
                    <!-- Campana de Notificaciones -->
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-light btn-sm position-relative dropdown-toggle" 
                                type="button" id="notificacionesDropdown" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($no_leidas > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $no_leidas; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificacionesDropdown" style="min-width: 350px; max-height: 400px; overflow-y: auto;">
                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <strong>Notificaciones</strong>
                                <?php if ($no_leidas > 0): ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button" onclick="event.preventDefault(); event.stopPropagation(); marcarTodasLeidas();">
                                    Marcar todas como leídas
                                </button>
                                <?php endif; ?>
                            </li>
                            <?php if (empty($notifs)): ?>
                            <li><a class="dropdown-item text-center text-muted py-3" href="#">No hay notificaciones</a></li>
                            <?php else: ?>
                            <?php foreach ($notifs as $notif): ?>
                            <li>
                                <a class="dropdown-item <?php echo !$notif['leido'] ? 'bg-light' : ''; ?> notificacion-item" 
                                   href="javascript:void(0);" 
                                   data-id="<?php echo $notif['id_notificacion']; ?>"
                                   onclick="event.preventDefault(); event.stopPropagation(); marcarLeida(<?php echo $notif['id_notificacion']; ?>);">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($notif['titulo']); ?></h6>
                                        <small class="text-muted"><?php 
                                            $fecha = new DateTime($notif['fecha_creacion']);
                                            echo $fecha->format('H:i');
                                        ?></small>
                                    </div>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($notif['mensaje']); ?></p>
                                    <?php if (!$notif['leido']): ?>
                                    <span class="badge bg-primary">Nuevo</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php if ($notif !== end($notifs)): ?>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <li class="dropdown-footer text-center">
                                <a href="notificaciones.php" class="btn btn-sm btn-outline-primary w-100">Ver todas las notificaciones</a>
                            </li>
                        </ul>
                    </div>

                    <span class="navbar-text me-3 d-none d-md-block">
                        <i class="fas fa-user me-1"></i>Hola, <strong><?php echo $_SESSION['nombre']; ?></strong>
                    </span>
                    <!-- RUTA CORRECTA DEL LOGOUT -->
                    <a href="../../procesos/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Salir
                    </a>
                </div>
            </div>
        </nav>
    </header>
    <?php endif; ?>
    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Gestor de temas persistente -->
    <script src="../../assets/js/theme-manager.js"></script>
    
    <!-- Script para notificaciones -->
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
            const item = document.querySelector(`.notificacion-item[data-id="${id_notificacion}"]`);
            if (item) {
                item.classList.remove('bg-light');
                const badge = item.querySelector('.badge');
                if (badge) badge.remove();
            }
            actualizarContadorNotificaciones();
        }
    })
    .catch(error => console.error('Error:', error));
}

function marcarTodasLeidas() {
    fetch('../../procesos/marcar_todas_leidas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notificacion-item').forEach(item => {
                item.classList.remove('bg-light');
                const badge = item.querySelector('.badge');
                if (badge) badge.remove();
            });
            actualizarContadorNotificaciones();
            const dropdown = document.getElementById('notificacionesDropdown');
            if (dropdown) {
                const instance = bootstrap.Dropdown.getInstance(dropdown);
                if (instance) instance.hide();
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function actualizarContadorNotificaciones() {
    fetch('../../procesos/obtener_notificaciones.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector('.navbar .badge.bg-danger');
            const dropdownToggle = document.getElementById('notificacionesDropdown');
            
            if (data.no_leidas > 0) {
                if (!badge && dropdownToggle) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    newBadge.textContent = data.no_leidas;
                    dropdownToggle.appendChild(newBadge);
                } else if (badge) {
                    badge.textContent = data.no_leidas;
                }
            } else if (badge) {
                badge.remove();
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

document.addEventListener('DOMContentLoaded', function() {
    actualizarContadorNotificaciones();
});

setInterval(actualizarContadorNotificaciones, 30000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Swift Alerts (Sistema de alertas deslizantes) -->
    <script src="../../assets/js/swift-alerts.js"></script>
    
    <!-- Convertidor de alertas antiguas a Swift Alerts -->
    <script src="../../assets/js/alert-converter.js"></script>
    
    <!-- Gestor de temas persistente -->
    <script src="../../assets/js/theme-manager.js"></script>
    
    <!-- Tus otros scripts... -->
<!-- Contenedor principal flex -->
<div class="container-fluid flex-grow-1 d-flex flex-column p-0">
    <div class="row flex-grow-1 m-0">
        </body>
</html>