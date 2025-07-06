<?php
// File: /var/www/html/index.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$title = 'ERP System - Dashboard';
$headerTitle = 'Main Menu';
require_once __DIR__.'/includes/header.php';
?>

<div class="dashboard-container">
    <div class="menu-grid">
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
        
        <a href="modules/pricing/calculator.php" class="menu-card">
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
</div>

<?php require_once __DIR__.'/includes/footer.php'; ?>