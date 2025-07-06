<?php
// File: includes/auth.php

// Recommended: Disable display errors in production, log them instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/db.php'; // db() must return a valid mysqli connection

define('SESSION_TIMEOUT', 600); // 10 minutes

/**
 * Initializes or resumes a secure session
 */
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check for session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        logout(); // Auto logout due to inactivity
        header("Location: /login.php?timeout=1");
        exit;
    }

    // Update activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * Attempt to login the user
 */
function login($username, $password) {
    try {
        if (empty($username) || empty($password)) {
            error_log("Login failed: Missing credentials");
            return false;
        }

        $conn = db();
        if (!$conn || $conn->connect_error) {
            error_log("Database connection error: " . $conn->connect_error);
            throw new Exception("Database error");
        }

        $check = $conn->query("SHOW TABLES LIKE 'users'");
        if ($check->num_rows === 0) {
            error_log("Login failed: users table missing");
            throw new Exception("Configuration error");
        }

        $stmt = $conn->prepare("SELECT id, password, is_active FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception("Query error");
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            error_log("Login failed: User not found ($username)");
            usleep(rand(100000, 300000)); // anti-brute force
            return false;
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            error_log("Login failed: Invalid password ($username)");
            return false;
        }

        if ((int)$user['is_active'] !== 1) {
            error_log("Login failed: Account inactive ($username)");
            throw new Exception("Inactive account");
        }

        // Login success
        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $username;
        $_SESSION['last_activity'] = time();

        error_log("Login success for user: $username");
        return true;

    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log the user out safely
 */
function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = [];
    session_unset();
    session_destroy();

    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    error_log("User logged out successfully");
}
