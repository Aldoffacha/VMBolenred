<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

// Búsqueda y filtros
$busqueda = $_GET['busqueda'] ?? '';
$categoria = $_GET['categoria'] ?? '';

// Productos de Amazon y eBay (simulados) - CON IMÁGENES QUE SÍ FUNCIONAN
$productos_externos = [
    // Amazon Products (2 productos)
    [
        'id_producto' => 'amz001',
        'nombre' => 'Razer DeathAdder Essential - Mouse Gaming',
        'descripcion' => 'Mouse gaming Razer con sensor óptico de 6400 DPI, 5 botones programables y diseño ergonómico para diestros.',
        'precio' => 29.99,
        'categoria' => 'electronico',
        'stock' => 15,
        'plataforma' => 'amazon',
        'imagen' => 'https://images.unsplash.com/photo-1527814050087-3793815479db?w=400&h=300&fit=crop',
        'enlace' => 'https://amazon.com/dp/B07QSCM51V'
    ],
    [
        'id_producto' => 'amz002',
        'nombre' => 'Sony WH-1000XM4 - Audífonos Inalámbricos',
        'descripcion' => 'Audífonos noise canceling con sonido de alta resolución, 30 horas de batería y asistente de voz integrado.',
        'precio' => 348.00,
        'categoria' => 'electronico',
        'stock' => 8,
        'plataforma' => 'amazon',
        'imagen' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&h=300&fit=crop',
        'enlace' => 'https://amazon.com/dp/B0863TXGM3'
    ],

    // eBay Products (2 productos)
    [
        'id_producto' => 'eby001',
        'nombre' => 'Logitech G Pro X - Headset Gaming',
        'descripcion' => 'Headset gaming con sonido surround 7.1, micrófono desmontable Blue Voice y memoria integrada para perfiles.',
        'precio' => 89.99,
        'categoria' => 'electronico',
        'stock' => 10,
        'plataforma' => 'ebay',
        'imagen' => 'https://images.unsplash.com/photo-1599669454699-248893623440?w=400&h=300&fit=crop',
        'enlace' => 'https://ebay.com/itm/Logitech-G-PRO-X-Gaming-Headset'
    ],
    [
        'id_producto' => 'eby002',
        'nombre' => 'SteelSeries Apex Pro - Teclado Mecánico',
        'descripcion' => 'Teclado gaming mecánico con switches ajustables OmniPoint, iluminación RGB y reposamuñecas magnético.',
        'precio' => 179.99,
        'categoria' => 'electronico',
        'stock' => 6,
        'plataforma' => 'ebay',
        'imagen' => 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=400&h=300&fit=crop',
        'enlace' => 'https://ebay.com/itm/SteelSeries-Apex-Pro-TKL-Gaming-Keyboard'
    ]
];

// Obtener productos de tu base de datos LOCAL
$query = "SELECT * FROM productos WHERE estado = 1";
$params = [];

if (!empty($busqueda)) {
    // BÚSQUEDA CASE-INSENSITIVE - CORREGIDO
    $query .= " AND (LOWER(nombre) LIKE LOWER(?) OR LOWER(descripcion) LIKE LOWER(?))";
    $searchTerm = "%$busqueda%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($categoria) && $categoria != 'todos') {
    $query .= " AND categoria = ?";
    $params[] = $categoria;
}

$query .= " ORDER BY fecha_registro DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$productos_bd = $stmt->fetchAll();

// Agregar plataforma 'local' a los productos de la BD y procesar imágenes
foreach ($productos_bd as &$producto) {
    $producto['plataforma'] = 'local';
    
    // Procesar imagen local como lo tenías antes
    if (!empty($producto['imagen'])) {
        $ruta_imagen = '../../assets/img/productos/' . $producto['imagen'];
        if (file_exists($ruta_imagen)) {
            $producto['imagen_url'] = $ruta_imagen;
        } else {
            $producto['imagen_url'] = 'https://via.placeholder.com/300x200/2c7be5/ffffff?text=' . urlencode(substr($producto['nombre'], 0, 20));
        }
    } else {
        $producto['imagen_url'] = 'https://via.placeholder.com/300x200/2c7be5/ffffff?text=' . urlencode(substr($producto['nombre'], 0, 20));
    }
}
unset($producto); // Limpiar referencia

// DEBUG: Ver qué productos tenemos
error_log("Productos BD: " . count($productos_bd));
error_log("Productos externos: " . count($productos_externos));

