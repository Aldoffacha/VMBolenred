<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// Verificar autenticación
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
            case 'crear':
                $nombre = $_POST['nombre'];
                $descripcion = $_POST['descripcion'] ?? '';
                $precio = $_POST['precio'];
                $stock = $_POST['stock'] ?? 0;
                $imagen = $_POST['imagen'] ?? '';
                
                $stmt = $db->prepare("INSERT INTO productos (nombre, descripcion, precio, stock, imagen) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$nombre, $descripcion, $precio, $stock, $imagen])) {
                    $_SESSION['success'] = 'Producto creado correctamente';
                } else {
                    $_SESSION['error'] = 'Error al crear el producto';
                }
                break;
                
            case 'editar':
                $id = $_POST['id'];
                $nombre = $_POST['nombre'];
                $descripcion = $_POST['descripcion'] ?? '';
                $precio = $_POST['precio'];
                $stock = $_POST['stock'] ?? 0;
                $imagen = $_POST['imagen'] ?? '';
                
                $stmt = $db->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, imagen=? WHERE id_producto=?");
                if ($stmt->execute([$nombre, $descripcion, $precio, $stock, $imagen, $id])) {
                    $_SESSION['success'] = 'Producto actualizado correctamente';
                } else {
                    $_SESSION['error'] = 'Error al actualizar el producto';
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                $stmt = $db->prepare("UPDATE productos SET estado=0 WHERE id_producto=?");
                if ($stmt->execute([$id])) {
                    $_SESSION['success'] = 'Producto eliminado correctamente';
                } else {
                    $_SESSION['error'] = 'Error al eliminar el producto';
                }
                break;
        }
        header('Location: productos.php');
        exit;
    }
}

// Obtener productos
$productos = $db->query("SELECT * FROM productos WHERE estado = 1 ORDER BY id_producto DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - VMBol en Red</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Productos</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                        <i class="fas fa-plus me-1"></i> Nuevo Producto
                    </button>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabla de productos -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Imagen</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><?php echo $producto['id_producto']; ?></td>
                                <td>
                                    <?php if (!empty($producto['imagen'])): ?>
                                        <img src="../../assets/images/productos/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                <td><?php echo htmlspecialchars(substr($producto['descripcion'] ?? '', 0, 50)) . '...'; ?></td>
                                <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                                <td>
                                    <span class="<?php echo $producto['stock'] <= 5 ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo $producto['stock']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($producto['fecha_registro'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary btn-editar" 
                                            data-id="<?php echo $producto['id_producto']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                            data-descripcion="<?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?>"
                                            data-precio="<?php echo $producto['precio']; ?>"
                                            data-stock="<?php echo $producto['stock']; ?>"
                                            data-imagen="<?php echo htmlspecialchars($producto['imagen'] ?? ''); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-eliminar" 
                                            data-id="<?php echo $producto['id_producto']; ?>">
                                        <i class="fas fa-trash"></i>
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

    <!-- Modal Producto -->
    <div class="modal fade" id="modalProducto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="productoId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="precio" class="form-label">Precio *</label>
                                    <input type="number" step="0.01" class="form-control" id="precio" name="precio" required min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="stock" class="form-label">Stock</label>
                                    <input type="number" class="form-control" id="stock" name="stock" min="0" value="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="imagen" class="form-label">Imagen (URL o nombre de archivo)</label>
                                    <input type="text" class="form-control" id="imagen" name="imagen" 
                                           placeholder="Ej: producto1.jpg">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="8" 
                                              placeholder="Descripción detallada del producto..."></textarea>
                                </div>
                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script>
        // Editar producto
        document.querySelectorAll('.btn-editar').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('accion').value = 'editar';
                document.getElementById('productoId').value = this.dataset.id;
                document.getElementById('nombre').value = this.dataset.nombre;
                document.getElementById('descripcion').value = this.dataset.descripcion;
                document.getElementById('precio').value = this.dataset.precio;
                document.getElementById('stock').value = this.dataset.stock;
                document.getElementById('imagen').value = this.dataset.imagen;
                
                document.querySelector('.modal-title').textContent = 'Editar Producto';
                new bootstrap.Modal(document.getElementById('modalProducto')).show();
            });
        });

        // Eliminar producto
        document.querySelectorAll('.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('¿Estás seguro de eliminar este producto?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="${this.dataset.id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        // Reset modal al cerrar
        document.getElementById('modalProducto').addEventListener('hidden.bs.modal', function() {
            document.getElementById('accion').value = 'crear';
            document.getElementById('productoId').value = '';
            document.querySelector('.modal-title').textContent = 'Nuevo Producto';
            this.querySelector('form').reset();
        });
    </script>
</body>
</html>