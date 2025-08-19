<?php
// File: /modules/purchase/entry_grn.php
// FINAL version with all buttons and correct UI.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// --- AJAX Endpoint for Item Search ---
if (isset($_GET['item_lookup'])) {
    header('Content-Type: application/json');
    try {
        $name = trim($_GET['item_lookup']);
        echo json_encode(strlen($name) >= 2 ? search_items_by_name($name) : []);
    } catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

$message = '';
$message_type = '';
$is_edit = false;
$grn = null;

if (isset($_GET['grn_id'])) {
    $is_edit = true;
    // Future logic: $grn = get_grn_details($_GET['grn_id']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_edit) {
    try {
        $grn_date = $_POST['grn_date'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        $items_to_process = [];
        foreach ($_POST['items']['id'] ?? [] as $index => $item_id) {
            if (!empty($item_id)) {
                $items_to_process[] = [
                    'item_id'  => $item_id,
                    'uom'      => $_POST['items']['uom'][$index],
                    'quantity' => $_POST['items']['quantity'][$index],
                    'cost'     => $_POST['items']['cost'][$index],
                    'weight'   => $_POST['items']['weight'][$index]
                ];
            }
        }
        
        $new_grn_id = process_grn($grn_date, $items_to_process, $remarks);
        
        $_SESSION['success_message'] = "✅ GRN #$new_grn_id successfully created and stock updated.";
        header("Location: entry_grn.php?grn_id=" . $new_grn_id);
        exit;

    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2><?= $is_edit ? 'View GRN' : 'New Goods Received Note (GRN)' ?></h2>
            </div>

            <?php if ($message): ?>
            <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate id="grnForm">
                <div class="card">
                    <div class="card-header">GRN Details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label for="grn_date" class="form-label">GRN Date *</label><input type="date" class="form-control" id="grn_date" name="grn_date" value="<?= date('Y-m-d') ?>" required <?= $is_edit ? 'readonly' : '' ?>></div>
                            <div class="col-md-8"><label for="remarks" class="form-label">Remarks</label><input type="text" class="form-control" id="remarks" name="remarks" placeholder="e.g., Supplier invoice number..." <?= $is_edit ? 'readonly' : '' ?>></div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Items Received</span>
                        <?php if (!$is_edit): ?><button type="button" class="btn btn-sm btn-success" id="addItemRow"><i class="bi bi-plus-circle"></i> Add Another Item</button><?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive"><table class="table table-sm"><thead><tr><th style="width: 40%;">Item *</th><th style="width: 15%;">UOM *</th><th style="width: 15%;">Quantity *</th><th style="width: 15%;">Cost *</th><th style="width: 15%;">Weight (g) *</th><?php if (!$is_edit): ?><th>Actions</th><?php endif; ?></tr></thead><tbody id="grnItemRows"></tbody></table></div>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <?php if (!$is_edit): ?>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save GRN & Update Stock</button>
                    <?php endif; ?>
                    
                    <!-- CORRECTED: Added GRN List button -->
                    <a href="/modules/purchase/list_grns.php" class="btn btn-secondary">GRN List</a>
                    <a href="/index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                </div>
            </form>
        </main>
    </div>
</div>

<template id="grnItemRowTemplate">
    <tr class="grn-item-row">
        <td class="position-relative"><input type="hidden" name="items[id][]" class="item-id-input"><input type="text" class="form-control item-search-input" placeholder="Start typing item..." required><div class="item-results list-group mt-1 position-absolute w-100 d-none" style="z-index: 100;"></div></td>
        <td><input type="text" class="form-control uom-input" name="items[uom][]" value="No" required></td>
        <td><input type="number" class="form-control quantity-input" name="items[quantity][]" value="1" min="1" step="1" required></td>
        <td><input type="number" class="form-control cost-input" name="items[cost][]" value="0.00" min="0.00" step="0.01" required></td>
        <td><input type="number" class="form-control weight-input" name="items[weight][]" value="0" min="0" step="1" required></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-item-row"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>