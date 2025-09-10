<?php
// File: /modules/inventory/entry_item.php
// FINAL version with full image management capabilities.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$db = db();
if (!$db) die("Database connection failed");

$item_id_from_get = $_GET['item_id'] ?? null;

// --- Handle Image Management Actions (Delete, Set Main) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action_item_id = $_POST['item_id'] ?? null;
        if (!$action_item_id) {
            throw new Exception("Item ID is missing for image management.");
        }
        
        if ($_POST['action'] === 'delete_image') {
            $image_path_to_delete = $_POST['image_path'] ?? '';
            if (empty($image_path_to_delete)) {
                throw new Exception("Image path is missing.");
            }
            $full_server_path = realpath(__DIR__ . '/../../' . $image_path_to_delete);
            
            // Security check to prevent directory traversal
            if (!$full_server_path || strpos($full_server_path, realpath(__DIR__ . '/../../Images')) !== 0) {
                throw new Exception("Invalid image path.");
            }

            if (!file_exists($full_server_path)) {
                throw new Exception("Image file not found.");
            }
            
            $stmt_check_main = $db->prepare("SELECT main_image_path FROM items WHERE item_id = ?");
            $stmt_check_main->bind_param("s", $action_item_id);
            $stmt_check_main->execute();
            $current_item = $stmt_check_main->get_result()->fetch_assoc();

            if ($current_item && $current_item['main_image_path'] === $image_path_to_delete) {
                $stmt_clear_main = $db->prepare("UPDATE items SET main_image_path = NULL WHERE item_id = ?");
                $stmt_clear_main->bind_param("s", $action_item_id);
                $stmt_clear_main->execute();
            }

            if (!unlink($full_server_path)) {
                throw new Exception("Failed to delete the image file.");
            }
            $_SESSION['success_message'] = "✅ Image successfully deleted.";

        } elseif ($_POST['action'] === 'set_main_image') {
            $image_path_to_set = $_POST['image_path'] ?? '';
            $stmt = $db->prepare("UPDATE items SET main_image_path = ? WHERE item_id = ?");
            $stmt->bind_param("ss", $image_path_to_set, $action_item_id);
            $stmt->execute();
            $_SESSION['success_message'] = "✅ Main image has been updated.";
        }

        header("Location: entry_item.php?item_id=" . $action_item_id);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "❌ Image Action Failed: " . $e->getMessage();
        header("Location: entry_item.php?item_id=" . ($_POST['item_id'] ?? ''));
        exit;
    }
}

