<?php
require_once 'config.php';
require_once 'database.php';

class Notificaciones {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    // Crear notificación
    public function crear($id_usuario, $tipo_usuario, $titulo, $mensaje, $tipo, $enlace = null, $metadata = null) {
        $query = "INSERT INTO notificaciones (id_usuario, tipo_usuario, titulo, mensaje, tipo, enlace, metadata) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        
        $metadata_json = $metadata ? json_encode($metadata) : null;
        return $stmt->execute([$id_usuario, $tipo_usuario, $titulo, $mensaje, $tipo, $enlace, $metadata_json]);
    }

    // Obtener notificaciones del usuario
    public function obtener($id_usuario, $tipo_usuario, $limite = 10) {
        $query = "SELECT * FROM notificaciones 
                 WHERE id_usuario = ? AND tipo_usuario = ? 
                 ORDER BY fecha_creacion DESC 
                 LIMIT ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id_usuario, $tipo_usuario, $limite]);
        return $stmt->fetchAll();
    }

    // Obtener notificaciones no leídas
    public function obtenerNoLeidas($id_usuario, $tipo_usuario) {
        $query = "SELECT COUNT(*) as total FROM notificaciones 
                 WHERE id_usuario = ? AND tipo_usuario = ? AND leido = FALSE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id_usuario, $tipo_usuario]);
        return $stmt->fetch()['total'];
    }

    // Marcar como leída
    public function marcarLeida($id_notificacion) {
        $query = "UPDATE notificaciones SET leido = TRUE WHERE id_notificacion = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$id_notificacion]);
    }

    // Marcar todas como leídas
    public function marcarTodasLeidas($id_usuario, $tipo_usuario) {
        $query = "UPDATE notificaciones SET leido = TRUE 
                 WHERE id_usuario = ? AND tipo_usuario = ? AND leido = FALSE";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$id_usuario, $tipo_usuario]);
    }

    // NOTIFICACIONES AUTOMÁTICAS

    // Notificar nuevo producto (para clientes)
    public function notificarNuevoProducto($id_producto, $nombre, $categoria) {
        // Obtener todos los clientes activos
        $query = "SELECT id_cliente FROM clientes WHERE estado = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $clientes = $stmt->fetchAll();

        foreach ($clientes as $cliente) {
            $this->crear(
                $cliente['id_cliente'],
                'cliente',
                '🎉 Nuevo Producto Disponible',
                "Se ha agregado: {$nombre} en la categoría {$categoria}",
                'nuevo_producto',
                "tienda.php?categoria=" . urlencode($categoria),
                ['id_producto' => $id_producto, 'categoria' => $categoria]
            );
        }
    }

    // Notificar producto en oferta
    public function notificarOferta($id_producto, $nombre, $descuento) {
        $query = "SELECT id_cliente FROM clientes WHERE estado = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $clientes = $stmt->fetchAll();

        foreach ($clientes as $cliente) {
            $this->crear(
                $cliente['id_cliente'],
                'cliente',
                '🔥 Oferta Especial',
                "{$nombre} con {$descuento}% de descuento",
                'oferta',
                "tienda.php?busqueda=" . urlencode($nombre),
                ['id_producto' => $id_producto, 'descuento' => $descuento]
            );
        }
    }

    // Notificar cambio de estado de pedido
    public function notificarEstadoPedido($id_cliente, $id_pedido, $nuevo_estado) {
        $estados = [
            'pendiente' => '⏳ Pendiente',
            'procesando' => '🔄 En Proceso',
            'enviado' => '🚚 Enviado',
            'completado' => 'Completado',
            'cancelado' => 'Cancelado'
        ];

        $this->crear(
            $id_cliente,
            'cliente',
            '📦 Actualización de Pedido',
            "Tu pedido #{$id_pedido} ahora está: {$estados[$nuevo_estado]}",
            'estado_pedido',
            "pedidos.php",
            ['id_pedido' => $id_pedido, 'estado' => $nuevo_estado]
        );
    }

    // Notificar confirmación de pago
    public function notificarPagoConfirmado($id_cliente, $id_pedido, $monto) {
        $this->crear(
            $id_cliente,
            'cliente',
            '💳 Pago Confirmado',
            "Tu pago de \${$monto} para el pedido #{$id_pedido} ha sido confirmado",
            'pago_confirmado',
            "pedidos.php",
            ['id_pedido' => $id_pedido, 'monto' => $monto]
        );
    }

    // Notificar stock bajo (para admin)
    public function notificarStockBajo($id_producto, $nombre, $stock_actual) {
        // Obtener todos los administradores
        $query = "SELECT id_admin FROM administradores WHERE estado = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $admins = $stmt->fetchAll();

        foreach ($admins as $admin) {
            $this->crear(
                $admin['id_admin'],
                'admin',
                '⚠️ Stock Bajo',
                "Producto: {$nombre} - Solo quedan {$stock_actual} unidades",
                'stock_bajo',
                "inventario.php",
                ['id_producto' => $id_producto, 'stock_actual' => $stock_actual]
            );
        }
    }

    // Notificar reporte diario (para admin)
    public function notificarReporteDiario($ventas_total, $pedidos_total, $productos_vendidos) {
        $query = "SELECT id_admin FROM administradores WHERE estado = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $admins = $stmt->fetchAll();

        foreach ($admins as $admin) {
            $this->crear(
                $admin['id_admin'],
                'admin',
                '📊 Reporte Diario',
                "Ventas: \${$ventas_total} | Pedidos: {$pedidos_total} | Productos: {$productos_vendidos}",
                'reporte_diario',
                "reportes.php",
                ['ventas' => $ventas_total, 'pedidos' => $pedidos_total, 'productos' => $productos_vendidos]
            );
        }
    }
}

// Función helper para usar en otros archivos
function notificar($id_usuario, $tipo_usuario, $titulo, $mensaje, $tipo, $enlace = null, $metadata = null) {
    $notificaciones = new Notificaciones();
    return $notificaciones->crear($id_usuario, $tipo_usuario, $titulo, $mensaje, $tipo, $enlace, $metadata);
}
?>