<?php
// File: index.php

define('_IN_APP_', true);
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

// User is authenticated - show dashboard
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
            </div>
            
            <!-- Dashboard content goes here -->
            <div class="alert alert-success">
                Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            </div>
        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>