// --- Handle Form Submission (Create or Update Item Details & Uploads) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $posted_id = $_POST['item_id'] ?? '';
    try {
        $is_edit_post = !empty($posted_id);
        $name = trim($_POST['name'] ?? '');
        $category_sub_id = trim($_POST['category_sub_id'] ?? '');
        $uom = trim($_POST['uom'] ?? 'No');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) throw new Exception("Item name is required.");
        if (empty($category_sub_id)) throw new Exception("A Sub-Category must be selected.");

        $check_stmt = $db->prepare("SELECT item_id FROM items WHERE name = ? AND item_id != ?");
        $check_stmt->bind_param("ss", $name, $posted_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) throw new Exception("This item name already exists.");

        if ($is_edit_post) {
            $stmt = $db->prepare("UPDATE items SET name = ?, category_sub_id = ?, uom = ?, description = ?, updated_at = NOW(), updated_by = ?, updated_by_name = ? WHERE item_id = ?");
            $stmt->bind_param("ssssisss", $name, $category_sub_id, $uom, $description, $_SESSION['user_id'], $_SESSION['username'], $posted_id);
            $action_item_id = $posted_id;
            $action = 'updated';
        } else {
            $action_item_id = generate_sequence_id('item_id', 'items', 'item_id');
            $stmt = $db->prepare("INSERT INTO items (item_id, category_sub_id, name, uom, description, created_at, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->bind_param("sssssis", $action_item_id, $category_sub_id, $name, $uom, $description, $_SESSION['user_id'], $_SESSION['username']);
            $action = 'created';
        }

        if (!$stmt->execute()) {
            throw new Exception("Database error: Failed to save item details.");
        }
        
        if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
            $image_dir = __DIR__ . '/../../Images/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            
            // Find the highest existing number for this item's images to continue the sequence
            $existing_images = glob($image_dir . $action_item_id . '-*.jpg');
            $last_num = 0;
            if ($existing_images) {
                foreach ($existing_images as $img) {
                    preg_match('/-(\d{2,})\.jpg$/', $img, $matches);
                    if ($matches && (int)$matches[1] > $last_num) {
                        $last_num = (int)$matches[1];
                    }
                }
            }
            
            foreach ($_FILES['new_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['new_images']['error'][$key] === UPLOAD_ERR_OK) {
                    if (!in_array($_FILES['new_images']['type'][$key], $allowed_types)) {
                        throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
                    }
                    $last_num++;
                    $new_filename = $action_item_id . '-' . str_pad($last_num, 2, '0', STR_PAD_LEFT) . '.jpg';
                    if (!move_uploaded_file($tmp_name, $image_dir . $new_filename)) {
                        throw new Exception("Failed to move uploaded image.");
                    }
                    
                    // If this is the VERY FIRST image uploaded for this item, set it as the main image.
                    $check_main = $db->prepare("SELECT main_image_path FROM items WHERE item_id = ?");
                    $check_main->bind_param("s", $action_item_id);
                    $check_main->execute();
                    $current_item_main = $check_main->get_result()->fetch_assoc();
                    if (empty($current_item_main['main_image_path'])) {
                        $stmt_set_main = $db->prepare("UPDATE items SET main_image_path = ? WHERE item_id = ?");
                        $main_image_url = '/Images/' . $new_filename;
                        $stmt_set_main->bind_param("ss", $main_image_url, $action_item_id);
                        $stmt_set_main->execute();
                    }
                }
            }
        }
        
        $_SESSION['success_message'] = "✅ Item successfully $action.";
        header("Location: entry_item.php?item_id=" . $action_item_id);
        exit;

    } catch (Exception $e) {
        $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
        header("Location: entry_item.php" . ($posted_id ? '?item_id=' . $posted_id : ''));
        exit;
    }
}

// --- Standard Page Logic (Data Fetching for Display) ---
$is_edit = false;
$item = [];
$parent_categories = get_all_categories();
$sub_categories = [];

if ($item_id_from_get) {
    $item_data = get_item_details($item_id_from_get);
    if ($item_data) {
        $item = $item_data;
        $is_edit = true;
        if (!empty($item['category_id'])) {
            $sub_categories = get_sub_categories_by_category_id($item['category_id']);
        }
    } else {
        $_SESSION['error_message'] = "Item not found.";
        header("Location: /modules/inventory/list_stock_levels.php");
        exit;
    }
}

