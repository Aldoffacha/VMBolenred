<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

// Función para obtener el tipo de cambio actual
function obtenerTipoCambio() {
    $tipo_cambio = 10.47; // Valor por defecto
    
    try {
        // Intentar obtener el tipo de cambio de dolarboliviahoy.com
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $html = @file_get_contents('https://dolarboliviahoy.com/', false, $context);
        
        if ($html !== false) {
            // Buscar el tipo de cambio en el HTML (patrón común)
            if (preg_match('/Bs\.?\s*([0-9]+[.,][0-9]+)/', $html, $matches)) {
                $tipo_cambio = floatval(str_replace(',', '.', $matches[1]));
            }
        }
    } catch (Exception $e) {
        error_log("Error obteniendo tipo de cambio: " . $e->getMessage());
    }
    
    return $tipo_cambio;
}

// Función para calcular costo de almacenamiento basado en dimensiones
function calcularCostoAlmacen($largo, $ancho, $alto, $peso) {
    // Tarifas en Bolivianos según las dimensiones
    $tarifas = [
        // [largo_min, largo_max, ancho_min, ancho_max, alto_min, alto_max, peso_max, costo_bs]
        [20, 20, 15, 15, 1, 1, 100, 135],      // 20x15x1
        [20, 20, 15, 15, 15, 15, 100, 180],    // 20x15x15
        [25, 25, 15, 15, 15, 15, 100, 225],    // 25x15x15
        [30, 30, 20, 20, 20, 20, 100, 270],    // 30x20x20
        [35, 35, 20, 20, 20, 20, 100, 360],    // 35x20x20
        [50, 50, 40, 40, 10, 10, 10, 450],     // 50x40x10 (hasta 10kg)
        [50, 50, 40, 40, 10, 10, 100, 1350],   // 50x40x10 (más de 10kg)
        [60, 60, 60, 60, 60, 60, 20, 1800],    // 60x60x60 (hasta 20kg)
        [100, 100, 100, 100, 60, 60, 25, 2250], // 100x100x60 (hasta 25kg)
        [150, 150, 100, 100, 100, 100, 30, 3150] // 150x100x100 (hasta 30kg)
    ];
    
    // Buscar la tarifa que coincida con las dimensiones
    foreach ($tarifas as $tarifa) {
        list($l_min, $l_max, $a_min, $a_max, $h_min, $h_max, $p_max, $costo) = $tarifa;
        
        if ($largo >= $l_min && $largo <= $l_max &&
            $ancho >= $a_min && $ancho <= $a_max &&
            $alto >= $h_min && $alto <= $h_max &&
            $peso <= $p_max) {
            return $costo;
        }
    }
    
    // Si no encuentra tarifa específica, calcular por volumen aproximado
    $volumen = $largo * $ancho * $alto;
    if ($volumen <= 300) return 135;      // Hasta 300 cm³
    if ($volumen <= 4500) return 180;     // Hasta 4500 cm³
    if ($volumen <= 5625) return 225;     // Hasta 5625 cm³
    if ($volumen <= 12000) return 270;    // Hasta 12000 cm³
    if ($volumen <= 14000) return 360;    // Hasta 14000 cm³
    if ($volumen <= 20000) return 450;    // Hasta 20000 cm³
    if ($volumen <= 20000 && $peso > 10) return 1350;
    if ($volumen <= 216000) return 1800;  // Hasta 216000 cm³
    if ($volumen <= 600000) return 2250;  // Hasta 600000 cm³
    return 3150; // Mayor a 600000 cm³
}

