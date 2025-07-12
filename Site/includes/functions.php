<?php
defined('_IN_APP_') or die('Unauthorized access');

// Database Connection
function db() {
    static $db = null;
    if ($db === null) {
        require_once __DIR__ . '/../config/db.php';
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            error_log("Database connection failed: " . $db->connect_error);
            return false;
        }
        $db->set_charset("utf8mb4");
    }
    return $db;
}

// Customer Functions
function search_customers_by_phone($phone) {
    $db = db();
    if (!$db) return [];
    
    $stmt = $db->prepare("SELECT customer_id, name, phone FROM customers WHERE phone LIKE ? LIMIT 10");
    if (!$stmt) return [];
    
    $search_term = "%$phone%";
    $stmt->bind_param("s", $search_term);
    
    if (!$stmt->execute()) return [];
    
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_customer($customer_id) {
    $db = db();
    if (!$db) return false;
    
    $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
    if (!$stmt) return false;
    
    $stmt->bind_param("s", $customer_id);
    if (!$stmt->execute()) return false;
    
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : false;
}

function save_customer($data) {
    $db = db();
    if (!$db) return false;
    
    if (empty($data['customer_id'])) {
        // Create new customer
        $stmt = $db->prepare("INSERT INTO customers (...) VALUES (...)");
        // ... bind parameters ...
    } else {
        // Update existing customer
        $stmt = $db->prepare("UPDATE customers SET ... WHERE customer_id = ?");
        // ... bind parameters ...
    }
    
    return $stmt->execute();
}

// User Authentication Functions
function is_account_locked($username) {
    $db = db();
    $stmt = $db->prepare("SELECT locked_until FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    return ($user && $user['locked_until'] && strtotime($user['locked_until']) > time());
}

function get_user_by_username($username) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function update_user_login($user_id, $success, $ip_address) {
    $db = db();
    if ($success) {
        $stmt = $db->prepare("UPDATE users SET last_login = NOW(), failed_attempts = 0 WHERE id = ?");
    } else {
        $stmt = $db->prepare("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?");
    }
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Session Management
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }
}

// Utility Functions
function generate_sequence_id($sequence_name) {
    $db = db();
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT prefix, next_value FROM system_sequences WHERE sequence_name = ? FOR UPDATE");
        $stmt->bind_param("s", $sequence_name);
        $stmt->execute();
        $seq = $stmt->get_result()->fetch_assoc();
        
        $new_id = $seq['prefix'] . str_pad($seq['next_value'], 5, '0', STR_PAD_LEFT);
        
        $update = $db->prepare("UPDATE system_sequences SET next_value = next_value + 1 WHERE sequence_name = ?");
        $update->bind_param("s", $sequence_name);
        $update->execute();
        
        $db->commit();
        return $new_id;
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}