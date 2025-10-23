<?php
class Auditoria {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function registrar($tabla, $id_registro, $accion, $datos_anteriores = null, $datos_nuevos = null) {
        // CORREGIDO: Usar las variables de sesión correctas de tu sistema
        $id_usuario = $_SESSION['usuario_id'] ?? null;
        $tipo_usuario = $_SESSION['tipo_usuario'] ?? 'cliente';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Convertir arrays/objetos a JSON
        if ($datos_anteriores && !is_string($datos_anteriores)) {
            $datos_anteriores = json_encode($datos_anteriores, JSON_PRETTY_PRINT);
        }
        
        if ($datos_nuevos && !is_string($datos_nuevos)) {
            $datos_nuevos = json_encode($datos_nuevos, JSON_PRETTY_PRINT);
        }
        
        $stmt = $this->db->prepare("INSERT INTO auditoria 
            (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, tipo_usuario, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
        return $stmt->execute([
            $tabla, 
            $id_registro, 
            $accion, 
            $datos_anteriores, 
            $datos_nuevos, 
            $id_usuario, 
            $tipo_usuario, 
            $ip_address
        ]);
    }
    
    public function registrarInsercion($tabla, $id_registro, $datos_nuevos) {
        return $this->registrar($tabla, $id_registro, 'INSERT', null, $datos_nuevos);
    }
    
    public function registrarActualizacion($tabla, $id_registro, $datos_anteriores, $datos_nuevos) {
        return $this->registrar($tabla, $id_registro, 'UPDATE', $datos_anteriores, $datos_nuevos);
    }
    
    public function registrarEliminacion($tabla, $id_registro, $datos_anteriores) {
        return $this->registrar($tabla, $id_registro, 'DELETE', $datos_anteriores, null);
    }
}
?>