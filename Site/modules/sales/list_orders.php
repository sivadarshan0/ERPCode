<?php
// File: /modules/sales/list_orders.php
// FINAL version with a "Back to Dashboard" button.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// --- AJAX Endpoint for Live Search ---
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    try {
        $filters = [
            'order_id'       => $_GET['order_id'] ?? null,
            'customer'       => $_GET['customer'] ?? null,
            'date_from'      => $_GET['date_from'] ?? null,
            'date_to'        => $_GET['date_to'] ?? null,
            'status'         => $_GET['status'] ?? null,
            'payment_status' => $_GET['payment_status'] ?? null,
        ];
        $orders = search_orders($filters);
        echo json_encode($orders);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$initial_orders = search_orders();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Order List</h1>
                <!-- MODIFIED: Added a button toolbar and the Back to Dashboard button -->
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/index.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left-circle"></i> Back
                    </a>
                    <a href="/modules/sales/entry_order.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> New Sales Order
                    </a>
                </div>
            </div>

            <!-- Search Filters -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-search"></i> Find Orders</div>
                <div class="card-body">
                    <form id="orderSearchForm" class="row gx-3 gy-2 align-items-center">
                        <div class="col-sm-6 col-lg-2"><input type="text" class="form-control" id="search_order_id" placeholder="Order ID"></div>
                        <div class="col-sm-6 col-lg-3"><input type="text" class="form-control" id="search_customer" placeholder="Customer (Name/Phone)"></div>
                        <div class="col-sm-6 col-lg-2"><select id="search_status" class="form-select"><option value="">All Order Statuses</option><option value="New">New</option><option value="Processing">Processing</option><option value="With Courier">With Courier</option><option value="Delivered">Delivered</option><option value="Canceled">Canceled</option></select></div>
                        <div class="col-sm-6 col-lg-2"><select id="search_payment_status" class="form-select"><option value="">All Payment Statuses</option><option value="Pending">Pending</option><option value="Received">Received</option></select></div>
                        <div class="col-sm-12 col-lg-3"><input type="text" class="form-control" id="search_date_range" placeholder="Select Date Range"></div>
                    </form>
                </div>
            </div>

            <!-- Order List Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th class="text-end">Total Amount</th>
                                    <th>Order Status</th>
                                    <th>Payment Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="orderListTableBody">
                                <?php if (empty($initial_orders)): ?>
                                    <tr><td colspan="8" class="text-center text-muted">No orders found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($initial_orders as $order): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['order_id']) ?></td>
                                            <td><?= htmlspecialchars(date("d-m-Y", strtotime($order['order_date']))) ?></td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                                            <td class="text-end"><?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($order['status']) ?></span></td>
                                            <td><span class="badge bg-<?= $order['payment_status'] == 'Received' ? 'success' : 'warning' ?>"><?= htmlspecialchars($order['payment_status']) ?></span></td>
                                            <td><a href="/modules/sales/entry_order.php?order_id=<?= htmlspecialchars($order['order_id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>