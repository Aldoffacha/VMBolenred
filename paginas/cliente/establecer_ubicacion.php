<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

// Verificar sesión
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['usuario']['id_cliente'])) {
    $user_id = $_SESSION['usuario']['id_cliente'];
} else {
    header('Location: ../../public/login.php');
    exit;
}

$id_pedido = isset($_GET['id_pedido']) ? intval($_GET['id_pedido']) : 0;

if ($id_pedido === 0) {
    header('Location: mis_pedidos.php');
    exit;
}

// Función para verificar si el pedido está pagado (verifica AMBAS tablas)
function estaPagado($pedido) {
    // Verificar estado en tabla pedidos (lo que actualiza el admin)
    $estado_pedido = strtolower(trim($pedido['estado_pedido'] ?? ''));
    
    // Verificar estado en tabla pagos (si existe)
    $estado_pago = strtolower(trim($pedido['estado_pago'] ?? ''));
    
    // Si el estado del pedido es 'pagado' O el estado del pago es 'pagado'
    return ($estado_pedido === 'pagado' || $estado_pago === 'pagado' || $estado_pago === 'confirmado');
}

// Verificar que el pedido pertenece al cliente y está pagado
try {
    $query = "SELECT p.*, 
              p.estado AS estado_pedido,  -- ¡IMPORTANTE!
              COALESCE(pg.estado, 'sin_pago') as estado_pago,
              ue.direccion_entrega, ue.latitud, ue.longitud,
              ue.referencia, ue.nombre_receptor, ue.telefono_receptor
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

    // DEBUG
    error_log("DEBUG establecer_ubicacion - Estado pedido: " . $pedido['estado_pedido'] . " | Estado pago: " . $pedido['estado_pago']);

    if (!estaPagado($pedido)) {
        $_SESSION['error'] = 'El pedido debe estar pagado para establecer ubicación de entrega. Estado actual: ' . 
                           $pedido['estado_pedido'] . ' (pedido) / ' . $pedido['estado_pago'] . ' (pago)';
        header('Location: detalle_pedido.php?id_pedido=' . $id_pedido);
        exit;
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error al cargar información del pedido: ' . $e->getMessage();
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
                <a href="detalle_pedido.php?id_pedido=<?php echo $id_pedido; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Volver al pedido
                </a>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Pedido #<?php echo $pedido['id_pedido']; ?></strong> - 
                Total: $<?php echo number_format($pedido['total'], 2); ?> - 
                Estado: <span class="badge bg-success"><?php echo strtoupper($pedido['estado_pedido']); ?></span>
                <?php if ($pedido['estado_pago'] !== 'sin_pago'): ?>
                | Pago: <span class="badge bg-info"><?php echo strtoupper($pedido['estado_pago']); ?></span>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Selecciona tu ubicación en el mapa</h5>
                        </div>
                        <div class="card-body">
                            <div id="map" style="height: 400px; width: 100%; border-radius: 8px; margin-bottom: 20px;"></div>
                            
                            <form id="formUbicacion" class="mt-3">
                                <input type="hidden" id="latitud" name="latitud" value="<?php echo htmlspecialchars($pedido['latitud'] ?? '-16.5000'); ?>" required>
                                <input type="hidden" id="longitud" name="longitud" value="<?php echo htmlspecialchars($pedido['longitud'] ?? '-68.1500'); ?>" required>
                                
                                <div class="mb-3">
                                    <label for="direccion_entrega" class="form-label">
                                        <i class="fas fa-map-marker-alt me-1"></i> Dirección completa *
                                    </label>
                                    <textarea class="form-control" id="direccion_entrega" name="direccion_entrega" 
                                              rows="2" required placeholder="Calle, número, zona, ciudad"><?php echo htmlspecialchars($pedido['direccion_entrega'] ?? ''); ?></textarea>
                                    <div class="form-text">Esta dirección será usada para la entrega del pedido.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="referencia" class="form-label">
                                        <i class="fas fa-flag me-1"></i> Referencias adicionales
                                    </label>
                                    <input type="text" class="form-control" id="referencia" name="referencia" 
                                           value="<?php echo htmlspecialchars($pedido['referencia'] ?? ''); ?>"
                                           placeholder="Ej: Casa de dos pisos color azul, portón negro, etc.">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nombre_receptor" class="form-label">
                                            <i class="fas fa-user me-1"></i> Nombre de quien recibe
                                        </label>
                                        <input type="text" class="form-control" id="nombre_receptor" name="nombre_receptor"
                                               value="<?php echo htmlspecialchars($pedido['nombre_receptor'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="telefono_receptor" class="form-label">
                                            <i class="fas fa-phone me-1"></i> Teléfono de contacto
                                        </label>
                                        <input type="text" class="form-control" id="telefono_receptor" name="telefono_receptor"
                                               value="<?php echo htmlspecialchars($pedido['telefono_receptor'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Importante:</strong> Haz clic en el mapa para establecer tu ubicación exacta de entrega. 
                                    La ubicación debe ser precisa para una entrega exitosa.
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="detalle_pedido.php?id_pedido=<?php echo $id_pedido; ?>" class="btn btn-secondary me-md-2">
                                        Cancelar
                                    </a>
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
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar mapa
    const latInput = document.getElementById('latitud');
    const lngInput = document.getElementById('longitud');
    
    const defaultLat = parseFloat(latInput.value) || -16.5000;
    const defaultLng = parseFloat(lngInput.value) || -68.1500;
    
    const map = L.map('map').setView([defaultLat, defaultLng], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    let marker = null;
    
    // Si ya hay ubicación guardada, mostrar marker
    if (latInput.value && lngInput.value && latInput.value != '-16.5000' && lngInput.value != '-68.1500') {
        marker = L.marker([defaultLat, defaultLng]).addTo(map);
        marker.bindPopup('Ubicación actual').openPopup();
    }
    
    // Click en el mapa para establecer ubicación
    map.on('click', function(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        
        if (marker) {
            map.removeLayer(marker);
        }
        
        marker = L.marker([lat, lng]).addTo(map);
        marker.bindPopup('Nueva ubicación').openPopup();
        
        latInput.value = lat;
        lngInput.value = lng;
        
        // Intentar obtener dirección de OpenStreetMap (Nominatim)
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(response => response.json())
            .then(data => {
                if (data.display_name) {
                    document.getElementById('direccion_entrega').value = data.display_name;
                }
            })
            .catch(error => console.error('Error obteniendo dirección:', error));
    });
    
    // Manejar envío del formulario
    document.getElementById('formUbicacion').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const latitud = latInput.value;
        const longitud = lngInput.value;
        const direccion = document.getElementById('direccion_entrega').value;
        
        if (!latitud || !longitud || latitud == '-16.5000' || longitud == '-68.1500') {
            showAlert('Por favor selecciona una ubicación en el mapa haciendo clic en él', 'warning', 5000);
            return;
        }
        
        if (!direccion.trim()) {
            showAlert('Por favor ingresa una dirección completa', 'warning', 5000);
            return;
        }
        
        const btnGuardar = document.getElementById('btnGuardar');
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';
        
        const formData = new FormData(this);
        formData.append('action', 'guardar');
        formData.append('id_pedido', <?php echo $id_pedido; ?>);
        formData.append('id_cliente', <?php echo $user_id; ?>);
        
        // Reemplaza el fetch con esta versión mejorada:
try {
    const response = await fetch('../../procesos/guardar_ubicacion.php', {
        method: 'POST',
        body: formData,
        // Añadir timeout y mejor manejo de errores
        signal: AbortSignal.timeout(30000) // 30 segundos timeout
    });
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    console.log('Resultado del servidor:', result); // Para debugging
    
    if (result.success) {
        showAlert('¡Ubicación guardada correctamente!', 'success', 3000);
        setTimeout(() => {
            window.location.href = 'detalle_pedido.php?id_pedido=<?php echo $id_pedido; ?>';
        }, 1500);
    } else {
        showAlert('Error: ' + (result.message || 'No se pudo guardar la ubicación'), 'danger', 5000);
        console.error('Error del servidor:', result);
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-save me-1"></i> Guardar Ubicación';
    }
} catch (error) {
    console.error('Error de red o servidor:', error);
    
    // Verificar si es error de timeout
    if (error.name === 'TimeoutError' || error.name === 'AbortError') {
        showAlert('Error: Tiempo de espera agotado. Verifica tu conexión a internet.', 'danger', 5000);
    } else {
        showAlert('Error de conexión: ' + error.message, 'danger', 5000);
    }
    
    btnGuardar.disabled = false;
    btnGuardar.innerHTML = '<i class="fas fa-save me-1"></i> Guardar Ubicación';
}
    });
    
    function showAlert(message, type = 'info', duration = 3000) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        alertDiv.style.zIndex = '1050';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.remove();
        }, duration);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>