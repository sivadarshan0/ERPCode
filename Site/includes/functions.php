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
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function update_user_login($user_id, $success, $ip_address) {
    $db = db();
    
    if ($success) {
        // Successful login
        $db->prepare("UPDATE users SET last_login = NOW(), failed_attempts = 0, locked_until = NULL WHERE id = ?")
           ->execute([$user_id]);
    } else {
        // Failed login
        $db->prepare("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?")
           ->execute([$user_id]);
        
        // Check if we need to lock the account
        $stmt = $db->prepare("SELECT failed_attempts FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user['failed_attempts'] >= 5) {
            $lock_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $db->prepare("UPDATE users SET locked_until = ? WHERE id = ?")
               ->execute([$lock_time, $user_id]);
        }
    }
    
    // Log the attempt
    log_login_attempt($user_id, $ip_address, $success);
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
        header('Location: login.php?timeout=1');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
}
?>