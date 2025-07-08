<?php
// File: /var/www/html/includes/functions.php

// Enable error reporting globally
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --------------------
// Input Sanitization
// --------------------
if (!function_exists('sanitize_input')) {
    /**
     * Sanitizes user input for safe usage
     * @param string $data
     * @return string
     */
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

// --------------------
// Logging Helper
// --------------------
if (!function_exists('log_message')) {
    /**
     * Logs a message to the PHP error log with a timestamp
     * @param string $message
     */
    function log_message($message) {
        error_log(date('[Y-m-d H:i:s] ') . $message);
    }
}

// --------------------
// AJAX Detection
// --------------------
if (!function_exists('is_ajax')) {
    /**
     * Checks if the request is an AJAX call
     * @return bool
     */
    function is_ajax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

// --------------------
// JSON Response
// --------------------
if (!function_exists('json_response')) {
    /**
     * Sends a JSON response and exits
     * @param mixed $data
     * @param int $statusCode
     */
    function json_response($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// --------------------
// CSRF Token Generator
// --------------------
if (!function_exists('generate_csrf_token')) {
    /**
     * Generates and stores a CSRF token in session
     * @return string
     */
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// --------------------
// CSRF Token Validator
// --------------------
if (!function_exists('validate_csrf_token')) {
    /**
     * Validates the CSRF token from request
     * @param string $token
     * @return bool
     */
    function validate_csrf_token($token) {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
