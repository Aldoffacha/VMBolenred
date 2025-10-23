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

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipo_usuario = $_GET['tipo_usuario'] ?? '';
$accion = $_GET['accion'] ?? '';
$tabla = $_GET['tabla'] ?? '';

$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;
$query = "SELECT a.*, 
                 COALESCE(ad.nombre, e.nombre, c.nombre) as nombre_usuario
          FROM auditoria a 
          LEFT JOIN administradores ad ON a.id_usuario = ad.id_admin AND a.tipo_usuario = 'admin'
          LEFT JOIN empleados e ON a.id_usuario = e.id_empleado AND a.tipo_usuario = 'empleado'
          LEFT JOIN clientes c ON a.id_usuario = c.id_cliente AND a.tipo_usuario = 'cliente'
          WHERE a.fecha_auditoria BETWEEN ? AND ? + INTERVAL 1 DAY";
$params = [$fecha_inicio, $fecha_fin];

if (!empty($tipo_usuario)) {
    $query .= " AND a.tipo_usuario = ?";
    $params[] = $tipo_usuario;
}

if (!empty($accion)) {
    $query .= " AND a.accion = ?";
    $params[] = $accion;
}

if (!empty($tabla)) {
    $query .= " AND a.tabla_afectada = ?";
    $params[] = $tabla;
}

$query_count = "SELECT COUNT(*) as total FROM auditoria a WHERE a.fecha_auditoria BETWEEN ? AND ? + INTERVAL 1 DAY";
$params_count = [$fecha_inicio, $fecha_fin];

if (!empty($tipo_usuario)) {
    $query_count .= " AND a.tipo_usuario = ?";
    $params_count[] = $tipo_usuario;
}

if (!empty($accion)) {
    $query_count .= " AND a.accion = ?";
    $params_count[] = $accion;
}

if (!empty($tabla)) {
    $query_count .= " AND a.tabla_afectada = ?";
    $params_count[] = $tabla;
}

$stmt_count = $db->prepare($query_count);
$stmt_count->execute($params_count);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

if ($pagina_actual < 1) $pagina_actual = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

$query .= " ORDER BY a.fecha_auditoria DESC LIMIT $registros_por_pagina OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$registros_auditoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_query = "SELECT 
    COUNT(*) as total_registros,
    COUNT(DISTINCT id_usuario) as usuarios_activos,
    COUNT(DISTINCT tabla_afectada) as tablas_afectadas,
    SUM(CASE WHEN fecha_auditoria >= CURDATE() THEN 1 ELSE 0 END) as registros_hoy
