<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];

// Obtener datos del cliente
$stmt = $db->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt->execute([$user_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

$mensaje = '';

// --- Actualizar perfil ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $nombre = htmlspecialchars(trim($_POST['nombre'] ?? ''));
    $telefono = htmlspecialchars(trim($_POST['telefono'] ?? ''));
    $direccion = htmlspecialchars(trim($_POST['direccion'] ?? ''));

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

// --- Cambio de foto de perfil ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_foto'])) {
    // Verificar si se subió un archivo
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_perfil'];
        
        // Obtener extensión del archivo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validar tipo de archivo por extensión (solo imágenes)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($extension, $allowedExtensions)) {
            $mensaje = "❌ Solo se permiten archivos de imagen (JPEG, PNG, GIF).";
        } else {
            // Validar tamaño (máximo 2MB)
            $maxSize = 2 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $mensaje = "❌ El archivo es demasiado grande. Máximo 2MB.";
            } else {
                // Validación adicional: intentar abrir como imagen
                $imageInfo = @getimagesize($file['tmp_name']);
                if ($imageInfo === false) {
                    $mensaje = "❌ El archivo no es una imagen válida.";
                } else {
                    // Generar un nombre único para el archivo
                    $filename = 'cliente_' . $user_id . '_' . time() . '.' . $extension;
                    $uploadPath = '../../assets/img/logos/' . $filename;

                    // Crear directorio si no existe
                    if (!is_dir('../../assets/img/logos/')) {
                        mkdir('../../assets/img/logos/', 0755, true);
                    }

                    // Mover el archivo a la carpeta de destino
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        // Si ya existe una foto anterior, eliminarla
                        if (!empty($cliente['foto_perfil']) && file_exists('../../assets/img/logos/' . $cliente['foto_perfil'])) {
                            unlink('../../assets/img/logos/' . $cliente['foto_perfil']);
                        }
                        
                        // Actualizar la base de datos PostgreSQL
                        $stmt = $db->prepare("UPDATE clientes SET foto_perfil = ? WHERE id_cliente = ?");
                        if ($stmt->execute([$filename, $user_id])) {
                            $cliente['foto_perfil'] = $filename;
                            $mensaje = "✅ Foto de perfil actualizada correctamente";
                        } else {
                            $mensaje = "❌ Error al actualizar la foto en la base de datos";
                            // Mostrar error de PostgreSQL
                            $errorInfo = $stmt->errorInfo();
                            $mensaje .= "<br>Error: " . $errorInfo[2];
                        }
                    } else {
                        $mensaje = "❌ Error al subir el archivo. Verifica los permisos de la carpeta.";
                    }
                }
            }
        }
    } else {
        $error = $_FILES['foto_perfil']['error'] ?? 'Unknown';
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño permitido por el formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo solo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'No existe directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo'
        ];
        $errorText = $errorMessages[$error] ?? 'Error desconocido';
        $mensaje = "❌ Error al subir el archivo: " . $errorText;
    }
}
// --- Cambio de contraseña ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $nueva = trim($_POST['nueva_password'] ?? '');
    $confirmar = trim($_POST['confirmar_password'] ?? '');

    if (empty($nueva) || empty($confirmar)) {
        $mensaje = "⚠️ Debes llenar ambos campos de contraseña";
    } elseif ($nueva !== $confirmar) {
        $mensaje = "❌ Las contraseñas no coinciden";
    } elseif (strlen($nueva) < 6) {
        $mensaje = "⚠️ La contraseña debe tener al menos 6 caracteres";
    } else {
        $hash = password_hash($nueva, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE clientes SET contrasena = ? WHERE id_cliente = ?");
        if ($stmt->execute([$hash, $user_id])) {
            $mensaje = "✅ Contraseña actualizada correctamente";
        } else {
            $mensaje = "❌ Error al actualizar la contraseña";
            $errorInfo = $stmt->errorInfo();
            $mensaje .= "<br>Error: " . $errorInfo[2];
        }
    }
}

