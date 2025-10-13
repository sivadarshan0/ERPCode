<?php
// File: /modules/sales/entry_order.php
// FINAL VALIDATED version: Includes all backdating UI elements and logic.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_acc.php';


require_login();

// --- AJAX Endpoints ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_GET['action']) {
            case 'customer_lookup':
                echo json_encode(search_customers_by_phone(trim($_GET['query'] ?? '')));
                break;
            case 'item_lookup':
                $name = trim($_GET['query'] ?? '');
                $type = $_GET['type'] ?? 'Ex-Stock';
                echo json_encode(strlen($name) >= 2 ? ($type === 'Pre-Book' ? search_items_for_prebook($name) : search_items_for_order($name)) : []);
                break;
            case 'get_item_stock_details':
                echo json_encode(get_item_stock_details($_GET['item_id'] ?? null)); 
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

require_login();
$db = db();

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
    $action = $_POST['action'] ?? ($is_edit ? 'update_header' : 'create_order');
    try {
        if ($action === 'update_items' && $is_edit) {
            $db->begin_transaction();
            $order_id_to_update = $_POST['order_id'];
            $items_data = $_POST['items'] ?? [];
            $new_total_amount = 0;

            if (empty($items_data['order_item_id'])) {
                throw new Exception("No items found to update.");
            }

            $stmt_update_item = $db->prepare("UPDATE order_items SET price = ?, quantity = ?, profit_margin = ? WHERE order_item_id = ?");
            if (!$stmt_update_item) throw new Exception("DB prepare failed for item update.");

            foreach ($items_data['order_item_id'] as $index => $order_item_id) {
                $price = (float)($items_data['price'][$index] ?? 0);
                $quantity = (float)($items_data['quantity'][$index] ?? 0);
                $cost = (float)($items_data['cost'][$index] ?? 0);
                $margin = ($cost > 0 && $price > 0) ? (($price / $cost) - 1) * 100 : 0;
                
                $stmt_update_item->bind_param("dddi", $price, $quantity, $margin, $order_item_id);
                $stmt_update_item->execute();
                $new_total_amount += $price * $quantity;
            }

            $stmt_update_total = $db->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
            if (!$stmt_update_total) throw new Exception("DB prepare failed for total update.");
            $stmt_update_total->bind_param("ds", $new_total_amount, $order_id_to_update);
            $stmt_update_total->execute();

            $db->commit();
            $_SESSION['success_message'] = "✅ Order items and total have been successfully updated.";
            header("Location: entry_order.php?order_id=" . $order_id_to_update);
            exit;

        } elseif ($action === 'update_header' && $is_edit) {
            $order_id_to_update = $_POST['order_id'];
            $details_to_update = [
                'order_status'   => $_POST['order_status'] ?? $order['status'],
                'payment_method' => $_POST['payment_method'] ?? $order['payment_method'],
                'payment_status' => $_POST['payment_status'] ?? $order['payment_status'],
                'other_expenses' => $_POST['other_expenses'] ?? $order['other_expenses'],
                'remarks'        => $_POST['remarks'] ?? $order['remarks']
            ];
            update_order_details($order_id_to_update, $details_to_update, $_POST);
            $_SESSION['success_message'] = "✅ Order details successfully updated.";
            header("Location: entry_order.php?order_id=" . $order_id_to_update);
            exit;

        } elseif ($action === 'create_order') {
            $order_details = [
                'payment_method' => $_POST['payment_method'] ?? 'COD', 'payment_status' => $_POST['payment_status'] ?? 'Pending',
                'order_status'   => $_POST['order_status'] ?? 'New', 'stock_type'     => $_POST['stock_type'] ?? 'Ex-Stock',
                'remarks'        => $_POST['remarks'] ?? '', 'other_expenses' => $_POST['other_expenses'] ?? 0
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
        if (isset($_POST['customer_id'])) $order['customer'] = get_customer($_POST['customer_id']);
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
                            <div class="col-md-6 position-relative"><label for="customer_search" class="form-label">Search Customer by Phone *</label><input type="text" class="form-control" id="customer_search" required autocomplete="off" value="<?= $is_edit ? htmlspecialchars($order['customer']['phone']) : '' ?>" <?= $is_edit ? 'readonly' : '' ?>><div id="customerResults" class="list-group mt-1 position-absolute w-100 d-none" style="z-index: 1000;"></div><input type="hidden" name="customer_id" id="customer_id" value="<?= $is_edit ? htmlspecialchars($order['customer_id']) : '' ?>"></div>
                            <div class="col-md-6"><label class="form-label">Selected Customer</label><div id="selected_customer_display" class="form-control-plaintext fw-bold"><?= $is_edit ? htmlspecialchars($order['customer']['name']) . ' (ID: ' . htmlspecialchars($order['customer_id']) . ')' : 'None' ?></div></div>
                            <div class="col-md-4"><label for="stock_type" class="form-label">Stock Type</label><select name="stock_type" id="stock_type" class="form-select" <?= $is_edit ? 'disabled' : '' ?>><option value="Ex-Stock" <?= ($is_edit && $order['stock_type'] == 'Ex-Stock') ? 'selected' : '' ?>>Ex-Stock</option><option value="Pre-Book" <?= ($is_edit && $order['stock_type'] == 'Pre-Book') ? 'selected' : '' ?>>Pre-Book</option></select></div>
                            <div class="col-md-4"><label for="payment_method" class="form-label">Payment Method</label><select name="payment_method" id="payment_method" class="form-select"><option value="COD" <?= ($is_edit && $order['payment_method'] == 'COD') ? 'selected' : '' ?>>COD</option><option value="BT" <?= ($is_edit && $order['payment_method'] == 'BT') ? 'selected' : '' ?>>Bank Transfer</option></select></div>
                            <div class="col-md-4"><label for="payment_status" class="form-label">Payment Status</label><select name="payment_status" id="payment_status" class="form-select"><option value="Pending" <?= ($is_edit && $order['payment_status'] == 'Pending') ? 'selected' : '' ?>>Pending</option><option value="Received" <?= ($is_edit && $order['payment_status'] == 'Received') ? 'selected' : '' ?>>Received</option></select></div>
                            <div class="col-md-4 d-none" id="payment_status_date_wrapper"><label for="payment_status_event_date" class="form-label">Payment Date</label><input type="datetime-local" class="form-control" id="payment_status_event_date" name="payment_status_event_date"></div>
                            <div class="col-md-4"><label for="order_date" class="form-label">Order Date *</label><input type="date" class="form-control" id="order_date" name="order_date" value="<?= $is_edit ? htmlspecialchars($order['order_date']) : date('Y-m-d') ?>" required <?= $is_edit ? 'readonly' : '' ?>></div>
                            <div class="col-md-8"><label for="remarks" class="form-label">Remarks</label><input type="text" class="form-control" id="remarks" name="remarks" placeholder="e.g., Delivery notes..." value="<?= $is_edit ? htmlspecialchars($order['remarks']) : '' ?>"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Right Column -->
            <div class="col-lg-4">
                 <div class="card h-100">
                     <div class="card-header">2. Status & Totals</div>
                     <div class="card-body">
                         <div class="mb-3"><label for="order_status" class="form-label">Order Status</label><select name="order_status" id="order_status" class="form-select"><option value="New" <?= ($is_edit && $order['status'] == 'New') ? 'selected' : '' ?>>New</option><option value="Processing" <?= ($is_edit && $order['status'] == 'Processing') ? 'selected' : '' ?>>Processing</option><option value="Awaiting Stock" <?= ($is_edit && $order['status'] == 'Awaiting Stock') ? 'selected' : '' ?>>Awaiting Stock</option><option value="With Courier" <?= ($is_edit && $order['status'] == 'With Courier') ? 'selected' : '' ?>>With Courier</option><option value="Delivered" <?= ($is_edit && $order['status'] == 'Delivered') ? 'selected' : '' ?>>Delivered</option><option value="Canceled" <?= ($is_edit && $order['status'] == 'Canceled') ? 'selected' : '' ?>>Canceled</option></select></div>
                        <div class="mb-3 d-none" id="order_status_date_wrapper"><label for="order_status_event_date" class="form-label">Status Date</label><input type="datetime-local" class="form-control" id="order_status_event_date" name="order_status_event_date"></div>
                         <div class="mb-3"><label for="other_expenses" class="form-label">Other Expenses</label><input type="number" class="form-control" id="other_expenses" name="other_expenses" value="<?= $is_edit ? htmlspecialchars($order['other_expenses'] ?? '0.00') : '0.00' ?>" min="0.00" step="0.01"></div>
                         <hr>
                         <!-- CORRECTED ID -->
                         <h3 class="text-end">Total: <span id="orderTotalDisplay">0.00</span></h3>
                     </div>
                 </div>
            </div>
        </div>

        <!-- Items Section -->
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center"><span>3. Items</span><?php if (!$is_edit): ?><button type="button" class="btn btn-sm btn-success" id="addItemRow"><i class="bi bi-plus-circle"></i> Add Item</button><?php endif; ?></div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th class="w-25">Item</th><th>UOM</th><th class="stock-col">Stock</th><th class="cost-col">Cost Price</th><th>Margin %</th><th>Sell Price</th><th>Quantity</th><th class="text-end">Subtotal</th><?php if (!$is_edit): ?><th></th><?php endif; ?></tr></thead>
                        <tbody id="orderItemRows">
                            <?php if ($is_edit && !empty($order['items'])): ?>
                                <?php foreach ($order['items'] as $item): ?>
                                    <tr class="order-item-row">
                                        <input type="hidden" name="items[order_item_id][]" value="<?= htmlspecialchars($item['order_item_id']) ?>">
                                        <input type="hidden" name="items[id][]" class="item-id-input" value="<?= htmlspecialchars($item['item_id']) ?>">
                                        <input type="hidden" name="items[cost][]" class="cost-input" value="<?= htmlspecialchars($item['cost_price']) ?>">
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($item['uom']) ?></td>
                                        <td class="stock-col"><?= htmlspecialchars(number_format($item['stock_on_hand'], 2)) ?></td>
                                        <td class="cost-col"><?= htmlspecialchars(number_format($item['cost_price'], 2)) ?></td>
                                        <td><input type="text" class="form-control-plaintext form-control-sm text-end margin-display" value="<?= htmlspecialchars(number_format($item['profit_margin'], 2)) ?>" readonly></td>
                                        <td>
                                            <?php if ($is_edit && isset($order['status']) && $order['status'] === 'With Courier'): ?>
                                                <input type="number" class="form-control form-control-sm text-end price-input" name="items[price][]" value="<?= htmlspecialchars($item['price']) ?>" min="0.00" step="0.01" required>
                                            <?php else: ?>
                                                <?= htmlspecialchars(number_format($item['price'], 2)) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_edit && isset($order['status']) && $order['status'] === 'With Courier'): ?>
                                                 <input type="number" class="form-control form-control-sm text-end quantity-input" name="items[quantity][]" value="<?= htmlspecialchars($item['quantity']) ?>" min="1" step="1" required>
                                            <?php else: ?>
                                                <?= htmlspecialchars(number_format($item['quantity'], 2)) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold subtotal-display">0.00</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody> 
                        <?php if($is_edit): ?>
                        <tfoot>
                            <tr>
                                <th colspan="7" class="text-end border-0">Items Total:</th>
                                <!-- CORRECTED ID -->
                                <th id="itemsTotalDisplay" class="text-end border-0">0.00</th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div><!-- THIS DIV WAS MISSING ITS CLOSING TAG -->
            </div>
        </div>
        
        <!-- History Sections (Unchanged) -->
        <?php if ($is_edit && !empty($order['status_history'])): ?>
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-clock-history"></i> Order Status History</div>
            <div class="card-body"><ul class="list-group list-group-flush"><?php foreach ($order['status_history'] as $history): ?><li class="list-group-item d-flex justify-content-between align-items-center"><div>Status set to <strong><?= htmlspecialchars($history['status']) ?></strong><small class="d-block text-muted">by <?= htmlspecialchars($history['created_by_name']) ?></small></div><span class="badge bg-secondary rounded-pill"><?= date("d-M-Y h:i A", strtotime($history['event_date'] ?? $history['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
        </div>
        <?php endif; ?>
        <?php if ($is_edit && !empty($order['payment_history'])): ?>
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-credit-card"></i> Payment Status History</div>
            <div class="card-body"><ul class="list-group list-group-flush"><?php foreach ($order['payment_history'] as $history): ?><li class="list-group-item d-flex justify-content-between align-items-center"><div>Payment Status set to <strong><?= htmlspecialchars($history['payment_status']) ?></strong><small class="d-block text-muted">by <?= htmlspecialchars($history['created_by_name']) ?></small></div><span class="badge bg-secondary rounded-pill"><?= date("d-M-Y h:i A", strtotime($history['event_date'] ?? $history['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
        </div>
        <?php endif; ?>
        
        <!-- CORRECTED BUTTON LAYOUT -->
        <div class="col-12 mt-4">
            <?php if ($is_edit): ?>
                <button class="btn btn-primary me-2" type="submit" name="action" value="update_header"><i class="bi bi-floppy"></i> Update Order Details</button>
                <?php if (isset($order['status']) && $order['status'] === 'With Courier'): ?>
                    <button class="btn btn-success me-2" type="submit" name="action" value="update_items"><i class="bi bi-save"></i> Update Items & Recalculate</button>
                <?php endif; ?>
            <?php else: ?>
                <button class="btn btn-primary me-2" type="submit" name="action" value="create_order"><i class="bi bi-save"></i> Create Order & Update Stock</button>
            <?php endif; ?>
            <a href="/index.php" class="btn btn-outline-secondary">Back</a>
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