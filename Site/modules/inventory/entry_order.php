<?php
// File: /modules/inventory/entry_order.php
// Final version with definitive layout and UI fixes.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// --- AJAX Endpoints ---
if (isset($_GET['customer_lookup'])) {
    header('Content-Type: application/json');
    try {
        $phone = trim($_GET['customer_lookup']);
        echo json_encode(strlen($phone) >= 3 ? search_customers_by_phone($phone) : []);
    } catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }
    exit;
}
if (isset($_GET['item_lookup'])) {
    header('Content-Type: application/json');
    try {
        $name = trim($_GET['item_lookup']);
        $type = $_GET['type'] ?? 'Ex-Stock';
        if ($type === 'Pre-Book') {
            echo json_encode(strlen($name) >= 2 ? search_items_for_prebook($name) : []);
        } else {
            echo json_encode(strlen($name) >= 2 ? search_items_for_order($name) : []);
        }
    } catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $order_details = [
            'payment_method' => $_POST['payment_method'] ?? 'COD',
            'payment_status' => $_POST['payment_status'] ?? 'Pending',
            'order_status'   => $_POST['order_status'] ?? 'New',
            'stock_type'     => $_POST['stock_type'] ?? 'Ex-Stock',
            'remarks'        => $_POST['remarks'] ?? '',
            'other_expenses' => $_POST['other_expenses'] ?? 0
        ];
        $items_to_process = [];
        foreach ($_POST['items']['id'] ?? [] as $index => $item_id) {
            if (!empty($item_id)) {
                $items_to_process[] = [
                    'item_id'       => $item_id,
                    'quantity'      => $_POST['items']['quantity'][$index],
                    'price'         => $_POST['items']['price'][$index],
                    'cost_price'    => $_POST['items']['cost'][$index],
                    'profit_margin' => $_POST['items']['margin'][$index]
                ];
            }
        }
        $new_order = process_order($_POST['customer_id'], $_POST['order_date'], $items_to_process, $order_details);
        $message = "✅ Order #{$new_order['id']} created successfully! Total Amount: {$new_order['total']}";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2>New Sales Order</h2>
    <p>Create a new order, calculate pricing, and update stock levels.</p>

    <?php if ($message): ?>
    <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="needs-validation" novalidate id="orderForm">
        <div class="row">
            <!-- Left Column: Customer & Order Details -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">1. Order & Customer Details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6 position-relative"><label for="customer_search" class="form-label">Search Customer by Phone *</label><input type="text" class="form-control" id="customer_search" required autocomplete="off" placeholder="Enter phone..." tabindex="1"><div id="customerResults" class="list-group mt-1 position-absolute w-100 d-none" style="z-index: 1000;"></div><input type="hidden" name="customer_id" id="customer_id" required><div class="invalid-feedback">Please select a customer.</div></div>
                            <div class="col-md-6"><label class="form-label">Selected Customer</label><div id="selected_customer_display" class="form-control-plaintext fw-bold">None</div></div>
                            <div class="col-md-4"><label for="stock_type" class="form-label">Stock Type</label><select name="stock_type" id="stock_type" class="form-select" tabindex="2"><option value="Ex-Stock">Ex-Stock</option><option value="Pre-Book">Pre-Book</option></select></div>
                            <div class="col-md-4"><label for="payment_method" class="form-label">Payment Method</label><select name="payment_method" class="form-select" tabindex="3"><option value="COD">COD</option><option value="BT">Bank Transfer</option></select></div>
                            <div class="col-md-4"><label for="payment_status" class="form-label">Payment Status</label><select name="payment_status" class="form-select" tabindex="4"><option value="Pending">Pending</option><option value="Received">Received</option></select></div>
                            <div class="col-md-4"><label for="order_date" class="form-label">Order Date *</label><input type="date" class="form-control" id="order_date" name="order_date" value="<?= date('Y-m-d') ?>" required tabindex="5"></div>
                            <div class="col-md-8"><label for="remarks" class="form-label">Remarks</label><input type="text" class="form-control" id="remarks" name="remarks" placeholder="e.g., Delivery notes..." tabindex="6"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Right Column: Status & Totals -->
            <div class="col-lg-4">
                 <div class="card">
                     <div class="card-header">2. Status & Totals</div>
                     <div class="card-body">
                         <div class="mb-3"><label for="order_status" class="form-label">Order Status</label><select name="order_status" class="form-select" tabindex="7"><option value="New">New</option><option value="Processing">Processing</option><option value="With Courier">With Courier</option><option value="Delivered">Delivered</option><option value="Canceled">Canceled</option></select></div>
                         <div class="mb-3"><label for="other_expenses" class="form-label">Other Expenses</label><input type="number" class="form-control" id="other_expenses" name="other_expenses" value="0.00" min="0.00" step="0.01" tabindex="8"></div>
                         <hr>
                         <h3 class="text-end">Total: <span id="orderTotal">0.00</span></h3>
                     </div>
                 </div>
            </div>
        </div>

        <!-- Items Section -->
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center"><span>3. Items</span><button type="button" class="btn btn-sm btn-success" id="addItemRow" tabindex="10"><i class="bi bi-plus-circle"></i> Add Item</button></div>
            <div class="card-body p-2">
                <!-- CORRECTED: Set a min-height on this container div, not the tbody -->
                <div class="table-responsive" style="min-height: 150px;">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <!-- CORRECTED: Removed all inline style="width:%" attributes to allow for a flexible layout -->
                            <tr>
                                <th class="w-25">Item *</th>
                                <th>UOM</th>
                                <th class="stock-col">Stock</th>
                                <th class="cost-col">Cost Price</th>
                                <th>Margin %</th>
                                <th>Sell Price *</th>
                                <th>Quantity *</th>
                                <th class="text-end">Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="orderItemRows"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 mt-4"><button class="btn btn-primary btn-lg" type="submit" tabindex="11"><i class="bi bi-save"></i> Create Order & Update Stock</button></div>
    </form>
</main>

<!-- HTML Template for a single item row -->
<template id="orderItemRowTemplate">
    <tr class="order-item-row">
        <td class="position-relative">
            <input type="hidden" name="items[id][]" class="item-id-input">
            <input type="hidden" name="items[cost][]" class="cost-input">
            <input type="text" class="form-control form-control-sm item-search-input" placeholder="Type to search..." required tabindex="9">
            <div class="item-results list-group mt-1 position-absolute w-100 d-none" style="z-index: 100;"></div>
            <div class="stock-warning text-danger small mt-1 d-none">Warning: Insufficient stock!</div>
        </td>
        <td><input type="text" class="form-control form-control-sm uom-display" readonly></td>
        <td class="stock-col"><input type="text" class="form-control form-control-sm stock-display" readonly></td>
        <td class="cost-col"><input type="number" class="form-control form-control-sm cost-display" min="0.00" step="0.01"></td>
        <td><input type="number" class="form-control form-control-sm margin-input" name="items[margin][]" value="0" step="1"></td>
        <td><input type="number" class="form-control form-control-sm price-input" name="items[price][]" min="0.00" step="0.01" required></td>
        <td><input type="number" class="form-control form-control-sm quantity-input" name="items[quantity][]" value="1" min="1" step="1" required></td>
        <td class="text-end subtotal-display fw-bold">0.00</td>
        <td><button type="button" class="btn btn-danger btn-sm remove-item-row" tabindex="-1"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>