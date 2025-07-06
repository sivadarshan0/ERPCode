<?php
// Add at the very top
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable on production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/../logs/php_errors.log');

function login($username, $password) {
    try {
        if (!function_exists('password_verify')) {
            throw new Exception("Password functions not available");
        }

        $conn = db();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }

        $stmt = $conn->prepare("SELECT id, password, is_active FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: ".$conn->error);
        }

        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: ".$stmt->error);
        }

        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Login error: ".$e->getMessage());
        return false;
    }
}