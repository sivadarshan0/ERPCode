<?php
// File: includes/functions.php

defined('_IN_APP_') or die('Unauthorized access');

function is_account_locked($username) {
    $db = db();
    $stmt = $db->prepare("SELECT locked_until FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return $user['locked_until'];
    }
    return false;
}

function get_user_by_username($username) {
    $db = db();
    
    // Prepare the statement
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("Database prepare error: " . $db->error);
        return null; // Return null instead of false for better type consistency
    }
    
    // Bind and execute
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return null;
    }
    
    // Get result
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Database get_result error: " . $db->error);
        return null;
    }
    
    // Fetch user data
    $user = $result->fetch_assoc();
    
    // Explicitly return null if no user found
    return $user ? $user : null;
}

function update_user_login($user_id, $success, $ip_address) {
    // Validate user_id exists and is numeric
    if (!is_numeric($user_id) || $user_id <= 0) {
        error_log("Invalid user_id in update_user_login: $user_id");
        return false;
    }

    $db = db();
    
    try {
        if ($success) {
            $stmt = $db->prepare("UPDATE users SET last_login = NOW(), failed_attempts = 0, locked_until = NULL WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?");
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Only check for lock if it was a failed attempt
        if (!$success) {
            $stmt = $db->prepare("SELECT failed_attempts FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user && $user['failed_attempts'] >= 5) {
                $lock_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $stmt = $db->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
                $stmt->bind_param("si", $lock_time, $user_id);
                $stmt->execute();
            }
        }
        
        // Log the attempt
        log_login_attempt($user_id, $ip_address, $success);
        
        return true;
    } catch (Exception $e) {
        error_log("Error in update_user_login: " . $e->getMessage());
        return false;
    }
}

function log_login_attempt($user_id, $ip_address, $success) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO user_login_audit (user_id, login_time, ip_address, success) VALUES (?, NOW(), ?, ?)");
    $stmt->execute([$user_id, $ip_address, (int)$success]);
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    
    // Check for inactivity (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: /login.php?timeout=1');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
}

/**
 * Generate sequential IDs with prefix (e.g., CUS00001)
 */
function generate_sequence_id($sequence_name) {

    
    $db = db();
    $db->begin_transaction();
    
    try {
        // Lock and retrieve sequence
        $stmt = $db->prepare("SELECT prefix, next_value, digit_length 
                            FROM system_sequences 
                            WHERE sequence_name = ? FOR UPDATE");
        $stmt->bind_param("s", $sequence_name);
        $stmt->execute();
        $seq = $stmt->get_result()->fetch_assoc();
        
        if (!$seq) {
            throw new Exception("Sequence '$sequence_name' not found");
        }
        
        // Format ID
        $new_id = $seq['prefix'] . str_pad($seq['next_value'], $seq['digit_length'], '0', STR_PAD_LEFT);
        
        // Increment sequence
        $update = $db->prepare("UPDATE system_sequences 
                              SET next_value = next_value + 1,
                                  last_used_at = NOW(),
                                  last_used_by = ?
                              WHERE sequence_name = ?");
        $update->bind_param("is", $_SESSION['user_id'], $sequence_name);
        $update->execute();
        
        $db->commit();
        return $new_id;
    } catch (Exception $e) {
        $db->rollback();
        error_log("Sequence generation failed: " . $e->getMessage());
        throw new Exception("Could not generate ID");
    }
}

/**
 * Get customer by ID
 */
function get_customer($customer_id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param("s", $customer_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Add this to /includes/functions.php
function search_customers_by_phone($phone) {
    $db = db();
    if (!$db) {
        error_log("Database connection failed");
        return [];
    }
    
    $stmt = $db->prepare("SELECT customer_id, name, phone FROM customers WHERE phone LIKE ? LIMIT 10");
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error);
        return [];
    }
    
    $search_term = "%$phone%";
    $stmt->bind_param("s", $search_term);
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return [];
    }
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
