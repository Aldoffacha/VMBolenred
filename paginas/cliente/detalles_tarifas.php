<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

Auth::checkAuth('cliente');
$db = (new Database())->getConnection();

$user_id = $_SESSION['user_id'];
?>
<?php include '../../includes/header.php'; ?>
<?php $pageTitle = "Detalles de Tarifas"; ?>

<div class="container-fluid">
    <div class="row flex-grow-1 m-0">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">📋 Detalles de Tarifas de Importación</h1>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">🏷️ Tarifas de Almacenamiento por Dimensiones</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Dimensiones (Largo x Ancho x Alto)</th>
                                            <th>Peso Máximo</th>
                                            <th>Costo en Bolivianos</th>
                                            <th>Descripción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>20 x 15 x 1 cm</strong></td>
                                            <td>Sin límite</td>
                                            <td><strong>Bs. 135</strong></td>
                                            <td>Paquete pequeño y plano</td>
                                        </tr>
                                        <tr>
                                            <td><strong>20 x 15 x 15 cm</strong></td>
                                            <td>Sin límite</td>
                                            <td><strong>Bs. 180</strong></td>
                                            <td>Paquete mediano estándar</td>
                                        </tr>
                                        <tr>
                                            <td><strong>25 x 15 x 15 cm</strong></td>
                                            <td>Sin límite</td>
                                            <td><strong>Bs. 225</strong></td>
                                            <td>Paquete grande estándar</td>
                                        </tr>
                                        <tr>
                                            <td><strong>30 x 20 x 20 cm</strong></td>
                                            <td>Sin límite</td>
                                            <td><strong>Bs. 270</strong></td>
                                            <td>Paquete extra grande</td>
                                        </tr>
                                        <tr>
                                            <td><strong>35 x 20 x 20 cm</strong></td>
                                            <td>Sin límite</td>
                                            <td><strong>Bs. 360</strong></td>
                                            <td>Envío pequeño</td>
                                        </tr>
                                        <tr>
                                            <td><strong>50 x 40 x 10 cm</strong></td>
                                            <td>10 kg</td>
                                            <td><strong>Bs. 450</strong></td>
                                            <td>Tamaño laptop (hasta 10kg)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>50 x 40 x 10 cm</strong></td>
                                            <td>Más de 10 kg</td>
                                            <td><strong>Bs. 1,350</strong></td>
                                            <td>Tamaño laptop (más de 10kg)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>60 x 60 x 60 cm</strong></td>
                                            <td>20 kg</td>
                                            <td><strong>Bs. 1,800</strong></td>
                                            <td>Grande pesado (hasta 20kg)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>100 x 100 x 60 cm</strong></td>
                                            <td>25 kg</td>
                                            <td><strong>Bs. 2,250</strong></td>
                                            <td>Extra grande pesado (hasta 25kg)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>150 x 100 x 100 cm</strong></td>
                                            <td>30 kg</td>
                                            <td><strong>Bs. 3,150</strong></td>
                                            <td>Gigante (hasta 30kg)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">💰 Otros Costos de Importación</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>📦 Flete Marítimo</h6>
                                    <ul>
                                        <li><strong>Cálculo:</strong> Máximo entre $15 y (Peso en kg × $3)</li>
                                        <li><strong>Ejemplo:</strong> Producto de 2kg = $15 (mínimo)</li>
                                        <li><strong>Ejemplo:</strong> Producto de 10kg = $30</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>🛡️ Seguro</h6>
                                    <ul>
                                        <li><strong>Cálculo:</strong> 2% del precio del producto</li>
                                        <li><strong>Ejemplo:</strong> Producto de $100 = $2 de seguro</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <h6>🏛️ Impuestos de Aduana</h6>
                                    <ul>
                                        <li><strong>Electrónicos:</strong> 30% del precio</li>
                                        <li><strong>Ropa y Moda:</strong> 20% del precio</li>
                                        <li><strong>Artículos del Hogar:</strong> 15% del precio</li>
                                        <li><strong>Deportes:</strong> 25% del precio</li>
                                        <li><strong>Otros:</strong> 18% del precio</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>💱 Tipo de Cambio</h6>
                                    <ul>
                                        <li><strong>Fuente:</strong> dolarboliviahoy.com</li>
                                        <li><strong>Actualización:</strong> Automática diaria</li>
                                        <li><strong>Uso:</strong> Para convertir Bs. a USD en almacenamiento</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">📊 Ejemplo de Cálculo</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Producto de Ejemplo:</h6>
                                    <ul>
                                        <li><strong>Precio:</strong> $100 USD</li>
                                        <li><strong>Peso:</strong> 2 kg</li>
                                        <li><strong>Dimensiones:</strong> 25x15x15 cm</li>
                                        <li><strong>Categoría:</strong> Electrónico</li>
                                        <li><strong>Tipo de cambio:</strong> Bs. 10.47 por $1</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Desglose de Costos:</h6>
                                    <ul>
                                        <li><strong>Producto:</strong> $100.00</li>
                                        <li><strong>Flete:</strong> $15.00 (mínimo)</li>
                                        <li><strong>Seguro:</strong> $2.00 (2% de $100)</li>
                                        <li><strong>Aduana:</strong> $30.00 (30% de $100)</li>
                                        <li><strong>Almacén:</strong> $21.49 (Bs. 225 ÷ 10.47)</li>
                                        <li><strong>Total:</strong> $168.49 USD</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>