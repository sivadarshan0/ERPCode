<?php
// File: index.php
// FINAL version with 5-card layout and all requested link/UI updates.

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

define('_IN_APP_', true);
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
            </div>

            <div class="alert alert-success">
                Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            </div>

            <!-- Main Dashboard Cards -->
            <div class="row mt-4">

                <!-- Card 1: Sales & Orders -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-cart-check-fill"></i> Sales & Orders
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Order</h5>
                            <div class="d-grid gap-2 mt-3">
                                <a href="/modules/sales/entry_order.php" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Sales Order
                                </a>
                                <a href="/modules/sales/list_orders.php" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Find Orders
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Purchasing -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-truck"></i> Purchasing
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Supplier & Stock In</h5>
                            <div class="d-grid gap-2 mt-3">
                                <a href="/modules/purchase/entry_purchase_order.php" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Purchase Order
                                </a>
                                <!-- CORRECTED: Link now points to the purchase folder -->
                                <a href="/modules/purchase/entry_grn.php" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-down"></i> Receive Stock
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card 3: Customer Management -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-people-fill"></i> CRM
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Customer</h5>
                            <div class="d-grid gap-2 mt-3">
                                <a href="/modules/customer/entry_customer.php" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> New Customer
                                </a>
                                <a href="/modules/customer/list_customers.php" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Find Customer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Inventory & Products -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-boxes"></i> Inventory & Products
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Manage Products</h5>
                            <div class="d-grid gap-2 mt-3">
                                <a href="/modules/inventory/list_stock_levels.php" class="btn btn-success">
                                    <i class="bi bi-card-list"></i> View Stock Levels
                                </a>
                                <a href="/modules/inventory/entry_item.php" class="btn btn-primary">
                                    <i class="bi bi-box-seam"></i> Manage Items
                                </a>
                                <!-- REMOVED: Manage Categories button has been removed -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card for Price Calculator -->
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-calculator-fill"></i> Price Calculator
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Pricing Tools</h5>
                            <div class="d-grid gap-2 mt-3">
                                <a href="/modules/price/calculate_price.php" class="btn btn-success">
                                    <i class="bi bi-play-circle"></i> Cost Calculator
                                </a>
                                <!-- THIS IS THE NEW BUTTON -->
                                <a href="/modules/price/calculate_courier_charge.php" class="btn btn-primary">
                                    <i class="bi bi-truck"></i> Courier Calculator
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 6: Accounts -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-bank"></i> Accounts
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Accounts</h5>
                            <div class="d-grid gap-2">
                                <a href="/modules/accounts/report_trial_balance.php" class="btn btn-success">
                                    <i class="bi bi-journal-text"></i> Trial Balance
                                </a>
                                <a href="/modules/accounts/entry_transaction.php" class="btn btn-primary">
                                    <i class="bi bi-pencil-square"></i> Journal Entry
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- End of the row -->
        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>