// Función principal de cálculo de importación
function calcularCostoImportacion($precio, $peso, $categoria, $largo = 20, $ancho = 15, $alto = 1) {
    $tipo_cambio = obtenerTipoCambio();
    
    $impuestos = [
        'electronico' => 0.30, 'ropa' => 0.20, 'hogar' => 0.15, 
        'deportes' => 0.25, 'otros' => 0.18
    ];
    
    // Calcular costos en dólares
    $impuesto = $impuestos[$categoria] ?? 0.18;
    $flete_maritimo = max(15, $peso * 3); // Flete base
    $seguro = $precio * 0.02;
    $aduana = $precio * $impuesto;
    
    // Calcular almacenamiento en Bs y convertir a dólares
    $costo_almacen_bs = calcularCostoAlmacen($largo, $ancho, $alto, $peso);
    $costo_almacen = $costo_almacen_bs / $tipo_cambio;
    
    $costo_total = $precio + $flete_maritimo + $seguro + $aduana + $costo_almacen;
    
    return [
        'total' => $costo_total,
        'tipo_cambio' => $tipo_cambio,
        'desglose' => [
            'producto' => $precio, 
            'flete' => $flete_maritimo, 
            'seguro' => $seguro, 
            'aduana' => $aduana, 
            'almacen' => $costo_almacen,
            'almacen_bs' => $costo_almacen_bs
        ],
        'dimensiones' => [
            'largo' => $largo,
            'ancho' => $ancho, 
            'alto' => $alto,
            'peso' => $peso
        ]
    ];
}
// Procesar búsqueda si viene del header
if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {
    $termino_busqueda = trim($_GET['buscar']);
    
    // Buscar productos
    $stmt = $db->prepare("
        SELECT * FROM productos 
        WHERE estado = 1 AND (
            nombre ILIKE ? OR 
            descripcion ILIKE ? OR 
            categoria ILIKE ? OR
            REPLACE(LOWER(nombre), ' ', '') LIKE REPLACE(LOWER(?), ' ', '')
        )
        ORDER BY 
            CASE 
                WHEN nombre ILIKE ? THEN 1
                WHEN descripcion ILIKE ? THEN 2
                WHEN categoria ILIKE ? THEN 3
                ELSE 4
            END,
            nombre ASC
        LIMIT 20
    ");
    
    $termino_like = "%$termino_busqueda%";
    $termino_sin_espacios = "%" . str_replace(' ', '', $termino_busqueda) . "%";
    
    $stmt->execute([
        $termino_like, 
        $termino_like, 
        $termino_like,
        $termino_sin_espacios,
        $termino_like,
        $termino_like,
        $termino_like
    ]);
    
    $resultados_busqueda = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $termino_busqueda = '';
    $resultados_busqueda = [];
}
Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];

