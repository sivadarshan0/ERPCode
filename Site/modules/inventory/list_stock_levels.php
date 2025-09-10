<?php
// File: /modules/inventory/list_stock_levels.php
// FINAL version with "Actions" column linking to the new view_item.php page.

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
            case 'search':
                $filters = [
                    'item_name'       => $_GET['item_name'] ?? null,
                    'category_id'     => $_GET['category_id'] ?? null,
                    'sub_category_id' => $_GET['sub_category_id'] ?? null,
                    'stock_status'    => $_GET['stock_status'] ?? null,
                ];
                echo json_encode(search_stock_levels($filters));
                break;
            
            case 'get_sub_categories':
                $category_id = $_GET['category_id'] ?? null;
                if ($category_id) {
                    echo json_encode(get_sub_categories_by_category_id($category_id));
                } else {
                    echo json_encode([]);
                }
                break;
        }
    } catch (Exception $e) { 
        http_response_code(500); 
        echo json_encode(['error' => $e->getMessage()]); 
    }
    exit;
}

// Initial page load data
$initial_stock_levels = search_stock_levels();
$all_categories = get_all_categories();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Stock Levels</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Search Filters -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-search"></i> Find Stock</div>
                <div class="card-body">
                    <form id="stockSearchForm" class="row gx-3 gy-2 align-items-center">
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="search_item_name" placeholder="Search by Item Name...">
                        </div>
                        <div class="col-md-3">
                            <select id="search_category_id" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($all_categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['category_id']) ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="search_sub_category_id" class="form-select" disabled>
                                <option value="">All Sub-Categories</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="search_stock_status" class="form-select">
                                <option value="">All Stock Statuses</option>
                                <option value="in_stock">In Stock</option>
                                <option value="out_of_stock">Out of Stock</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stock List Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Item ID</th>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Sub-Category</th>
                                    <th class="text-end">Quantity On Hand</th>
                                    <!-- NEW: Actions column header -->
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="stockListTableBody">
                                <?php if (empty($initial_stock_levels)): ?>
                                    <!-- Colspan updated to 6 -->
                                    <tr><td colspan="6" class="text-center text-muted">No items found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($initial_stock_levels as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['item_id']) ?></td>
                                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                                            <td><?= htmlspecialchars($item['sub_category_name']) ?></td>
                                            <td class="text-end fw-bold"><?= htmlspecialchars(number_format($item['quantity'], 2)) ?></td>
                                            <!-- NEW: View button that links to the new page -->
                                            <td class="text-center">
                                                <a href="/modules/inventory/view_item.php?item_id=<?= htmlspecialchars($item['item_id']) ?>" class="btn btn-sm btn-outline-primary" title="View Item Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>