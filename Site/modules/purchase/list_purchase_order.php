<?php
// File: /modules/purchase/list_purchase_order.php
// FINAL VALIDATED version with corrected AJAX endpoint.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// --- AJAX Endpoint for Live Search ---
// THIS IS THE CRITICAL FIX: Ensure the script stops completely after sending JSON.
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    $filters = [
        'purchase_order_id' => $_GET['purchase_order_id'] ?? null,
        'status'            => $_GET['status'] ?? null,
        'date_from'         => $_GET['date_from'] ?? null,
        'date_to'           => $_GET['date_to'] ?? null,
    ];
    try {
        $purchase_orders = search_purchase_orders($filters);
        echo json_encode($purchase_orders);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    die(); // Use die() for a more forceful exit than exit;
}

// --- Initial Page Load ---
$initial_purchase_orders = search_purchase_orders();
$all_categories = get_all_categories(); // Assuming you might need this for filters later

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Purchase Order List</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/index.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left-circle"></i> Back to Dashboard</a>
                    <a href="/modules/purchase/entry_purchase_order.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> New Purchase Order</a>
                </div>
            </div>
            
            <?php
            if (isset($_SESSION['success_message'])) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            ?>

            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-search"></i> Find Purchase Orders</div>
                <div class="card-body">
                    <form id="poSearchForm" class="row gx-3 gy-2 align-items-center">
                        <div class="col-md-4"><input type="text" class="form-control" id="search_po_id" placeholder="Search by PO ID..."></div>
                        <div class="col-md-4">
                            <select id="search_status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="Draft">Draft</option>
                                <option value="Ordered">Ordered</option>
                                <option value="Paid">Paid</option>
                                <option value="Delivered">Delivered</option>
                                <option value="With int courier">With int courier</option>
                                <option value="Received">Received</option>
                                <option value="Canceled">Canceled</option>
                            </select>
                        </div>
                        <div class="col-md-4"><input type="text" class="form-control" id="search_date_range" placeholder="Select Date Range"></div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>PO ID</th>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Linked Order</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="purchaseOrderListTableBody">
                                <!-- The initial list is now rendered by JavaScript for consistency -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>