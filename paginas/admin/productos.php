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

// Configuración de paginación
$productos_por_pagina = 9;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

// Obtener el total de productos
$stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE estado = 1");
$total_productos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_productos / $productos_por_pagina);

// Ajustar página actual si es mayor que el total de páginas
if ($pagina_actual > $total_paginas && $total_paginas > 0) {
    $pagina_actual = $total_paginas;
}

// Calcular offset
$offset = ($pagina_actual - 1) * $productos_por_pagina;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'crear':
                $nombre = $_POST['nombre'];
                $descripcion = $_POST['descripcion'] ?? '';
                $precio = $_POST['precio'];
                $stock = $_POST['stock'] ?? 0;
                $categoria = $_POST['categoria'] ?? 'otros';
                
                $nombreImagen = '';
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                    $nombreImagen = uniqid() . '.' . $extension;
                    $rutaDestino = '../../assets/img/productos/' . $nombreImagen;
                    
                    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
                        $_SESSION['error'] = 'Error al subir la imagen';
                        header('Location: productos.php');
                        exit;
                    }
                }
                
                $stmt = $db->prepare("INSERT INTO productos (nombre, descripcion, precio, stock, imagen, categoria) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$nombre, $descripcion, $precio, $stock, $nombreImagen, $categoria])) {
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
                $categoria = $_POST['categoria'] ?? 'otros';
                
                $nombreImagen = $_POST['imagen_actual'] ?? '';
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    if (!empty($nombreImagen)) {
                        $rutaAnterior = '../../assets/img/productos/' . $nombreImagen;
                        if (file_exists($rutaAnterior)) {
                            unlink($rutaAnterior);
                        }
                    }
                    
                    $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                    $nombreImagen = uniqid() . '.' . $extension;
                    $rutaDestino = '../../assets/img/productos/' . $nombreImagen;
                    
                    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
                        $_SESSION['error'] = 'Error al subir la imagen';
                        header('Location: productos.php');
                        exit;
                    }
                }
                
                $stmt = $db->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, imagen=?, categoria=? WHERE id_producto=?");
                if ($stmt->execute([$nombre, $descripcion, $precio, $stock, $nombreImagen, $categoria, $id])) {
                    $_SESSION['success'] = 'Producto actualizado correctamente';
                } else {
                    $_SESSION['error'] = 'Error al actualizar el producto';
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                
                $stmt = $db->prepare("SELECT imagen FROM productos WHERE id_producto = ?");
                $stmt->execute([$id]);
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("UPDATE productos SET estado=0 WHERE id_producto=?");
                if ($stmt->execute([$id])) {
                    if (!empty($producto['imagen'])) {
                        $rutaImagen = '../../assets/img/productos/' . $producto['imagen'];
                        if (file_exists($rutaImagen)) {
                            unlink($rutaImagen);
                        }
                    }
                    $_SESSION['success'] = 'Producto eliminado correctamente';
                } else {
                    $_SESSION['error'] = 'Error al eliminar el producto';
                }
                break;
        }
        // Redirigir manteniendo la página actual
        $pagina_redirect = isset($_POST['pagina_actual']) ? '?pagina=' . (int)$_POST['pagina_actual'] : '';
        header('Location: productos.php' . $pagina_redirect);
        exit;
    }
}

