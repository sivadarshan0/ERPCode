<?php
// File: login.php

// Production-safe error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Start secure session and check login status
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => false, // Set to true on HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// If user already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$username = '';

// Try DB connectivity for diagnostic (optional in prod)
try {
    $conn = db();
    if (!$conn || $conn->connect_error) {
        throw new Exception("DB error: " . ($conn ? $conn->connect_error : 'null connection'));
    }
    error_log("DB connection successful in login.php");
} catch (Throwable $e) {
    error_log("DB init error in login.php: " . $e->getMessage());
    die(
