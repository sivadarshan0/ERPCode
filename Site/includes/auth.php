<?php
// File: /includes/auth.php

/**
 * Authentication System with Password Hashing
 * 
 * This file handles all user authentication functions
 * including secure password storage and verification
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Check if a user is logged in
 * @return bool True if user is authenticated
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Authenticate a user with password verification
 * @param string $username 
 * @param string $password
 * @return bool True if authentication succeeds
 */
function login($username, $password) {
    try {
        $conn = db();
        
        // Prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, password, is_active FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Database query preparation failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is active
            if (isset($user['is_active']) && $user['is_active'] != 1) {
                error_log("Login attempt for inactive account: $username");
                return false;
            }
            
            // Verify password against stored hash
            if (password_verify($password, $user['password'])) {
                
                // Check if password needs rehashing (if algorithm changed)
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $newHash = hash_password($password);
                    update_password($user['id'], $newHash);
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);
                
                // Update last login time
                update_last_login($user['id']);
                
                return true;
            }
        }
    } catch (Exception $e) {
        error_log("Login error for $username: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Create a password hash for secure storage
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Check if a password hash needs rehashing
 * @param string $hash Existing password hash
 * @return bool True if needs rehash
 */
function password_needs_rehash($hash) {
    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}

/**
 * Update a user's password in the database
 * @param int $userId
 * @param string $hashedPassword
 * @return bool True on success
 */
function update_password($userId, $hashedPassword) {
    try {
        $conn = db();
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Password update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Update last login timestamp
 * @param int $userId
 */
function update_last_login($userId) {
    try {
        $conn = db();
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

/**
 * Destroy user session securely
 */
function logout() {
    // Unset all session variables
    $_SESSION = array();

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }

    // Destroy session
    session_destroy();
}

/**
 * Redirect to login if not authenticated
 */
function require_login() {
    if (!is_logged_in()) {
        // Store requested URL for redirect after login
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }
}

/**
 * Check if user has specific permission
 * @param string $permission Permission name
 * @return bool True if user has permission
 */
function has_permission($permission) {
    if (!is_logged_in()) {
        return false;
    }
    
    try {
        $conn = db();
        $stmt = $conn->prepare("
            SELECT 1 FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND p.name = ?
        ");
        $stmt->bind_param("is", $_SESSION['user_id'], $permission);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    } catch (Exception $e) {
        error_log("Permission check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current user ID
 * @return int|null User ID or null if not logged in
 */
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 * @return string|null Username or null if not logged in
 */
function current_username() {
    return $_SESSION['username'] ?? null;
}

/**
 * Validate password strength
 * @param string $password
 * @return bool|array True if valid, or array of errors
 */
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Must be at least 8 characters";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Must contain uppercase letters";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Must contain lowercase letters";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Must contain numbers";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Must contain special characters";
    }
    
    return empty($errors) ? true : $errors;
}