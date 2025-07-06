<?php
// File: /var/www/html/includes/functions.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function declarations with existence checks
if (!function_exists('sanitize_input')) {
    /**
     * Sanitizes user input
     * @param string $data The input to sanitize
     * @return string Sanitized output
     */
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

// Add other functions with similar protection
if (!function_exists('log_message')) {
    function log_message($message) {
        error_log(date('[Y-m-d H:i:s] ') . $message);
    }
}