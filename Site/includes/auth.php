<?php
// File: /var/www/html/includes/auth.php

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Authenticate user credentials
 * @param string $username 
 * @param string $password
 * @return bool True if login successful, false otherwise
 */
function login($username, $password) {
    try {
        $conn = db();
        
        // Prepare statement to prevent SQL injection
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
            
            // TEMPORARY: Plain text comparison (remove in production)
            // Replace with password_verify() in production
            if ($password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);
                
                // Update last login time
                update_last_login($user['id']);
                
                return true;
            }
            
            /* PRODUCTION VERSION:
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                session_regenerate_id(true);
                update_last_login($user['id']);
                return true;
            }
            */
        }
    } catch (Exception $e) {
        error_log("Login error for $username: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Update user's last login timestamp
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
 * Destroy user session
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
 * Redirect to login page if not authenticated
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
 * Check if user has required permission
 * @param string $permission
 * @return bool
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
 * Get current user's ID
 * @return int|null
 */
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's username
 * @return string|null
 */
function current_username() {
    return $_SESSION['username'] ?? null;
}