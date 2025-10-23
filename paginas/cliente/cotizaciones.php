<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
$mensaje = '';

// Procesar nueva cotización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_cotizacion'])) {
    $nombre_producto = htmlspecialchars($_POST['nombre_producto'] ?? '');
    $precio_base = floatval($_POST['precio_base'] ?? 0);
    $peso = floatval($_POST['peso'] ?? 0);
    $categoria = htmlspecialchars($_POST['categoria'] ?? 'otros');
    $tamano = htmlspecialchars($_POST['tamano'] ?? 'mediano');
    
    if ($precio_base > 0 && $peso > 0 && !empty($nombre_producto)) {
        // Calcular costos de importación
        $costos = calcularCostosCotizacion($precio_base, $peso, $categoria, $tamano);
        
        try {
            $stmt = $db->prepare("INSERT INTO cotizaciones (id_cliente, nombre_producto, precio_base, peso, categoria, tamano, costo_flete, costo_aduana, costo_almacen, costo_seguro, costo_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id, $nombre_producto, $precio_base, $peso, $categoria, $tamano,
                $costos['flete'], $costos['aduana'], $costos['almacen'], $costos['seguro'], $costos['total']
            ]);
            
            $mensaje = "✅ Cotización creada correctamente";
        } catch (Exception $e) {
            $mensaje = "❌ Error al crear cotización: " . $e->getMessage();
        }
    } else {
        $mensaje = "❌ Complete todos los campos correctamente";
    }
}

// Procesar eliminación de cotización
if (isset($_GET['eliminar'])) {
    $id_cotizacion = intval($_GET['eliminar']);
    
    // Verificar que la cotización pertenezca al cliente
    $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE id_cotizacion = ? AND id_cliente = ?");
    $stmt->execute([$id_cotizacion, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        $stmt = $db->prepare("DELETE FROM cotizaciones WHERE id_cotizacion = ?");
        $stmt->execute([$id_cotizacion]);
        $mensaje = "✅ Cotización eliminada";
    }
}

// Obtener cotizaciones del cliente
$stmt = $db->prepare("SELECT * FROM cotizaciones WHERE id_cliente = ? ORDER BY fecha DESC");
$stmt->execute([$user_id]);
$cotizaciones = $stmt->fetchAll();

// Función para calcular costos
function calcularCostosCotizacion($precio, $peso, $categoria, $tamano) {
    $impuestos = [
        'electronico' => 0.30, 'ropa' => 0.20, 'hogar' => 0.15, 
        'deportes' => 0.25, 'otros' => 0.18
    ];
    
    $almacen = ['pequeno' => 10, 'mediano' => 25, 'grande' => 50, 'extra' => 80];
    
    $flete = max(15, $peso * 3);
    $seguro = $precio * 0.02;
    $aduana = $precio * ($impuestos[$categoria] ?? 0.18);
    $costo_almacen = $almacen[$tamano] ?? 25;
    $total = $precio + $flete + $seguro + $aduana + $costo_almacen;
    
    return [
        'flete' => $flete,
        'seguro' => $seguro,
        'aduana' => $aduana,
        'almacen' => $costo_almacen,
        'total' => $total
    ];
}
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Mis Cotizaciones"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>💰 Mis Cotizaciones</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCotizacion">
                    ➕ Nueva Cotización
                </button>
            </div>

            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo strpos($mensaje, '✅') !== false ? 'success' : 'danger'; ?>">
                <?php echo $mensaje; ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <?php if (empty($cotizaciones)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                        <h5>No tienes cotizaciones aún</h5>
                        <p class="text-muted">Crea tu primera cotización para conocer los costos de importación</p>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($cotizaciones as $cotizacion): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>#C<?php echo str_pad($cotizacion['id_cotizacion'], 4, '0', STR_PAD_LEFT); ?></strong>
                            <span class="badge bg-<?php echo [
                                'pendiente' => 'warning',
                                'aprobada' => 'success', 
                                'rechazada' => 'danger'
                            ][$cotizacion['estado']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($cotizacion['estado']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h6><?php echo htmlspecialchars($cotizacion['nombre_producto']); ?></h6>
                            <p class="text-muted small">Cotizado: <?php echo date('d/m/Y', strtotime($cotizacion['fecha'])); ?></p>
                            
                            <div class="mb-2">
                                <small class="text-muted">Precio producto:</small>
                                <strong>$<?php echo number_format($cotizacion['precio_base'], 2); ?></strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Peso:</small>
                                <strong><?php echo $cotizacion['peso']; ?> kg</strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Categoría:</small>
                                <strong><?php echo ucfirst($cotizacion['categoria']); ?></strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Costo total:</small>
                                <strong class="text-success">$<?php echo number_format($cotizacion['costo_total'], 2); ?></strong>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="verDetalleCotizacion(<?php echo $cotizacion['id_cotizacion']; ?>)">
                                    👁️ Ver Detalles
                                </button>
                                <a href="cotizaciones.php?eliminar=<?php echo $cotizacion['id_cotizacion']; ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('¿Eliminar esta cotización?')">
                                    🗑️ Eliminar
                                </a>
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

<!-- Modal Nueva Cotización -->
<div class="modal fade" id="modalCotizacion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">💰 Nueva Cotización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Producto *</label>
                                <input type="text" name="nombre_producto" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Precio (USD) *</label>
                                <input type="number" step="0.01" name="precio_base" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Peso (kg) *</label>
                                <input type="number" step="0.1" name="peso" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Categoría *</label>
                                <select class="form-select" name="categoria" required>
                                    <option value="electronico">📱 Electrónico (30%)</option>
                                    <option value="ropa">👕 Ropa (20%)</option>
                                    <option value="hogar">🏠 Hogar (15%)</option>
                                    <option value="deportes">⚽ Deportes (25%)</option>
                                    <option value="otros">📦 Otros (18%)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tamaño *</label>
                                <select class="form-select" name="tamano" required>
                                    <option value="pequeno">Pequeño - $10</option>
                                    <option value="mediano" selected>Mediano - $25</option>
                                    <option value="grande">Grande - $50</option>
                                    <option value="extra">Extra Grande - $80</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_cotizacion" value="1" class="btn btn-primary">Crear Cotización</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalle Cotización -->
<div class="modal fade" id="modalDetalleCotizacion">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de Cotización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleCotizacionContent">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>
</div>

<script>
function verDetalleCotizacion(idCotizacion) {
    fetch(`../../procesos/obtener_detalle_cotizacion.php?id_cotizacion=${idCotizacion}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('detalleCotizacionContent').innerHTML = data.html;
            $('#modalDetalleCotizacion').modal('show');
        } else {
            alert('Error al cargar detalles');
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>