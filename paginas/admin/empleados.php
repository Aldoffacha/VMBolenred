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

// Parámetros de búsqueda y paginación
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$porPagina = 8;
$offset = ($pagina - 1) * $porPagina;

// Construir consulta base
$sql = "SELECT * FROM empleados";
$params = [];
$countSql = "SELECT COUNT(*) FROM empleados";

// Aplicar búsqueda si existe
if (!empty($busqueda)) {
    $sql .= " WHERE LOWER(nombre) LIKE LOWER(?)";
    $countSql .= " WHERE LOWER(nombre) LIKE LOWER(?)";
    $params[] = "%$busqueda%";
}

// Orden y paginación
$sql .= " ORDER BY fecha_registro DESC LIMIT ? OFFSET ?";
$countParams = $params;
$params[] = $porPagina;
$params[] = $offset;

// Obtener total de registros
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($countParams);
$totalRegistros = $stmtCount->fetchColumn();
$totalPaginas = ceil($totalRegistros / $porPagina);

// Obtener empleados
$stmt = $db->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                                            <?php echo $totalRegistros; ?>
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
                                            <?php 
                                            $sqlActivos = "SELECT COUNT(*) FROM empleados WHERE estado = 1";
                                            if (!empty($busqueda)) {
                                                $sqlActivos .= " AND LOWER(nombre) LIKE LOWER(?)";
                                                $stmtActivos = $db->prepare($sqlActivos);
                                                $stmtActivos->execute(["%$busqueda%"]);
                                            } else {
                                                $stmtActivos = $db->query($sqlActivos);
                                            }
                                            echo $stmtActivos->fetchColumn();
                                            ?>
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
                                            <?php 
                                            $sqlInactivos = "SELECT COUNT(*) FROM empleados WHERE estado = 0";
                                            if (!empty($busqueda)) {
                                                $sqlInactivos .= " AND LOWER(nombre) LIKE LOWER(?)";
                                                $stmtInactivos = $db->prepare($sqlInactivos);
                                                $stmtInactivos->execute(["%$busqueda%"]);
                                            } else {
                                                $stmtInactivos = $db->query($sqlInactivos);
                                            }
                                            echo $stmtInactivos->fetchColumn();
                                            ?>
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
                                            $sqlNuevos = "SELECT COUNT(*) FROM empleados WHERE fecha_registro >= ?";
                                            if (!empty($busqueda)) {
                                                $sqlNuevos .= " AND LOWER(nombre) LIKE LOWER(?)";
                                                $stmtNuevos = $db->prepare($sqlNuevos);
                                                $stmtNuevos->execute([$hace_30_dias, "%$busqueda%"]);
                                            } else {
                                                $stmtNuevos = $db->prepare($sqlNuevos);
                                                $stmtNuevos->execute([$hace_30_dias]);
                                            }
                                            echo $stmtNuevos->fetchColumn();
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

                <!-- Buscador de Empleados -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Buscar Empleados</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control" 
                                           name="buscar" 
                                           placeholder="Buscar empleados por nombre (no importa mayúsculas/minúsculas)..." 
                                           value="<?php echo htmlspecialchars($busqueda); ?>">
                                    <button class="btn btn-outline-primary" type="submit">
                                        <i class="fas fa-search me-1"></i> Buscar
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <?php if (!empty($busqueda)): ?>
                                <a href="empleados.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Limpiar Búsqueda
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if (!empty($busqueda)): ?>
                        <div class="mt-3">
                            <p class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Se encontraron <strong><?php echo $totalRegistros; ?></strong> empleados con el término: "<?php echo htmlspecialchars($busqueda); ?>"
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lista de Empleados -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lista de Empleados</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($empleados)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">
                                <?php if (!empty($busqueda)): ?>
                                No se encontraron empleados con el término "<?php echo htmlspecialchars($busqueda); ?>"
                                <?php else: ?>
                                No hay empleados registrados
                                <?php endif; ?>
                            </h5>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Fecha Registro</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empleados as $index => $empleado): ?>
                                    <tr>
                                        <td><strong><?php echo $offset + $index + 1; ?></strong></td>
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

                        <!-- Paginación -->
                        <?php if ($totalPaginas > 1): ?>
                        <nav aria-label="Paginación de empleados" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagina > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>&buscar=<?php echo urlencode($busqueda); ?>">
                                            <i class="fas fa-chevron-left me-1"></i> Anterior
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                // Mostrar números de página
                                $inicio = max(1, $pagina - 2);
                                $fin = min($totalPaginas, $pagina + 2);
                                
                                for ($i = $inicio; $i <= $fin; $i++): 
                                ?>
                                    <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $i; ?>&buscar=<?php echo urlencode($busqueda); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($pagina < $totalPaginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>&buscar=<?php echo urlencode($busqueda); ?>">
                                            Siguiente <i class="fas fa-chevron-right ms-1"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>

                        <!-- Información de paginación -->
                        <div class="text-center text-muted mt-2">
                            <small>
                                Mostrando <?php echo count($empleados); ?> de <?php echo $totalRegistros; ?> empleados
                                <?php if (!empty($busqueda)): ?>
                                    para la búsqueda "<?php echo htmlspecialchars($busqueda); ?>"
                                <?php endif; ?>
                                - Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
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
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(empleado => {
                if (empleado.error) {
                    alert(empleado.error);
                    return;
                }
                
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
                alert('Error al cargar datos del empleado: ' + error.message);
            });
    }

    // Función para resetear contraseña
    function resetearContrasena(id) {
        if (confirm('¿Estás seguro de resetear la contraseña de este empleado? La nueva contraseña será: temp123')) {
            // Crear un formulario dinámico para enviar la solicitud
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'empleados.php';
            
            const accionInput = document.createElement('input');
            accionInput.type = 'hidden';
            accionInput.name = 'accion';
            accionInput.value = 'resetear_contrasena';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id_empleado';
            idInput.value = id;
            
            form.appendChild(accionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Función para cambiar estado
    function cambiarEstado(id, nuevoEstado) {
        const accion = nuevoEstado ? 'activar' : 'desactivar';
        if (confirm(`¿Estás seguro de ${accion} este empleado?`)) {
            // Crear un formulario dinámico para enviar la solicitud
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'empleados.php';
            
            const accionInput = document.createElement('input');
            accionInput.type = 'hidden';
            accionInput.name = 'accion';
            accionInput.value = 'editar_empleado';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id_empleado';
            idInput.value = id;
            
            const estadoInput = document.createElement('input');
            estadoInput.type = 'hidden';
            estadoInput.name = 'estado';
            estadoInput.value = nuevoEstado;
            
            // Obtener los demás datos necesarios (puedes obtenerlos de la fila de la tabla si es necesario)
            const nombreInput = document.createElement('input');
            nombreInput.type = 'hidden';
            nombreInput.name = 'nombre';
            nombreInput.value = ''; // Este valor se debería obtener de la fila
            
            const correoInput = document.createElement('input');
            correoInput.type = 'hidden';
            correoInput.name = 'correo';
            correoInput.value = ''; // Este valor se debería obtener de la fila
            
            const telefonoInput = document.createElement('input');
            telefonoInput.type = 'hidden';
            telefonoInput.name = 'telefono';
            telefonoInput.value = ''; // Este valor se debería obtener de la fila
            
            form.appendChild(accionInput);
            form.appendChild(idInput);
            form.appendChild(estadoInput);
            form.appendChild(nombreInput);
            form.appendChild(correoInput);
            form.appendChild(telefonoInput);
            document.body.appendChild(form);
            form.submit();
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