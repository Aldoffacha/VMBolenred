<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

Auth::checkAuth('admin');
$db = (new Database())->getConnection();

$pageTitle = "Productos Externos";
include '../../includes/header.php';

// Obtener productos externos
$stmt = $db->prepare("SELECT * FROM productos_exterior ORDER BY fecha_agregado DESC LIMIT 50");
$stmt->execute();
$productos_externos = $stmt->fetchAll();

// Detectar plataforma por link
function detectarPlataforma($enlace) {
    if (stripos($enlace, 'amazon') !== false) {
        return 'amazon';
    } elseif (stripos($enlace, 'ebay') !== false) {
        return 'ebay';
    }
    return 'amazon'; // default
}
?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>🌐 Productos Externos Destacados</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarExterno">
                    <i class="fas fa-plus me-1"></i>Agregar Producto Externo
                </button>
            </div>

            <!-- Tabla de productos externos -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Plataforma</th>
                                    <th>Categoría</th>
                                    <th>Precio</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($productos_externos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                        No hay productos externos agregados
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($productos_externos as $producto): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo $producto['imagen']; ?>" alt="" 
                                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px;"
                                                 onerror="this.src='https://via.placeholder.com/40x40/2c7be5/ffffff?text=Img'">
                                            <div>
                                                <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 50)); ?>...</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($producto['plataforma'] === 'amazon'): ?>
                                            <span class="badge bg-warning"><i class="fab fa-amazon me-1"></i>Amazon</span>
                                        <?php else: ?>
                                            <span class="badge bg-info"><i class="fab fa-ebay me-1"></i>eBay</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo ucfirst($producto['categoria']); ?></td>
                                    <td><strong>$<?php echo number_format($producto['precio'], 2); ?></strong></td>
                                    <td>
                                        <?php if ($producto['estado']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" onclick="editarProductoExterno(<?php echo $producto['id_producto_exterior']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarProductoExterno(<?php echo $producto['id_producto_exterior']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Agregar Producto Externo -->
<div class="modal fade" id="modalAgregarExterno" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🌐 Agregar Producto Externo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAgregarExterno">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Nombre del Producto:</strong></label>
                            <input type="text" class="form-control" id="nombreExterno" required 
                                   placeholder="Ej: Sony WH-1000XM4">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Precio (USD):</strong></label>
                            <input type="number" class="form-control" id="precioExterno" required step="0.01"
                                   placeholder="299.99">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>Descripción:</strong></label>
                        <textarea class="form-control" id="descripcionExterno" rows="3" required
                                  placeholder="Descripción del producto..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Categoría:</strong></label>
                            <select class="form-select" id="categoriaExterno" required>
                                <option value="">Selecciona una categoría</option>
                                <option value="electronico">Electrónicos</option>
                                <option value="ropa">Ropa</option>
                                <option value="hogar">Hogar</option>
                                <option value="deportes">Deportes</option>
                                <option value="otros">Otros</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Peso (kg):</strong></label>
                            <input type="number" class="form-control" id="pesoExterno" step="0.01" value="0.50"
                                   placeholder="0.50">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>Enlace del Producto:</strong></label>
                        <input type="url" class="form-control" id="enlaceExterno" required 
                               placeholder="https://amazon.com/dp/... o https://ebay.com/itm/...">
                        <small class="text-muted d-block mt-1">
                            <i class="fas fa-info-circle"></i> La plataforma se detectará automáticamente
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>URL de Imagen:</strong></label>
                        <input type="url" class="form-control" id="imagenExterno" 
                               placeholder="https://...">
                        <small class="text-muted d-block mt-1">
                            <i class="fas fa-info-circle"></i> Ingresa la URL de la imagen del producto
                        </small>
                        <div id="previewImagen" class="mt-2"></div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="destacadoExterno" checked>
                            <label class="form-check-label" for="destacadoExterno">
                                Mostrar como destacado
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarProductoExterno()">
                    <i class="fas fa-save me-1"></i>Guardar Producto
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Preview de imagen
document.getElementById('imagenExterno').addEventListener('change', function() {
    const url = this.value;
    if (url) {
        const preview = document.getElementById('previewImagen');
        preview.innerHTML = `<img src="${url}" style="max-width: 200px; max-height: 150px; border-radius: 4px;" onerror="this.src='https://via.placeholder.com/200x150/2c7be5/ffffff?text=Imagen+No+Disponible'">`;
    }
});

// Detectar plataforma al escribir enlace
document.getElementById('enlaceExterno').addEventListener('change', function() {
    const enlace = this.value;
    let plataforma = 'amazon';
    
    if (enlace.toLowerCase().includes('ebay')) {
        plataforma = 'ebay';
    } else if (enlace.toLowerCase().includes('amazon')) {
        plataforma = 'amazon';
    }
    
    console.log('Plataforma detectada:', plataforma);
});

function guardarProductoExterno() {
    const nombre = document.getElementById('nombreExterno').value;
    const precio = document.getElementById('precioExterno').value;
    const descripcion = document.getElementById('descripcionExterno').value;
    const categoria = document.getElementById('categoriaExterno').value;
    const peso = document.getElementById('pesoExterno').value;
    const enlace = document.getElementById('enlaceExterno').value;
    const imagen = document.getElementById('imagenExterno').value;
    const destacado = document.getElementById('destacadoExterno').checked ? 1 : 0;

    // Detectar plataforma
    let plataforma = 'amazon';
    if (enlace.toLowerCase().includes('ebay')) {
        plataforma = 'ebay';
    }

    if (!nombre || !precio || !descripcion || !categoria || !enlace) {
        showWarning('Por favor completa todos los campos requeridos');
        return;
    }

    const formData = new FormData();
    formData.append('nombre', nombre);
    formData.append('precio', precio);
    formData.append('descripcion', descripcion);
    formData.append('categoria', categoria);
    formData.append('peso', peso);
    formData.append('enlace', enlace);
    formData.append('imagen', imagen);
    formData.append('plataforma', plataforma);
    formData.append('destacado', destacado);

    fetch('../../procesos/agregar_producto_externo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        showError('Error al guardar: ' + error.message);
    });
}

function eliminarProductoExterno(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este producto?')) return;

    fetch('../../procesos/eliminar_producto_externo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id_producto_exterior=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showError(data.message);
        }
    })
    .catch(error => showError('Error: ' + error.message));
}

function editarProductoExterno(id) {
    // TODO: Implementar edición
    showInfo('Función de edición en desarrollo');
}
</script>

<?php include '../../includes/footer.php'; ?>