// Obtener tipo de cambio actual para mostrar
$tipo_cambio_actual = obtenerTipoCambio();

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
// Obtener productos externos REALES de la base de datos
$stmt = $db->prepare("
    SELECT * FROM productos_exterior
    WHERE estado = 1 AND destacado = 1
    ORDER BY fecha_agregado DESC
    LIMIT 8
");
$stmt->execute();
$productos_externos_reales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos de Amazon y eBay (simulados como respaldo)
$productos_externos_simulados = [
    [
        'id_producto_externo' => 'amz001',
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
        'id_producto_externo' => 'amz002',
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
        'id_producto_externo' => 'eby001',
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
        'id_producto_externo' => 'eby002',
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

// Combinar productos reales con simulados (si no hay reales, usar simulados)
if (count($productos_externos_reales) > 0) {
    $productos_externos = $productos_externos_reales;
} else {
    $productos_externos = $productos_externos_simulados;
}

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
            <!-- Banner de Tipo de Cambio -->
            

            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">¡Bienvenido, <?php echo $_SESSION['nombre']; ?>! 👋</h1>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCotizacion">
                    💰 Cotización Rápida
                </button>
            </div>
            <!-- SECCIÓN DE RESULTADOS DE BÚSQUEDA -->
<?php if (!empty($termino_busqueda)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center bg-primary text-white">
                <h6 class="m-0 font-weight-bold">
                    🔍 Resultados de Búsqueda
                </h6>
                <span class="badge bg-light text-primary">
                    <?php echo count($resultados_busqueda); ?> producto(s) encontrado(s)
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Mostrando resultados para: <strong>"<?php echo htmlspecialchars($termino_busqueda); ?>"</strong>
                    <a href="?" class="btn btn-sm btn-outline-secondary ms-2">
                        <i class="fas fa-times me-1"></i>Limpiar búsqueda
                    </a>
                </p>
                
                <?php if (count($resultados_busqueda) > 0): ?>
                <div id="carouselBusqueda" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php 
                        // Dividir resultados en grupos de 4
                        $resultados_chunks = array_chunk($resultados_busqueda, 4);
                        foreach ($resultados_chunks as $chunk_index => $productos_chunk): 
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
                                                <span class="badge bg-primary">
                                                    <?php 
                                                    $categorias_icons = [
                                                        'electronico' => '📱',
                                                        'ropa' => '👕',
                                                        'hogar' => '🏠',
                                                        'deportes' => '⚽',
                                                        'otros' => '📦'
                                                    ];
                                                    echo $categorias_icons[$producto['categoria']] ?? '📦';
                                                    ?>
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
                                                <button class="btn btn-primary btn-sm w-100" 
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
                    
                    <?php if (count($resultados_chunks) > 1): ?>
                    <button class="carousel-control-prev carousel-btn-big" type="button" data-bs-target="#carouselBusqueda" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next carousel-btn-big" type="button" data-bs-target="#carouselBusqueda" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- Indicadores del carrusel -->
                <?php if (count($resultados_chunks) > 1): ?>
                <div class="text-center mt-3">
                    <?php for ($i = 0; $i < count($resultados_chunks); $i++): ?>
                    <button type="button" data-bs-target="#carouselBusqueda" data-bs-slide-to="<?php echo $i; ?>" 
                            class="btn btn-sm <?php echo $i === 0 ? 'btn-primary' : 'btn-outline-primary'; ?> mx-1"
                            style="width: 10px; height: 10px; padding: 0; border-radius: 50%;">
                    </button>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>No se encontraron productos</h5>
                    <p class="text-muted">No encontramos productos que coincidan con "<strong><?php echo htmlspecialchars($termino_busqueda); ?></strong>"</p>
                    <div class="mt-3">
                        <small class="text-muted">Sugerencias:</small>
                        <div class="mt-2">
                            <a href="tienda.php" class="btn btn-outline-primary btn-sm me-2">Ver todos los productos</a>
                            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregarExterno">
                                Buscar en Amazon/eBay
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
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
    // Determinar si es producto real o simulado
    $es_real = isset($producto['id_producto_externo']);
    $id_producto = $es_real ? $producto['id_producto_externo'] : ($producto['id_producto'] ?? $producto['id_producto_externo'] ?? 'temp');
    
    $cotizacion = calcularCostoImportacion($producto['precio'], $producto['peso'] ?? 0.5, $producto['categoria']);
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
                <?php if ($es_real): ?>
                <span class="badge bg-success ms-1">Real</span>
                <?php endif; ?>
            </div>
            <span class="position-absolute top-0 end-0 badge bg-dark m-2">
                $<?php echo number_format($producto['precio'], 2); ?>
            </span>
        </div>
        <div class="card-body d-flex flex-column">
            <h6 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
            <p class="card-text flex-grow-1 text-muted small">
                <?php echo substr($producto['descripcion'] ?? 'Producto importado de ' . $badge_text, 0, 60); ?>...
            </p>
            <div class="mt-auto">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted">Total con importación:</small>
                    <strong class="text-success">$<?php echo number_format($cotizacion['total'], 2); ?></strong>
                </div>
                <div class="d-grid gap-2">
                    <!-- BOTÓN ACTUALIZADO para productos externos reales -->
                    <button class="btn btn-<?php echo $producto['plataforma'] === 'amazon' ? 'warning' : 'info'; ?> btn-sm" 
                            onclick="mostrarModalCarritoExterno(<?php echo $id_producto; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>', <?php echo $producto['precio']; ?>, '<?php echo $producto['imagen']; ?>', <?php echo $producto['stock'] ?? 1; ?>, '<?php echo $producto['plataforma']; ?>', '<?php echo $producto['enlace']; ?>')">
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
                                <label class="form-label">Tamaño de Caja</label>
                                <select class="form-select" id="tamanoCaja" required>
                                    <option value="20x15x1">Pequeño (20x15x1 cm) - Bs. 135</option>
                                    <option value="20x15x15">Mediano (20x15x15 cm) - Bs. 180</option>
                                    <option value="25x15x15">Grande (25x15x15 cm) - Bs. 225</option>
                                    <option value="30x20x20">Extra Grande (30x20x20 cm) - Bs. 270</option>
                                    <option value="35x20x20">Envío Pequeño (35x20x20 cm) - Bs. 360</option>
                                    <option value="50x40x10">Laptop (50x40x10 cm) - Bs. 450</option>
                                    <option value="60x60x60">Grande Pesado (60x60x60 cm) - Bs. 1800</option>
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
                <a href="detalles_tarifas.php" class="btn btn-outline-info" target="_blank">
                    <i class="fas fa-info-circle me-1"></i>Ver Detalles de Tarifas
                </a>
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
                    <br><small class="mt-1"><strong>Tipo de cambio actual:</strong> Bs. <?php echo number_format($tipo_cambio_actual, 2); ?> por $1 USD</small>
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

                    <!-- Dimensiones de la caja -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><strong>Largo (cm)</strong></label>
                                <input type="number" step="0.1" class="form-control" id="largoProductoExterno" value="20" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><strong>Ancho (cm)</strong></label>
                                <input type="number" step="0.1" class="form-control" id="anchoProductoExterno" value="15" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><strong>Alto (cm)</strong></label>
                                <input type="number" step="0.1" class="form-control" id="altoProductoExterno" value="1" required>
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
                                <div class="mt-2 text-center">
                                    <small class="text-muted" id="modalTipoCambio"></small>
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

// Obtener tipo de cambio actual
let tipoCambioActual = <?php echo $tipo_cambio_actual; ?>;

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
    showInfo(`Seguimiento del pedido #VM${idPedido} - Esta funcionalidad estará disponible pronto.`);
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

// Función específica para productos externos
function mostrarModalCarritoExterno(id, nombre, precio, imagen, stock, plataforma, enlace = null) {
    console.log('Mostrando modal para producto EXTERNO:', {id, nombre, precio, plataforma});
    
    // Crear objeto producto con toda la información necesaria
    productoActual = {
        id: id,
        nombre: nombre,
        precio: precio,
        imagen: imagen,
        stock: stock,
        plataforma: plataforma,
        enlace: enlace,
        // Agregar campos adicionales para productos simulados
        categoria: 'electronico', // Por defecto para productos del carrusel
        peso: 0.5 // Por defecto
    };
    
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
    const costoImportacion = calcularCostoImportacionJS(precioUnitario, 0.5, 'electronico', 20, 15, 1, tipoCambioActual);
    const totalImportacion = costoImportacion.total * cantidad;
    const impuestos = totalImportacion - subtotal;
    
    document.getElementById('modalSubtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('modalImpuestos').textContent = `$${impuestos.toFixed(2)}`;
    document.getElementById('modalTotal').textContent = `$${totalImportacion.toFixed(2)}`;
    document.getElementById('modalTipoCambio').textContent = `Tipo de cambio: Bs. ${tipoCambioActual.toFixed(2)} por $1 USD`;
}

// Función para calcular costo de almacenamiento en JavaScript
function calcularCostoAlmacenJS(largo, ancho, alto, peso) {
    // Tarifas en Bolivianos según las dimensiones
    const tarifas = [
        [20, 20, 15, 15, 1, 1, 100, 135],      // 20x15x1
        [20, 20, 15, 15, 15, 15, 100, 180],    // 20x15x15
        [25, 25, 15, 15, 15, 15, 100, 225],    // 25x15x15
        [30, 30, 20, 20, 20, 20, 100, 270],    // 30x20x20
        [35, 35, 20, 20, 20, 20, 100, 360],    // 35x20x20
        [50, 50, 40, 40, 10, 10, 10, 450],     // 50x40x10 (hasta 10kg)
        [50, 50, 40, 40, 10, 10, 100, 1350],   // 50x40x10 (más de 10kg)
        [60, 60, 60, 60, 60, 60, 20, 1800],    // 60x60x60 (hasta 20kg)
        [100, 100, 100, 100, 60, 60, 25, 2250], // 100x100x60 (hasta 25kg)
        [150, 150, 100, 100, 100, 100, 30, 3150] // 150x100x100 (hasta 30kg)
    ];
    
    // Buscar la tarifa que coincida con las dimensiones
    for (const tarifa of tarifas) {
        const [l_min, l_max, a_min, a_max, h_min, h_max, p_max, costo] = tarifa;
        
        if (largo >= l_min && largo <= l_max &&
            ancho >= a_min && ancho <= a_max &&
            alto >= h_min && alto <= h_max &&
            peso <= p_max) {
            return costo;
        }
    }
    
    // Si no encuentra tarifa específica, calcular por volumen aproximado
    const volumen = largo * ancho * alto;
    if (volumen <= 300) return 135;
    if (volumen <= 4500) return 180;
    if (volumen <= 5625) return 225;
    if (volumen <= 12000) return 270;
    if (volumen <= 14000) return 360;
    if (volumen <= 20000) return 450;
    if (volumen <= 20000 && peso > 10) return 1350;
    if (volumen <= 216000) return 1800;
    if (volumen <= 600000) return 2250;
    return 3150;
}

// Función principal de cálculo de importación en JavaScript
function calcularCostoImportacionJS(precio, peso, categoria, largo, ancho, alto, tipoCambio) {
    const impuestos = {
        'electronico': 0.30, 
        'ropa': 0.20, 
        'hogar': 0.15, 
        'deportes': 0.25, 
        'otros': 0.18
    };
    
    // Calcular costos en dólares
    const impuesto = impuestos[categoria] || 0.18;
    const flete_maritimo = Math.max(15, peso * 3); // Flete base
    const seguro = precio * 0.02;
    const aduana = precio * impuesto;
    
    // Calcular almacenamiento en Bs y convertir a dólares
    const costo_almacen_bs = calcularCostoAlmacenJS(largo, ancho, alto, peso);
    const costo_almacen = costo_almacen_bs / tipoCambio;
    
    const total = precio + flete_maritimo + seguro + aduana + costo_almacen;
    
    return {
        total: total,
        tipo_cambio: tipoCambio,
        desglose: {
            producto: precio, 
            flete: flete_maritimo, 
            seguro: seguro, 
            aduana: aduana, 
            almacen: costo_almacen,
            almacen_bs: costo_almacen_bs
        },
        dimensiones: {
            largo: largo,
            ancho: ancho, 
            alto: alto,
            peso: peso
        }
    };
}

function confirmarAgregarCarrito() {
    const cantidad = parseInt(document.getElementById('cantidadProducto').value);
    
    if (cantidad < 1) {
        showWarning('Por favor selecciona una cantidad válida');
        return;
    }
    
    const formData = new FormData();
    let endpoint;
    
    console.log('Agregando producto:', {
        productoActual,
        plataformaActual,
        cantidad
    });
    
    // Determinar qué endpoint usar y qué datos enviar
    if (plataformaActual === 'local') {
        // Producto local - usar el endpoint existente
        formData.append('id_producto', productoActual);
        formData.append('cantidad', cantidad);
        endpoint = '../../procesos/agregar_carrito.php';
    } else {
        // Producto externo - usar el nuevo endpoint
        if (typeof productoActual === 'object' && productoActual.id) {
            // Producto externo del carrusel (puede ser real o simulado)
            formData.append('id_producto_externo', productoActual.id);
            formData.append('plataforma', productoActual.plataforma || plataformaActual);
            formData.append('nombre', productoActual.nombre);
            formData.append('precio', productoActual.precio);
            formData.append('url', productoActual.enlace || '');
            
            // Si es un producto simulado, agregar más datos
            if (productoActual.id.toString().startsWith('amz') || productoActual.id.toString().startsWith('eby')) {
                formData.append('categoria', productoActual.categoria || 'electronico');
                formData.append('peso', productoActual.peso || 0.5);
                formData.append('largo', 20);
                formData.append('ancho', 15);
                formData.append('alto', 1);
            }
        } else {
            // Producto externo con ID simple
            formData.append('id_producto_externo', productoActual);
            formData.append('plataforma', plataformaActual);
        }
        endpoint = '../../procesos/agregar_carrito_externo.php';
    }
    
    console.log('Enviando a:', endpoint);
    console.log('Datos:', Object.fromEntries(formData));
    
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Respuesta HTTP:', response.status);
        return response.json().catch(() => {
            throw new Error('El servidor devolvió una respuesta no JSON');
        });
    })
    .then(data => {
        console.log('Respuesta del servidor:', data);
        
        if (data.success) {
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAgregarCarrito'));
            if (modal) modal.hide();
            
            // Mostrar mensaje de éxito
            showSuccess(data.message);
            
            // Actualizar contador del carrito
            actualizarContadorCarrito();
            
            // Recargar página si es producto externo para mostrar en carrusel
            if (plataformaActual !== 'local') {
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error completo:', error);
        showError('Error de conexión: ' + error.message);
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
        showWarning('Por favor ingresa una URL válida');
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
            
            showSuccess('Información del producto obtenida correctamente');
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Error al obtener información del producto');
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
    const largo = parseFloat(document.getElementById('largoProductoExterno').value) || 0;
    const ancho = parseFloat(document.getElementById('anchoProductoExterno').value) || 0;
    const alto = parseFloat(document.getElementById('altoProductoExterno').value) || 0;
    
    if (precio <= 0 || peso <= 0) {
        showWarning('Complete precio y peso correctamente');
        return;
    }

    const cotizacion = calcularCostoImportacionJS(precio, peso, categoria, largo, ancho, alto, tipoCambioActual);
    
    document.getElementById('resultadoCotizacionExterno').innerHTML = `
        <div class="row">
            <div class="col-6"><small>Producto:</small><br><strong>$${precio.toFixed(2)}</strong></div>
            <div class="col-6"><small>Flete:</small><br><strong>$${cotizacion.desglose.flete.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-6"><small>Seguro (2%):</small><br><strong>$${cotizacion.desglose.seguro.toFixed(2)}</strong></div>
            <div class="col-6"><small>Aduana (${(cotizacion.desglose.aduana/precio*100).toFixed(0)}%):</small><br><strong>$${cotizacion.desglose.aduana.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-6">
                <small>Almacenaje (Bs. ${cotizacion.desglose.almacen_bs.toFixed(2)}):</small><br>
                <strong>$${cotizacion.desglose.almacen.toFixed(2)}</strong>
            </div>
            <div class="col-6"><small>TOTAL:</small><br><strong class="text-success">$${cotizacion.total.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                <small class="text-muted">
                    <strong>Dimensiones:</strong> ${largo}x${ancho}x${alto} cm | 
                    <strong>Peso:</strong> ${peso} kg | 
                    <strong>Tipo cambio:</strong> Bs. ${tipoCambioActual.toFixed(2)}
                </small>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                <a href="detalles_tarifas.php" class="btn btn-sm btn-outline-info w-100" target="_blank">
                    <i class="fas fa-info-circle me-1"></i>Ver detalles completos de tarifas
                </a>
            </div>
        </div>
    `;
    
    // Guardar información para usar al agregar
    productoExternoActual = {
        precio: precio,
        peso: peso,
        categoria: categoria,
        largo: largo,
        ancho: ancho,
        alto: alto,
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
    const largo = parseFloat(document.getElementById('largoProductoExterno').value) || 0;
    const ancho = parseFloat(document.getElementById('anchoProductoExterno').value) || 0;
    const alto = parseFloat(document.getElementById('altoProductoExterno').value) || 0;
    
    if (!url || precio <= 0) {
        showWarning('Complete todos los campos correctamente');
        return;
    }
    
    const formData = new FormData();
    formData.append('url', url);
    formData.append('precio', precio);
    formData.append('peso', peso);
    formData.append('categoria', categoria);
    formData.append('largo', largo);
    formData.append('ancho', ancho);
    formData.append('alto', alto);
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
            showSuccess(data.message);
            
            // Actualizar contador del carrito
            actualizarContadorCarrito();
            
            // Recargar la página para mostrar el nuevo producto en el carrusel
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Error de conexión');
    });
}

// Función existente para cotización
function calcularCotizacion() {
    const precio = parseFloat(document.getElementById('precioProducto').value) || 0;
    const peso = parseFloat(document.getElementById('pesoProducto').value) || 0;
    const categoria = document.getElementById('categoriaProducto').value;
    const tamanoCaja = document.getElementById('tamanoCaja').value;
    
    // Convertir tamaño de caja a dimensiones
    let largo = 20, ancho = 15, alto = 1;
    switch(tamanoCaja) {
        case '20x15x1': largo = 20; ancho = 15; alto = 1; break;
        case '20x15x15': largo = 20; ancho = 15; alto = 15; break;
        case '25x15x15': largo = 25; ancho = 15; alto = 15; break;
        case '30x20x20': largo = 30; ancho = 20; alto = 20; break;
        case '35x20x20': largo = 35; ancho = 20; alto = 20; break;
        case '50x40x10': largo = 50; ancho = 40; alto = 10; break;
        case '60x60x60': largo = 60; ancho = 60; alto = 60; break;
    }
    
    if (precio <= 0 || peso <= 0) {
        showWarning('Complete precio y peso correctamente');
        return;
    }

    const cotizacion = calcularCostoImportacionJS(precio, peso, categoria, largo, ancho, alto, tipoCambioActual);
    
    document.getElementById('resultadoCotizacion').innerHTML = `
        <div class="row">
            <div class="col-6"><small>Producto:</small><br><strong>$${precio.toFixed(2)}</strong></div>
            <div class="col-6"><small>Flete:</small><br><strong>$${cotizacion.desglose.flete.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-6"><small>Seguro (2%):</small><br><strong>$${cotizacion.desglose.seguro.toFixed(2)}</strong></div>
            <div class="col-6"><small>Aduana (${(cotizacion.desglose.aduana/precio*100).toFixed(0)}%):</small><br><strong>$${cotizacion.desglose.aduana.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-6">
                <small>Almacenaje (Bs. ${cotizacion.desglose.almacen_bs.toFixed(2)}):</small><br>
                <strong>$${cotizacion.desglose.almacen.toFixed(2)}</strong>
            </div>
            <div class="col-6"><small>TOTAL:</small><br><strong class="text-success">$${cotizacion.total.toFixed(2)}</strong></div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                <small class="text-muted">
                    <strong>Dimensiones:</strong> ${largo}x${ancho}x${alto} cm | 
                    <strong>Tipo cambio:</strong> Bs. ${tipoCambioActual.toFixed(2)}
                </small>
            </div>
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
.carousel-btn-big {
    width: 50px;
    height: 50px;
    background-color: rgba(0,0,0,0.5);
    border-radius: 50%;
    top: 50%;
    transform: translateY(-50%);
}

.carousel-btn-big:hover {
    background-color: rgba(0,0,0,0.7);
}

.clickable-card:hover {
    transform: translateY(-2px);
    transition: all 0.2s ease;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.product-card {
    transition: all 0.3s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
}
</style>

<?php include '../../includes/footer.php'; ?>