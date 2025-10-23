<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id_cotizacion'])) {
    echo json_encode(['success' => false, 'message' => 'ID no especificado']);
    exit;
}

$id_cotizacion = intval($_GET['id_cotizacion']);

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE id_cotizacion = ?");
    $stmt->execute([$id_cotizacion]);
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cotizacion) {
        echo json_encode(['success' => false, 'message' => 'Cotización no encontrada']);
        exit;
    }
    
    $html = "
        <h6>{$cotizacion['nombre_producto']}</h6>
        <div class='row'>
            <div class='col-6'><small>Precio producto:</small><br><strong>$" . number_format($cotizacion['precio_base'], 2) . "</strong></div>
            <div class='col-6'><small>Peso:</small><br><strong>{$cotizacion['peso']} kg</strong></div>
        </div>
        <div class='row mt-2'>
            <div class='col-6'><small>Flete:</small><br><strong>$" . number_format($cotizacion['costo_flete'], 2) . "</strong></div>
            <div class='col-6'><small>Seguro (2%):</small><br><strong>$" . number_format($cotizacion['costo_seguro'], 2) . "</strong></div>
        </div>
        <div class='row mt-2'>
            <div class='col-6'><small>Aduana:</small><br><strong>$" . number_format($cotizacion['costo_aduana'], 2) . "</strong></div>
            <div class='col-6'><small>Almacenaje:</small><br><strong>$" . number_format($cotizacion['costo_almacen'], 2) . "</strong></div>
        </div>
        <hr>
        <div class='row mt-2'>
            <div class='col-12'><h5 class='text-success'>TOTAL: $" . number_format($cotizacion['costo_total'], 2) . "</h5></div>
        </div>
    ";
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>