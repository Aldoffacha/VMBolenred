<?php
// En tu footer.php, obtén los datos de configuración
require_once '../../includes/database.php';
$db = (new Database())->getConnection();
$configStmt = $db->query("SELECT * FROM configuracion WHERE id = 1");
$config = $configStmt->fetch(PDO::FETCH_ASSOC);
?>

</div><!-- Cierra row -->
</div><!-- Cierra container-fluid -->

<!-- Footer siempre abajo -->
<footer class="footer bg-light mt-auto py-3">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <span class="text-muted"><?php echo htmlspecialchars($config['nombre_empresa'] ?? 'VMBol en Red'); ?> &copy; 2024 - Sistema de Importación a Bolivia</span>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="text-muted">
                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($config['telefono_contacto'] ?? '+591 777 12345'); ?> | 
                    <i class="fas fa-envelope ms-2 me-1"></i><?php echo htmlspecialchars($config['email_contacto'] ?? 'info@vmbol.com'); ?>
                </span>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/js/main.js"></script>
<?php if (isset($jsFile)): ?>
<script src="../assets/js/<?php echo $jsFile; ?>"></script>
<?php endif; ?>

<?php if (isset($customScript)): ?>
<script><?php echo $customScript; ?></script>
<?php endif; ?>
</body>
</html>