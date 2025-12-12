<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/swift-alerts-helper.php';

try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    header('Location: ../../public/login.php');
    exit;
}

$db = (new Database())->getConnection();

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'actualizar':
                $id = $_POST['id_inventario'];
                $cantidad = $_POST['cantidad'];
                $cantidad_minima = $_POST['cantidad_minima'];
                $ubicacion = $_POST['ubicacion'];
                
                $stmt = $db->prepare("UPDATE inventario SET cantidad=?, cantidad_minima=?, ubicacion=? WHERE id_inventario=?");
                if ($stmt->execute([$cantidad, $cantidad_minima, $ubicacion, $id])) {
                    $_SESSION['success'] = 'Inventario actualizado correctamente';
                }
                break;
                
            case 'agregar':
                $id_producto = $_POST['id_producto'];
                $id_deposito = $_POST['id_deposito'];
                $cantidad = $_POST['cantidad'];
                $cantidad_minima = $_POST['cantidad_minima'];
                $ubicacion = $_POST['ubicacion'];
                
                // Verificar si ya existe
                $check = $db->prepare("SELECT * FROM inventario WHERE id_producto = ? AND id_deposito = ?");
                $check->execute([$id_producto, $id_deposito]);
                
                if ($check->rowCount() > 0) {
                    $stmt = $db->prepare("UPDATE inventario SET cantidad=cantidad+?, cantidad_minima=?, ubicacion=? WHERE id_producto=? AND id_deposito=?");
                    if ($stmt->execute([$cantidad, $cantidad_minima, $ubicacion, $id_producto, $id_deposito])) {
                        $_SESSION['success'] = 'Inventario actualizado correctamente';
                    }
                } else {
                    $stmt = $db->prepare("INSERT INTO inventario (id_producto, id_deposito, cantidad, cantidad_minima, ubicacion) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$id_producto, $id_deposito, $cantidad, $cantidad_minima, $ubicacion])) {
                        $_SESSION['success'] = 'Producto agregado al inventario correctamente';
                    }
                }
                break;
        }
        header('Location: inventario.php');
        exit;
    }
}

// Obtener inventario con joins
$inventario = $db->query("
    SELECT i.*, p.nombre as producto, p.precio, d.nombre_deposito as deposito 
    FROM inventario i 
    JOIN productos p ON i.id_producto = p.id_producto 
    JOIN depositos_miami d ON i.id_deposito = d.id_deposito 
    WHERE p.estado = 1
    ORDER BY i.id_inventario DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos y depósitos para selects
$productos = $db->query("SELECT id_producto, nombre FROM productos WHERE estado = 1")->fetchAll(PDO::FETCH_ASSOC);
$depositos = $db->query("SELECT id_deposito, nombre_deposito FROM depositos_miami WHERE estado = 1")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - VMBol en Red</title>
    <!-- Bootstrap PRIMERO -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tus CSS DESPUÉS (para que sobrescriban a Bootstrap) -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-dashboard">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Inventario</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalInventario">
                        <i class="fas fa-plus me-1"></i> Agregar al Inventario
                    </button>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Alertas de stock bajo -->
                <?php
                $stockBajo = $db->query("
                    SELECT i.*, p.nombre as producto, d.nombre_deposito as deposito 
                    FROM inventario i 
                    JOIN productos p ON i.id_producto = p.id_producto 
                    JOIN depositos_miami d ON i.id_deposito = d.id_deposito 
                    WHERE i.cantidad <= i.cantidad_minima AND p.estado = 1
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($stockBajo) > 0): ?>
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Alertas de Stock Bajo</h6>
                    <ul class="mb-0">
                        <?php foreach ($stockBajo as $alerta): ?>
                        <li><?php echo $alerta['producto']; ?> en <?php echo $alerta['deposito']; ?>: 
                            <?php echo $alerta['cantidad']; ?> unidades (mínimo: <?php echo $alerta['cantidad_minima']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Depósito</th>
                                <th>Cantidad</th>
                                <th>Mínimo</th>
                                <th>Ubicación</th>
                                <th>Última Actualización</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventario as $item): 
                                $claseStock = $item['cantidad'] <= $item['cantidad_minima'] ? 'table-warning' : '';
                            ?>
                            <tr class="<?php echo $claseStock; ?>">
                                <td><?php echo $item['id_inventario']; ?></td>
                                <td><?php echo htmlspecialchars($item['producto']); ?></td>
                                <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                <td><?php echo htmlspecialchars($item['deposito']); ?></td>
                                <td>
                                    <span class="<?php echo $item['cantidad'] <= $item['cantidad_minima'] ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo $item['cantidad']; ?>
                                    </span>
                                </td>
                                <td><?php echo $item['cantidad_minima']; ?></td>
                                <td><?php echo htmlspecialchars($item['ubicacion']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_actualizacion'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary btn-editar" 
                                            data-id="<?php echo $item['id_inventario']; ?>"
                                            data-cantidad="<?php echo $item['cantidad']; ?>"
                                            data-minima="<?php echo $item['cantidad_minima']; ?>"
                                            data-ubicacion="<?php echo htmlspecialchars($item['ubicacion']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Inventario -->
    <div class="modal fade" id="modalInventario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar al Inventario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="agregar">
                        
                        <div class="mb-3">
                            <label for="id_producto" class="form-label">Producto</label>
                            <select class="form-control" id="id_producto" name="id_producto" required>
                                <option value="">Seleccionar producto</option>
                                <?php foreach ($productos as $producto): ?>
                                <option value="<?php echo $producto['id_producto']; ?>"><?php echo htmlspecialchars($producto['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_deposito" class="form-label">Depósito</label>
                            <select class="form-control" id="id_deposito" name="id_deposito" required>
                                <option value="">Seleccionar depósito</option>
                                <?php foreach ($depositos as $deposito): ?>
                                <option value="<?php echo $deposito['id_deposito']; ?>"><?php echo htmlspecialchars($deposito['nombre_deposito']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cantidad" class="form-label">Cantidad</label>
                            <input type="number" class="form-control" id="cantidad" name="cantidad" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label for="cantidad_minima" class="form-label">Cantidad Mínima</label>
                            <input type="number" class="form-control" id="cantidad_minima" name="cantidad_minima" required min="1" value="5">
                        </div>
                        
                        <div class="mb-3">
                            <label for="ubicacion" class="form-label">Ubicación</label>
                            <input type="text" class="form-control" id="ubicacion" name="ubicacion" placeholder="Ej: Estante A-1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Inventario -->
    <div class="modal fade" id="modalEditarInventario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Inventario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="id_inventario" id="editInventarioId">
                        
                        <div class="mb-3">
                            <label for="editCantidad" class="form-label">Cantidad</label>
                            <input type="number" class="form-control" id="editCantidad" name="cantidad" required min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="editCantidadMinima" class="form-label">Cantidad Mínima</label>
                            <input type="number" class="form-control" id="editCantidadMinima" name="cantidad_minima" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label for="editUbicacion" class="form-label">Ubicación</label>
                            <input type="text" class="form-control" id="editUbicacion" name="ubicacion">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script>
        // Editar inventario
        document.querySelectorAll('.btn-editar').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('editInventarioId').value = this.dataset.id;
                document.getElementById('editCantidad').value = this.dataset.cantidad;
                document.getElementById('editCantidadMinima').value = this.dataset.minima;
                document.getElementById('editUbicacion').value = this.dataset.ubicacion;
                
                new bootstrap.Modal(document.getElementById('modalEditarInventario')).show();
            });
        });
    </script>
</body>
</html>