<?php
// File: includes/auth.php

// Production-ready error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Display off
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/db.php';

/**
 * Attempt user login
 * 
 * @param string $username
 * @param string $password
 * @return bool
 */
function login($username, $password) {
    try {
        if (empty($username) || empty($password)) {
            error_log("Login error: empty username or password");
            return false;
        }

        $conn = db();
        if (!$conn || $conn->connect_error) {
            error_log("DB error: " . ($conn ? $conn->connect_error : 'null connection'));
            throw new Exception("Database unavailable");
        }

        // Ensure 'users' table exists
        $check = $conn->query("SHOW TABLES LIKE 'users'");
        if (!$check || $check->num_rows === 0) {
            error_log("Login error: users table missing");
            throw new Exception("DB misconfiguration");
        }

        // Query user by username
        $stmt = $conn->prepare("SELECT id, password, is_active FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            error_log("Login error: prepare failed - " . $conn->error);
            return false;
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows !== 1) {
            error_log("Login failed: user not found [$username]");
            usleep(rand(100000, 300000)); // Throttle brute force
            return false;
        }

        $user = $res->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            error_log("Login failed: incorrect password [$username]");
            return false;
        }

        if ((int)$user['is_active'] !== 1) {
            error_log("Login failed: account inactive [$username]");
            return false;
        }

        // Authenticated – start session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $username;
        $_SESSION['last_activity'] = time();

        error_log("Login success: $username");
        return true;
    } catch (Exception $e) {
        error_log("Login exception: " . $e->getMessage());
        return false;
    }
}


/**
 * Log the user out safely
 */
function logout() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Clear session
    $_SESSION = [];
    session_unset();
    session_destroy();

    // Remove session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    error_log("User logged out.");
}
