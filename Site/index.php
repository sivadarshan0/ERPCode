<?php
// File: /var/www/html/index.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session at the VERY TOP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Define page metadata
$title = 'ERP System - Dashboard';
$headerTitle = 'Main Menu';

// Include header
require_once __DIR__.'/includes/header.php';
?>

<div class="menu-grid">
    <a href="modules/customers/customer.php" class="card">
        <h3>Customers</h3>
        <p>Manage customer information</p>
    </a>
    
    <a href="modules/inventory/item.php" class="card">
        <h3>Items</h3>
        <p>Manage inventory items</p>
    </a>
    
    <a href="modules/pricing/calculator.php" class="card">
        <h3>Pricing Calculator</h3>
        <p>Calculate product prices</p>
    </a>
    
    <a href="modules/inventory/grn.php" class="card">
        <h3>GRN Management</h3>
        <p>Goods receipt notes</p>
    </a>
</div>

<?php 
// Include footer
require_once __DIR__.'/includes/footer.php'; 
?>