// Obtener productos con paginación
$stmt = $db->prepare("SELECT * FROM productos WHERE estado = 1 ORDER BY id_producto DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $productos_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - VMBol en Red</title>
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
                    <h1 class="h2">Gestión de Productos</h1>
                    <div>
                        <span class="badge bg-secondary me-2">
                            Total: <?php echo $total_productos; ?> productos
                        </span>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                            <i class="fas fa-plus me-1"></i> Nuevo Producto
                        </button>
                    </div>
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

                <!-- Información de paginación -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="mb-0 text-muted">
                        Mostrando <?php echo count($productos); ?> de <?php echo $total_productos; ?> productos
                    </p>
                    <?php if ($total_paginas > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=1">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            // Mostrar números de página
                            $inicio = max(1, $pagina_actual - 2);
                            $fin = min($total_paginas, $pagina_actual + 2);
                            
                            for ($i = $inicio; $i <= $fin; $i++): 
                            ?>
                                <li class="page-item <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $total_paginas; ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>

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
                                <th>Categoría</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                        <h5>No hay productos activos</h5>
                                        <p class="text-muted">No se encontraron productos en el sistema</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?php echo $producto['id_producto']; ?></td>
                                    <td>
                                        <?php if (!empty($producto['imagen'])): ?>
                                            <img src="../../assets/img/productos/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                                 alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                 onerror="this.src='https://via.placeholder.com/50x50/2c7be5/ffffff?text=IMG'">
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
                                    <td><?php echo htmlspecialchars($producto['categoria']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($producto['fecha_registro'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary btn-editar" 
                                                data-id="<?php echo $producto['id_producto']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                data-descripcion="<?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?>"
                                                data-precio="<?php echo $producto['precio']; ?>"
                                                data-stock="<?php echo $producto['stock']; ?>"
                                                data-imagen="<?php echo htmlspecialchars($producto['imagen'] ?? ''); ?>"
                                                data-categoria="<?php echo htmlspecialchars($producto['categoria']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-eliminar" 
                                                data-id="<?php echo $producto['id_producto']; ?>"
                                                data-pagina="<?php echo $pagina_actual; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación inferior -->
                <?php if ($total_paginas > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <p class="text-muted mb-0">
                        Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                    </p>
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=1">
                                    <i class="fas fa-angle-double-left"></i> Primera
                                </a>
                            </li>
                            <li class="page-item <?php echo $pagina_actual == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?>">
                                    <i class="fas fa-angle-left"></i> Anterior
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 1 && $i <= $pagina_actual + 1)): ?>
                                    <li class="page-item <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php elseif ($i == $pagina_actual - 2 || $i == $pagina_actual + 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?>">
                                    Siguiente <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $pagina_actual == $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $total_paginas; ?>">
                                    Última <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <div class="modal fade" id="modalProducto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="productoId">
                        <input type="hidden" name="imagen_actual" id="imagenActual">
                        <input type="hidden" name="pagina_actual" value="<?php echo $pagina_actual; ?>">
                        
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
                                    <label for="categoria" class="form-label">Categoría</label>
                                    <select class="form-control" id="categoria" name="categoria">
                                        <option value="otros">Otros</option>
                                        <option value="electronica">Electrónica</option>
                                        <option value="ropa">Ropa</option>
                                        <option value="hogar">Hogar</option>
                                        <option value="deportes">Deportes</option>
                                        <option value="libros">Libros</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="imagen" class="form-label">Imagen</label>
                                    <input type="file" class="form-control" id="imagen" name="imagen" 
                                           accept="image/*">
                                    <div class="form-text">Formatos aceptados: JPG, PNG, GIF, WEBP</div>
                                    <div id="vistaPrevia" class="mt-2 text-center"></div>
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
        document.querySelectorAll('.btn-editar').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('accion').value = 'editar';
                document.getElementById('productoId').value = this.dataset.id;
                document.getElementById('nombre').value = this.dataset.nombre;
                document.getElementById('descripcion').value = this.dataset.descripcion;
                document.getElementById('precio').value = this.dataset.precio;
                document.getElementById('stock').value = this.dataset.stock;
                document.getElementById('categoria').value = this.dataset.categoria;
                document.getElementById('imagenActual').value = this.dataset.imagen;
                
                const vistaPrevia = document.getElementById('vistaPrevia');
                if (this.dataset.imagen) {
                    vistaPrevia.innerHTML = `<img src="../../assets/img/productos/${this.dataset.imagen}" style="max-width: 200px; max-height: 150px;" class="img-thumbnail">`;
                } else {
                    vistaPrevia.innerHTML = '';
                }
                
                document.querySelector('.modal-title').textContent = 'Editar Producto';
                new bootstrap.Modal(document.getElementById('modalProducto')).show();
            });
        });

        document.querySelectorAll('.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('¿Estás seguro de eliminar este producto?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="${this.dataset.id}">
                        <input type="hidden" name="pagina_actual" value="${this.dataset.pagina}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        document.getElementById('imagen').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const vistaPrevia = document.getElementById('vistaPrevia');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    vistaPrevia.innerHTML = `<img src="${e.target.result}" style="max-width: 200px; max-height: 150px;" class="img-thumbnail">`;
                }
                reader.readAsDataURL(file);
            } else {
                vistaPrevia.innerHTML = '';
            }
        });

        document.getElementById('modalProducto').addEventListener('hidden.bs.modal', function() {
            document.getElementById('accion').value = 'crear';
            document.getElementById('productoId').value = '';
            document.getElementById('imagenActual').value = '';
            document.querySelector('.modal-title').textContent = 'Nuevo Producto';
            document.getElementById('vistaPrevia').innerHTML = '';
            this.querySelector('form').reset();
        });

        // Auto-redirección para mantener la página después de acciones
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('pagina')) {
            // Mantener el parámetro de página en los formularios
            document.querySelectorAll('form').forEach(form => {
                if (!form.querySelector('input[name="pagina_actual"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'pagina_actual';
                    input.value = urlParams.get('pagina');
                    form.appendChild(input);
                }
            });
        }
    </script>
</body>
</html>