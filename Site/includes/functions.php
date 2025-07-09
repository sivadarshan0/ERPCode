<?php
// File: /var/www/html/includes/functions.php

// Enable error reporting globally
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --------------------
// Input Sanitization
// --------------------
if (!function_exists('sanitize_input')) {
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
    function log_message($message) {
        error_log(date('[Y-m-d H:i:s] ') . $message);
    }
}

// --------------------
// AJAX Detection
// --------------------
if (!function_exists('is_ajax')) {
    function is_ajax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

// --------------------
// JSON Response
// --------------------
if (!function_exists('json_response')) {
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
    function validate_csrf_token($token) {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}

// --------------------
// Code Generator for Entity Codes (e.g. cat001, itm001)
// --------------------
if (!function_exists('generateCode')) {
    /**
     * Generates a unique code with prefix (e.g., cat001) for a table column
     * @param string $prefix        Prefix like 'cat' or 'itm'
     * @param string $table         Table name like 'category' or 'item'
     * @param string $codeField     Field to find max code (e.g., 'CategoryCode')
     * @param int $length           Number of digits (default 3 -> cat001)
     * @return string
     */
    function generateCode($prefix, $table, $codeField, $length = 3) {
        $conn = db();
        $stmt = $conn->prepare("SELECT MAX($codeField) AS MaxCode FROM $table WHERE $codeField LIKE CONCAT(?, '%')");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        $maxCode = $row['MaxCode'] ?? null;
        $nextNumber = 1;

        if ($maxCode) {
            $number = intval(substr($maxCode, strlen($prefix)));
            $nextNumber = $number + 1;
        }

        return $prefix . str_pad($nextNumber, $length, '0', STR_PAD_LEFT);
    }
}
