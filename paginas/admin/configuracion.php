<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/auditoria.class.php'; // <-- AÑADIR ESTA LÍNEA

try {
    Auth::checkAuth('admin');
} catch (Exception $e) {
    header('Location: ../public/login.php');
    exit;
}

$db = (new Database())->getConnection();
$auditoria = new Auditoria($db); // <-- AÑADIR ESTA LÍNEA

// Obtener información del admin para auditoría
$admin_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0;
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

// Determinar el tipo de equipo
$equipo = "Desconocido";
if (preg_match('/Windows/i', $user_agent)) {
    $equipo = "Windows PC";
} elseif (preg_match('/Macintosh|Mac OS/i', $user_agent)) {
    $equipo = "Mac";
} elseif (preg_match('/Linux/i', $user_agent)) {
    $equipo = "Linux";
} elseif (preg_match('/Android/i', $user_agent)) {
    $equipo = "Android";
} elseif (preg_match('/iPhone|iPad/i', $user_agent)) {
    $equipo = "iOS";
}

// Detectar navegador
$navegador = "Desconocido";
if (preg_match('/Chrome/i', $user_agent) && !preg_match('/Edg/i', $user_agent)) {
    $navegador = "Chrome";
} elseif (preg_match('/Firefox/i', $user_agent)) {
    $navegador = "Firefox";
} elseif (preg_match('/Safari/i', $user_agent) && !preg_match('/Chrome/i', $user_agent)) {
    $navegador = "Safari";
} elseif (preg_match('/Edg/i', $user_agent)) {
    $navegador = "Edge";
}

$info_equipo = "$equipo - $navegador";

// Obtener configuración actual
$configStmt = $db->query("SELECT * FROM configuracion WHERE id = 1");
$config = $configStmt->fetch(PDO::FETCH_ASSOC);

