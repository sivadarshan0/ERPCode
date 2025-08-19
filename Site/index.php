<?php
// File: index.php
// FINAL version with the corrected color scheme and layout for the dashboard.

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
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-cart-check-fill"></i> Sales & Orders
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Order Management</h5>
                            <div class="d-grid gap-2 mt-3">
                                <a href="/modules/sales/entry_order.php" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> New Sales Order
                                </a>
                                <a href="/modules/sales/list_orders.php" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Find Orders
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Purchasing -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-danger text-white">
                            <i class="bi bi-truck"></i> Purchasing
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Supplier & Stock In</h5>
                            <div class="d-grid gap-2 mt-3">
                                <a href="/modules/purchase/entry_purchase_order.php" class="btn btn-danger">
                                    <i class="bi bi-plus-circle"></i> New Purchase Order
                                </a>
                                <a href="/modules/inventory/entry_grn.php" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-down"></i> Receive Stock (GRN)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Inventory & Products -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-dark">
                            <i class="bi bi-boxes"></i> Inventory & Products
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Manage Products</h5>
                            <div class="d-grid gap-2 mt-3">
                                <a href="/modules/inventory/list_stock.php" class="btn btn-primary">
                                    <i class="bi bi-card-list"></i> View Stock Levels
                                </a>
                                <a href="/modules/inventory/entry_item.php" class="btn btn-secondary">
                                    <i class="bi bi-box-seam"></i> Manage Items
                                </a>
                                <a href="/modules/inventory/entry_category.php" class="btn btn-secondary">
                                    <i class="bi bi-tags-fill"></i> Manage Categories
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card 4: Customer Management -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-people-fill"></i> Customer Management
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Quick Actions</h5>
                            <div class="d-grid gap-2 mt-3">
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

            </div> <!-- End of the row -->

        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>