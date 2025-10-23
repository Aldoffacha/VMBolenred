<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();
if ($_SERVER['REQUEST_METHOD'] == 'POST' && Funciones::isFormSubmitted('actualizar_perfil')) 
    $nombre = Funciones::getInput('nombre');
    $telefono = Funciones::getInput('telefono');
    $direccion = Funciones::getInput('direccion');
// Obtener datos del cliente
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt->execute([$user_id]);
$cliente = $stmt->fetch();

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && Funciones::isFormSubmitted('actualizar_perfil')) {
    $nombre = Funciones::getInput('nombre');
    $telefono = Funciones::getInput('telefono');
    $direccion = Funciones::getInput('direccion');}     
    
// Actualizar perfil - CORREGIDO: verificar si existe primero
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_perfil'])) {
    $nombre = htmlspecialchars($_POST['nombre'] ?? '');
    $telefono = htmlspecialchars($_POST['telefono'] ?? '');
    $direccion = htmlspecialchars($_POST['direccion'] ?? '');
    
    // Validar que el nombre no esté vacío
    if (empty($nombre)) {
        $mensaje = "❌ El nombre es obligatorio";
    } else {
        $stmt = $db->prepare("UPDATE clientes SET nombre = ?, telefono = ?, direccion = ? WHERE id_cliente = ?");
        if ($stmt->execute([$nombre, $telefono, $direccion, $user_id])) {
            $_SESSION['nombre'] = $nombre;
            $cliente['nombre'] = $nombre;
            $cliente['telefono'] = $telefono;
            $cliente['direccion'] = $direccion;
            $mensaje = "✅ Perfil actualizado correctamente";
        } else {
            $mensaje = "❌ Error al actualizar el perfil";
        }
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Mi Perfil"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>👤 Mi Perfil</h2>
            </div>

            <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo strpos($mensaje, '✅') !== false ? 'success' : 'danger'; ?>">
                <?php echo $mensaje; ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <img src="https://via.placeholder.com/150" class="rounded-circle mb-3" alt="Avatar">
                            <h5><?php echo htmlspecialchars($cliente['nombre']); ?></h5>
                            <p class="text-muted">Cliente desde: <?php echo date('M Y', strtotime($cliente['fecha_registro'])); ?></p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary btn-sm">📷 Cambiar Foto</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Estadísticas rápidas -->
                    <div class="card mt-3">
                        <div class="card-body">
                            <h6>📊 Mis Estadísticas</h6>
                            <div class="small">
                                <div class="d-flex justify-content-between">
                                    <span>Pedidos realizados:</span>
                                    <strong>5</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Cotizaciones:</span>
                                    <strong>3</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Miembro desde:</span>
                                    <strong><?php echo date('M Y', strtotime($cliente['fecha_registro'])); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Información Personal</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre Completo *</label>
                                            <input type="text" name="nombre" class="form-control" 
                                                   value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Correo Electrónico</label>
                                            <input type="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($cliente['correo']); ?>" disabled>
                                            <small class="text-muted">El correo no puede ser modificado</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Teléfono</label>
                                            <input type="tel" name="telefono" class="form-control" 
                                                   value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>"
                                                   placeholder="Ej: 77712345">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha de Registro</label>
                                            <input type="text" class="form-control" 
                                                   value="<?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Dirección</label>
                                    <textarea name="direccion" class="form-control" rows="3" 
                                              placeholder="Dirección para envíos"><?php echo htmlspecialchars($cliente['direccion'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="actualizar_perfil" class="btn btn-primary">
                                        💾 Guardar Cambios
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        ← Volver al Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Cambio de contraseña -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">🔒 Cambio de Contraseña</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="formPassword">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nueva Contraseña</label>
                                            <input type="password" name="nueva_password" class="form-control" 
                                                   minlength="6" placeholder="Mínimo 6 caracteres">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Confirmar Contraseña</label>
                                            <input type="password" name="confirmar_password" class="form-control" 
                                                   placeholder="Repite la contraseña">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="cambiar_password" class="btn btn-warning">
                                    🔑 Cambiar Contraseña
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Validación de contraseñas en tiempo real
document.getElementById('formPassword')?.addEventListener('input', function(e) {
    const nueva = document.querySelector('input[name="nueva_password"]').value;
    const confirmar = document.querySelector('input[name="confirmar_password"]').value;
    
    if (confirmar.length > 0) {
        if (nueva === confirmar) {
            document.querySelector('input[name="confirmar_password"]').classList.remove('is-invalid');
            document.querySelector('input[name="confirmar_password"]').classList.add('is-valid');
        } else {
            document.querySelector('input[name="confirmar_password"]').classList.remove('is-valid');
            document.querySelector('input[name="confirmar_password"]').classList.add('is-invalid');
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>