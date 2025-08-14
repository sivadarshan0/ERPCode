<?php
// File: /modules/inventory/entry_item.php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// --- Flash message handling ---
$message = '';
$message_type = '';
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']); 
}

// Initialize database connection
$db = db();
if (!$db) die("Database connection failed");

// --- AJAX Endpoints ---
if (isset($_GET['get_sub_categories'])) {
    header('Content-Type: application/json');
    try {
        $category_id = trim($_GET['get_sub_categories']);
        echo json_encode(get_sub_categories_by_category_id($category_id));
    } catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }
    exit;
}
if (isset($_GET['item_lookup'])) {
    header('Content-Type: application/json');
    try {
        $name = trim($_GET['item_lookup']);
        echo json_encode(strlen($name) >= 2 ? search_items_by_name($name) : []);
    } catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

require_login();

$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['username'] ?? 'Unknown';
$parent_categories = get_all_categories();
$sub_categories = [];

$item = [
    'item_id' => '',
    'category_id' => '',
    'category_sub_id' => '',
    'name' => '',
    'uom' => 'No', // ADDED: Default UOM
    'description' => '',
    'created_by_name' => $current_user_name,
    'updated_by_name' => null
];
$is_edit = false;

if (isset($_GET['item_id'])) {
    $item_data = get_item(trim($_GET['item_id']));
    if ($item_data) {
        $item = array_merge($item, $item_data);
        $is_edit = true;
        if (!empty($item['category_id'])) {
            $sub_categories = get_sub_categories_by_category_id($item['category_id']);
        }
    } else {
        $_SESSION['error_message'] = "Item not found.";
        header("Location: entry_item.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $is_edit_post = !empty($_POST['item_id']);
        $name = trim($_POST['name'] ?? '');
        $category_sub_id = trim($_POST['category_sub_id'] ?? '');
        $uom = trim($_POST['uom'] ?? 'No'); // ADDED: Get UOM from POST
        
        if (empty($name)) throw new Exception("Item name is required.");
        if (empty($category_sub_id)) throw new Exception("A Sub-Category must be selected.");

        $check_stmt = $db->prepare("SELECT item_id FROM items WHERE name = ? AND item_id != ?");
        $posted_id = $_POST['item_id'] ?? '';
        $check_stmt->bind_param("ss", $name, $posted_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("This item name already exists.");
        }
        $check_stmt->close();

        $description = trim($_POST['description'] ?? '');
        
        if ($is_edit_post) {
            // MODIFIED: Added uom to the UPDATE statement
            $stmt = $db->prepare("UPDATE items SET name = ?, category_sub_id = ?, uom = ?, description = ?, updated_at = NOW(), updated_by = ?, updated_by_name = ? WHERE item_id = ?");
            $stmt->bind_param("ssssisss", $name, $category_sub_id, $uom, $description, $current_user_id, $current_user_name, $posted_id);
            $action = 'updated';
        } else {
            $item_id = generate_sequence_id('item_id', 'items', 'item_id');
            // MODIFIED: Added uom to the INSERT statement
            $stmt = $db->prepare("INSERT INTO items (item_id, category_sub_id, name, uom, description, created_at, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->bind_param("sssssis", $item_id, $category_sub_id, $name, $uom, $description, $current_user_id, $current_user_name);
            $action = 'created';
        }

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "✅ Item successfully $action.";
            header("Location: entry_item.php");
            exit;
        } else {
            throw new Exception("Database error: Failed to save item.");
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        $item = array_merge($item, $_POST);
        $is_edit = !empty($_POST['item_id']); 
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2><?= $is_edit ? 'Edit Item' : 'New Item' ?>
        <?php if ($is_edit): ?>
            <span class='badge bg-primary'><?= htmlspecialchars($item['item_id']) ?></span>
        <?php endif; ?>
    </h2>
    <p><strong>Mode:</strong> <?= $is_edit ? 'EDIT' : 'CREATE' ?></p>

    <?php if ($message): ?>
    <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3 needs-validation" novalidate id="itemForm" action="entry_item.php<?= $is_edit ? '?item_id=' . htmlspecialchars($item['item_id']) : '' ?>">
        <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['item_id']) ?>">

        <div class="col-md-6"><label for="category_id" class="form-label">Category *</label><div class="input-group"><select class="form-select" id="category_id" name="category_id" required><option value="">Choose Category...</option><?php foreach ($parent_categories as $parent): ?><option value="<?= htmlspecialchars($parent['category_id']) ?>" <?= ($item['category_id'] == $parent['category_id']) ? 'selected' : '' ?>><?= htmlspecialchars($parent['name']) ?></option><?php endforeach; ?></select><a href="entry_category.php" class="btn btn-success" title="Add New Category" target="_blank"><i class="bi bi-plus-lg"></i></a></div></div>
        <div class="col-md-6"><label for="category_sub_id" class="form-label">Sub-Category *</label><div class="input-group"><select class="form-select" id="category_sub_id" name="category_sub_id" required <?= $is_edit ? '' : 'disabled' ?>><option value="">Choose Sub-Category...</option><?php foreach ($sub_categories as $sub): ?><option value="<?= htmlspecialchars($sub['category_sub_id']) ?>" <?= ($item['category_sub_id'] == $sub['category_sub_id']) ? 'selected' : '' ?>><?= htmlspecialchars($sub['name']) ?></option><?php endforeach; ?></select><a href="entry_category_sub.php" class="btn btn-success" title="Add New Sub-Category" target="_blank"><i class="bi bi-plus-lg"></i></a><div class="invalid-feedback">Please select a sub-category.</div></div></div>
        
        <!-- MODIFIED: Item Name and UOM are now on the same row -->
        <div class="col-md-8 position-relative">
            <label for="name" class="form-label">Item Name *</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($item['name']) ?>" required autocomplete="off">
            <div class="invalid-feedback">Item name is required.</div>
            <div id="itemResults" class="list-group mt-1 position-absolute w-100 d-none" style="z-index: 1000;"></div>
        </div>
        
        <!-- ADDED: UOM Dropdown -->
        <div class="col-md-4">
            <label for="uom" class="form-label">Unit of Measure (UOM) *</label>
            <select class="form-select" id="uom" name="uom" required>
                <option value="No" <?= $item['uom'] == 'No' ? 'selected' : '' ?>>No</option>
                <option value="Set" <?= $item['uom'] == 'Set' ? 'selected' : '' ?>>Set</option>
                <option value="Pair" <?= $item['uom'] == 'Pair' ? 'selected' : '' ?>>Pair</option>
            </select>
        </div>

        <div class="col-12"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($item['description']) ?></textarea></div>
        <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-<?= $is_edit ? 'floppy' : 'plus-circle' ?>"></i> <?= $is_edit ? 'Update' : 'Create' ?> Item</button><a href="/index.php" class="btn btn-outline-secondary">Back</a></div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>```
