<?php
// File: index.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

define('_IN_APP_', true);
session_start();

require_once __DIR__ . '/config/db.php';
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

            <!-- Welcome Message -->
            <div class="alert alert-success">
                Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            </div>

            <!-- Main Dashboard Cards -->
            <div class="row mt-4">

                <!-- Card 1: Customer Management -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-people-fill"></i> Customer Management
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">Quick Actions</h5>
                            <div class="d-grid gap-2 mt-auto">
                                <a href="/modules/customer/entry_customer.php" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Add New Customer
                                </a>
                                <a href="/modules/customer/list_customers.php" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Find Customer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Price Calculator -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <i class="bi bi-calculator-fill"></i> Price Calculator
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">Pricing Tools</h5>
                            <div class="d-grid gap-2 mt-auto">
                                <a href="/modules/price/calculate_price.php" class="btn btn-info text-white">
                                    <i class="bi bi-play-circle"></i> Open Calculator
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- This is the closing tag for the single "row" div -->

            <!-- You can add another <div class="row"> here for more cards -->

        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>