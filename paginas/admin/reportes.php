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

// Estadísticas generales (excluyendo cancelados)
$ventas_totales = $db->prepare("SELECT SUM(total) as total FROM pedidos WHERE fecha BETWEEN ? AND ? AND estado != 'cancelado'");
$ventas_totales->execute([$fecha_inicio, $fecha_fin]);
$total_ventas = $ventas_totales->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$pedidos_totales = $db->prepare("SELECT COUNT(*) as total FROM pedidos WHERE fecha BETWEEN ? AND ? AND estado != 'cancelado'");
$pedidos_totales->execute([$fecha_inicio, $fecha_fin]);
$total_pedidos = $pedidos_totales->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$clientes_nuevos = $db->prepare("SELECT COUNT(*) as total FROM clientes WHERE fecha_registro BETWEEN ? AND ?");
$clientes_nuevos->execute([$fecha_inicio, $fecha_fin]);
$total_clientes = $clientes_nuevos->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Ventas por mes para el gráfico 
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
// Productos más vendidos 
$productos_vendidos = $db->prepare("
    SELECT p.nombre, SUM(pd.cantidad) as cantidad, SUM(pd.precio * pd.cantidad) as total 
    FROM pedido_detalles pd 
    JOIN productos p ON pd.id_producto = p.id_producto 
    JOIN pedidos pe ON pd.id_pedido = pe.id_pedido 
    WHERE pe.fecha BETWEEN ? AND ? 
    AND pe.estado != 'cancelado'
    GROUP BY p.id_producto 
    ORDER BY cantidad DESC 
    LIMIT 10
");
$productos_vendidos->execute([$fecha_inicio, $fecha_fin]);

//estado de pedidos
$estados_pedidos = $db->prepare("
    SELECT estado, COUNT(*) as total 
    FROM pedidos 
    WHERE fecha BETWEEN ? AND ? 
    AND estado != 'cancelado'
    GROUP BY estado
");
$estados_pedidos->execute([$fecha_inicio, $fecha_fin]);
$distribucion_estados = $estados_pedidos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - VMBol en Red</title>
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
                    <h1 class="h2">Reportes y Estadísticas</h1>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Imprimir
                        </button>
                        <button class="btn btn-outline-success" onclick="exportarExcel()">
                            <i class="fas fa-file-excel me-1"></i> Exportar Excel
                        </button>
                    </div>
                </div>

                
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
                            <div class="col-md-3">
                                <label for="tipo_reporte" class="form-label">Tipo de Reporte</label>
                                <select class="form-control" id="tipo_reporte" name="tipo_reporte">
                                    <option value="ventas" <?php echo $tipo_reporte == 'ventas' ? 'selected' : ''; ?>>Ventas</option>
                                    <option value="productos" <?php echo $tipo_reporte == 'productos' ? 'selected' : ''; ?>>Productos</option>
                                    <option value="clientes" <?php echo $tipo_reporte == 'clientes' ? 'selected' : ''; ?>>Clientes</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4>$<?php echo number_format($total_ventas, 2); ?></h4>
                                        <p class="mb-0">Ventas Totales</p>
                                        <small>Excluye cancelados</small>
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
                                            $pedidos_cancelados = $db->prepare("SELECT COUNT(*) as total FROM pedidos WHERE fecha BETWEEN ? AND ? AND estado = 'cancelado'");
                                            $pedidos_cancelados->execute([$fecha_inicio, $fecha_fin]);
                                            echo $pedidos_cancelados->fetch(PDO::FETCH_ASSOC)['total'];
                                            ?>
                                        </h4>
                                        <p class="mb-0">Pedidos Cancelados</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Ventas de los Últimos 6 Meses</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="ventasChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Distribución de Pedidos</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="estadosChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Productos Más Vendidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad Vendida</th>
                                        <th>Total Vendido</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($producto = $productos_vendidos->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                        <td><?php echo $producto['cantidad']; ?> unidades</td>
                                        <td>$<?php echo number_format($producto['total'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
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
        // Gráfico de ventas
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
                    backgroundColor: ['#f39c12', '#3498db', '#27ae60', '#e74c3c']
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

        function exportarExcel() {
            // Simulación de exportación a Excel
            alert('Funcionalidad de exportación a Excel. En una implementación real, esto generaría un archivo Excel.');
        }
    </script>
</body>
</html>