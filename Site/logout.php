<?php
// File: Site/logout.php

error_reporting(E_ALL);
ini_set('display_errors', 0); // Avoid showing errors to the user in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // Logging outside web root is better

require_once __DIR__ . '/includes/auth.php';

// Perform logout
logout();

// If logout was triggered via JS (e.g., navigator.sendBeacon), skip redirect
if (isset($_GET['auto']) && $_GET['auto'] == '1') {
    http_response_code(200);
    exit;
}

// Regular logout (manual via UI) → redirect to login
header("Location: /login.php");
exit;