// Combinar productos - PRIMERO los locales, LUEGO los externos
$productos = array_merge($productos_bd, $productos_externos);

// Aplicar filtros a los productos combinados si es necesario - CORREGIDO PARA SER CASE-INSENSITIVE
if (!empty($busqueda)) {
    $busqueda_lower = strtolower($busqueda);
    $productos = array_filter($productos, function($producto) use ($busqueda_lower) {
        $nombre_lower = strtolower($producto['nombre']);
        $descripcion_lower = strtolower($producto['descripcion'] ?? '');
        
        return strpos($nombre_lower, $busqueda_lower) !== false || 
               strpos($descripcion_lower, $busqueda_lower) !== false;
    });
}

if (!empty($categoria) && $categoria != 'todos') {
    $productos = array_filter($productos, function($producto) use ($categoria) {
        return ($producto['categoria'] ?? 'electronico') === $categoria;
    });
}

// Función de cálculo
function calcularCostoImportacion($precio, $peso, $categoria) {
    $impuestos = ['electronico' => 0.30, 'ropa' => 0.20, 'hogar' => 0.15, 'deportes' => 0.25, 'otros' => 0.18];
    $flete = max(15, $peso * 3);
    $seguro = $precio * 0.02;
    $aduana = $precio * ($impuestos[$categoria] ?? 0.18);
    $almacen = 25; // promedio
    
    return [
        'total' => $precio + $flete + $seguro + $aduana + $almacen,
        'desglose' => ['producto' => $precio, 'flete' => $flete, 'seguro' => $seguro, 'aduana' => $aduana, 'almacen' => $almacen]
    ];
}
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Tienda"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>🛍️ Tienda de Importación</h2>
                <div class="d-flex">
                    <input type="text" class="form-control me-2" placeholder="Buscar productos..." 
                           id="busquedaInput" value="<?php echo htmlspecialchars($busqueda); ?>">
                    <select class="form-select me-2" id="categoriaSelect" style="width: 150px;">
                        <option value="todos">Todas las categorías</option>
                        <option value="electronico" <?php echo $categoria == 'electronico' ? 'selected' : ''; ?>>Electrónicos</option>
                        <option value="ropa" <?php echo $categoria == 'ropa' ? 'selected' : ''; ?>>Ropa</option>
                        <option value="hogar" <?php echo $categoria == 'hogar' ? 'selected' : ''; ?>>Hogar</option>
                    </select>
                    <button class="btn btn-primary" onclick="buscarProductos()">🔍 Buscar</button>
                </div>
            </div>

            <!-- Filtros de Plataforma -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">🌐 Filtrar por Plataforma</h6>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-primary active" onclick="filtrarPlataforma('todas')">
                                    Todas las Plataformas
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="filtrarPlataforma('amazon')">
                                    <i class="fab fa-amazon me-1"></i>Amazon
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="filtrarPlataforma('ebay')">
                                    <i class="fab fa-ebay me-1"></i>eBay
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="filtrarPlataforma('local')">
                                    <i class="fas fa-store me-1"></i>Tienda Local
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contador de Productos -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <strong>📊 Total de productos:</strong> 
                        <span class="badge bg-primary"><?php echo count($productos); ?> productos encontrados</span> |
                        <span class="badge bg-success"><?php echo count($productos_bd); ?> locales</span> |
                        <span class="badge bg-warning"><?php echo count(array_filter($productos_externos, fn($p) => $p['plataforma'] === 'amazon')); ?> Amazon</span> |
                        <span class="badge bg-info"><?php echo count(array_filter($productos_externos, fn($p) => $p['plataforma'] === 'ebay')); ?> eBay</span>
                    </div>
                </div>
            </div>

            <div class="row" id="productosContainer">
                <?php if (empty($productos)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>No se encontraron productos</h4>
                    <p class="text-muted">Intenta con otros términos de búsqueda</p>
                </div>
                <?php else: ?>
                <?php foreach ($productos as $producto): 
                    $cotizacion = calcularCostoImportacion($producto['precio'], 0.5, $producto['categoria'] ?? 'electronico');
                    
                    // Determinar la imagen - CORREGIDO: usar imagen_url para locales, imagen para externos
                    if ($producto['plataforma'] === 'local') {
                        $imagen_url = $producto['imagen_url'] ?? 'https://via.placeholder.com/300x200/2c7be5/ffffff?text=' . urlencode(substr($producto['nombre'], 0, 20));
                    } else {
                        $imagen_url = $producto['imagen'] ?? 'https://via.placeholder.com/300x200/2c7be5/ffffff?text=' . urlencode(substr($producto['nombre'], 0, 20));
                    }
                    
                    // Determinar plataforma y badge
                    $plataforma = $producto['plataforma'] ?? 'local';
                    $badge_class = [
                        'amazon' => 'bg-warning',
                        'ebay' => 'bg-info',
                        'local' => 'bg-success'
                    ][$plataforma] ?? 'bg-secondary';
                    
                    $badge_text = [
                        'amazon' => 'Amazon',
                        'ebay' => 'eBay', 
                        'local' => 'Local'
                    ][$plataforma] ?? 'Local';
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4 producto-item" data-plataforma="<?php echo $plataforma; ?>">
                    <div class="card product-card h-100">
                        <div class="position-relative">
                            <img src="<?php echo $imagen_url; ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                 style="height: 200px; object-fit: cover;"
                                 onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200/2c7be5/ffffff?text=Imagen+No+Disponible'">
                            <span class="position-absolute top-0 start-0 badge <?php echo $badge_class; ?> m-2">
                                <?php echo $badge_text; ?>
                            </span>
                            <span class="position-absolute top-0 end-0 badge bg-primary m-2">
                                $<?php echo number_format($producto['precio'], 2); ?>
                            </span>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                            <p class="card-text flex-grow-1 text-muted small">
                                <?php echo substr($producto['descripcion'] ?? 'Sin descripción', 0, 100); ?>...
                            </p>
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Costo total:</small>
                                    <strong class="text-success">$<?php echo number_format($cotizacion['total'], 2); ?></strong>
                                </div>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary btn-sm" 
                                            onclick="mostrarModalCarrito('<?php echo $producto['id_producto']; ?>', '<?php echo htmlspecialchars($producto['nombre']); ?>', <?php echo $producto['precio']; ?>, '<?php echo $imagen_url; ?>', <?php echo $producto['stock']; ?>, '<?php echo $plataforma; ?>')">
                                        <i class="fas fa-cart-plus me-1"></i>Agregar al Carrito
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" 
                                            onclick="verDetalle('<?php echo $producto['id_producto']; ?>', '<?php echo $imagen_url; ?>', '<?php echo htmlspecialchars($producto['nombre']); ?>', <?php echo $producto['precio']; ?>, '<?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?>', <?php echo $producto['stock']; ?>, '<?php echo $plataforma; ?>', '<?php echo $producto['enlace'] ?? '#'; ?>')">
                                        <i class="fas fa-info-circle me-1"></i>Ver Detalles
                                    </button>
                                    <?php if (isset($producto['enlace']) && $producto['enlace'] != '#'): ?>
                                    <a href="<?php echo $producto['enlace']; ?>" target="_blank" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-external-link-alt me-1"></i>Ver en <?php echo ucfirst($plataforma); ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Modal Detalle Producto -->
<div class="modal fade" id="modalDetalle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContenido">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>
</div>

<!-- Modal para Agregar al Carrito -->
<div class="modal fade" id="modalAgregarCarrito" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🛒 Agregar al Carrito</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5">
                        <img id="modalImagenProducto" src="" class="img-fluid rounded" alt="Producto" 
                             style="max-height: 300px; object-fit: cover;"
                             onerror="this.onerror=null; this.src='https://via.placeholder.com/400x300/2c7be5/ffffff?text=Imagen+No+Disponible'">
                        <div class="mt-2 text-center">
                            <span id="modalPlataformaBadge" class="badge bg-warning"></span>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h4 id="modalNombreProducto"></h4>
                        <p class="text-muted" id="modalDescripcionProducto"></p>
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Precio unitario:</strong></label>
                            <h5 id="modalPrecioProducto" class="text-primary"></h5>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Stock disponible:</strong></label>
                            <span id="modalStockProducto" class="badge bg-success"></span>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label"><strong>Cantidad:</strong></label>
                            <div class="input-group" style="max-width: 200px;">
                                <button class="btn btn-outline-secondary" type="button" onclick="cambiarCantidad(-1)">-</button>
                                <input type="number" class="form-control text-center" id="cantidadProducto" value="1" min="1" max="10">
                                <button class="btn btn-outline-secondary" type="button" onclick="cambiarCantidad(1)">+</button>
                            </div>
                            <small class="text-muted">Máximo 10 unidades por pedido</small>
                        </div>
                        
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">📊 Resumen</h6>
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal:</span>
                                    <strong id="modalSubtotal">$0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Envío e impuestos:</span>
                                    <strong id="modalImpuestos">$0.00</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span><strong>Total estimado:</strong></span>
                                    <strong id="modalTotal" class="text-success">$0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarAgregarCarrito()">
                    <i class="fas fa-cart-plus me-1"></i>Agregar al Carrito
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales para el modal
let productoActual = null;
let precioUnitario = 0;
let plataformaActual = 'local';

function mostrarModalCarrito(id, nombre, precio, imagen, stock, plataforma) {
    productoActual = id;
    precioUnitario = precio;
    plataformaActual = plataforma;
    
    // Llenar datos del modal
    document.getElementById('modalImagenProducto').src = imagen;
    document.getElementById('modalNombreProducto').textContent = nombre;
    document.getElementById('modalPrecioProducto').textContent = `$${precio.toFixed(2)}`;
    document.getElementById('modalStockProducto').textContent = `${stock} unidades disponibles`;
    
    // Mostrar plataforma
    const plataformaText = {
        'amazon': 'Amazon',
        'ebay': 'eBay',
        'local': 'Tienda Local'
    }[plataforma] || 'Local';
    
    const badgeClass = {
        'amazon': 'bg-warning',
        'ebay': 'bg-info', 
        'local': 'bg-success'
    }[plataforma] || 'bg-secondary';
    
    const badgeElement = document.getElementById('modalPlataformaBadge');
    badgeElement.textContent = plataformaText;
    badgeElement.className = `badge ${badgeClass}`;
    
    // Reset cantidad
    document.getElementById('cantidadProducto').value = 1;
    
    // Calcular costos iniciales
    calcularResumen();
    
    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalAgregarCarrito'));
    modal.show();
}

function cambiarCantidad(cambio) {
    const input = document.getElementById('cantidadProducto');
    let nuevaCantidad = parseInt(input.value) + cambio;
    
    // Validar límites
    if (nuevaCantidad < 1) nuevaCantidad = 1;
    if (nuevaCantidad > 10) nuevaCantidad = 10;
    
    input.value = nuevaCantidad;
    calcularResumen();
}

function calcularResumen() {
    const cantidad = parseInt(document.getElementById('cantidadProducto').value);
    const subtotal = precioUnitario * cantidad;
    
    // Calcular costos de importación
    const costoImportacion = calcularCostoImportacionJS(precioUnitario, 0.5, 'electronico');
    const totalImportacion = costoImportacion.total * cantidad;
    const impuestos = totalImportacion - subtotal;
    
    document.getElementById('modalSubtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('modalImpuestos').textContent = `$${impuestos.toFixed(2)}`;
    document.getElementById('modalTotal').textContent = `$${totalImportacion.toFixed(2)}`;
}

function calcularCostoImportacionJS(precio, peso, categoria) {
    const impuestos = {'electronico':0.30, 'ropa':0.20, 'hogar':0.15, 'deportes':0.25, 'otros':0.18};
    const flete = Math.max(15, peso * 3);
    const seguro = precio * 0.02;
    const aduana = precio * (impuestos[categoria] || 0.18);
    const almacen = 25;
    
    return {
        total: precio + flete + seguro + aduana + almacen,
        flete: flete,
        seguro: seguro,
        aduana: aduana,
        almacen: almacen
    };
}

function confirmarAgregarCarrito() {
    const cantidad = parseInt(document.getElementById('cantidadProducto').value);
    
    if (cantidad < 1) {
        alert('Por favor selecciona una cantidad válida');
        return;
    }
    
    const formData = new FormData();
    formData.append('id_producto', productoActual);
    formData.append('cantidad', cantidad);
    formData.append('plataforma', plataformaActual);
    
    fetch('../../procesos/agregar_carrito.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAgregarCarrito'));
            modal.hide();
            
            // Mostrar mensaje de éxito
            alert(`✅ ${data.message}`);
            
            // Actualizar contador del carrito
            actualizarContadorCarrito();
        } else {
            alert(`❌ ${data.message}`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error de conexión');
    });
}

