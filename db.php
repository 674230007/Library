<?php
class Database {
    private $host = "sql306.infinityfree.com";
    private $db_name = "if0_42962645_library";
    private $username = "if0_42962645";
    private $password = "moF2tpSal3";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>