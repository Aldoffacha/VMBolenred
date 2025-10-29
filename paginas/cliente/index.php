<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

function calcularCostoImportacion($precio, $peso, $categoria) {
    $impuestos = [
        'electronico' => 0.30, 'ropa' => 0.20, 'hogar' => 0.15, 
        'deportes' => 0.25, 'otros' => 0.18
    ];
    
    $almacen = ['pequeno' => 10, 'mediano' => 25, 'grande' => 50, 'extra' => 80];
    
    $impuesto = $impuestos[$categoria] ?? 0.18;
    $flete_maritimo = max(15, $peso * 3);
    $seguro = $precio * 0.02;
    $aduana = $precio * $impuesto;
    $costo_almacen = 25; 
    
    $costo_total = $precio + $flete_maritimo + $seguro + $aduana + $costo_almacen;
    
    return [
        'total' => $costo_total,
        'desglose' => [
            'producto' => $precio, 'flete' => $flete_maritimo, 
            'seguro' => $seguro, 'aduana' => $aduana, 'almacen' => $costo_almacen
        ]
    ];
}

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];

// Obtener estadísticas REALES
$stmt = $db->prepare("SELECT COUNT(*) as total_pedidos FROM pedidos WHERE id_cliente = ? AND estado != 'cancelado'");
$stmt->execute([$user_id]);
$stats['total_pedidos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_pedidos'];