function buscarProductos() {
    const busqueda = document.getElementById('busquedaInput').value;
    const categoria = document.getElementById('categoriaSelect').value;
    window.location.href = `tienda.php?busqueda=${encodeURIComponent(busqueda)}&categoria=${categoria}`;
}

function filtrarPlataforma(plataforma) {
    const productos = document.querySelectorAll('.producto-item');
    const botones = document.querySelectorAll('.btn-group .btn');
    
    // Actualizar botones activos
    botones.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filtrar productos
    productos.forEach(producto => {
        if (plataforma === 'todas') {
            producto.style.display = 'block';
        } else {
            const productoPlataforma = producto.getAttribute('data-plataforma');
            producto.style.display = productoPlataforma === plataforma ? 'block' : 'none';
        }
    });
}

function actualizarContadorCarrito() {
    fetch('../../procesos/obtener_carrito.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.querySelector('.carrito-badge');
        if (badge) {
            badge.textContent = data.total || '0';
        }
    });
}

function verDetalle(idProducto, imagenUrl, nombre, precio, descripcion, stock, plataforma, enlace) {
    const cotizacion = calcularCostoDetalle(precio);
    
    const plataformaText = {
        'amazon': 'Amazon',
        'ebay': 'eBay',
        'local': 'Tienda Local'
    }[plataforma] || 'Local';
    
    const contenido = `
        <div class="row">
            <div class="col-md-6">
                <img src="${imagenUrl}" class="img-fluid rounded" alt="${nombre}" 
                     onerror="this.onerror=null; this.src='https://via.placeholder.com/400x300/2c7be5/ffffff?text=Imagen+No+Disponible'">
                <div class="mt-3 text-center">
                    <span class="badge ${plataforma === 'amazon' ? 'bg-warning' : plataforma === 'ebay' ? 'bg-info' : 'bg-success'}">
                        ${plataformaText}
                    </span>
                    ${enlace && enlace !== '#' ? `<a href="${enlace}" target="_blank" class="btn btn-outline-warning btn-sm mt-2">
                        <i class="fas fa-external-link-alt me-1"></i>Ver en ${plataformaText}
                    </a>` : ''}
                </div>
            </div>
            <div class="col-md-6">
                <h4>${nombre}</h4>
                <p class="text-muted">${descripcion || 'Sin descripción disponible'}</p>
                
                <div class="mb-3">
                    <label class="form-label"><strong>Precio base:</strong></label>
                    <h5 class="text-primary">$${precio.toFixed(2)}</h5>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><strong>Stock disponible:</strong></label>
                    <span class="badge bg-success">${stock} unidades</span>
                </div>
                
                <hr>
                <h5>Costos de importación (por unidad):</h5>
                <ul class="list-unstyled">
                    <li>• Producto: $${precio.toFixed(2)}</li>
                    <li>• Flete marítimo: $${cotizacion.flete.toFixed(2)}</li>
                    <li>• Seguro: $${cotizacion.seguro.toFixed(2)}</li>
                    <li>• Aduana: $${cotizacion.aduana.toFixed(2)}</li>
                    <li>• Almacenaje: $${cotizacion.almacen.toFixed(2)}</li>
                    <li class="border-top pt-2 mt-2"><strong>Total por unidad: $${cotizacion.total.toFixed(2)}</strong></li>
                </ul>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="$('#modalDetalle').modal('hide'); setTimeout(() => mostrarModalCarrito('${idProducto}', '${nombre}', ${precio}, '${imagenUrl}', ${stock}, '${plataforma}'), 300);">
                        <i class="fas fa-cart-plus me-1"></i>Agregar al Carrito
                    </button>
                </div>
            </div>
        </div>
    `;
    document.getElementById('detalleContenido').innerHTML = contenido;
    $('#modalDetalle').modal('show');
}

function calcularCostoDetalle(precio) {
    const flete = Math.max(15, 0.5 * 3);
    const seguro = precio * 0.02;
    const aduana = precio * 0.30;
    const almacen = 25;
    
    return {
        total: precio + flete + seguro + aduana + almacen,
        flete: flete,
        seguro: seguro,
        aduana: aduana,
        almacen: almacen
    };
}

// Buscar al presionar Enter
document.getElementById('busquedaInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') buscarProductos();
});

function actualizarCarrito() {
    fetch('../../procesos/obtener_carrito.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.querySelector('.carrito-badge');
        if (badge) badge.textContent = data.total || '0';
    });
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    actualizarCarrito();
    
    // Actualizar resumen cuando cambia la cantidad manualmente
    document.getElementById('cantidadProducto').addEventListener('input', calcularResumen);
});
</script>

<?php include '../../includes/footer.php'; ?>