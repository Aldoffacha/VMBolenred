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
            case 'agregar_empleado':
                $nombre = $_POST['nombre'];
                $correo = $_POST['correo'];
                $telefono = $_POST['telefono'];
                
                // Generar contraseña temporal
                $contrasena_temp = 'temp123';
                $contrasena_hash = password_hash($contrasena_temp, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO empleados (nombre, correo, telefono, contrasena) 
                                    VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $correo, $telefono, $contrasena_hash]);
                
                $_SESSION['mensaje'] = 'Empleado agregado correctamente. Contraseña temporal: ' . $contrasena_temp;
                break;
                
            case 'editar_empleado':
                $id = $_POST['id_empleado'];
                $nombre = $_POST['nombre'];
                $correo = $_POST['correo'];
                $telefono = $_POST['telefono'];
                $estado = $_POST['estado'];
                
                $stmt = $db->prepare("UPDATE empleados SET 
                                    nombre = ?, correo = ?, telefono = ?, estado = ?
                                    WHERE id_empleado = ?");
                $stmt->execute([$nombre, $correo, $telefono, $estado, $id]);
                
                $_SESSION['mensaje'] = 'Empleado actualizado correctamente';
                break;
                
            case 'resetear_contrasena':
                $id = $_POST['id_empleado'];
                $contrasena_temp = 'temp123';
                $contrasena_hash = password_hash($contrasena_temp, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE empleados SET contrasena = ? WHERE id_empleado = ?");
                $stmt->execute([$contrasena_hash, $id]);
                
                $_SESSION['mensaje'] = 'Contraseña reseteada correctamente. Nueva contraseña: ' . $contrasena_temp;
                break;
        }
    }
    header('Location: empleados.php');
    exit;
}

// Obtener lista de empleados
$empleados = $db->query("SELECT * FROM empleados ORDER BY fecha_registro DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empleados - VMBol en Red</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Empleados</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEmpleado">
                        <i class="fas fa-plus me-1"></i> Agregar Empleado
                    </button>
                </div>

                <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Estadísticas Rápidas -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Empleados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo count($empleados); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Empleados Activos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo count(array_filter($empleados, fn($e) => $e['estado'] == 1)); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Empleados Inactivos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo count(array_filter($empleados, fn($e) => $e['estado'] == 0)); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-slash fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Nuevos (30 días)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $hace_30_dias = date('Y-m-d', strtotime('-30 days'));
                                            echo count(array_filter($empleados, fn($e) => strtotime($e['fecha_registro']) >= strtotime($hace_30_dias)));
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Empleados -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lista de Empleados</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Fecha Registro</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empleados as $empleado): ?>
                                    <tr>
                                        <td><strong>#<?php echo $empleado['id_empleado']; ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary rounded-circle text-white d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <?php echo strtoupper(substr($empleado['nombre'], 0, 1)); ?>
                                                </div>
                                                <?php echo htmlspecialchars($empleado['nombre']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($empleado['correo']); ?></td>
                                        <td><?php echo htmlspecialchars($empleado['telefono'] ?? 'No especificado'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($empleado['fecha_registro'])); ?></td>
                                        <td>
                                            <?php if ($empleado['estado'] == 1): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="editarEmpleado(<?php echo $empleado['id_empleado']; ?>)"
                                                        data-bs-toggle="tooltip" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" 
                                                        onclick="resetearContrasena(<?php echo $empleado['id_empleado']; ?>)"
                                                        data-bs-toggle="tooltip" title="Resetear Contraseña">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <?php if ($empleado['estado'] == 1): ?>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="cambiarEstado(<?php echo $empleado['id_empleado']; ?>, 0)"
                                                        data-bs-toggle="tooltip" title="Desactivar">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                                <?php else: ?>
                                                <button class="btn btn-outline-success" 
                                                        onclick="cambiarEstado(<?php echo $empleado['id_empleado']; ?>, 1)"
                                                        data-bs-toggle="tooltip" title="Activar">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Agregar/Editar Empleado -->
    <div class="modal fade" id="modalEmpleado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEmpleadoTitulo">Agregar Nuevo Empleado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEmpleado">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="agregar_empleado">
                        <input type="hidden" name="id_empleado" id="id_empleado">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="correo" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="correo" name="correo" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono" placeholder="+591 123 456 789">
                        </div>
                        
                        <div id="campoEstado" style="display: none;">
                            <div class="mb-3">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-control" id="estado" name="estado">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Al agregar un empleado, se generará automáticamente una contraseña temporal: <strong>temp123</strong>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Empleado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Función para editar empleado
    function editarEmpleado(id) {
        fetch(`../../procesos/obtener_empleado.php?id=${id}`)
            .then(response => response.json())
            .then(empleado => {
                document.getElementById('modalEmpleadoTitulo').textContent = 'Editar Empleado';
                document.getElementById('accion').value = 'editar_empleado';
                document.getElementById('id_empleado').value = empleado.id_empleado;
                document.getElementById('nombre').value = empleado.nombre;
                document.getElementById('correo').value = empleado.correo;
                document.getElementById('telefono').value = empleado.telefono || '';
                document.getElementById('estado').value = empleado.estado;
                document.getElementById('campoEstado').style.display = 'block';
                
                const modal = new bootstrap.Modal(document.getElementById('modalEmpleado'));
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar datos del empleado');
            });
    }

    // Función para resetear contraseña
    function resetearContrasena(id) {
        if (confirm('¿Estás seguro de resetear la contraseña de este empleado?')) {
            const formData = new FormData();
            formData.append('accion', 'resetear_contrasena');
            formData.append('id_empleado', id);
            
            fetch('empleados.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }
    }

    // Función para cambiar estado
    function cambiarEstado(id, nuevoEstado) {
        const accion = nuevoEstado ? 'activar' : 'desactivar';
        if (confirm(`¿Estás seguro de ${accion} este empleado?`)) {
            // Obtener datos actuales del empleado primero
            fetch(`../../procesos/obtener_empleado.php?id=${id}`)
                .then(response => response.json())
                .then(empleado => {
                    const formData = new FormData();
                    formData.append('accion', 'editar_empleado');
                    formData.append('id_empleado', id);
                    formData.append('nombre', empleado.nombre);
                    formData.append('correo', empleado.correo);
                    formData.append('telefono', empleado.telefono || '');
                    formData.append('estado', nuevoEstado);
                    
                    return fetch('empleados.php', {
                        method: 'POST',
                        body: formData
                    });
                })
                .then(response => {
                    if (response.ok) {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cambiar estado del empleado');
                });
        }
    }

    // Resetear modal cuando se cierra
    document.getElementById('modalEmpleado').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalEmpleadoTitulo').textContent = 'Agregar Nuevo Empleado';
        document.getElementById('accion').value = 'agregar_empleado';
        document.getElementById('formEmpleado').reset();
        document.getElementById('campoEstado').style.display = 'none';
        document.getElementById('id_empleado').value = '';
    });

    // Inicializar tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>
</html>