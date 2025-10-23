</main>
        </div>
    </div>
    <script src="<?php echo URL_ASSETS; ?>js/vendors/jquery/jquery.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>js/vendors/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>js/vendors/apexcharts/apexcharts.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>js/custom/main.js"></script>
    <?php
    $ruta = isset($_GET['ruta']) ? $_GET['ruta'] : '';
    if (strpos($ruta, 'dashboard') !== false) {
        echo '<script src="' . URL_ASSETS . 'js/custom/dashboard.js"></script>';
    }
    if (strpos($ruta, 'cotizaciones') !== false) {
        echo '<script src="' . URL_ASSETS . 'js/custom/cotizaciones.js"></script>';
    }
    if (strpos($ruta, 'inventario') !== false) {
        echo '<script src="' . URL_ASSETS . 'js/custom/inventario.js"></script>';
    }
    ?>
</body>
</html>