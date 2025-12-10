<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

try {
    Auth::checkAuth('cliente');
} catch (Exception $e) {
    header('Location: ../paginas/public/login.php');
    exit;
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../paginas/cliente/pedidos.php');
    exit;
}

$id_pago = isset($_POST['id_pago']) ? (int)$_POST['id_pago'] : 0;
$id_pedido = isset($_POST['id_pedido']) ? (int)$_POST['id_pedido'] : 0;

if ($id_pago <= 0 || $id_pedido <= 0) {
    header('Location: ../paginas/cliente/pedidos.php');
    exit;
}

// Verify payment belongs to the customer's order
$stmt = $db->prepare("SELECT p.* FROM pagos p JOIN pedidos pe ON p.id_pedido = pe.id_pedido WHERE p.id_pago = ? AND pe.id_cliente = ?");
$stmt->execute([$id_pago, $user_id]);
$pago = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pago) {
    header('Location: ../paginas/cliente/pedidos.php');
    exit;
}

if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../paginas/cliente/confirmacion_pago.php?id_pedido=' . $id_pedido);
    exit;
}

$file = $_FILES['comprobante'];
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowed)) {
    header('Location: ../paginas/cliente/confirmacion_pago.php?id_pedido=' . $id_pedido);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'comprobante_' . $id_pago . '_' . time() . '.' . $ext;
$target = __DIR__ . '/../uploads/payments/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    header('Location: ../paginas/cliente/confirmacion_pago.php?id_pedido=' . $id_pedido);
    exit;
}

// Update pagos record
$update = $db->prepare("UPDATE pagos SET comprobante = ?, estado = 'subido' WHERE id_pago = ?");
$update->execute([$filename, $id_pago]);

// Redirect back to confirmation
header('Location: ../paginas/cliente/confirmacion_pago.php?id_pedido=' . $id_pedido);
exit;
?>