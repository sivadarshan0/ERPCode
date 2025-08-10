<?php
// File: /modules/inventory/entry_grn.php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// The item search endpoint is already in entry_item.php, we will reuse it.
if (isset($_GET['item_lookup'])) {
    header('Content-Type: application/json');
    try {
        $name = trim($_GET['item_lookup']);
        echo json_encode(strlen($name) >= 2 ? search_items_by_name($name) : []);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // The data will be submitted as arrays
        $grn_date = $_POST['grn_date'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        $item_ids = $_POST['items']['id'] ?? [];
        $uoms = $_POST['items']['uom'] ?? [];
        $quantities = $_POST['items']['quantity'] ?? [];

        // Restructure the POST data into a clean array for our function
        $items_to_process = [];
        foreach ($item_ids as $index => $item_id) {
            if (!empty($item_id)) { // Only process rows with a selected item
                $items_to_process[] = [
                    'item_id'  => $item_id,
                    'uom'      => $uoms[$index],
                    'quantity' => $quantities[$index]
                ];
            }
        }
        
        $new_grn_id = process_grn($grn_date, $items_to_process, $remarks);
        
        $message = "✅ GRN #$new_grn_id successfully created and stock updated.";
        $message_type = 'success';
        // Form will be cleared automatically on success due to no data being preserved.

    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2>Goods Received Note (GRN)</h2>
    <p>Use this form to record new inventory received from a supplier.</p>

    <?php if ($message): ?>
    <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <form method="POST" class="needs-validation" novalidate id="grnForm">
        <div class="card">
            <div class="card-header">
                GRN Details
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="grn_date" class="form-label">GRN Date *</label>
                        <input type="date" class="form-control" id="grn_date" name="grn_date" value="<?= date('Y-m-d') ?>" required>
                        <div class="invalid-feedback">Please provide a GRN date.</div>
                    </div>
                    <div class="col-md-8">
                        <label for="remarks" class="form-label">Remarks</label>
                        <input type="text" class="form-control" id="remarks" name="remarks" placeholder="e.g., Supplier invoice number, delivery details...">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Items Received</span>
                <button type="button" class="btn btn-sm btn-success" id="addItemRow">
                    <i class="bi bi-plus-circle"></i> Add Another Item
                </button>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Item *</th>
                            <th style="width: 20%;">UOM *</th>
                            <th style="width: 20%;">Quantity *</th>
                            <th style="width: 10%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grnItemRows">
                        <!-- Item rows will be dynamically inserted here by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-12 mt-4">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save GRN & Update Stock</button>
            <a href="/index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </form>
</main>

<!-- Hidden template for a new item row -->
<template id="grnItemRowTemplate">
    <tr class="grn-item-row">
        <td class="position-relative">
            <input type="hidden" name="items[id][]" class="item-id-input">
            <input type="text" class="form-control item-search-input" placeholder="Start typing item name..." required>
            <div class="item-results list-group mt-1 position-absolute w-100 d-none" style="z-index: 100;"></div>
            <div class="invalid-feedback">Please select an item.</div>
        </td>
        <td>
            <select class="form-select uom-input" name="items[uom][]" required>
                <option value="No">No</option>
                <option value="Set">Set</option>
                <option value="Pair">Pair</option>
            </select>
        </td>
        <td>
            <input type="number" class="form-control quantity-input" name="items[quantity][]" min="0.01" step="0.01" required>
            <div class="invalid-feedback">Req.</div>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm remove-item-row"><i class="bi bi-trash"></i></button>
        </td>
    </tr>
</template>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>