<?php
// File: includes/auth.php

// NOTE: Disable error display in production
error_reporting(E_ALL);
ini_set('display_errors', 0); // Show errors in logs, not to the user
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/db.php'; // Ensure db() function is available

/**
 * Attempt user login
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
            throw new Exception("Database connection failed");
        }

        // Ensure users table exists
        $check = $conn->query("SHOW TABLES LIKE 'users'");
        if ($check->num_rows === 0) {
            error_log("Login failed: 'users' table does not exist");
            throw new Exception("Configuration error");
        }

        // Lookup user
        $stmt = $conn->prepare("SELECT id, password, is_active FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            error_log("Login prepare failed: " . $conn->error);
            throw new Exception("Query preparation error");
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            error_log("Login failed: User not found ($username)");
            usleep(rand(100000, 300000)); // Anti-brute delay
            return false;
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            error_log("Login failed: Incorrect password ($username)");
            return false;
        }

        if ((int)$user['is_active'] !== 1) {
            error_log("Login failed: Inactive account ($username)");
            throw new Exception("Account is inactive");
        }

        // Successful login
        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $username;
        $_SESSION['last_activity'] = time();

        error_log("Login successful for user: $username");
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
    session_start();

    // Clear session data
    $_SESSION = [];
    session_unset();
    session_destroy();

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    error_log("User logged out successfully");
}
