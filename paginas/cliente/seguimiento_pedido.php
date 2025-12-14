<?php
require_once '../../includes/config.php';

if (!isset($_GET['id_pedido'])) {
    die('Pedido no válido');
}

$id_pedido = $_GET['id_pedido'];

$conexion = new PDO(
    "pgsql:host=".DB_HOST.";dbname=".DB_NAME,
    DB_USER,
    DB_PASS
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seguimiento de pedido</title>
    <style>
        #mapa {
            width: 100%;
            height: 400px;
            display: none;
        }
        .mensaje {
            padding: 15px;
            background: #f5f5f5;
            border-radius: 6px;
            font-size: 16px;
        }
    </style>
</head>
<body>

<h2>Seguimiento de tu pedido #<?= $id_pedido ?></h2>

<div class="mensaje" id="mensaje">
    Consultando estado del pedido...
</div>

<div id="mapa"></div>

<script>
function cargarSeguimiento() {
    fetch('../../api/ver_seguimiento.php?id_pedido=<?= $id_pedido ?>')
    .then(res => res.json())
    .then(data => {

        if (!data || data.activo !== true) {
            document.getElementById('mensaje').innerText =
              'El empleado se está preparando para llevar tu pedido';
            document.getElementById('mapa').style.display = 'none';
            return;
        }

        document.getElementById('mensaje').innerText =
          'Tu pedido está en camino con ' + data.nombre;

        document.getElementById('mapa').style.display = 'block';

        // Google Maps
        const map = new google.maps.Map(document.getElementById('mapa'), {
            zoom: 15,
            center: {
                lat: parseFloat(data.latitud),
                lng: parseFloat(data.longitud)
            }
        });

        new google.maps.Marker({
            position: {
                lat: parseFloat(data.latitud),
                lng: parseFloat(data.longitud)
            },
            map: map
        });
    });
}

// Actualiza cada 5 segundos
setInterval(cargarSeguimiento, 5000);
cargarSeguimiento();
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=TU_API_KEY"></script>

</body>
</html>