// Handle session messages
if (isset($_SESSION['success_message'])) { $message = $_SESSION['success_message']; $message_type = 'success'; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message'])) { $message = $_SESSION['error_message']; $message_type = 'danger'; unset($_SESSION['error_message']); }

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2><?= $is_edit ? 'Manage Item' : 'New Item' ?>
        <?php if ($is_edit): ?>
            <span class='badge bg-primary'><?= htmlspecialchars($item['item_id']) ?></span>
        <?php endif; ?>
    </h2>

    <?php if ($message): ?>
    <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Main Item Details Form -->
    <form method="POST" class="row g-3 needs-validation mb-5" novalidate id="itemForm" enctype="multipart/form-data" action="entry_item.php<?= $is_edit ? '?item_id=' . htmlspecialchars($item['item_id']) : '' ?>">
        <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['item_id'] ?? '') ?>">
        
        <div class="card">
            <div class="card-header">1. Item Details</div>
            <div class="card-body row g-3">
                <div class="col-md-6"><label for="category_id" class="form-label">Category *</label><div class="input-group"><select class="form-select" id="category_id" name="category_id" required><option value="">Choose...</option><?php foreach ($parent_categories as $parent): ?><option value="<?= htmlspecialchars($parent['category_id']) ?>" <?= (isset($item['category_id']) && $item['category_id'] == $parent['category_id']) ? 'selected' : '' ?>><?= htmlspecialchars($parent['name']) ?></option><?php endforeach; ?></select><a href="entry_category.php" class="btn btn-success" title="Add New Category" target="_blank"><i class="bi bi-plus-lg"></i></a></div></div>
                <div class="col-md-6"><label for="category_sub_id" class="form-label">Sub-Category *</label><div class="input-group"><select class="form-select" id="category_sub_id" name="category_sub_id" required <?= $is_edit ? '' : 'disabled' ?>><option value="">Choose...</option><?php foreach ($sub_categories as $sub): ?><option value="<?= htmlspecialchars($sub['category_sub_id']) ?>" <?= (isset($item['category_sub_id']) && $item['category_sub_id'] == $sub['category_sub_id']) ? 'selected' : '' ?>><?= htmlspecialchars($sub['name']) ?></option><?php endforeach; ?></select><a href="entry_category_sub.php" class="btn btn-success" title="Add New Sub-Category" target="_blank"><i class="bi bi-plus-lg"></i></a><div class="invalid-feedback">Please select a sub-category.</div></div></div>
                
                <div class="col-md-8 position-relative"><label for="name" class="form-label">Item Name *</label><input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required autocomplete="off"><div class="invalid-feedback">Item name is required.</div></div>
                <div class="col-md-4"><label for="uom" class="form-label">Unit of Measure (UOM) *</label><select class="form-select" id="uom" name="uom" required><option value="No" <?= (isset($item['uom']) && $item['uom'] == 'No') ? 'selected' : '' ?>>No</option><option value="Set" <?= (isset($item['uom']) && $item['uom'] == 'Set') ? 'selected' : '' ?>>Set</option><option value="Pair" <?= (isset($item['uom']) && $item['uom'] == 'Pair') ? 'selected' : '' ?>>Pair</option></select></div>

                <div class="col-12"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($item['description'] ?? '') ?></textarea></div>
            </div>
        </div>

        <!-- Image Management Section -->
        <div class="card mt-4">
            <div class="card-header">2. Image Management</div>
            <div class="card-body">
                <?php if ($is_edit): ?>
                <div class="mb-3">
                    <h5>Existing Images</h5>
                    <div class="row g-2">
                        <?php if (empty($item['images'])): ?>
                            <p class="text-muted">No images have been uploaded for this item.</p>
                        <?php else: ?>
                            <?php foreach ($item['images'] as $image_url): ?>
                                <div class="col-auto text-center">
                                    <img src="<?= htmlspecialchars($image_url) ?>" class="img-thumbnail" style="height: 100px; width: 100px; object-fit: cover;" alt="Item Image">
                                    <div class="d-flex justify-content-center gap-1 mt-1">
                                        <!-- Form for "Set as Main" -->
                                        <form method="POST" action="entry_item.php" class="d-inline">
                                            <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                            <input type="hidden" name="image_path" value="<?= htmlspecialchars($image_url) ?>">
                                            <?php if (($item['main_image_path'] ?? '') === $image_url): ?>
                                                <button type="button" class="btn btn-sm btn-success disabled" title="This is the main image">Main</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="set_main_image" class="btn btn-sm btn-outline-success" title="Set as Main Image">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        <!-- Form for "Delete" -->
                                        <form method="POST" action="entry_item.php" class="d-inline">
                                            <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                            <input type="hidden" name="image_path" value="<?= htmlspecialchars($image_url) ?>">
                                            <button type="submit" name="action" value="delete_image" class="btn btn-sm btn-outline-danger" title="Delete Image" onclick="return confirm('Are you sure you want to delete this image?');">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <hr>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="new_images" class="form-label"><?= $is_edit ? 'Upload More Images' : 'Upload Images' ?></label>
                    <input class="form-control" type="file" id="new_images" name="new_images[]" multiple accept="image/jpeg,image/png,image/gif">
                    <div class="form-text">You can select multiple files. Allowed types: JPG, PNG, GIF.</div>
                </div>
            </div>
        </div>

        <div class="col-12 mt-4">
            <button class="btn btn-primary" type="submit"><i class="bi bi-<?= $is_edit ? 'floppy' : 'plus-circle' ?>"></i> <?= $is_edit ? 'Update Item Details' : 'Create Item' ?></button>
            <a href="/index.php" class="btn btn-outline-secondary">Back</a>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>