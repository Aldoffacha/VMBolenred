<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    header('Location: ../public/login.php');
    exit;
}

$db = (new Database())->getConnection();

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'crear':
                $nombre = $_POST['nombre'];
                $correo = $_POST['correo'];
                $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
                $telefono = $_POST['telefono'];
                $direccion = $_POST['direccion'];
                
                $stmt = $db->prepare("INSERT INTO clientes (nombre, correo, contrasena, telefono, direccion) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $correo, $contrasena, $telefono, $direccion]);
                break;
                
            case 'editar':
                $id = $_POST['id'];
                $nombre = $_POST['nombre'];
                $correo = $_POST['correo'];
                $telefono = $_POST['telefono'];
                $direccion = $_POST['direccion'];
                
                $stmt = $db->prepare("UPDATE clientes SET nombre=?, correo=?, telefono=?, direccion=? WHERE id_cliente=?");
                $stmt->execute([$nombre, $correo, $telefono, $direccion, $id]);
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                $stmt = $db->prepare("UPDATE clientes SET estado=0 WHERE id_cliente=?");
                $stmt->execute([$id]);
                break;
        }
    }
}

// Obtener clientes
$clientes = $db->query("SELECT * FROM clientes WHERE estado = 1 ORDER BY id_cliente DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - VMBol en Red</title>
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
                    <h1 class="h2">Gestión de Usuarios</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                        <i class="fas fa-plus me-1"></i> Nuevo Usuario
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Dirección</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo $cliente['id_cliente']; ?></td>
                                <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['correo']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['direccion']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary btn-editar" 
                                            data-id="<?php echo $cliente['id_cliente']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($cliente['nombre']); ?>"
                                            data-correo="<?php echo htmlspecialchars($cliente['correo']); ?>"
                                            data-telefono="<?php echo htmlspecialchars($cliente['telefono']); ?>"
                                            data-direccion="<?php echo htmlspecialchars($cliente['direccion']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-eliminar" 
                                            data-id="<?php echo $cliente['id_cliente']; ?>">
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

    <!-- Modal Usuario -->
    <div class="modal fade" id="modalUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="usuarioId">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="correo" class="form-label">Email</label>
                            <input type="email" class="form-control" id="correo" name="correo" required>
                        </div>
                        
                        <div class="mb-3" id="contrasenaGroup">
                            <label for="contrasena" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="contrasena" name="contrasena">
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                        
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="2"></textarea>
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

    <script src="../../assets/js/main.js"></script>
    <script>
        // Editar usuario
        document.querySelectorAll('.btn-editar').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('accion').value = 'editar';
                document.getElementById('usuarioId').value = this.dataset.id;
                document.getElementById('nombre').value = this.dataset.nombre;
                document.getElementById('correo').value = this.dataset.correo;
                document.getElementById('telefono').value = this.dataset.telefono;
                document.getElementById('direccion').value = this.dataset.direccion;
                
                document.getElementById('contrasenaGroup').style.display = 'none';
                document.querySelector('.modal-title').textContent = 'Editar Usuario';
                new bootstrap.Modal(document.getElementById('modalUsuario')).show();
            });
        });

        // Eliminar usuario
        document.querySelectorAll('.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('¿Estás seguro de eliminar este usuario?')) {
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

        // Reset modal
        document.getElementById('modalUsuario').addEventListener('hidden.bs.modal', function() {
            document.getElementById('accion').value = 'crear';
            document.getElementById('usuarioId').value = '';
            document.getElementById('contrasenaGroup').style.display = 'block';
            document.querySelector('.modal-title').textContent = 'Nuevo Usuario';
            this.querySelector('form').reset();
        });
    </script>
</body>
</html>