<?php
// File: index.php

// Session settings
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, // expire on browser close
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="login-container">
    <div class="login-box" style="max-width: 600px">
        <div class="login-header">
            <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h1>
            <h2>ERP Dashboard</h2>
        </div>

        <div class="dashboard-menu">
            <a href="modules/customers/customer.php" class="menu-card">
                <div class="card-icon">👤</div>
                <h3>Customers</h3>
                <p>Customer management</p>
            </a>

            <a href="modules/inventory/item.php" class="menu-card">
                <div class="card-icon">📦</div>
                <h3>Products</h3>
                <p>Product management</p>
            </a>

            <a href="modules/inventory/grn.php" class="menu-card">
                <div class="card-icon">📥</div>
                <h3>GRN Entry</h3>
                <p>Goods receipt notes</p>
            </a>

            <a href="modules/pricing/calculate_price.php" class="menu-card">
                <div class="card-icon">💰</div>
                <h3>Price Calculator</h3>
                <p>Live FX-based pricing</p>
            </a>
        </div>

        <div class="dashboard-footer">
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
