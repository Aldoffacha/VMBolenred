<?php
class Funciones {
    // Sanitizar entrada de datos
    public static function sanitizar($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
// Función para obtener valores de POST/GET sin warnings
    public static function getInput($key, $default = '') {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
    
    // Función para verificar si un formulario fue enviado
    public static function isFormSubmitted($buttonName) {
        return isset($_POST[$buttonName]) || isset($_GET[$buttonName]);
    }
    
    // Sanitizar array completo
    public static function sanitizeArray($array) {
        $clean = [];
        foreach ($array as $key => $value) {
            $clean[$key] = is_array($value) ? self::sanitizeArray($value) : self::sanitizar($value);
        }
        return $clean;
    }
    // Formatear moneda
    public static function formatoMoneda($monto) {
        return '$ ' . number_format($monto, 2, '.', ',');
    }

    // Calcular costo de importación
    public static function calcularCostoImportacion($precio_producto, $peso, $tipo_producto) {
        $impuesto = 0.15; // 15% de impuestos
        $flete = max(10, $peso * 2.5); // Flete base $10 + $2.5 por kg
        $costo_total = $precio_producto + ($precio_producto * $impuesto) + $flete;
        return $costo_total;
    }
// Agregar esta función al archivo existente
public static function validarTelefono($telefono) {
    return preg_match('/^[0-9]{7,15}$/', $telefono);
}
    // Generar código de seguimiento
    public static function generarCodigoSeguimiento() {
        return 'VMB' . strtoupper(uniqid());
    }

    // Validar correo electrónico
    public static function validarEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
?>