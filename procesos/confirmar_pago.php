<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

try { Auth::checkAuth('admin'); } catch (Exception $e) { header('Location: ../paginas/public/login.php'); exit; }
$db = (new Database())->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/admin/pagos.php');
    exit;
}

$id_pago = isset($_POST['id_pago']) ? (int)$_POST['id_pago'] : 0;
$id_pedido = isset($_POST['id_pedido']) ? (int)$_POST['id_pedido'] : 0;

if ($id_pago <= 0 || $id_pedido <= 0) {
    header('Location: ../paginas/admin/pagos.php');
    exit;
}

try {
    $db->beginTransaction();

    // marcar pago como confirmado
    $stmt = $db->prepare("UPDATE pagos SET estado = 'confirmado' WHERE id_pago = ?");
    $stmt->execute([$id_pago]);

    // marcar pedido como pagado
    $stmt2 = $db->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id_pedido = ?");
    $stmt2->execute([$id_pedido]);

    // opcional: registrar auditoria si la tabla existe
    try {
        $aud = $db->prepare("INSERT INTO auditoria (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, tipo_usuario) VALUES ('pagos', ?, 'UPDATE', ?, ?, 'admin')");
        $aud->execute([$id_pago, 'Pago confirmado por admin', $_SESSION['user_id']]);
    } catch (Exception $e) { /* Ignorar */ }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
}

header('Location: ../paginas/admin/pagos.php');
exit;
?>