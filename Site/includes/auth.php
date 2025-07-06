<?php
// File: /var/www/html/includes/auth.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function declarations with existence checks
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('login')) {
    function login($username, $password) {
        try {
            $conn = db();
            $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if ($password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    error_log("Login success for: $username");
                    return true;
                }
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
        }
        
        error_log("Failed login attempt for: $username");
        return false;
    }
}

if (!function_exists('logout')) {
    function logout() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}

if (!function_exists('require_login')) {
    function require_login() {
        if (!is_logged_in()) {
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
            header('Location: login.php');
            exit;
        }
    }
}