<?php
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $port = 5432; // Puerto por defecto de PostgreSQL
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Cambiamos el DSN de mysql a pgsql
            $this->conn = new PDO(
                "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}",
                $this->username,
                $this->password
            );

            // Configuraciones adicionales
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names 'UTF8'");

        } catch(PDOException $exception) {
            echo "❌ Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
