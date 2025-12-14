<?php
// Rutas corregidas - subir 2 niveles para llegar a includes/
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

// Verificar sesión de usuario (compatible con todo el sistema)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['usuario']['id_cliente'])) {
    $user_id = $_SESSION['usuario']['id_cliente'];
} else {
    header('Location: ../../public/login.php');
    exit;
}

$id_pedido = isset($_GET['id_pedido']) ? intval($_GET['id_pedido']) : 0;
$mensaje = '';
$mensaje_tipo = '';

if ($id_pedido === 0) {
    header('Location: mis_pedidos.php');
    exit;
}

// Verificar que el pedido pertenece al cliente y está pagado
try {
    $query = "SELECT p.*, COALESCE(pg.estado, 'sin_pago') as estado_pago, 
              ue.direccion_entrega, ue.latitud, ue.longitud
              FROM pedidos p
              LEFT JOIN pagos pg ON p.id_pedido = pg.id_pedido
              LEFT JOIN ubicacion_entrega ue ON p.id_pedido = ue.id_pedido
              WHERE p.id_pedido = ? AND p.id_cliente = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$id_pedido, $user_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        $_SESSION['error'] = 'Pedido no encontrado';
        header('Location: mis_pedidos.php');
        exit;
    }

    if ($pedido['estado_pago'] !== 'pagado') {
        $_SESSION['error'] = 'El pedido debe estar pagado para establecer ubicación de entrega';
        header('Location: mis_pedidos.php');
        exit;
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error al cargar información del pedido';
    header('Location: mis_pedidos.php');
    exit;
}
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Establecer Ubicación de Entrega"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>📍 Establecer Ubicación de Entrega</h2>
                <a href="mis_pedidos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Volver
                </a>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Pedido #<?php echo $pedido['id_pedido']; ?></strong> - 
                Total: $<?php echo number_format($pedido['total'], 2); ?>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Selecciona tu ubicación en el mapa</h5>
                        </div>
                        <div class="card-body">
                            <div class="position-relative mb-4">
                                <div id="map" style="height: 400px; width: 100%; border-radius: 8px;"></div>
                            </div>
                            
                            <form id="formUbicacion" class="mt-3">
                                <input type="hidden" id="latitud" name="latitud" required>
                                <input type="hidden" id="longitud" name="longitud" required>
                                
                                <div class="mb-3">
                                    <label for="direccion_entrega" class="form-label">
                                        <i class="fas fa-map-marker-alt me-1"></i> Dirección completa *
                                    </label>
                                    <textarea class="form-control" id="direccion_entrega" name="direccion_entrega" 
                                              rows="2" required><?php echo htmlspecialchars($pedido['direccion_entrega'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="referencia" class="form-label">
                                        <i class="fas fa-flag me-1"></i> Referencias
                                    </label>
                                    <input type="text" class="form-control" id="referencia" name="referencia" 
                                           placeholder="Ej: Cerca del mercado central, casa de dos pisos color amarillo">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nombre_receptor" class="form-label">
                                            <i class="fas fa-user me-1"></i> Nombre de quien recibe
                                        </label>
                                        <input type="text" class="form-control" id="nombre_receptor" name="nombre_receptor">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="telefono_receptor" class="form-label">
                                            <i class="fas fa-phone me-1"></i> Teléfono de contacto
                                        </label>
                                        <input type="text" class="form-control" id="telefono_receptor" name="telefono_receptor">
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Nota:</strong> Haz clic en el mapa para establecer tu ubicación exacta de entrega.
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary" id="btnGuardar">
                                        <i class="fas fa-save me-1"></i> Guardar Ubicación
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Leaflet CSS y JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Inicializar mapa (La Paz, Bolivia por defecto)
const defaultLat = <?php echo $pedido['latitud'] ?? '-16.5000'; ?>;
const defaultLng = <?php echo $pedido['longitud'] ?? '-68.1500'; ?>;
const map = L.map('map').setView([defaultLat, defaultLng], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

let marker = null;

// Si ya hay ubicación guardada, mostrar marker
<?php if ($pedido['latitud'] && $pedido['longitud']): ?>
marker = L.marker([defaultLat, defaultLng]).addTo(map);
marker.bindPopup('Tu ubicación de entrega').openPopup();
document.getElementById('latitud').value = defaultLat;
document.getElementById('longitud').value = defaultLng;
<?php endif; ?>

// Click en el mapa para establecer ubicación
map.on('click', function(e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;
    
    if (marker) {
        map.removeLayer(marker);
    }
    
    marker = L.marker([lat, lng]).addTo(map);
    marker.bindPopup('Tu ubicación de entrega').openPopup();
    
    document.getElementById('latitud').value = lat;
    document.getElementById('longitud').value = lng;
});

// Intentar obtener ubicación actual
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        
        // Solo centrar si no hay marcador existente
        if (!marker) {
            map.setView([lat, lng], 15);
            marker = L.marker([lat, lng]).addTo(map);
            marker.bindPopup('Tu ubicación actual').openPopup();
            
            document.getElementById('latitud').value = lat;
            document.getElementById('longitud').value = lng;
        }
    });
}

// Guardar ubicación
document.getElementById('formUbicacion').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const latitud = document.getElementById('latitud').value;
    const longitud = document.getElementById('longitud').value;
    
    if (!latitud || !longitud) {
        showAlert('Por favor selecciona una ubicación en el mapa', 'warning', 5000);
        return;
    }
    
    const btnGuardar = document.getElementById('btnGuardar');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';
    
    const data = {
        id_pedido: <?php echo $id_pedido; ?>,
        id_cliente: <?php echo $user_id; ?>,
        direccion_entrega: document.getElementById('direccion_entrega').value,
        latitud: parseFloat(latitud),
        longitud: parseFloat(longitud),
        referencia: document.getElementById('referencia').value,
        nombre_receptor: document.getElementById('nombre_receptor').value,
        telefono_receptor: document.getElementById('telefono_receptor').value
    };
    
    try {
        const response = await fetch('../../procesos/api_ubicacion.php?action=guardar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Ubicación guardada correctamente', 'success', 3000);
            setTimeout(() => {
                window.location.href = 'mis_pedidos.php';
            }, 1000);
        } else {
            showAlert('Error: ' + result.message, 'danger', 5000);
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="fas fa-save me-1"></i> Guardar Ubicación';
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error al guardar la ubicación', 'danger', 5000);
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-save me-1"></i> Guardar Ubicación';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>