FROM auditoria 
WHERE fecha_auditoria BETWEEN ? AND ? + INTERVAL 1 DAY";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$fecha_inicio, $fecha_fin]);
$estadisticas = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría del Sistema - VMBol en Red</title>
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
                    <h1 class="h2">🔍 Auditoría del Sistema</h1>
                    <button class="btn btn-outline-primary" onclick="exportarAuditoria()">
                        <i class="fas fa-download me-1"></i> Exportar
                    </button>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Registros</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $estadisticas['total_registros']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-database fa-2x text-gray-300"></i>
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
                                            Registros Hoy</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $estadisticas['registros_hoy']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
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
                                            Usuarios Activos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $estadisticas['usuarios_activos']; ?>
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
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Tablas Afectadas</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $estadisticas['tablas_afectadas']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-table fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Filtros de Búsqueda</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                       value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                       value="<?php echo htmlspecialchars($fecha_fin); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="tipo_usuario" class="form-label">Tipo Usuario</label>
                                <select class="form-control" id="tipo_usuario" name="tipo_usuario">
                                    <option value="">Todos</option>
                                    <option value="admin" <?php echo $tipo_usuario == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="empleado" <?php echo $tipo_usuario == 'empleado' ? 'selected' : ''; ?>>Empleado</option>
                                    <option value="cliente" <?php echo $tipo_usuario == 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="accion" class="form-label">Acción</label>
                                <select class="form-control" id="accion" name="accion">
                                    <option value="">Todas</option>
                                    <option value="INSERT" <?php echo $accion == 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
                                    <option value="UPDATE" <?php echo $accion == 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                                    <option value="DELETE" <?php echo $accion == 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="tabla" class="form-label">Tabla</label>
                                <select class="form-control" id="tabla" name="tabla">
                                    <option value="">Todas</option>
                                    <option value="pedidos" <?php echo $tabla == 'pedidos' ? 'selected' : ''; ?>>Pedidos</option>
                                    <option value="clientes" <?php echo $tabla == 'clientes' ? 'selected' : ''; ?>>Clientes</option>
                                    <option value="empleados" <?php echo $tabla == 'empleados' ? 'selected' : ''; ?>>Empleados</option>
                                    <option value="productos" <?php echo $tabla == 'productos' ? 'selected' : ''; ?>>Productos</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i> Buscar
                                </button>
                                <a href="auditoria.php" class="btn btn-secondary">Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Registros de Auditoría</h5>
                        <span class="badge bg-primary">
                            <?php echo count($registros_auditoria); ?> registros encontrados
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha/Hora</th>
                                        <th>Usuario</th>
                                        <th>Tipo</th>
                                        <th>Tabla</th>
                                        <th>Acción</th>
                                        <th>ID Registro</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($registros_auditoria)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-search fa-2x mb-2"></i><br>
                                            No se encontraron registros de auditoría
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($registros_auditoria as $registro): ?>
                                    <tr>
                                        <td><strong>#<?php echo $registro['id_auditoria']; ?></strong></td>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($registro['fecha_auditoria'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($registro['nombre_usuario']): ?>
                                                <?php echo htmlspecialchars($registro['nombre_usuario']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Usuario #<?php echo $registro['id_usuario']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $registro['tipo_usuario'] == 'admin' ? 'bg-danger' : 
                                                       ($registro['tipo_usuario'] == 'empleado' ? 'bg-warning' : 'bg-info'); ?>">
                                                <?php echo ucfirst($registro['tipo_usuario']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($registro['tabla_afectada']); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $accion_class = [
                                                'INSERT' => 'bg-success',
                                                'UPDATE' => 'bg-warning',
                                                'DELETE' => 'bg-danger'
                                            ][$registro['accion']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $accion_class; ?>">
                                                <?php echo $registro['accion']; ?>
                                            </span>
                                        </td>
                                        <td>#<?php echo $registro['id_registro']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="verDetalles(<?php echo $registro['id_auditoria']; ?>)"
                                                    data-bs-toggle="tooltip" title="Ver Detalles">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Paginación de auditoría">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </a>
                                </li>

                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 2 && $i <= $pagina_actual + 2)): ?>
                                        <li class="page-item <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                            <a class="page-link" 
                                               href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php elseif ($i == $pagina_actual - 3 || $i == $pagina_actual + 3): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])); ?>">
                                        Siguiente <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <div class="text-center text-muted mt-2">
                            <small>Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?> • 
                                   Total de registros: <?php echo $total_registros; ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="modalDetalles" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles de Auditoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detallesContenido">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function verDetalles(id) {
        fetch(`../../procesos/obtener_detalles_auditoria.php?id=${id}`)
            .then(response => response.json())
            .then(datos => {
                const contenido = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Información General</h6>
                            <table class="table table-sm">
                                <tr><td><strong>ID Auditoría:</strong></td><td>#${datos.id_auditoria}</td></tr>
                                <tr><td><strong>Fecha:</strong></td><td>${new Date(datos.fecha_auditoria).toLocaleString()}</td></tr>
                                <tr><td><strong>Usuario:</strong></td><td>${datos.nombre_usuario || 'Usuario #' + datos.id_usuario}</td></tr>
                                <tr><td><strong>Tipo:</strong></td><td><span class="badge ${datos.tipo_usuario == 'admin' ? 'bg-danger' : datos.tipo_usuario == 'empleado' ? 'bg-warning' : 'bg-info'}">${datos.tipo_usuario}</span></td></tr>
                                <tr><td><strong>Tabla:</strong></td><td>${datos.tabla_afectada}</td></tr>
                                <tr><td><strong>Acción:</strong></td><td><span class="badge ${datos.accion == 'INSERT' ? 'bg-success' : datos.accion == 'UPDATE' ? 'bg-warning' : 'bg-danger'}">${datos.accion}</span></td></tr>
                                <tr><td><strong>ID Registro:</strong></td><td>#${datos.id_registro}</td></tr>
                                ${datos.ip_address ? `<tr><td><strong>IP:</strong></td><td>${datos.ip_address}</td></tr>` : ''}
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Datos de la Operación</h6>
                            ${datos.datos_anteriores ? `
                                <div class="mb-3">
                                    <label class="form-label"><strong>Datos Anteriores:</strong></label>
                                    <pre class="bg-light p-2 rounded" style="font-size: 12px;">${JSON.stringify(JSON.parse(datos.datos_anteriores), null, 2)}</pre>
                                </div>
                            ` : ''}
                            ${datos.datos_nuevos ? `
                                <div class="mb-3">
                                    <label class="form-label"><strong>Datos Nuevos:</strong></label>
                                    <pre class="bg-light p-2 rounded" style="font-size: 12px;">${JSON.stringify(JSON.parse(datos.datos_nuevos), null, 2)}</pre>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                document.getElementById('detallesContenido').innerHTML = contenido;
                new bootstrap.Modal(document.getElementById('modalDetalles')).show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar detalles');
            });
    }

    function exportarAuditoria() {
        const params = new URLSearchParams(window.location.search);
        window.open(`../../procesos/exportar_auditoria.php?${params.toString()}`, '_blank');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>
</html>