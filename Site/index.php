<?php
// File: /var/www/html/index.php

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Secure session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
start_secure_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$title = 'ERP System - Dashboard';
require_once __DIR__.'/includes/header.php';
?>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h1>ERP System</h1>
            <h2>Main Dashboard</h2>
            <div class="user-welcome">
                Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
            </div>
        </div>

        <div class="dashboard-menu">
            <a href="modules/customers/customer.php" class="menu-card">
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Customers</h3>
                <p>Manage customer information</p>
            </a>
            
            <a href="modules/inventory/item.php" class="menu-card">
                <div class="card-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <h3>Items</h3>
                <p>Manage inventory items</p>
            </a>
            
            <a href="modules/pricing/calculate_price.php" class="menu-card">
                <div class="card-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <h3>Pricing Calculator</h3>
                <p>Calculate product prices</p>
            </a>
            
            <a href="modules/inventory/grn.php" class="menu-card">
                <div class="card-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h3>GRN Management</h3>
                <p>Goods receipt notes</p>
            </a>
        </div>

        <div class="dashboard-footer">
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/includes/footer.php'; ?>