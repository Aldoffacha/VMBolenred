<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    header('Location: ../../public/login.php');
    exit;
}

$db = (new Database())->getConnection();

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$tipo_reporte = $_GET['tipo_reporte'] ?? 'ventas';
$producto_id = $_GET['producto_id'] ?? '';
$cliente_id = $_GET['cliente_id'] ?? '';
$exportar = $_GET['exportar'] ?? '';

// Obtener listas para filtros
$productos = $db->query("SELECT id_producto, nombre FROM productos WHERE estado = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$clientes = $db->query("SELECT id_cliente, nombre FROM clientes WHERE estado = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Construir consultas base con filtros
$where_conditions = ["pe.estado != 'cancelado'", "pe.fecha BETWEEN ? AND ?"];
$params = [$fecha_inicio, $fecha_fin];

if (!empty($producto_id)) {
    $where_conditions[] = "pd.id_producto = ?";
    $params[] = $producto_id;
}

if (!empty($cliente_id)) {
    $where_conditions[] = "pe.id_cliente = ?";
    $params[] = $cliente_id;
}

$where_clause = implode(" AND ", $where_conditions);

// Estadísticas generales
$ventas_totales = $db->prepare("
    SELECT SUM(pe.total) as total 
    FROM pedidos pe
    LEFT JOIN pedido_detalles pd ON pe.id_pedido = pd.id_pedido
    WHERE $where_clause
");
$ventas_totales->execute($params);
$total_ventas = $ventas_totales->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$pedidos_totales = $db->prepare("
    SELECT COUNT(DISTINCT pe.id_pedido) as total 
    FROM pedidos pe
    LEFT JOIN pedido_detalles pd ON pe.id_pedido = pd.id_pedido
    WHERE $where_clause
");
$pedidos_totales->execute($params);
$total_pedidos = $pedidos_totales->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$clientes_nuevos = $db->prepare("SELECT COUNT(*) as total FROM clientes WHERE fecha_registro BETWEEN ? AND ?");
$clientes_nuevos->execute([$fecha_inicio, $fecha_fin]);
$total_clientes = $clientes_nuevos->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Ventas por mes (6 meses)
$ventas_mensuales = $db->query("
    SELECT 
        EXTRACT(YEAR FROM fecha) AS año,
        EXTRACT(MONTH FROM fecha) AS mes,
        SUM(total) AS total
    FROM pedidos
    WHERE fecha >= (NOW() - INTERVAL '6 months')
      AND estado != 'cancelado'
    GROUP BY año, mes
    ORDER BY año, mes
")->fetchAll(PDO::FETCH_ASSOC);

// Ventas por semana (últimas 8 semanas)
$ventas_semanales = $db->query("
    SELECT 
        EXTRACT(YEAR FROM fecha) AS año,
        EXTRACT(WEEK FROM fecha) AS semana,
        MIN(fecha) as fecha_inicio,
        MAX(fecha) as fecha_fin,
        SUM(total) AS total
    FROM pedidos
    WHERE fecha >= (NOW() - INTERVAL '8 weeks')
      AND estado != 'cancelado'
    GROUP BY año, semana
    ORDER BY año, semana DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Productos más vendidos
$productos_vendidos_sql = "
    SELECT p.nombre, SUM(pd.cantidad) as cantidad, SUM(pd.precio * pd.cantidad) as total 
    FROM pedido_detalles pd 
    JOIN productos p ON pd.id_producto = p.id_producto 
    JOIN pedidos pe ON pd.id_pedido = pe.id_pedido 
    WHERE $where_clause
    GROUP BY p.id_producto 
    ORDER BY cantidad DESC 
    LIMIT 10
";
$productos_vendidos = $db->prepare($productos_vendidos_sql);
$productos_vendidos->execute($params);

// Estado de pedidos
$estados_pedidos_sql = "
    SELECT estado, COUNT(*) as total 
    FROM pedidos pe
    LEFT JOIN pedido_detalles pd ON pe.id_pedido = pd.id_pedido
    WHERE $where_clause
    GROUP BY estado
";
$estados_pedidos = $db->prepare($estados_pedidos_sql);
$estados_pedidos->execute($params);
$distribucion_estados = $estados_pedidos->fetchAll(PDO::FETCH_ASSOC);

// Detalles de ventas para exportación
$detalles_ventas_sql = "
    SELECT 
        pe.id_pedido,
        pe.fecha,
        c.nombre as cliente,
        p.nombre as producto,
        pd.cantidad,
        pd.precio,
        (pd.cantidad * pd.precio) as total_linea,
        pe.estado
    FROM pedidos pe
    JOIN pedido_detalles pd ON pe.id_pedido = pd.id_pedido
    JOIN clientes c ON pe.id_cliente = c.id_cliente
    JOIN productos p ON pd.id_producto = p.id_producto
    WHERE $where_clause
    ORDER BY pe.fecha DESC
";
$detalles_ventas = $db->prepare($detalles_ventas_sql);
$detalles_ventas->execute($params);
$ventas_detalladas = $detalles_ventas->fetchAll(PDO::FETCH_ASSOC);

// Exportar a Excel
if ($exportar === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="reporte_ventas_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='7'>Reporte de Ventas - " . date('d/m/Y') . "</th></tr>";
    echo "<tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Total</th><th>Estado</th></tr>";
    
    foreach ($ventas_detalladas as $venta) {
        echo "<tr>";
        echo "<td>" . $venta['id_pedido'] . "</td>";
        echo "<td>" . $venta['fecha'] . "</td>";
        echo "<td>" . $venta['cliente'] . "</td>";
        echo "<td>" . $venta['producto'] . "</td>";
        echo "<td>" . $venta['cantidad'] . "</td>";
        echo "<td>$" . number_format($venta['precio'], 2) . "</td>";
        echo "<td>$" . number_format($venta['total_linea'], 2) . "</td>";
        echo "<td>" . ucfirst($venta['estado']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

// Exportar a PDF
if ($exportar === 'pdf') {
    require_once '../../includes/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('VMBol en Red');
    $pdf->SetAuthor('Sistema VMBol');
    $pdf->SetTitle('Reporte de Ventas');
    $pdf->AddPage();
    
    $html = "
        <h1>Reporte de Ventas</h1>
        <p><strong>Período:</strong> " . date('d/m/Y', strtotime($fecha_inicio)) . " - " . date('d/m/Y', strtotime($fecha_fin)) . "</p>
        <p><strong>Generado:</strong> " . date('d/m/Y H:i') . "</p>
        
        <table border='1' cellpadding='4'>
            <tr>
                <th>Pedido</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
                <th>Estado</th>
            </tr>
    ";
    
    foreach ($ventas_detalladas as $venta) {
        $html .= "
            <tr>
                <td>" . $venta['id_pedido'] . "</td>
                <td>" . date('d/m/Y', strtotime($venta['fecha'])) . "</td>
                <td>" . $venta['cliente'] . "</td>
                <td>" . $venta['producto'] . "</td>
                <td>" . $venta['cantidad'] . "</td>
                <td>$" . number_format($venta['precio'], 2) . "</td>
                <td>$" . number_format($venta['total_linea'], 2) . "</td>
                <td>" . ucfirst($venta['estado']) . "</td>
            </tr>
        ";
    }
    
    $html .= "</table>";
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('reporte_ventas_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - VMBol en Red</title>
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
                    <h1 class="h2">Reportes y Estadísticas</h1>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Imprimir
                        </button>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['exportar' => 'excel'])); ?>" class="btn btn-outline-success">
                            <i class="fas fa-file-excel me-1"></i> Exportar Excel
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['exportar' => 'pdf'])); ?>" class="btn btn-outline-danger">
                            <i class="fas fa-file-pdf me-1"></i> Exportar PDF
                        </a>
                    </div>
                </div>

                <!-- Filtros avanzados -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="producto_id" class="form-label">Producto</label>
                                <select class="form-control" id="producto_id" name="producto_id">
                                    <option value="">Todos los productos</option>
                                    <?php foreach ($productos as $producto): ?>
                                        <option value="<?php echo $producto['id_producto']; ?>" <?php echo $producto_id == $producto['id_producto'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($producto['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="cliente_id" class="form-label">Cliente</label>
                                <select class="form-control" id="cliente_id" name="cliente_id">
                                    <option value="">Todos los clientes</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?php echo $cliente['id_cliente']; ?>" <?php echo $cliente_id == $cliente['id_cliente'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cliente['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="tipo_reporte" class="form-label">Tipo Reporte</label>
                                <select class="form-control" id="tipo_reporte" name="tipo_reporte">
                                    <option value="ventas" <?php echo $tipo_reporte == 'ventas' ? 'selected' : ''; ?>>Ventas</option>
                                    <option value="productos" <?php echo $tipo_reporte == 'productos' ? 'selected' : ''; ?>>Productos</option>
                                    <option value="clientes" <?php echo $tipo_reporte == 'clientes' ? 'selected' : ''; ?>>Clientes</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i> Aplicar Filtros
                                    </button>
                                    <a href="reportes.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-refresh me-1"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Estadísticas principales -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4>$<?php echo number_format($total_ventas, 2); ?></h4>
                                        <p class="mb-0">Ventas Totales</p>
                                        <small>Período seleccionado</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-dollar-sign fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $total_pedidos; ?></h4>
                                        <p class="mb-0">Pedidos Activos</p>
                                        <small>Excluye cancelados</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $total_clientes; ?></h4>
                                        <p class="mb-0">Clientes Nuevos</p>
                                        <small>En el período</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4>
                                            <?php 
                                            $promedio_venta = $total_pedidos > 0 ? $total_ventas / $total_pedidos : 0;
                                            echo '$' . number_format($promedio_venta, 2);
                                            ?>
                                        </h4>
                                        <p class="mb-0">Ticket Promedio</p>
                                        <small>Por pedido</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-bar fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos principales -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Ventas Mensuales (Últimos 6 Meses)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="ventasChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Ventas Semanales (Últimas 8 Semanas)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="semanasChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos secundarios -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Distribución de Pedidos</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="estadosChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top Productos Más Vendidos</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="productosChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de productos más vendidos -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Detalle de Productos Más Vendidos</h5>
                        <small class="text-muted">Período: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad Vendida</th>
                                        <th>Total Vendido</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_general = 0;
                                    $productos_data = [];
                                    while ($producto = $productos_vendidos->fetch(PDO::FETCH_ASSOC)): 
                                        $total_general += $producto['total'];
                                        $productos_data[] = $producto;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                        <td><?php echo $producto['cantidad']; ?> unidades</td>
                                        <td>$<?php echo number_format($producto['total'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $porcentaje = $total_ventas > 0 ? ($producto['total'] / $total_ventas) * 100 : 0;
                                            echo number_format($porcentaje, 1) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if (empty($productos_data)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No hay datos para el período seleccionado</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Gráfico de ventas mensuales
        const ventasCtx = document.getElementById('ventasChart').getContext('2d');
        const ventasChart = new Chart(ventasCtx, {
            type: 'line',
            data: {
                labels: [<?php 
                    $meses = [];
                    foreach ($ventas_mensuales as $venta) {
                        $meses[] = "'" . date('M Y', mktime(0, 0, 0, $venta['mes'], 1, $venta['año'])) . "'";
                    }
                    echo implode(', ', $meses);
                ?>],
                datasets: [{
                    label: 'Ventas Mensuales',
                    data: [<?php echo implode(', ', array_column($ventas_mensuales, 'total')); ?>],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de ventas semanales
        const semanasCtx = document.getElementById('semanasChart').getContext('2d');
        const semanasChart = new Chart(semanasCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $semanas_labels = [];
                    foreach ($ventas_semanales as $semana) {
                        $semanas_labels[] = "'Sem " . $semana['semana'] . "'";
                    }
                    echo implode(', ', array_reverse($semanas_labels));
                ?>],
                datasets: [{
                    label: 'Ventas por Semana',
                    data: [<?php 
                        $semanas_data = array_column($ventas_semanales, 'total');
                        echo implode(', ', array_reverse($semanas_data));
                    ?>],
                    backgroundColor: 'rgba(46, 204, 113, 0.8)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de estados de pedidos
        const estadosCtx = document.getElementById('estadosChart').getContext('2d');
        const estadosChart = new Chart(estadosCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php 
                    $labels = [];
                    foreach ($distribucion_estados as $estado) {
                        $labels[] = "'" . ucfirst($estado['estado']) . "'";
                    }
                    echo implode(', ', $labels);
                ?>],
                datasets: [{
                    data: [<?php echo implode(', ', array_column($distribucion_estados, 'total')); ?>],
                    backgroundColor: ['#f39c12', '#3498db', '#27ae60', '#e74c3c', '#9b59b6']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de productos más vendidos
        const productosCtx = document.getElementById('productosChart').getContext('2d');
        const productosChart = new Chart(productosCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $productos_labels = [];
                    foreach ($productos_data as $prod) {
                        $nombre = htmlspecialchars($prod['nombre']);
                        $productos_labels[] = "'" . (strlen($nombre) > 15 ? substr($nombre, 0, 15) . '...' : $nombre) . "'";
                    }
                    echo implode(', ', $productos_labels);
                ?>],
                datasets: [{
                    label: 'Ventas en $',
                    data: [<?php echo implode(', ', array_column($productos_data, 'total')); ?>],
                    backgroundColor: 'rgba(155, 89, 182, 0.8)',
                    borderColor: 'rgba(155, 89, 182, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Auto-submit cuando cambian filtros importantes
        document.getElementById('fecha_inicio').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('fecha_fin').addEventListener('change', function() {
            this.form.submit();
        });
    </script>

    <style>
    @media print {
        .btn-group, .card-header .btn, .card .d-flex.justify-content-between {
            display: none !important;
        }
        
        .card {
            border: 1px solid #000 !important;
            margin-bottom: 20px;
        }
        
        .table {
            font-size: 12px;
        }
    }
    </style>
</body>
</html>