<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Sistema Admin'; ?></title>
    
    <!-- AdminLTE y librerías -->
    <link rel="stylesheet" href="assets/css/vendors/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/vendors/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/vendors/adminlte/adminlte.min.css">
    <link rel="stylesheet" href="assets/css/vendors/overlay-scrollbars/overlayScrollbars.min.css">
    
    <!-- Fuentes personalizadas -->
    <link rel="stylesheet" href="assets/fonts/source-sans-3/source-sans-3.css">
    
    <!-- Tus estilos personalizados -->
    <link rel="stylesheet" href="assets/css/custom/main.css">
    <?php if(isset($css_files)): ?>
        <?php foreach($css_files as $css): ?>
            <link rel="stylesheet" href="assets/css/custom/<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="app-content">
            <div class="container-fluid">
                <?php echo $content; ?>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript de librerías -->
    <script src="assets/js/vendors/jquery/jquery.min.js"></script>
    <script src="assets/js/vendors/popper/popper.min.js"></script>
    <script src="assets/js/vendors/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/js/vendors/adminlte/adminlte.min.js"></script>
    <script src="assets/js/vendors/overlay-scrollbars/overlayscrollbars.browser.es6.min.js"></script>
    
    <!-- Tus scripts personalizados -->
    <script src="assets/js/custom/main.js"></script>
    <?php if(isset($js_files)): ?>
        <?php foreach($js_files as $js): ?>
            <script src="assets/js/custom/<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>