// Si no existe configuración, crear una por defecto
if (!$config) {
    $db->query("INSERT INTO configuracion (id, nombre_empresa, email_contacto, telefono_contacto, moneda) 
                VALUES (1, 'VMBol en Red', 'info@vmbol.com', '+591 777 12345', 'USD')");
    $configStmt = $db->query("SELECT * FROM configuracion WHERE id = 1");
    $config = $configStmt->fetch(PDO::FETCH_ASSOC);
}

// Procesar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'guardar_general':
                $nombre_empresa = $_POST['nombre_empresa'];
                $email_contacto = $_POST['email_contacto'];
                $telefono_contacto = $_POST['telefono_contacto'];
                $moneda = $_POST['moneda'];
                
                // Obtener datos anteriores para auditoría
                $datos_anteriores = [
                    'nombre_empresa' => $config['nombre_empresa'],
                    'email_contacto' => $config['email_contacto'],
                    'telefono_contacto' => $config['telefono_contacto'],
                    'moneda' => $config['moneda']
                ];
                
                $stmt = $db->prepare("UPDATE configuracion SET 
                                    nombre_empresa = ?, 
                                    email_contacto = ?, 
                                    telefono_contacto = ?, 
                                    moneda = ?,
                                    fecha_actualizacion = NOW() 
                                    WHERE id = 1");
                $stmt->execute([$nombre_empresa, $email_contacto, $telefono_contacto, $moneda]);
                $_SESSION['mensaje'] = 'Configuración general actualizada correctamente';
                
                // Auditoría: Configuración general actualizada
                $datos_nuevos = [
                    'nombre_empresa' => $nombre_empresa,
                    'email_contacto' => $email_contacto,
                    'telefono_contacto' => $telefono_contacto,
                    'moneda' => $moneda,
                    'admin_id' => $admin_id,
                    'ip_address' => $ip_address,
                    'equipo' => $info_equipo,
                    'fecha_cambio' => date('Y-m-d H:i:s')
                ];
                
                try {
                    $query = "INSERT INTO auditoria 
                             (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, tipo_usuario, ip_address) 
                             VALUES (:tabla, :id_registro, :accion, :datos_anteriores, :datos_nuevos, :id_usuario, :tipo_usuario, :ip_address)";
                    
                    $stmt_audit = $db->prepare($query);
                    $stmt_audit->execute([
                        ':tabla' => 'configuracion',
                        ':id_registro' => 1,
                        ':accion' => 'UPDATE',
                        ':datos_anteriores' => json_encode($datos_anteriores, JSON_PRETTY_PRINT),
                        ':datos_nuevos' => json_encode($datos_nuevos, JSON_PRETTY_PRINT),
                        ':id_usuario' => $admin_id,
                        ':tipo_usuario' => 'admin',
                        ':ip_address' => $ip_address
                    ]);
                    
                    error_log("✅ Auditoría: Configuración general actualizada por admin ID: $admin_id");
                    
                } catch (PDOException $e) {
                    error_log("❌ Error en auditoría de configuración: " . $e->getMessage());
                }
                
                // Actualizar variable local
                $config['nombre_empresa'] = $nombre_empresa;
                $config['email_contacto'] = $email_contacto;
                $config['telefono_contacto'] = $telefono_contacto;
                $config['moneda'] = $moneda;
                break;
                
            case 'agregar_deposito':
                $nombre = $_POST['nombre_deposito'];
                $direccion = $_POST['direccion'];
                $telefono = $_POST['telefono'];
                $contacto = $_POST['contacto'];
                
                $stmt = $db->prepare("INSERT INTO depositos_miami (nombre_deposito, direccion, telefono, contacto) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $direccion, $telefono, $contacto]);
                $id_deposito = $db->lastInsertId();
                
                $_SESSION['mensaje'] = 'Depósito agregado correctamente';
                
                // Auditoría: Depósito agregado
                $datos_deposito = [
                    'id_deposito' => $id_deposito,
                    'nombre_deposito' => $nombre,
                    'direccion' => $direccion,
                    'telefono' => $telefono,
                    'contacto' => $contacto,
                    'admin_id' => $admin_id,
                    'ip_address' => $ip_address,
                    'equipo' => $info_equipo,
                    'fecha_creacion' => date('Y-m-d H:i:s')
                ];
                
                try {
                    $query = "INSERT INTO auditoria 
                             (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, tipo_usuario, ip_address) 
                             VALUES (:tabla, :id_registro, :accion, :datos_nuevos, :id_usuario, :tipo_usuario, :ip_address)";
                    
                    $stmt_audit = $db->prepare($query);
                    $stmt_audit->execute([
                        ':tabla' => 'depositos_miami',
                        ':id_registro' => $id_deposito,
                        ':accion' => 'INSERT',
                        ':datos_nuevos' => json_encode($datos_deposito, JSON_PRETTY_PRINT),
                        ':id_usuario' => $admin_id,
                        ':tipo_usuario' => 'admin',
                        ':ip_address' => $ip_address
                    ]);
                    
                    error_log("✅ Auditoría: Depósito agregado por admin ID: $admin_id");
                    
                } catch (PDOException $e) {
                    error_log("❌ Error en auditoría de depósito: " . $e->getMessage());
                }
                break;
                
            case 'agregar_tienda':
                $nombre = $_POST['nombre_tienda'];
                $url = $_POST['url_tienda'];
                $tipo = $_POST['tipo'];
                $api_key = $_POST['api_key'];
                
                $stmt = $db->prepare("INSERT INTO tiendas_usa (nombre_tienda, url_tienda, tipo, api_key) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $url, $tipo, $api_key]);
                $id_tienda = $db->lastInsertId();
                
                $_SESSION['mensaje'] = 'Tienda agregada correctamente';
                
                // Auditoría: Tienda agregada
                $datos_tienda = [
                    'id_tienda' => $id_tienda,
                    'nombre_tienda' => $nombre,
                    'url_tienda' => $url,
                    'tipo' => $tipo,
                    'api_key' => $api_key ? '***' : 'No configurada', // Por seguridad, no guardar API keys reales
                    'admin_id' => $admin_id,
                    'ip_address' => $ip_address,
                    'equipo' => $info_equipo,
                    'fecha_creacion' => date('Y-m-d H:i:s')
                ];
                
                try {
                    $query = "INSERT INTO auditoria 
                             (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, tipo_usuario, ip_address) 
                             VALUES (:tabla, :id_registro, :accion, :datos_nuevos, :id_usuario, :tipo_usuario, :ip_address)";
                    
                    $stmt_audit = $db->prepare($query);
                    $stmt_audit->execute([
                        ':tabla' => 'tiendas_usa',
                        ':id_registro' => $id_tienda,
                        ':accion' => 'INSERT',
                        ':datos_nuevos' => json_encode($datos_tienda, JSON_PRETTY_PRINT),
                        ':id_usuario' => $admin_id,
                        ':tipo_usuario' => 'admin',
                        ':ip_address' => $ip_address
                    ]);
                    
                    error_log("✅ Auditoría: Tienda agregada por admin ID: $admin_id");
                    
                } catch (PDOException $e) {
                    error_log("❌ Error en auditoría de tienda: " . $e->getMessage());
                }
                break;
        }
    }
    header('Location: configuracion.php');
    exit;
}

// Obtener datos existentes
$depositos = $db->query("SELECT * FROM depositos_miami WHERE estado = 1")->fetchAll(PDO::FETCH_ASSOC);
$tiendas = $db->query("SELECT * FROM tiendas_usa WHERE estado = 1")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - VMBol en Red</title>
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
                    <h1 class="h2">Configuración del Sistema</h1>
                </div>

                <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Configuración General -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Configuración General</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="guardar_general">
                                    
                                    <div class="mb-3">
                                        <label for="nombre_empresa" class="form-label">Nombre de la Empresa</label>
                                        <input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa" 
                                               value="<?php echo htmlspecialchars($config['nombre_empresa'] ?? 'VMBol en Red'); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email_contacto" class="form-label">Email de Contacto</label>
                                        <input type="email" class="form-control" id="email_contacto" name="email_contacto" 
                                               value="<?php echo htmlspecialchars($config['email_contacto'] ?? 'info@vmbol.com'); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="telefono_contacto" class="form-label">Teléfono de Contacto</label>
                                        <input type="text" class="form-control" id="telefono_contacto" name="telefono_contacto" 
                                               value="<?php echo htmlspecialchars($config['telefono_contacto'] ?? '+591 777 12345'); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="moneda" class="form-label">Moneda Principal</label>
                                        <select class="form-control" id="moneda" name="moneda" required>
                                            <option value="USD" <?php echo ($config['moneda'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>Dólar Americano (USD)</option>
                                            <option value="BOB" <?php echo ($config['moneda'] ?? 'USD') === 'BOB' ? 'selected' : ''; ?>>Boliviano (BOB)</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Gestión de Depósitos -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Depósitos en Miami</h5>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalDeposito">
                                    <i class="fas fa-plus me-1"></i> Agregar
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Contacto</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($depositos as $deposito): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($deposito['nombre_deposito']); ?></td>
                                                <td><?php echo htmlspecialchars($deposito['contacto']); ?></td>
                                                <td><span class="badge bg-success">Activo</span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Gestión de Tiendas -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Tiendas USA Configuradas</h5>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTienda">
                                    <i class="fas fa-plus me-1"></i> Agregar
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Tienda</th>
                                                <th>Tipo</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tiendas as $tienda): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tienda['nombre_tienda']); ?></td>
                                                <td><span class="badge bg-info"><?php echo ucfirst($tienda['tipo']); ?></span></td>
                                                <td><span class="badge bg-success">Activo</span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Depósito -->
    <div class="modal fade" id="modalDeposito" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Depósito en Miami</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar_deposito">
                        
                        <div class="mb-3">
                            <label for="nombre_deposito" class="form-label">Nombre del Depósito</label>
                            <input type="text" class="form-control" id="nombre_deposito" name="nombre_deposito" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="2" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                        
                        <div class="mb-3">
                            <label for="contacto" class="form-label">Persona de Contacto</label>
                            <input type="text" class="form-control" id="contacto" name="contacto">
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

    <!-- Modal Tienda -->
    <div class="modal fade" id="modalTienda" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Tienda USA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar_tienda">
                        
                        <div class="mb-3">
                            <label for="nombre_tienda" class="form-label">Nombre de la Tienda</label>
                            <input type="text" class="form-control" id="nombre_tienda" name="nombre_tienda" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="url_tienda" class="form-label">URL de la Tienda</label>
                            <input type="url" class="form-control" id="url_tienda" name="url_tienda">
                        </div>
                        
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo de Tienda</label>
                            <select class="form-control" id="tipo" name="tipo" required>
                                <option value="amazon">Amazon</option>
                                <option value="ebay">eBay</option>
                                <option value="alibaba">Alibaba</option>
                                <option value="walmart">Walmart</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="api_key" class="form-label">API Key (opcional)</label>
                            <input type="text" class="form-control" id="api_key" name="api_key" placeholder="Clave API para integración">
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
</body>
</html>