$stmt = $db->prepare("SELECT COUNT(*) as envios_camino FROM pedidos p 
                     LEFT JOIN envios_importacion e ON p.id_pedido = e.id_pedido 
                     WHERE p.id_cliente = ? AND e.estado = 'en_transito'");
$stmt->execute([$user_id]);
$stats['envios_camino'] = $stmt->fetch(PDO::FETCH_ASSOC)['envios_camino'];

$stmt = $db->prepare("SELECT COUNT(*) as total_carrito FROM carrito WHERE id_cliente = ?");
$stmt->execute([$user_id]);
$stats['total_carrito'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_carrito'];

$stmt = $db->prepare("SELECT COUNT(*) as cotizaciones_pendientes FROM cotizaciones WHERE id_cliente = ? AND estado = 'pendiente'");
$stmt->execute([$user_id]);
$stats['cotizaciones_pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['cotizaciones_pendientes'];

// Obtener pedidos en camino para el modal
$stmt = $db->prepare("
    SELECT p.*, e.* 
    FROM pedidos p 
    LEFT JOIN envios_importacion e ON p.id_pedido = e.id_pedido 
    WHERE p.id_cliente = ? AND e.estado = 'en_transito'
    ORDER BY p.fecha DESC
");
$stmt->execute([$user_id]);
$envios_camino = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos del carrito para el modal
$stmt = $db->prepare("
    SELECT c.*, p.nombre, p.precio, p.imagen 
    FROM carrito c 
    JOIN productos p ON c.id_producto = p.id_producto 
    WHERE c.id_cliente = ?
");
$stmt->execute([$user_id]);
$carrito_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener cotizaciones pendientes para el modal
$stmt = $db->prepare("
    SELECT c.*, cl.nombre as cliente_nombre
    FROM cotizaciones c 
    JOIN clientes cl ON c.id_cliente = cl.id_cliente 
    WHERE c.id_cliente = ? AND c.estado = 'pendiente'
    ORDER BY c.fecha DESC
");
$stmt->execute([$user_id]);
$cotizaciones_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular total del carrito
$total_carrito = 0;
foreach ($carrito_productos as $item) {
    $total_carrito += $item['precio'] * $item['cantidad'];
}

$stmt = $db->prepare("
    SELECT p.*, e.estado as estado_envio 
    FROM pedidos p 
    LEFT JOIN envios_importacion e ON p.id_pedido = e.id_pedido 
    WHERE p.id_cliente = ? 
    ORDER BY p.fecha DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$pedidos_recientes = $stmt->fetchAll();

// Obtener productos por categorías REALES
$categorias = [
    'electronico' => ['icon' => '📱', 'nombre' => 'Electrónicos', 'color' => 'primary'],
    'ropa' => ['icon' => '👕', 'nombre' => 'Ropa y Moda', 'color' => 'success'],
    'hogar' => ['icon' => '🏠', 'nombre' => 'Hogar', 'color' => 'info'],
    'deportes' => ['icon' => '⚽', 'nombre' => 'Deportes', 'color' => 'warning'],
    'otros' => ['icon' => '📦', 'nombre' => 'Otros', 'color' => 'secondary']
];

$productos_por_categoria = [];
foreach ($categorias as $categoria_key => $categoria_info) {
    $stmt = $db->prepare("
        SELECT * FROM productos 
        WHERE estado = 1 AND categoria = ? 
        LIMIT 8
    ");
    $stmt->execute([$categoria_key]);
    $productos = $stmt->fetchAll();
    
    if (count($productos) > 0) {
        $productos_por_categoria[$categoria_key] = [
            'info' => $categoria_info,
            'productos' => $productos
        ];
    }
}

// Productos de Amazon y eBay (simulados)
$productos_externos = [
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

// Obtener productos destacados
$productos = $db->query("SELECT * FROM productos WHERE estado = 1 LIMIT 8")->fetchAll();
?>
<?php include '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/cliente.css">
<?php $pageTitle = "Dashboard Cliente"; ?>

<div class="container-fluid cliente-dashboard">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 cliente-dashboard">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">¡Bienvenido, <?php echo $_SESSION['nombre']; ?>! 👋</h1>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCotizacion">
                    💰 Cotización Rápida
                </button>
            </div>

            <!-- Estadísticas Rápidas -->
            <div class="row mb-4">
                <!-- Pedidos Activos - Clickable -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2 clickable-card" onclick="irAPedidos()" style="cursor: pointer;">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Pedidos Activos</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_pedidos']; ?></div>
                                    <small class="text-muted">Haz click para ver detalles</small>
                                </div>
                                <div class="col-auto">📋</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Envíos en Camino - Modal -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2 clickable-card" onclick="mostrarEnviosCamino()" style="cursor: pointer;">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Envíos en Camino</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['envios_camino']; ?></div>
                                    <small class="text-muted">Haz click para ver seguimiento</small>
                                </div>
                                <div class="col-auto">🚚</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cotizaciones Pendientes - Modal -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2 clickable-card" onclick="mostrarCotizaciones()" style="cursor: pointer;">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Cotizaciones Pendientes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['cotizaciones_pendientes']; ?></div>
                                    <small class="text-muted">Haz click para ver detalles</small>
                                </div>
                                <div class="col-auto">💵</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Productos en Carrito - Modal -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2 clickable-card" onclick="mostrarCarrito()" style="cursor: pointer;">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Productos en Carrito</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="contador-carrito"><?php echo $stats['total_carrito']; ?></div>
                                    <small class="text-muted">Haz click para ver carrito</small>
                                </div>
                                <div class="col-auto">🛒</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carrusel de Amazon y eBay - CON BOTONES GRANDES Y FUNCIONALIDAD COMPLETA -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-gradient-warning text-dark">
                            <h6 class="m-0 font-weight-bold">
                                🌐 Productos de Amazon & eBay
                            </h6>
                            <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregarExterno">
                                <i class="fas fa-plus me-1"></i>Agregar Producto Externo
                            </button>
                        </div>
                        <div class="card-body position-relative">
                            <?php if (count($productos_externos) > 0): ?>
                            <div id="carouselExternos" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner">
                                    <?php 
                                    // Dividir productos externos en grupos de 4
                                    $externos_chunks = array_chunk($productos_externos, 4);
                                    foreach ($externos_chunks as $chunk_index => $productos_chunk): 
                                    ?>
                                    <div class="carousel-item <?php echo $chunk_index === 0 ? 'active' : ''; ?>">
                                        <div class="row g-3">
                                            <?php foreach ($productos_chunk as $producto): 
                                                $cotizacion = calcularCostoImportacion($producto['precio'], 0.5, $producto['categoria']);
                                                $badge_class = $producto['plataforma'] === 'amazon' ? 'bg-warning' : 'bg-info';
                                                $badge_text = $producto['plataforma'] === 'amazon' ? 'Amazon' : 'eBay';
                                            ?>
                                            <div class="col-xl-3 col-lg-4 col-md-6">
                                                <div class="card product-card h-100 shadow-sm border-<?php echo $producto['plataforma'] === 'amazon' ? 'warning' : 'info'; ?>">
                                                    <div class="position-relative">
                                                        <img src="<?php echo $producto['imagen']; ?>" 
                                                             class="card-img-top" 
                                                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                             style="height: 200px; object-fit: cover;"
                                                             onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200/2c7be5/ffffff?text=Imagen+No+Disponible'">
                                                        <div class="position-absolute top-0 start-0 m-2">
                                                            <span class="badge <?php echo $badge_class; ?> text-dark">
                                                                <i class="fab fa-<?php echo $producto['plataforma']; ?> me-1"></i><?php echo $badge_text; ?>
                                                            </span>
                                                        </div>
                                                        <span class="position-absolute top-0 end-0 badge bg-dark m-2">
                                                            $<?php echo number_format($producto['precio'], 2); ?>
                                                        </span>
                                                    </div>
                                                    <div class="card-body d-flex flex-column">
                                                        <h6 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                                        <p class="card-text flex-grow-1 text-muted small">
                                                            <?php echo substr($producto['descripcion'] ?? 'Descripción no disponible', 0, 60); ?>...
                                                        </p>
                                                        <div class="mt-auto">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <small class="text-muted">Total con importación:</small>
                                                                <strong class="text-success">$<?php echo number_format($cotizacion['total'], 2); ?></strong>
                                                            </div>
                                                            <div class="d-grid gap-2">
                                                                <!-- BOTÓN CORREGIDO - onclick con parámetros correctos -->
                                                                <button class="btn btn-<?php echo $producto['plataforma'] === 'amazon' ? 'warning' : 'info'; ?> btn-sm" 
                                                                        onclick="mostrarModalCarrito('<?php echo $producto['id_producto']; ?>', '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>', <?php echo $producto['precio']; ?>, '<?php echo $producto['imagen']; ?>', <?php echo $producto['stock']; ?>, '<?php echo $producto['plataforma']; ?>', '<?php echo $producto['enlace']; ?>')">
                                                                    <i class="fas fa-cart-plus me-1"></i>Cotizar y Agregar
                                                                </button>
                                                                <a href="<?php echo $producto['enlace']; ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                                                                    <i class="fas fa-external-link-alt me-1"></i>Ver en <?php echo $badge_text; ?>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($externos_chunks) > 1): ?>
                                <!-- BOTONES GRANDES Y VISIBLES -->
                                <button class="carousel-control-prev carousel-btn-big" type="button" data-bs-target="#carouselExternos" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Anterior</span>
                                </button>
                                <button class="carousel-control-next carousel-btn-big" type="button" data-bs-target="#carouselExternos" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Siguiente</span>
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Indicadores del carrusel (opcionales) -->
                            <?php if (count($externos_chunks) > 1): ?>
                            <div class="text-center mt-3">
                                <?php for ($i = 0; $i < count($externos_chunks); $i++): ?>
                                <button type="button" data-bs-target="#carouselExternos" data-bs-slide-to="<?php echo $i; ?>" 
                                        class="btn btn-sm <?php echo $i === 0 ? 'btn-warning' : 'btn-outline-warning'; ?> mx-1"
                                        style="width: 10px; height: 10px; padding: 0; border-radius: 50%;">
                                </button>
                                <?php endfor; ?>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-globe-americas fa-3x text-muted mb-3"></i>
                                <h6>No hay productos externos disponibles</h6>
                                <p class="text-muted small">Agrega productos de Amazon o eBay para verlos aquí</p>
                                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalAgregarExterno">
                                    <i class="fas fa-plus me-1"></i>Agregar Primer Producto
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carruseles Individuales por Categoría - CON BOTONES GRANDES Y FUNCIONALIDAD COMPLETA -->
            <div class="row mb-4">
                <div class="col-12">
                    <?php foreach ($productos_por_categoria as $categoria_key => $categoria_data): 
                        $categoria_info = $categoria_data['info'];
                        $productos_categoria = $categoria_data['productos'];
                    ?>
                    <div class="card shadow mb-4 categoria-carousel-container">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-<?php echo $categoria_info['color']; ?> text-white">
                            <h6 class="m-0 font-weight-bold">
                                <?php echo $categoria_info['icon']; ?> 
                                <?php echo $categoria_info['nombre']; ?>
                            </h6>
                            <a href="tienda.php?categoria=<?php echo $categoria_key; ?>" class="btn btn-sm btn-light">
                                Ver Todos <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body position-relative">
                            <?php if (count($productos_categoria) > 0): ?>
                            <div class="position-relative">
                                <!-- ESTRUCTURA CORREGIDA: agregar class="carousel slide" y data-bs-ride -->
                                <div id="carousel-<?php echo $categoria_key; ?>" class="carousel slide" data-bs-ride="carousel">
                                    <div class="carousel-inner">
                                        <?php 
                                        // Dividir productos en grupos de 4 para el carrusel
                                        $productos_chunks = array_chunk($productos_categoria, 4);
                                        foreach ($productos_chunks as $chunk_index => $productos_chunk): 
                                        ?>
                                        <div class="carousel-item <?php echo $chunk_index === 0 ? 'active' : ''; ?>">
                                            <div class="row g-3">
                                                <?php foreach ($productos_chunk as $producto): 
                                                    $cotizacion = calcularCostoImportacion($producto['precio'], 0.5, $producto['categoria']);
                                                    
                                                    $imagen_url = '';
                                                    if (!empty($producto['imagen'])) {
                                                        $ruta_imagen = '../../assets/img/productos/' . $producto['imagen'];
                                                        
                                                        if (file_exists($ruta_imagen)) {
                                                            $imagen_url = $ruta_imagen;
                                                        } else {
                                                            $imagen_url = 'https://via.placeholder.com/300x200/2c7be5/ffffff?text=' . urlencode(substr($producto['nombre'], 0, 20));
                                                        }
                                                    } else {
                                                        $imagen_url = 'https://via.placeholder.com/300x200/2c7be5/ffffff?text=' . urlencode(substr($producto['nombre'], 0, 20));
                                                    }
                                                ?>
                                                <div class="col-xl-3 col-lg-4 col-md-6">
                                                    <div class="card product-card h-100 shadow-sm">
                                                        <div class="position-relative">
                                                            <img src="<?php echo $imagen_url; ?>" 
                                                                 class="card-img-top" 
                                                                 alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                                 style="height: 200px; object-fit: cover;"
                                                                 onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200/2c7be5/ffffff?text=Imagen+No+Disponible'">
                                                            <div class="position-absolute top-0 start-0 m-2">
                                                                <span class="badge bg-<?php echo $categoria_info['color']; ?>">
                                                                    <?php echo $categoria_info['icon']; ?>
                                                                </span>
                                                            </div>
                                                            <span class="position-absolute top-0 end-0 badge bg-dark m-2">
                                                                $<?php echo number_format($producto['precio'], 2); ?>
                                                            </span>
                                                        </div>
                                                        <div class="card-body d-flex flex-column">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                                            <p class="card-text flex-grow-1 text-muted small">
                                                                <?php echo substr($producto['descripcion'] ?? 'Descripción no disponible', 0, 60); ?>...
                                                            </p>
                                                            <div class="mt-auto">
                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                    <small class="text-muted">Total con importación:</small>
                                                                    <strong class="text-success">$<?php echo number_format($cotizacion['total'], 2); ?></strong>
                                                                </div>
                                                                <!-- BOTÓN CORREGIDO - onclick con parámetros correctos -->
                                                                <button class="btn btn-<?php echo $categoria_info['color']; ?> btn-sm w-100" 
                                                                        onclick="mostrarModalCarrito(<?php echo $producto['id_producto']; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>', <?php echo $producto['precio']; ?>, '<?php echo $imagen_url; ?>', <?php echo $producto['stock']; ?>, 'local')">
                                                                    <i class="fas fa-cart-plus me-1"></i>Agregar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if (count($productos_chunks) > 1): ?>
                                    <!-- BOTONES GRANDES Y VISIBLES -->
                                    <button class="carousel-control-prev carousel-btn-big" type="button" data-bs-target="#carousel-<?php echo $categoria_key; ?>" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Anterior</span>
                                    </button>
                                    <button class="carousel-control-next carousel-btn-big" type="button" data-bs-target="#carousel-<?php echo $categoria_key; ?>" data-bs-slide="next">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Siguiente</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Indicadores del carrusel (opcionales) -->
                                <?php if (count($productos_chunks) > 1): ?>
                                <div class="text-center mt-3">
                                    <?php for ($i = 0; $i < count($productos_chunks); $i++): ?>
                                    <button type="button" 
                                            data-bs-target="#carousel-<?php echo $categoria_key; ?>" 
                                            data-bs-slide-to="<?php echo $i; ?>" 
                                            class="btn btn-sm <?php echo $i === 0 ? 'btn-' . $categoria_info['color'] : 'btn-outline-' . $categoria_info['color']; ?> mx-1"
                                            style="width: 10px; height: 10px; padding: 0; border-radius: 50%;">
                                    </button>
                                    <?php endfor; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h6>No hay productos en esta categoría</h6>
                                <p class="text-muted small">Próximamente tendremos productos aquí</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Productos Destacados -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-gradient-primary text-white">
                            <h6 class="m-0 font-weight-bold">🔥 Productos Destacados</h6>
                            <a href="tienda.php" class="btn btn-sm btn-light">Ver Todos <i class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($productos as $producto): 
                                    $cotizacion = calcularCostoImportacion($producto['precio'], 0.5, $producto['categoria']);
                                    
                                    $imagen_url = '';
                                    if (!empty($producto['imagen'])) {
                                        $ruta_imagen = '../../assets/img/productos/' . $producto['imagen'];
                                        
                                        if (file_exists($ruta_imagen)) {
                                            $imagen_url = $ruta_imagen;
                                        } else {
                                            $imagen_url = 'https://via.placeholder.com/300x200/2c7be5/ffffff?text=' . urlencode(substr($producto['nombre'], 0, 20));
                                        }
                                    } else {
                                        $imagen_url = 'https://via.placeholder.com/300x200/2c7be5/ffffff?text=' . urlencode(substr($producto['nombre'], 0, 20));
                                    }
                                ?>
                                <div class="col-lg-3 col-md-6 mb-4">
                                    <div class="card product-card h-100 shadow-sm">
                                        <div class="position-relative">
                                            <img src="<?php echo $imagen_url; ?>" 
                                                 class="card-img-top" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                                 style="height: 200px; object-fit: cover;"
                                                 onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200/2c7be5/ffffff?text=Imagen+No+Disponible'">
                                            <span class="position-absolute top-0 end-0 badge bg-primary m-2">
                                                $<?php echo number_format($producto['precio'], 2); ?>
                                            </span>
                                        </div>
                                        <div class="card-body d-flex flex-column">
                                            <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                            <p class="card-text flex-grow-1 text-muted small">
                                                <?php echo substr($producto['descripcion'] ?? 'Descripción no disponible', 0, 80); ?>...
                                            </p>
                                            <div class="mt-auto">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">Costo total:</small>
                                                    <strong class="text-success">$<?php echo number_format($cotizacion['total'], 2); ?></strong>
                                                </div>
                                                <!-- BOTÓN CORREGIDO - onclick con parámetros correctos -->
                                                <button class="btn btn-primary btn-sm w-100" 
                                                        onclick="mostrarModalCarrito(<?php echo $producto['id_producto']; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>', <?php echo $producto['precio']; ?>, '<?php echo $imagen_url; ?>', <?php echo $producto['stock']; ?>, 'local')">
                                                    🛒 Agregar al Carrito
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pedidos Recientes -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3 bg-gradient-secondary text-white">
                            <h6 class="m-0 font-weight-bold">📦 Pedidos Recientes</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th># Pedido</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($pedidos_recientes)): ?>
                                            <?php foreach ($pedidos_recientes as $pedido): 
                                                $badgeClass = [
                                                    'pendiente' => 'warning',
                                                    'pagado' => 'info', 
                                                    'enviado' => 'success',
                                                    'cancelado' => 'danger'
                                                ][$pedido['estado']] ?? 'secondary';
                                            ?>
                                            <tr>
                                                <td><strong>#VM<?php echo $pedido['id_pedido']; ?></strong></td>
                                                <td><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></td>
                                                <td>$<?php echo number_format($pedido['total'], 2); ?></td>
                                                <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($pedido['estado']); ?></span></td>
                                                <td>
                                                    <?php if ($pedido['estado'] == 'enviado'): ?>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="mostrarSeguimiento(<?php echo $pedido['id_pedido']; ?>)">
                                                            <i class="fas fa-shipping-fast me-1"></i>Seguimiento
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-eye me-1"></i>Ver Detalles
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fas fa-shopping-cart fa-3x mb-3"></i><br>
                                                    No tienes pedidos recientes
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Cotización Rápida -->
<div class="modal fade" id="modalCotizacion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">💰 Calculadora de Importación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCotizacion">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Precio del Producto (USD)</label>
                                <input type="number" step="0.01" class="form-control" id="precioProducto" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Peso (kg)</label>
                                <input type="number" step="0.1" class="form-control" id="pesoProducto" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" id="categoriaProducto" required>
                                    <option value="electronico">📱 Electrónico (30%)</option>
                                    <option value="ropa">👕 Ropa (20%)</option>
                                    <option value="hogar">🏠 Hogar (15%)</option>
                                    <option value="deportes">⚽ Deportes (25%)</option>
                                    <option value="otros">📦 Otros (18%)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tamaño</label>
                                <select class="form-select" id="tamanoProducto" required>
                                    <option value="pequeno">Pequeño - $10</option>
                                    <option value="mediano">Mediano - $25</option>
                                    <option value="grande">Grande - $50</option>
                                    <option value="extra">Extra Grande - $80+</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-light mt-3">
                        <div class="card-body">
                            <h6 class="card-title">📊 Costos de Importación</h6>
                            <div id="resultadoCotizacion">
                                <p class="text-muted">Complete los datos para calcular</p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="calcularCotizacion()">Calcular</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Agregar Producto Externo -->
<div class="modal fade" id="modalAgregarExterno" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🌐 Agregar Producto de Amazon/eBay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>¿Cómo funciona?</strong> Ingresa el enlace de cualquier producto de Amazon o eBay y nosotros obtenemos automáticamente la información para cotizar la importación.
                </div>
                
                <form id="formProductoExterno">
                    <div class="mb-3">
                        <label class="form-label"><strong>🔗 URL del Producto</strong></label>
                        <input type="url" class="form-control" id="urlProductoExterno" 
                               placeholder="https://amazon.com/dp/... o https://ebay.com/itm/..." required>
                        <div class="form-text">Pega el enlace completo del producto que deseas importar</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Precio (USD)</strong></label>
                                <input type="number" step="0.01" class="form-control" id="precioProductoExterno" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Peso estimado (kg)</strong></label>
                                <input type="number" step="0.1" class="form-control" id="pesoProductoExterno" value="0.5" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>Categoría</strong></label>
                        <select class="form-select" id="categoriaProductoExterno" required>
                            <option value="electronico">📱 Electrónico</option>
                            <option value="ropa">👕 Ropa</option>
                            <option value="hogar">🏠 Hogar</option>
                            <option value="deportes">⚽ Deportes</option>
                            <option value="otros">📦 Otros</option>
                        </select>
                    </div>
                    
                    <div class="card bg-light mt-3">
                        <div class="card-body">
                            <h6 class="card-title">📊 Resumen de Costos</h6>
                            <div id="resultadoCotizacionExterno">
                                <p class="text-muted">Complete los datos para calcular la cotización</p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="obtenerInfoProducto()">
                    <i class="fas fa-magic me-1"></i>Obtener Info Automática
                </button>
                <button type="button" class="btn btn-primary" onclick="calcularCotizacionExterno()">Calcular Cotización</button>
                <button type="button" class="btn btn-success" onclick="agregarProductoExterno()" id="btnAgregarExterno" disabled>
                    <i class="fas fa-cart-plus me-1"></i>Agregar al Carrito
                </button>
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

<!-- Modal para Envíos en Camino -->
<div class="modal fade" id="modalEnviosCamino" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🚚 Envíos en Camino</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($envios_camino)): ?>
                    <?php foreach ($envios_camino as $envio): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6>Pedido #VM<?php echo $envio['id_pedido']; ?></h6>
                                    <p class="mb-1"><strong>Total:</strong> $<?php echo number_format($envio['total'], 2); ?></p>
                                    <p class="mb-1"><strong>Guía Aérea:</strong> <?php echo $envio['guia_aerea'] ?? 'Pendiente'; ?></p>
                                    <p class="mb-1"><strong>Aerolínea:</strong> <?php echo $envio['aerolinea'] ?? 'Por asignar'; ?></p>
                                    <p class="mb-1"><strong>Estado:</strong> <span class="badge bg-info">En Tránsito</span></p>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-end">
                                        <p class="mb-1"><small>Salida Miami:</small><br>
                                        <strong><?php echo $envio['fecha_salida_miami'] ? date('d/m/Y', strtotime($envio['fecha_salida_miami'])) : 'Pendiente'; ?></strong></p>
                                        <p class="mb-0"><small>Llegada estimada:</small><br>
                                        <strong><?php echo $envio['fecha_llegada_bolivia'] ? date('d/m/Y', strtotime($envio['fecha_llegada_bolivia'])) : 'Por confirmar'; ?></strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-shipping-fast fa-3x text-muted mb-3"></i>
                        <h5>No tienes envíos en camino</h5>
                        <p class="text-muted">Todos tus pedidos están siendo procesados</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a href="pedidos.php" class="btn btn-primary">Ver Todos los Pedidos</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Cotizaciones Pendientes -->
<div class="modal fade" id="modalCotizaciones" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">💵 Cotizaciones Pendientes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($cotizaciones_pendientes)): ?>
                    <?php foreach ($cotizaciones_pendientes as $cotizacion): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6>Cotización #<?php echo $cotizacion['id_cotizacion']; ?></h6>
                                    <p class="mb-1"><strong>Producto:</strong> <?php echo htmlspecialchars($cotizacion['nombre_producto']); ?></p>
                                    <p class="mb-1"><strong>Precio Base:</strong> $<?php echo number_format($cotizacion['precio_base'], 2); ?></p>
                                    <p class="mb-1"><strong>Categoría:</strong> <?php echo htmlspecialchars($cotizacion['categoria']); ?></p>
                                    <p class="mb-1"><strong>Peso:</strong> <?php echo $cotizacion['peso']; ?> kg</p>
                                    <p class="mb-1"><strong>Estado:</strong> 
                                        <span class="badge bg-warning">Pendiente</span>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-end">
                                        <p class="mb-1"><small>Fecha solicitud:</small><br>
                                        <strong><?php echo date('d/m/Y', strtotime($cotizacion['fecha'])); ?></strong></p>
                                        <p class="mb-0"><small>Costo total estimado:</small><br>
                                        <strong class="text-success">$<?php echo number_format($cotizacion['costo_total'], 2); ?></strong></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <small class="text-muted">
                                        <strong>Desglose de costos:</strong><br>
                                        • Flete: $<?php echo number_format($cotizacion['costo_flete'], 2); ?> | 
                                        • Aduana: $<?php echo number_format($cotizacion['costo_aduana'], 2); ?> | 
                                        • Seguro: $<?php echo number_format($cotizacion['costo_seguro'], 2); ?> | 
                                        • Almacén: $<?php echo number_format($cotizacion['costo_almacen'], 2); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                        <h5>No tienes cotizaciones pendientes</h5>
                        <p class="text-muted">Todas tus cotizaciones han sido procesadas</p>
                        <button class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#modalCotizacion">
                            Crear Nueva Cotización
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#modalCotizacion">
                    Nueva Cotización
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Carrito -->
<div class="modal fade" id="modalCarrito" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🛒 Tu Carrito de Compras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($carrito_productos)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carrito_productos as $item): 
                                    $subtotal = $item['precio'] * $item['cantidad'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($item['imagen'])): ?>
                                            <img src="../../assets/img/productos/<?php echo $item['imagen']; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['nombre']); ?>" 
                                                 style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;"
                                                 onerror="this.src='https://via.placeholder.com/50x50/2c7be5/ffffff?text=IMG'">
                                            <?php else: ?>
                                            <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px; margin-right: 10px;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($item['nombre']); ?></span>
                                        </div>
                                    </td>
                                    <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                    <td><?php echo $item['cantidad']; ?></td>
                                    <td>$<?php echo number_format($subtotal, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card bg-light mt-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Total del Carrito:</h5>
                                <h4 class="mb-0 text-success">$<?php echo number_format($total_carrito, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5>Tu carrito está vacío</h5>
                        <p class="text-muted">Agrega algunos productos para continuar</p>
                        <a href="tienda.php" class="btn btn-primary">Ir a la Tienda</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Seguir Comprando</button>
                <?php if (!empty($carrito_productos)): ?>
                <a href="carrito.php" class="btn btn-primary">Proceder al Pago</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales para el modal
let productoActual = null;
let precioUnitario = 0;
let plataformaActual = 'local';

// Variables globales para productos externos
let productoExternoActual = null;
let infoProductoExterno = null;

// Funciones de navegación
function irAPedidos() {
    window.location.href = 'pedidos.php';
}

function mostrarEnviosCamino() {
    const modal = new bootstrap.Modal(document.getElementById('modalEnviosCamino'));
    modal.show();
}

function mostrarCotizaciones() {
    const modal = new bootstrap.Modal(document.getElementById('modalCotizaciones'));
    modal.show();
}

function mostrarCarrito() {
    const modal = new bootstrap.Modal(document.getElementById('modalCarrito'));
    modal.show();
}

function mostrarSeguimiento(idPedido) {
    alert(`Seguimiento del pedido #VM${idPedido}\n\nEsta funcionalidad estará disponible pronto.`);
}

// FUNCIÓN CORREGIDA - Ahora maneja correctamente todos los parámetros
function mostrarModalCarrito(id, nombre, precio, imagen, stock, plataforma, enlace = null) {
    console.log('Mostrando modal para producto:', {id, nombre, precio, plataforma});
    
    productoActual = id;
    precioUnitario = parseFloat(precio);
    plataformaActual = plataforma;
    
    // Llenar datos del modal
    document.getElementById('modalImagenProducto').src = imagen;
    document.getElementById('modalNombreProducto').textContent = nombre;
    document.getElementById('modalPrecioProducto').textContent = `$${precioUnitario.toFixed(2)}`;
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
    
    // Si hay enlace externo, mostrar botón
    if (enlace) {
        badgeElement.innerHTML = `${plataformaText} <a href="${enlace}" target="_blank" class="text-white ms-1"><i class="fas fa-external-link-alt"></i></a>`;
    }
    
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

function actualizarContadorCarrito() {
    fetch('../../procesos/obtener_carrito.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.querySelector('.carrito-badge');
        const contador = document.getElementById('contador-carrito');
        if (badge) badge.textContent = data.total || '0';
        if (contador) contador.textContent = data.total || '0';
    });
}

// Función para obtener información del producto desde la URL
function obtenerInfoProducto() {
    const url = document.getElementById('urlProductoExterno').value.trim();
    
    if (!url) {
        alert('Por favor ingresa una URL válida');
        return;
    }
    
    // Mostrar loading
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Obteniendo información...';
    btn.disabled = true;
    
    fetch('../../procesos/obtener_info_producto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ url: url })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            infoProductoExterno = data.producto;
            
            // Llenar los campos con la información obtenida
            document.getElementById('precioProductoExterno').value = infoProductoExterno.precio;
            document.getElementById('pesoProductoExterno').value = infoProductoExterno.peso;
            
            // Calcular cotización automáticamente
            calcularCotizacionExterno();
            
            // Habilitar botón de agregar
            document.getElementById('btnAgregarExterno').disabled = false;
            
            alert('✅ Información del producto obtenida correctamente');
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al obtener información del producto');
    })
    .finally(() => {
        // Restaurar botón
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Función para calcular cotización de producto externo
function calcularCotizacionExterno() {
    const precio = parseFloat(document.getElementById('precioProductoExterno').value) || 0;
    const peso = parseFloat(document.getElementById('pesoProductoExterno').value) || 0;
    const categoria = document.getElementById('categoriaProductoExterno').value;
    
    if (precio <= 0 || peso <= 0) {
        alert('Complete precio y peso correctamente');
        return;
    }
    
    const cotizacion = calcularCostoImportacionJS(precio, peso, categoria);
    
    document.getElementById('resultadoCotizacionExterno').innerHTML = `
        <div class="row">
            <div class="col-6"><small>Producto:</small><br><strong>$${precio.toFixed(2)}</strong></div>
            <div class="col-6"><small>Flete:</small><br><strong>$${cotizacion.flete.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-6"><small>Seguro (2%):</small><br><strong>$${cotizacion.seguro.toFixed(2)}</strong></div>
            <div class="col-6"><small>Aduana:</small><br><strong>$${cotizacion.aduana.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-6"><small>Almacenaje:</small><br><strong>$${cotizacion.almacen.toFixed(2)}</strong></div>
            <div class="col-6"><small>TOTAL:</small><br><strong class="text-success">$${cotizacion.total.toFixed(2)}</strong></div>
        </div>
    `;
    
    // Guardar información para usar al agregar
    productoExternoActual = {
        precio: precio,
        peso: peso,
        categoria: categoria,
        cotizacion: cotizacion
    };
    
    document.getElementById('btnAgregarExterno').disabled = false;
}

// Función para agregar producto externo al carrito
function agregarProductoExterno() {
    const url = document.getElementById('urlProductoExterno').value.trim();
    const precio = parseFloat(document.getElementById('precioProductoExterno').value) || 0;
    const peso = parseFloat(document.getElementById('pesoProductoExterno').value) || 0;
    const categoria = document.getElementById('categoriaProductoExterno').value;
    
    if (!url || precio <= 0) {
        alert('Complete todos los campos correctamente');
        return;
    }
    
    const formData = new FormData();
    formData.append('url', url);
    formData.append('precio', precio);
    formData.append('peso', peso);
    formData.append('categoria', categoria);
    formData.append('plataforma', url.includes('amazon') ? 'amazon' : 'ebay');
    formData.append('nombre', infoProductoExterno?.nombre || 'Producto Externo');
    
    fetch('../../procesos/agregar_carrito_externo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAgregarExterno'));
            modal.hide();
            
            // Mostrar mensaje de éxito
            alert(`✅ ${data.message}`);
            
            // Actualizar contador del carrito
            actualizarContadorCarrito();
            
            // Recargar la página para mostrar el nuevo producto en el carrusel
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            alert(`❌ ${data.message}`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error de conexión');
    });
}

// Función existente para cotización
function calcularCotizacion() {
    const precio = parseFloat(document.getElementById('precioProducto').value) || 0;
    const peso = parseFloat(document.getElementById('pesoProducto').value) || 0;
    
    if (precio <= 0 || peso <= 0) {
        alert('Complete precio y peso correctamente');
        return;
    }
    
    const impuestos = {'electronico':0.30, 'ropa':0.20, 'hogar':0.15, 'deportes':0.25, 'otros':0.18};
    const almacen = {'pequeno':10, 'mediano':25, 'grande':50, 'extra':80};
    
    const categoria = document.getElementById('categoriaProducto').value;
    const tamano = document.getElementById('tamanoProducto').value;
    
    const flete = Math.max(15, peso * 3);
    const seguro = precio * 0.02;
    const aduana = precio * (impuestos[categoria] || 0.18);
    const costoAlmacen = almacen[tamano] || 25;
    const total = precio + flete + seguro + aduana + costoAlmacen;
    
    document.getElementById('resultadoCotizacion').innerHTML = `
        <div class="row">
            <div class="col-6"><small>Producto:</small><br><strong>$${precio.toFixed(2)}</strong></div>
            <div class="col-6"><small>Flete:</small><br><strong>$${flete.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-6"><small>Seguro (2%):</small><br><strong>$${seguro.toFixed(2)}</strong></div>
            <div class="col-6"><small>Aduana:</small><br><strong>$${aduana.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-6"><small>Almacenaje:</small><br><strong>$${costoAlmacen.toFixed(2)}</strong></div>
            <div class="col-6"><small>TOTAL:</small><br><strong class="text-success">$${total.toFixed(2)}</strong></div>
        </div>
    `;
}

// INICIALIZACIÓN CORREGIDA DE LOS CARRUSELES
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar todos los carruseles manualmente
    const carousels = document.querySelectorAll('.carousel');
    carousels.forEach(carousel => {
        // Inicializar cada carrusel
        new bootstrap.Carousel(carousel, {
            interval: 5000, // Cambiar cada 5 segundos
            wrap: true,
            touch: true
        });
    });
    
    console.log('Carruseles inicializados:', carousels.length);
    
    actualizarContadorCarrito();
    
    // Actualizar resumen cuando cambia la cantidad manualmente
    const cantidadInput = document.getElementById('cantidadProducto');
    if (cantidadInput) {
        cantidadInput.addEventListener('input', calcularResumen);
    }
    
    // Agregar efecto hover a las tarjetas clickeables
    document.querySelectorAll('.clickable-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'all 0.2s ease';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<style>
/* ESTILOS MEJORADOS PARA BOTONES GRANDES DE CARRUSEL */
.clickable-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
}

.product-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.categoria-carousel-container {
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

/* BOTONES GRANDES Y VISIBLES - MEJORADOS */
.carousel-btn-big {
    width: 60px !important;
    height: 60px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    opacity: 0.9 !important;
    background: rgba(0,0,0,0.7) !important;
    border-radius: 50% !important;
    transition: all 0.3s ease !important;
    border: 3px solid white !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
}

.carousel-btn-big:hover {
    opacity: 1 !important;
    background: rgba(0,0,0,0.9) !important;
    transform: translateY(-50%) scale(1.1) !important;
    box-shadow: 0 6px 20px rgba(0,0,0,0.4) !important;
}

.carousel-control-prev.carousel-btn-big {
    left: 15px !important;
}

.carousel-control-next.carousel-btn-big {
    right: 15px !important;
}

.carousel-control-prev-icon,
.carousel-control-next-icon {
    width: 30px !important;
    height: 30px !important;
    background-size: 30px 30px !important;
    filter: brightness(0) invert(1) !important;
}

/* Efecto de brillo en hover */
.carousel-btn-big:hover .carousel-control-prev-icon,
.carousel-btn-big:hover .carousel-control-next-icon {
    filter: brightness(0) invert(1) drop-shadow(0 0 3px rgba(255,255,255,0.5)) !important;
}

.carousel-inner {
    border-radius: 8px;
    overflow: hidden;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%) !important;
}

.bg-gradient-secondary {
    background: linear-gradient(135deg, #858796 0%, #60616f 100%) !important;
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%) !important;
}

/* Mejoras responsive para botones grandes */
@media (max-width: 768px) {
    .carousel-btn-big {
        width: 50px !important;
        height: 50px !important;
    }
    
    .carousel-control-prev.carousel-btn-big {
        left: 10px !important;
    }
    
    .carousel-control-next.carousel-btn-big {
        right: 10px !important;
    }
    
    .carousel-control-prev-icon,
    .carousel-control-next-icon {
        width: 25px !important;
        height: 25px !important;
        background-size: 25px 25px !important;
    }
    
    .product-card {
        margin-bottom: 1rem;
    }
}

/* Para pantallas muy pequeñas */
@media (max-width: 576px) {
    .carousel-btn-big {
        width: 45px !important;
        height: 45px !important;
    }
    
    .carousel-control-prev-icon,
    .carousel-control-next-icon {
        width: 20px !important;
        height: 20px !important;
        background-size: 20px 20px !important;
    }
}

/* Animaciones suaves */
.card {
    transition: all 0.3s ease;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.02);
}

/* Mejora en los badges */
.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
}

/* Espaciado mejorado */
.g-3 {
    --bs-gutter-x: 1rem;
    --bs-gutter-y: 1rem;
}

/* Asegurar que los botones de los indicadores sean clickeables */
.btn-sm[data-bs-target] {
    cursor: pointer !important;
}

/* Posicionamiento relativo para el contenedor del carrusel */
.card-body.position-relative {
    position: relative;
}
</style>

<?php include '../../includes/footer.php'; ?>