// Obtener la ruta de la foto de perfil
$foto_perfil_url = 'https://via.placeholder.com/150';
if (!empty($cliente['foto_perfil']) && file_exists('../../assets/img/logos/' . $cliente['foto_perfil'])) {
    $foto_perfil_url = '../../assets/img/logos/' . $cliente['foto_perfil'] . '?t=' . time();
} elseif (!empty($cliente['foto_perfil'])) {
    // Si existe en la BD pero no en el filesystem, usar placeholder
    $foto_perfil_url = 'https://via.placeholder.com/150';
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
            <div class="alert alert-<?php echo str_contains($mensaje, '✅') ? 'success' : (str_contains($mensaje, '⚠️') ? 'warning' : 'danger'); ?> alert-dismissible fade show">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Panel lateral -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <form method="POST" enctype="multipart/form-data" id="formFoto">
                                <div class="position-relative d-inline-block">
                                    <img id="foto-perfil-actual" src="<?php echo $foto_perfil_url; ?>" 
                                         class="rounded-circle mb-3" alt="Avatar" 
                                         style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #dee2e6;">
                                    <div class="position-absolute bottom-0 end-0">
                                        <label for="inputFoto" class="btn btn-primary btn-sm rounded-circle cursor-pointer shadow">
                                            <i class="fas fa-camera"></i>
                                        </label>
                                    </div>
                                </div>
                                <h5><?php echo htmlspecialchars($cliente['nombre']); ?></h5>
                                <p class="text-muted">Cliente desde: <?php echo date('M Y', strtotime($cliente['fecha_registro'])); ?></p>
                                
                                <input type="file" name="foto_perfil" accept="image/*" style="display: none;" id="inputFoto">
                                <div class="d-grid gap-2 mt-3">
                                    <button type="submit" name="cambiar_foto" class="btn btn-success btn-sm" style="display: none;" id="btnSubirFoto">
                                        📤 Subir Foto
                                    </button>
                                    <small class="text-muted">Formatos: JPG, PNG, GIF (Máx. 2MB)</small>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-body">
                            <h6>📊 Mis Estadísticas</h6>
                            <div class="small">
                                <div class="d-flex justify-content-between">
                                    <span>Pedidos realizados:</span><strong>5</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Cotizaciones:</span><strong>3</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Miembro desde:</span>
                                    <strong><?php echo date('M Y', strtotime($cliente['fecha_registro'])); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario principal -->
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
                                            <input type="password" name="nueva_password" class="form-control" minlength="6" placeholder="Mínimo 6 caracteres">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Confirmar Contraseña</label>
                                            <input type="password" name="confirmar_password" class="form-control" placeholder="Repite la contraseña">
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
// Manejo de la foto de perfil
document.getElementById('inputFoto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    // Validar tipo de archivo por extensión
    const fileName = file.name.toLowerCase();
    const validExtensions = ['.jpg', '.jpeg', '.png', '.gif'];
    const hasValidExtension = validExtensions.some(ext => fileName.endsWith(ext));
    
    if (!hasValidExtension) {
        alert('❌ Solo se permiten archivos JPG, PNG y GIF');
        this.value = ''; // Limpiar el input
        return;
    }

    // Validar tamaño (2MB)
    if (file.size > 2 * 1024 * 1024) {
        alert('❌ El archivo es demasiado grande (máximo 2MB)');
        this.value = ''; // Limpiar el input
        return;
    }

    // Mostrar preview
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('foto-perfil-actual').src = e.target.result;
    };
    reader.readAsDataURL(file);

    // Mostrar botón de subir
    document.getElementById('btnSubirFoto').style.display = 'block';
});

// Validación de contraseñas
document.getElementById('formPassword')?.addEventListener('input', function() {
    const nueva = document.querySelector('input[name="nueva_password"]').value;
    const confirmar = document.querySelector('input[name="confirmar_password"]').value;

    if (confirmar.length > 0) {
        const input = document.querySelector('input[name="confirmar_password"]');
        if (nueva === confirmar) {
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
        } else {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
        }
    }
});

// Prevenir envío del formulario de foto si no hay archivo
document.getElementById('formFoto').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('inputFoto');
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('❌ Por favor selecciona una foto primero');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>