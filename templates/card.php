<?php
function renderProductCard($producto) {
    $costo_importacion = Funciones::calcularCostoImportacion($producto['precio'], 1, 'general');
    ?>
    <div class="product-card">
        <div class="product-image">
            <?php if ($producto['imagen']): ?>
                <img src="../../assets/img/productos/<?php echo $producto['imagen']; ?>" alt="<?php echo $producto['nombre']; ?>">
            <?php else: ?>
                <span>Sin imagen</span>
            <?php endif; ?>
        </div>
        <div class="product-info">
            <h5><?php echo $producto['nombre']; ?></h5>
            <p class="text-muted"><?php echo substr($producto['descripcion'], 0, 100); ?>...</p>
            <div class="d-flex justify-content-between align-items-center">
                <span class="price">$<?php echo number_format($costo_importacion, 2); ?></span>
                <button class="btn btn-primary btn-sm">Agregar al Carrito</button>
            </div>
        </div>
    </div>
    <?php
}
?>