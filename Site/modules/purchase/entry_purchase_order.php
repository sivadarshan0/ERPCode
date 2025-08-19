<?php
// File: /modules/purchase/entry_purchase_order.php
// FINAL version with live search for Pre-Order linking.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// --- AJAX Endpoints ---
if (isset($_GET['item_lookup'])) {
    header('Content-Type: application/json');
    try {
        $name = trim($_GET['item_lookup']);
        echo json_encode(strlen($name) >= 2 ? search_items_by_name($name) : []);
    } catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }
    exit;
}
// Endpoint to search for open Pre-Orders
if (isset($_GET['pre_order_lookup'])) {
    header('Content-Type: application/json');
    try {
        $query = trim($_GET['pre_order_lookup']);
        echo json_encode(strlen($query) >= 2 ? search_open_pre_orders($query) : []);
    } catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }
    exit;
}


$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $po_date = $_POST['po_date'] ?? '';
        $supplier_name = $_POST['supplier_name'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        $linked_sales_order_id = $_POST['linked_sales_order_id'] ?? null;
        $item_ids = $_POST['items']['id'] ?? [];
        $quantities = $_POST['items']['quantity'] ?? [];
        $costs = $_POST['items']['cost_price'] ?? [];

        $items_to_process = [];
        foreach ($item_ids as $index => $item_id) {
            if (!empty($item_id)) {
                $items_to_process[] = ['item_id' => $item_id, 'quantity' => $quantities[$index], 'cost_price' => $costs[$index]];
            }
        }
        
        $new_po_id = process_purchase_order($po_date, $supplier_name, $items_to_process, $remarks);
        
        $message = "✅ Purchase Order #$new_po_id successfully created.";
        $message_type = 'success';

    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>New Purchase Order</h2>
            </div>

            <?php if ($message): ?>
            <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate id="poForm">
                <div class="card">
                    <div class="card-header">Purchase Order Details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="po_date" class="form-label">PO Date *</label>
                                <input type="date" class="form-control" id="po_date" name="po_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label for="supplier_name" class="form-label">Supplier Name</label>
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" placeholder="Enter supplier name...">
                            </div>
                            <!-- CORRECTED: Changed back to a text input for live search -->
                            <div class="col-md-4 position-relative">
                                <label for="pre_order_search" class="form-label">Link to Pre-Order (Optional)</label>
                                <input type="text" class="form-control" id="pre_order_search" autocomplete="off" placeholder="Search Order ID or Customer...">
                                <input type="hidden" name="linked_sales_order_id" id="linked_sales_order_id">
                                <div id="preOrderResults" class="list-group mt-1 position-absolute w-100 d-none" style="z-index: 1000;"></div>
                            </div>
                            <div class="col-12">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="e.g., Delivery instructions..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Items to Order</span>
                        <button type="button" class="btn btn-sm btn-success" id="addPoItemRow"><i class="bi bi-plus-circle"></i> Add Item</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive"><table class="table table-sm"><thead><tr><th style="width: 50%;">Item *</th><th style="width: 20%;">Quantity *</th><th style="width: 20%;">Cost Price *</th><th style="width: 10%;">Actions</th></tr></thead><tbody id="poItemRows"></tbody></table></div>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save Purchase Order</button>
                    <a href="/index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                </div>
            </form>
        </main>
    </div>
</div>

<template id="poItemRowTemplate">
    <tr class="po-item-row">
        <td class="position-relative"><input type="hidden" name="items[id][]" class="item-id-input"><input type="text" class="form-control form-control-sm item-search-input" placeholder="Start typing item name..." required><div class="item-results list-group mt-1 position-absolute w-100 d-none" style="z-index: 100;"></div><div class="invalid-feedback">Please select an item.</div></td>
        <td><input type="number" class="form-control form-control-sm quantity-input" name="items[quantity][]" min="1" step="1" required><div class="invalid-feedback">Req.</div></td>
        <td><input type="number" class="form-control form-control-sm cost-price-input" name="items[cost_price][]" min="0.01" step="0.01" required><div class="invalid-feedback">Req.</div></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-item-row"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>