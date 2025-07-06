<?php
// File: /var/www/html/includes/db.php

require_once __DIR__.'/../config/database.php';

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        // Get the configuration array directly
        $config = require __DIR__.'/../config/database.php';
        
        $this->connection = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database']
        );
        
        if ($this->connection->connect_error) {
            error_log("Database connection error: " . $this->connection->connect_error);
            throw new Exception("Database connection failed");
        }
        
        $this->connection->set_charset($config['charset']);
    }
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function generateCode($sequenceName, $prefix, $digits = 6) {
        $conn = $this->getConnection();
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("SELECT next_value FROM sequences WHERE name = ? FOR UPDATE");
            $stmt->bind_param("s", $sequenceName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Sequence not found");
            }
            
            $row = $result->fetch_assoc();
            $nextValue = $row['next_value'];
            
            $update = $conn->prepare("UPDATE sequences SET next_value = next_value + 1 WHERE name = ?");
            $update->bind_param("s", $sequenceName);
            $update->execute();
            
            $conn->commit();
            
            return $prefix . str_pad($nextValue, $digits, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Generate code error: " . $e->getMessage());
            throw $e;
        }
    }
}

function db() {
    return Database::getInstance()->getConnection();
}