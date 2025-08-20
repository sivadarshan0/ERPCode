<?php
// File: /modules/purchase/list_purchase_order.php
// Page to search and list all Purchase Orders.

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
            'purchase_order_id' => $_GET['purchase_order_id'] ?? null,
            'date_from'         => $_GET['date_from'] ?? null,
            'date_to'           => $_GET['date_to'] ?? null,
        ];
        // Use the new function to search for purchase orders
        $purchase_orders = search_purchase_orders($filters);
        echo json_encode($purchase_orders);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Initial page load - get all recent purchase orders
$initial_purchase_orders = search_purchase_orders();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Purchase Order List</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/index.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
                    </a>
                    <a href="/modules/purchase/entry_purchase_order.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> New Purchase Order
                    </a>
                </div>
            </div>

            <!-- Search Filters -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-search"></i> Find Purchase Orders</div>
                <div class="card-body">
                    <form id="poSearchForm" class="row gx-3 gy-2 align-items-center">
                        <div class="col-md-4">
                            <label for="search_po_id" class="visually-hidden">PO ID</label>
                            <input type="text" class="form-control" id="search_po_id" placeholder="Search by PO ID...">
                        </div>
                        <div class="col-md-4">
                             <label for="search_date_range" class="visually-hidden">Date Range</label>
                            <input type="text" class="form-control" id="search_date_range" placeholder="Select Date Range">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Purchase Order List Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>PO ID</th>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="purchaseOrderListTableBody">
                                <?php if (empty($initial_purchase_orders)): ?>
                                    <tr><td colspan="6" class="text-center text-muted">No Purchase Orders found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($initial_purchase_orders as $po): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($po['purchase_order_id']) ?></td>
                                            <td><?= htmlspecialchars(date("d-m-Y", strtotime($po['po_date']))) ?></td>
                                            <td><?= htmlspecialchars($po['supplier_name']) ?></td>
                                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($po['status']) ?></span></td>
                                            <td><?= htmlspecialchars($po['created_by_name']) ?></td>
                                            <td>
                                                <a href="/modules/purchase/entry_purchase_order.php?purchase_order_id=<?= htmlspecialchars($po['purchase_order_id']) ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
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