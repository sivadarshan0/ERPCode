<?php
// File: /modules/purchase/entry_purchase_order.php
// FINAL VALIDATED version with multi-order linking and backdating.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// --- AJAX Endpoints ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_GET['action']) {
            case 'item_lookup':
                $name = trim($_GET['query'] ?? '');
                echo json_encode(strlen($name) >= 2 ? search_items_by_name($name) : []);
                break;
            case 'pre_order_lookup':
                $query = trim($_GET['query'] ?? '');
                echo json_encode(strlen($query) >= 2 ? search_open_pre_orders($query) : []);
                break;
        }
    } catch (Exception $e) { 
        http_response_code(500); 
        echo json_encode(['error' => $e->getMessage()]); 
    }
    exit;
}

$message = '';
$message_type = '';
$is_edit = false;
$po = null;

if (isset($_GET['purchase_order_id'])) {
    $po_id_to_load = trim($_GET['purchase_order_id']);
    $po = get_purchase_order_details($po_id_to_load);
    if ($po) {
        $is_edit = true;
    } else {
        $_SESSION['error_message'] = "Purchase Order #$po_id_to_load not found.";
        header("Location: /modules/purchase/list_purchase_order.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($is_edit) {
            $po_id_to_update = $_POST['purchase_order_id'] ?? '';
            $details_to_update = [
                'status'        => $_POST['status'] ?? 'Draft',
                'supplier_name' => $_POST['supplier_name'] ?? '',
                'remarks'       => $_POST['remarks'] ?? ''
            ];
            $update_result = update_purchase_order_details($po_id_to_update, $details_to_update, $_POST);
            if ($update_result['success']) {
                $_SESSION['success_message'] = $update_result['message'];
                header("Location: /modules/purchase/list_purchase_order.php");
                exit;
            }
        } else {
            $po_date = $_POST['po_date'] ?? '';
            $supplier_name = $_POST['supplier_name'] ?? '';
            $status = $_POST['status'] ?? 'Draft';
            $remarks = $_POST['remarks'] ?? '';
            $linked_sales_orders = $_POST['linked_sales_orders'] ?? [];
            
            $item_ids = $_POST['items']['id'] ?? [];
            $quantities = $_POST['items']['quantity'] ?? [];
            $costs = $_POST['items']['cost_price'] ?? [];

            $items_to_process = [];
            foreach ($item_ids as $index => $item_id) {
                if (!empty($item_id)) {
                    $items_to_process[] = ['item_id' => $item_id, 'quantity' => $quantities[$index], 'cost_price' => $costs[$index]];
                }
            }
            
            $new_po_id = process_purchase_order($po_date, $supplier_name, $items_to_process, $remarks, $status, $linked_sales_orders);
            $_SESSION['success_message'] = "✅ Purchase Order #$new_po_id successfully created.";
            header("Location: entry_purchase_order.php");
            exit;
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        $po = array_merge($po ?? [], $_POST);
    }
}

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'danger';
    unset($_SESSION['error_message']);
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2><?= $is_edit ? 'Manage Purchase Order <span class="badge bg-secondary">'.htmlspecialchars($po['purchase_order_id']).'</span>' : 'New Purchase Order' ?></h2>
            </div>

            <?php if ($message): ?>
            <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate id="poForm">
                <?php if($is_edit): ?><input type="hidden" name="purchase_order_id" value="<?= htmlspecialchars($po['purchase_order_id']) ?>"><?php endif; ?>
                
                <div class="card">
                    <div class="card-header">Purchase Order Details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="po_date" class="form-label">PO Date *</label>
                                <input type="date" class="form-control" id="po_date" name="po_date" value="<?= $is_edit ? htmlspecialchars($po['po_date']) : date('Y-m-d') ?>" <?= $is_edit ? 'readonly' : 'required' ?>>
                            </div>
                            <div class="col-md-4">
                                <label for="supplier_name" class="form-label">Supplier Name</label>
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" placeholder="Enter supplier name..." value="<?= htmlspecialchars($po['supplier_name'] ?? '') ?>" <?= $is_edit && !in_array($po['status'], ['Draft', 'Ordered']) ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="Draft" <?= ($is_edit && $po['status'] == 'Draft') ? 'selected' : '' ?>>Draft</option>
                                    <option value="Ordered" <?= ($is_edit && $po['status'] == 'Ordered') ? 'selected' : '' ?>>Ordered</option>
                                    <option value="Paid" <?= ($is_edit && $po['status'] == 'Paid') ? 'selected' : '' ?>>Paid</option>
                                    <option value="Delivered" <?= ($is_edit && $po['status'] == 'Delivered') ? 'selected' : '' ?>>Delivered</option>
                                    <option value="With int courier" <?= ($is_edit && $po['status'] == 'With int courier') ? 'selected' : '' ?>>With int courier</option>
                                    <option value="Received" <?= ($is_edit && $po['status'] == 'Received') ? 'selected' : '' ?>>Received</option>
                                    <option value="Canceled" <?= ($is_edit && $po['status'] == 'Canceled') ? 'selected' : '' ?>>Canceled</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-none" id="po_status_date_wrapper">
                                <label for="po_status_event_date" class="form-label">Status Date</label>
                                <input type="datetime-local" class="form-control" id="po_status_event_date" name="po_status_event_date">
                            </div>

                            <?php if (!$is_edit || in_array($po['status'], ['Draft', 'Ordered'])): ?>
                            <div class="col-12">
                                <label for="pre_order_search" class="form-label">Link to Pre-Order(s)</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" id="pre_order_search" autocomplete="off" placeholder="Search Order ID or Customer to link...">
                                    <div id="preOrderResults" class="list-group mt-1 position-absolute w-100 d-none" style="z-index: 1000;"></div>
                                </div>
                                <div id="linkedOrdersContainer" class="d-flex flex-wrap gap-2 mt-2 border rounded p-2 bg-light" style="min-height: 40px;">
                                    <?php if ($is_edit && !empty($po['linked_sales_orders'])): ?>
                                        <?php foreach ($po['linked_sales_orders'] as $linked_order): ?>
                                            <span class="badge bg-primary d-flex align-items-center">
                                                <input type="hidden" name="linked_sales_orders[]" value="<?= htmlspecialchars($linked_order['sales_order_id']) ?>">
                                                <a href="/modules/sales/entry_order.php?order_id=<?= htmlspecialchars($linked_order['sales_order_id']) ?>" target="_blank" class="text-white text-decoration-none">
                                                    <?= htmlspecialchars($linked_order['sales_order_id']) ?> (<?= htmlspecialchars($linked_order['customer_name']) ?>)
                                                </a>
                                                <button type="button" class="btn-close btn-close-white ms-2 remove-linked-order" aria-label="Remove"></button>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="e.g., Delivery instructions..." <?= $is_edit && !in_array($po['status'], ['Draft', 'Ordered']) ? 'readonly' : '' ?>><?= htmlspecialchars($po['remarks'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Items to Order</span>
                        <?php if (!$is_edit): ?>
                        <button type="button" class="btn btn-sm btn-success" id="addPoItemRow"><i class="bi bi-plus-circle"></i> Add Item</button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th style="width: 50%;">Item</th><th style="width: 20%;">Quantity</th><th style="width: 20%;">Cost Price</th><?php if (!$is_edit): ?><th>Actions</th><?php endif; ?></tr></thead>
                                <tbody id="poItemRows">
                                    <?php if ($is_edit && !empty($po['items'])): ?>
                                        <?php foreach ($po['items'] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                                            <td><?= htmlspecialchars(number_format($item['quantity'], 2)) ?></td>
                                            <td><?= htmlspecialchars(number_format($item['cost_price'], 2)) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if ($is_edit && !empty($po['status_history'])): ?>
                <div class="card mt-4">
                    <div class="card-header"><i class="bi bi-clock-history"></i> Status History</div>
                    <div class="card-body"><ul class="list-group list-group-flush"><?php foreach ($po['status_history'] as $history): ?><li class="list-group-item d-flex justify-content-between align-items-center"><div>Status set to <strong><?= htmlspecialchars($history['status']) ?></strong><small class="d-block text-muted">by <?= htmlspecialchars($history['created_by_name']) ?></small></div><span class="badge bg-secondary rounded-pill"><?= date("d-M-Y h:i A", strtotime($history['event_date'] ?? $history['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
                </div>
                <?php endif; ?>

                <div class="col-12 mt-4">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-<?= $is_edit ? 'floppy' : 'save' ?>"></i> <?= $is_edit ? 'Update Purchase Order' : 'Save Purchase Order' ?></button>
                    <a href="/modules/purchase/list_purchase_order.php" class="btn btn-secondary">PO List</a>
                    <a href="/index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                </div>
            </form>
        </main>
    </div>
</div>

<?php if (!$is_edit): ?>
<template id="poItemRowTemplate">
    <tr class="po-item-row">
        <td class="position-relative"><input type="hidden" name="items[id][]" class="item-id-input"><input type="text" class="form-control form-control-sm item-search-input" placeholder="Start typing item name..." required><div class="item-results list-group mt-1 position-absolute w-100 d-none" style="z-index: 100;"></div></td>
        <td><input type="number" class="form-control form-control-sm quantity-input" name="items[quantity][]" value="1" min="1" step="1" required></td>
        <td><input type="number" class="form-control form-control-sm cost-price-input" name="items[cost_price][]" value="0.00" min="0.00" step="0.01" required></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-item-row"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>