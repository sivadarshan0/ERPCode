<?php
// File: /modules/sales/entry_order.php
// FINAL VALIDATED version: Adds missing customer search elements and corrects form field attributes.

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
    echo json_encode(search_customers_by_phone(trim($_GET['customer_lookup'])));
    exit;
}
if (isset($_GET['item_lookup'])) {
    header('Content-Type: application/json');
    $name = trim($_GET['item_lookup']);
    $type = $_GET['type'] ?? 'Ex-Stock';
    echo json_encode(strlen($name) >= 2 ? ($type === 'Pre-Book' ? search_items_for_prebook($name) : search_items_for_order($name)) : []);
    exit;
}
if (isset($_GET['get_item_details'])) {
    header('Content-Type: application/json');
    $db = db();
    $stmt = $db->prepare("SELECT i.item_id, i.name, i.uom, COALESCE(sl.quantity, 0) as stock FROM items i LEFT JOIN stock_levels sl ON i.item_id = sl.item_id WHERE i.item_id = ?");
    $stmt->bind_param("s", $_GET['get_item_details']);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_assoc());
    exit;
}

$message = '';
$message_type = '';
$is_edit = false;
$order = null;

if (isset($_GET['order_id'])) {
    $order_id_to_load = trim($_GET['order_id']);
    $order = get_order_details($order_id_to_load);
    if ($order) {
        $is_edit = true;
    } else {
        $_SESSION['error_message'] = "Order #$order_id_to_load not found.";
        header("Location: /modules/sales/list_orders.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($is_edit) {
            $order_id_to_update = $_POST['order_id'] ?? '';
            $details_to_update = [
                'order_status'   => $_POST['order_status'] ?? 'New',
                'payment_method' => $_POST['payment_method'] ?? 'COD',
                'payment_status' => $_POST['payment_status'] ?? 'Pending',
                'other_expenses' => $_POST['other_expenses'] ?? 0,
                'remarks'        => $_POST['remarks'] ?? ''
            ];
            if (update_order_details($order_id_to_update, $details_to_update)) {
                $_SESSION['success_message'] = "✅ Order #$order_id_to_update successfully updated.";
                header("Location: entry_order.php?order_id=" . $order_id_to_update);
                exit;
            }
        } else {
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
                    $items_to_process[] = ['item_id' => $item_id, 'quantity' => $_POST['items']['quantity'][$index], 'price' => $_POST['items']['price'][$index], 'cost_price' => $_POST['items']['cost'][$index], 'profit_margin' => $_POST['items']['margin'][$index]];
                }
            }
            $new_order = process_order($_POST['customer_id'], $_POST['order_date'], $items_to_process, $order_details);
            $_SESSION['success_message'] = "✅ Order #{$new_order['id']} created successfully!";
            header("Location: entry_order.php");
            exit;
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        $order = array_merge($order ?? [], $_POST);
        $order['customer'] = get_customer($_POST['customer_id'] ?? '');
    }
}

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2><?= $is_edit ? 'Manage Order <span class="badge bg-secondary">'.htmlspecialchars($order['order_id']).'</span>' : 'Sales Order' ?></h2>
    
    <?php if ($message): ?>
    <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="needs-validation" novalidate id="orderForm">
        <?php if($is_edit): ?><input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>"><?php endif; ?>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">1. Order & Customer Details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6 position-relative">
                                <label for="customer_search" class="form-label">Search Customer by Phone *</label>
                                <input type="text" class="form-control" id="customer_search" required autocomplete="off" value="<?= $is_edit ? htmlspecialchars($order['customer']['phone']) : '' ?>" <?= $is_edit ? 'readonly' : '' ?>>
                                <!-- ADDED: Missing elements for customer search to work -->
                                <div id="customerResults" class="list-group mt-1 position-absolute w-100 d-none" style="z-index: 1000;"></div>
                                <input type="hidden" name="customer_id" id="customer_id" value="<?= $is_edit ? htmlspecialchars($order['customer_id']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Selected Customer</label>
                                <div id="selected_customer_display" class="form-control-plaintext fw-bold"><?= $is_edit ? htmlspecialchars($order['customer']['name']) . ' (ID: ' . htmlspecialchars($order['customer_id']) . ')' : 'None' ?></div>
                            </div>
                            <div class="col-md-4">
                                <label for="stock_type" class="form-label">Stock Type</label>
                                <!-- CORRECTED: Used a conditional to make field readonly in edit mode instead of disabled -->
                                <select name="stock_type" id="stock_type" class="form-select" <?= $is_edit ? 'onclick="return false;" onkeydown="return false;"' : '' ?>>
                                    <option value="Ex-Stock" <?= ($is_edit && $order['stock_type'] == 'Ex-Stock') ? 'selected' : '' ?>>Ex-Stock</option>
                                    <option value="Pre-Book" <?= ($is_edit && $order['stock_type'] == 'Pre-Book') ? 'selected' : '' ?>>Pre-Book</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="COD" <?= ($is_edit && $order['payment_method'] == 'COD') ? 'selected' : '' ?>>COD</option>
                                    <option value="BT" <?= ($is_edit && $order['payment_method'] == 'BT') ? 'selected' : '' ?>>Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" class="form-select">
                                    <option value="Pending" <?= ($is_edit && $order['payment_status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="Received" <?= ($is_edit && $order['payment_status'] == 'Received') ? 'selected' : '' ?>>Received</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Order Date *</label>
                                <input type="date" class="form-control" id="order_date" name="order_date" value="<?= $is_edit ? htmlspecialchars($order['order_date']) : date('Y-m-d') ?>" required <?= $is_edit ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Remarks</label>
                                <input type="text" class="form-control" id="remarks" name="remarks" placeholder="e.g., Delivery notes..." value="<?= $is_edit ? htmlspecialchars($order['remarks']) : '' ?>"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Right Column -->
            <div class="col-lg-4">
                 <div class="card h-100">
                     <div class="card-header">2. Status & Totals</div>
                     <div class="card-body">
                         <div class="mb-3">
                             <label class="form-label">Order Status</label>
                             <select name="order_status" id="order_status" class="form-select">
                                 <option value="New" <?= ($is_edit && $order['status'] == 'New') ? 'selected' : '' ?>>New</option>
                                 <option value="Processing" <?= ($is_edit && $order['status'] == 'Processing') ? 'selected' : '' ?>>Processing</option>
                                 <option value="Awaiting Stock" <?= ($is_edit && $order['status'] == 'Awaiting Stock') ? 'selected' : '' ?>>Awaiting Stock</option>
                                 <option value="With Courier" <?= ($is_edit && $order['status'] == 'With Courier') ? 'selected' : '' ?>>With Courier</option>
                                 <option value="Delivered" <?= ($is_edit && $order['status'] == 'Delivered') ? 'selected' : '' ?>>Delivered</option>
                                 <option value="Canceled" <?= ($is_edit && $order['status'] == 'Canceled') ? 'selected' : '' ?>>Canceled</option>
                             </select>
                         </div>
                         <div class="mb-3">
                             <label class="form-label">Other Expenses</label>
                             <input type="number" class="form-control" id="other_expenses" name="other_expenses" value="<?= $is_edit ? htmlspecialchars($order['other_expenses']) : '0.00' ?>" min="0.00" step="0.01">
                         </div>
                         <hr>
                         <h3 class="text-end">Total: <span id="orderTotal"><?= $is_edit ? htmlspecialchars(number_format($order['total_amount'], 2)) : '0.00' ?></span></h3>
                     </div>
                 </div>
            </div>
        </div>

        <!-- Items Section -->
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center"><span>3. Items</span><?php if (!$is_edit): ?><button type="button" class="btn btn-sm btn-success" id="addItemRow"><i class="bi bi-plus-circle"></i> Add Item</button><?php endif; ?></div>
            <div class="card-body p-2"><div class="table-responsive"><table class="table table-sm"><thead class="table-light"><tr><th class="w-25">Item</th><th>UOM</th><th class="stock-col">Stock</th><th class="cost-col">Cost Price</th><th>Margin %</th><th>Sell Price</th><th>Quantity</th><th class="text-end">Subtotal</th><?php if (!$is_edit): ?><th></th><?php endif; ?></tr></thead><tbody id="orderItemRows"><?php if ($is_edit): ?><?php foreach ($order['items'] as $item): ?>
                <tr class="order-item-row">
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= htmlspecialchars($item['uom']) ?></td>
                    <td class="stock-col"><?= htmlspecialchars($item['stock_on_hand']) ?></td>
                    <td class="cost-col"><?= htmlspecialchars(number_format($item['cost_price'], 2)) ?></td>
                    <td><?= htmlspecialchars(number_format($item['profit_margin'], 2)) ?></td>
                    <td><?= htmlspecialchars(number_format($item['price'], 2)) ?></td>
                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                    <td class="text-end fw-bold"><?= htmlspecialchars(number_format($item['quantity'] * $item['price'], 2)) ?></td>
                </tr>
            <?php endforeach; ?><?php endif; ?></tbody><?php if($is_edit): ?><tfoot><tr><th colspan="7" class="text-end border-0">Items Total:</th><th class="text-end border-0"><?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></th></tr></tfoot><?php endif; ?></table></div></div>
        </div>
        
        <!-- History Sections -->
        <?php if ($is_edit && !empty($order['status_history'])): ?>
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-clock-history"></i> Order Status History</div>
            <div class="card-body"><ul class="list-group list-group-flush"><?php foreach ($order['status_history'] as $history): ?><li class="list-group-item d-flex justify-content-between align-items-center"><div>Status set to <strong><?= htmlspecialchars($history['status']) ?></strong><small class="d-block text-muted">by <?= htmlspecialchars($history['created_by_name']) ?></small></div><span class="badge bg-secondary rounded-pill"><?= date("d-M-Y h:i A", strtotime($history['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
        </div>
        <?php endif; ?>
        <?php if ($is_edit && !empty($order['payment_history'])): ?>
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-credit-card"></i> Payment Status History</div>
            <div class="card-body"><ul class="list-group list-group-flush"><?php foreach ($order['payment_history'] as $history): ?><li class="list-group-item d-flex justify-content-between align-items-center"><div>Payment Status set to <strong><?= htmlspecialchars($history['payment_status']) ?></strong><small class="d-block text-muted">by <?= htmlspecialchars($history['created_by_name']) ?></small></div><span class="badge bg-secondary rounded-pill"><?= date("d-M-Y h:i A", strtotime($history['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
        </div>
        <?php endif; ?>
        
        <div class="col-12 mt-4">
            <button class="btn btn-primary btn-lg" type="submit" id="submitBtn"><i class="bi bi-<?= $is_edit ? 'floppy' : 'save' ?>"></i> <?= $is_edit ? 'Update Order' : 'Create Order & Update Stock' ?></button>
            <a href="/index.php" class="btn btn-outline-secondary btn-lg">Back</a>
        </div>
    </form>
</main>

<template id="orderItemRowTemplate">
    <tr class="order-item-row">
        <td class="position-relative"><input type="hidden" name="items[id][]" class="item-id-input"><input type="hidden" name="items[cost][]" class="cost-input"><input type="text" class="form-control form-control-sm item-search-input" placeholder="Type to search..." required><div class="item-results list-group mt-1 position-absolute w-100 d-none" style="z-index: 100;"></div><div class="stock-warning text-danger small mt-1 d-none">Warning: Insufficient stock!</div></td>
        <td><input type="text" class="form-control form-control-sm uom-display" readonly tabindex="-1"></td>
        <td class="stock-col"><input type="text" class="form-control form-control-sm stock-display" readonly tabindex="-1"></td>
        <td class="cost-col"><input type="number" class="form-control form-control-sm cost-display" min="0.00" step="0.01"></td>
        <td><input type="number" class="form-control form-control-sm margin-input" name="items[margin][]" value="0" step="0.01"></td>
        <td><input type="number" class="form-control form-control-sm price-input" name="items[price][]" min="0.00" step="0.01" required></td>
        <td><input type="number" class="form-control form-control-sm quantity-input" name="items[quantity][]" value="1" min="1" step="1" required></td>
        <td class="text-end subtotal-display fw-bold">0.00</td>
        <td><button type="button" class="btn btn-danger btn-sm remove-item-row" tabindex="-1"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>