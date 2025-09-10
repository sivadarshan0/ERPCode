<?php
// File: /modules/inventory/view_item.php
// This is the public-facing "profile" page for a single item.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// Get the item ID from the URL. Redirect if it's missing.
$item_id = $_GET['item_id'] ?? null;
if (!$item_id) {
    header("Location: /modules/inventory/list_stock.php");
    exit;
}

// Fetch all the details for this item using our powerful function.
$item = get_item_details($item_id);

// If the item doesn't exist, redirect with an error message.
if (!$item) {
    $_SESSION['error_message'] = "Item not found.";
    header("Location: /modules/inventory/list_stock.php");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>View Item <span class="badge bg-secondary"><?= htmlspecialchars($item['item_id']) ?></span></h2>
        <div>
            <a href="/modules/inventory/list_stock.php" class="btn btn-outline-secondary">
                <i class="bi bi-card-list"></i> Back to Stock List
            </a>
            <a href="/modules/inventory/entry_item.php?item_id=<?= htmlspecialchars($item['item_id']) ?>" class="btn btn-primary">
                <i class="bi bi-pencil-square"></i> Manage Item
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Image Gallery -->
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Images</div>
                <div class="card-body">
                    <?php if (empty($item['images'])): ?>
                        <div class="text-center text-muted p-4">
                            <i class="bi bi-image-alt fs-1"></i>
                            <p>No images available for this item.</p>
                        </div>
                    <?php else: ?>
                        <!-- Main Image Viewer -->
                        <div class="mb-3 text-center">
                            <img src="<?= htmlspecialchars($item['main_image_path'] ?? $item['images'][0]) ?>" 
                                 id="mainImageView" 
                                 class="img-fluid rounded" 
                                 style="max-height: 400px; object-fit: contain;" 
                                 alt="Main item image">
                        </div>
                        <!-- Thumbnails -->
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <?php foreach ($item['images'] as $image_url): ?>
                                <img src="<?= htmlspecialchars($image_url) ?>" 
                                     class="img-thumbnail item-thumbnail" 
                                     style="height: 60px; width: 60px; object-fit: cover; cursor: pointer;" 
                                     alt="Item thumbnail">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Details & Price History -->
        <div class="col-md-7">
            <!-- Core Details Card -->
            <div class="card mb-4">
                <div class="card-header">Item Details</div>
                <div class="card-body">
                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="text-muted"><?= htmlspecialchars($item['description'] ?: 'No description provided.') ?></p>
                    <table class="table table-sm table-bordered">
                        <tr>
                            <th style="width: 30%;">Category</th>
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Sub-Category</th>
                            <td><?= htmlspecialchars($item['sub_category_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Unit of Measure (UOM)</th>
                            <td><?= htmlspecialchars($item['uom']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Price History Card -->
            <div class="card">
                <div class="card-header">Sales Price History</div>
                <div class="card-body">
                    <?php if (empty($item['price_history'])): ?>
                        <p class="text-muted">This item has not been sold yet.</p>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 300px;">
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date Sold</th>
                                        <th>Customer</th>
                                        <th class="text-end">Cost Price</th>
                                        <th class="text-end">Sell Price</th>
                                        <th>Order ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($item['price_history'] as $history): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date("d-m-Y", strtotime($history['order_date']))) ?></td>
                                        <td><?= htmlspecialchars($history['customer_name']) ?></td>
                                        <td class="text-end"><?= number_format($history['cost_price'], 2) ?></td>
                                        <td class="text-end fw-bold"><?= number_format($history['sell_price'], 2) ?></td>
                                        <td>
                                            <a href="/modules/sales/entry_order.php?order_id=<?= htmlspecialchars($history['order_id']) ?>" target="_blank">
                                                <?= htmlspecialchars($history['order_id']) ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>