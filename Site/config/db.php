<?php
/**
 * Secure Database Connection Handler
 * 
 * Place this in /includes/db.php
 * Add to .htaccess: <Files "db.php">Require all denied</Files>
 */

// Prevent direct access
defined('_IN_APP_') or die('Unauthorized access');

class DB {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        // Load configuration
        $config = [
            'host' => 'localhost',
            'username' => 'dbauser',
            'password' => 'dbauser',
            'dbname' => 'erpdb',
            'port' => 3306,
            'charset' => 'utf8mb4'
        ];
        
        try {
            $this->connection = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['dbname'],
                $config['port']
            );
            
            if ($this->connection->connect_error) {
                throw new Exception("Database connection failed");
            }
            
            $this->connection->set_charset($config['charset']);
        } catch (Exception $e) {
            error_log('DB Error: ' . $e->getMessage());
            header('HTTP/1.1 503 Service Unavailable');
            exit('Service temporarily unavailable');
        }
    }
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning and unserialization
    private function __clone() { }
    public function __wakeup() {
        throw new Exception("Cannot unserialize database connection");
    }
}

// Helper function for easy access
function db() {
    return DB::getInstance()->getConnection();
}

// Define constant to check in other files
define('_IN_APP_', true);
?>