<?php
// File: /modules/purchase/entry_grn.php
// FINAL version with Create/View modes, CSRF protection, and UI enhancements.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// --- CSRF Token Generation for this page load ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- AJAX Endpoints: This block MUST be at the top ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_GET['action']) {
            case 'item_lookup':
                // This endpoint does not need login or CSRF check as it's for searching public item data
                $name = trim($_GET['query'] ?? '');
                echo json_encode(strlen($name) >= 2 ? search_items_by_name($name) : []);
                break;

            case 'cancel_grn':
                file_put_contents('/var/www/html/logs/grn_cancel_debug.log', "POST DATA: " . print_r($_POST, true) . "\nSESSION DATA: " . print_r($_SESSION, true), FILE_APPEND);
                // Cancellation is a sensitive action, so we start the session and check login/CSRF
                if (!is_logged_in()) {
                    throw new Exception('You must be logged in to perform this action.');
                }
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method.');
                }
                if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                    throw new Exception('Invalid security token. Please refresh the page and try again.');
                }
                
                $grn_id = $_POST['grn_id'] ?? null;
                if (!$grn_id) {
                    throw new Exception('GRN ID is missing.');
                }
                
                cancel_grn($grn_id);
                
                echo json_encode(['success' => true, 'message' => "GRN #$grn_id has been successfully canceled and stock reversed."]);
                break;
        }
    } catch (Exception $e) { 
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]); 
    }
    exit;
}

// --- Standard Page Logic ---
require_login();

$message = '';
$message_type = '';
$is_view_mode = false;
$grn = null;

// Check for View Mode
if (isset($_GET['grn_id'])) {
    $grn_id_to_load = trim($_GET['grn_id']);
    $grn = get_grn_details($grn_id_to_load);
    if ($grn) {
        $is_view_mode = true;
    } else {
        $_SESSION['error_message'] = "GRN #$grn_id_to_load not found.";
        header("Location: /modules/purchase/list_grns.php");
        exit;
    }
}

// Handle Form Submission (Create Mode Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_view_mode) {
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

// Handle session messages after a redirect
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
                <h2><?= $is_view_mode ? 'View GRN <span class="badge bg-secondary">' . htmlspecialchars($grn['grn_id']) . '</span>' : 'New Goods Received Note (GRN)' ?></h2>
            </div>

            <?php if ($message): ?>
            <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate id="grnForm">
                <div class="card">
                    <div class="card-header">GRN Details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">GRN Date *</label>
                                <?php if ($is_view_mode): ?>
                                    <p class="form-control-plaintext"><?= htmlspecialchars(date("d-m-Y", strtotime($grn['grn_date']))) ?></p>
                                <?php else: ?>
                                    <input type="date" class="form-control" id="grn_date" name="grn_date" value="<?= date('Y-m-d') ?>" required>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Remarks</label>
                                <?php if ($is_view_mode): ?>
                                     <p class="form-control-plaintext"><?= htmlspecialchars($grn['remarks'] ?: 'N/A') ?></p>
                                <?php else: ?>
                                    <input type="text" class="form-control" id="remarks" name="remarks" placeholder="e.g., Supplier invoice number...">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Items Received</span>
                        <?php if (!$is_view_mode): ?>
                            <button type="button" class="btn btn-sm btn-success" id="addItemRow"><i class="bi bi-plus-circle"></i> Add Another Item</button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Item</th>
                                        <th style="width: 15%;">UOM</th>
                                        <th class="text-end" style="width: 15%;">Quantity</th>
                                        <th class="text-end" style="width: 15%;">Cost</th>
                                        <th class="text-end" style="width: 15%;">Weight (g)</th>
                                        <?php if (!$is_view_mode): ?><th style="width: 5%;"></th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody id="grnItemRows">
                                    <?php if ($is_view_mode && !empty($grn['items'])): ?>
                                        <?php foreach ($grn['items'] as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                <td><?= htmlspecialchars($item['uom']) ?></td>
                                                <td class="text-end"><?= htmlspecialchars(number_format($item['quantity'], 2)) ?></td>
                                                <td class="text-end"><?= htmlspecialchars(number_format($item['cost'], 2)) ?></td>
                                                <td class="text-end"><?= htmlspecialchars(number_format($item['weight'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-4 d-flex justify-content-between align-items-center">
                    <div>
                        <?php if (!$is_view_mode): ?>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save GRN & Update Stock</button>
                        <?php endif; ?>
                        <a href="/modules/purchase/list_grns.php" class="btn btn-secondary">GRN List</a>
                        <a href="/index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                    </div>
                    <div>
                        <?php if ($is_view_mode && $grn['status'] === 'Posted'): ?>
                            <button type="button" class="btn btn-danger" id="cancelGrnBtn" 
                                    data-grn-id="<?= htmlspecialchars($grn['grn_id']) ?>" 
                                    data-csrf-token="<?= htmlspecialchars($csrf_token) ?>">
                                <i class="bi bi-x-circle"></i> Cancel GRN
                            </button>
                        <?php elseif ($is_view_mode): ?>
                            <p class="mb-0 text-muted fw-bold">Status: <span class="badge bg-warning text-dark"><?= htmlspecialchars($grn['status']) ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<?php if (!$is_view_mode): // Only include the template in create mode ?>
<template id="grnItemRowTemplate">
    <tr class="grn-item-row">
        <td class="position-relative">
            <input type="hidden" name="items[id][]" class="item-id-input">
            <input type="text" class="form-control item-search-input" placeholder="Start typing item..." required>
            <div class="item-results list-group mt-1 position-absolute w-100 d-none" style="z-index: 100;"></div>
        </td>
        <td><input type="text" class="form-control uom-input" name="items[uom][]" value="No" required></td>
        <td><input type="number" class="form-control quantity-input" name="items[quantity][]" value="1" min="1" step="1" required></td>
        <td><input type="number" class="form-control cost-input" name="items[cost][]" value="0.00" min="0.00" step="0.01" required></td>
        <td><input type="number" class="form-control weight-input" name="items[weight][]" value="0" min="0" step="1" required></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-item-row"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>