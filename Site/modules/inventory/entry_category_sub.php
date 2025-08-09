<?php
// File: /modules/inventory/entry_category_sub.php

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
if (!$db) {
    die("Database connection failed");
}

// Handle AJAX live search
if (isset($_GET['sub_category_lookup'])) {
    header('Content-Type: application/json');
    try {
        $name = trim($_GET['sub_category_lookup']);
        echo json_encode(strlen($name) >= 2 ? search_sub_categories_by_name($name) : []);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Security: Ensure user is logged in
require_login();

// Get current user info
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['username'] ?? 'Unknown';

// Fetch all parent categories for the dropdown
$parent_categories = get_all_categories();

// Initialize default sub-category structure
$sub_category = [
    'category_sub_id' => '',
    'category_id' => '', // For the parent category
    'name' => '',
    'description' => '',
    'created_by_name' => $current_user_name,
    'updated_by_name' => null
];
$is_edit = false;

// Load sub-category data if editing
if (isset($_GET['category_sub_id'])) {
    $sub_category_data = get_sub_category(trim($_GET['category_sub_id']));
    if ($sub_category_data) {
        $sub_category = array_merge($sub_category, $sub_category_data);
        $is_edit = true;
    } else {
        $_SESSION['error_message'] = "Sub-Category not found.";
        header("Location: entry_category_sub.php");
        exit;
    }
}

// Handle POST request (Create or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $is_edit_post = !empty($_POST['category_sub_id']);
        $name = trim($_POST['name'] ?? '');
        $category_id = trim($_POST['category_id'] ?? '');

        if (empty($name)) {
            throw new Exception("Sub-Category name is required.");
        }
        if (empty($category_id)) {
            throw new Exception("A parent Category must be selected.");
        }

        // Check for duplicate sub-category name
        $check_stmt = $db->prepare("SELECT category_sub_id FROM categories_sub WHERE name = ? AND category_sub_id != ?");
        $posted_id = $_POST['category_sub_id'] ?? '';
        $check_stmt->bind_param("ss", $name, $posted_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("This sub-category name already exists.");
        }
        $check_stmt->close();

        $description = trim($_POST['description'] ?? '');
        
        if ($is_edit_post) {
            // ----- UPDATE existing sub-category -----
            $stmt = $db->prepare("UPDATE categories_sub SET name = ?, category_id = ?, description = ?, updated_at = NOW(), updated_by = ?, updated_by_name = ? WHERE category_sub_id = ?");
            $stmt->bind_param("sssisss", $name, $category_id, $description, $current_user_id, $current_user_name, $posted_id);
            $action = 'updated';

        } else {
            // ----- CREATE new sub-category -----
            $category_sub_id = generate_sequence_id('category_sub_id', 'categories_sub', 'category_sub_id');
            $stmt = $db->prepare("INSERT INTO categories_sub (category_sub_id, category_id, name, description, created_at, created_by, created_by_name) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->bind_param("ssssis", $category_sub_id, $category_id, $name, $description, $current_user_id, $current_user_name);
            $action = 'created';
        }

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "✅ Sub-Category successfully $action.";
            header("Location: entry_category_sub.php");
            exit;
        } else {
            throw new Exception("Database error: Failed to save sub-category.");
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        $sub_category = array_merge($sub_category, $_POST);
        $is_edit = !empty($_POST['category_sub_id']); 
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2><?= $is_edit ? 'Edit Sub-Category' : 'New Sub-Category' ?>
        <?php if ($is_edit): ?>
            <span class='badge bg-primary'><?= htmlspecialchars($sub_category['category_sub_id']) ?></span>
        <?php endif; ?>
    </h2>
    <p><strong>Mode:</strong> <?= $is_edit ? 'EDIT' : 'CREATE' ?></p>

    <?php if ($message): ?>
    <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <form method="POST" class="row g-3 needs-validation" novalidate id="subCategoryForm" action="entry_category_sub.php<?= $is_edit ? '?category_sub_id=' . htmlspecialchars($sub_category['category_sub_id']) : '' ?>">

        <input type="hidden" name="category_sub_id" value="<?= htmlspecialchars($sub_category['category_sub_id']) ?>">

        <div class="col-md-6">
            <label for="category_id" class="form-label">Parent Category *</label>
            <div class="input-group">
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">Choose...</option>
                    <?php foreach ($parent_categories as $parent_cat): ?>
                        <option value="<?= htmlspecialchars($parent_cat['category_id']) ?>" <?= ($sub_category['category_id'] == $parent_cat['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($parent_cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="entry_category.php" class="btn btn-success" title="Add New Parent Category" target="_blank">
                    <i class="bi bi-plus-lg"></i>
                </a>
                <div class="invalid-feedback">Please select a parent category.</div>
            </div>
        </div>

        <div class="col-md-6 position-relative">
            <label for="name" class="form-label">Sub-Category Name *</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($sub_category['name']) ?>" required autocomplete="off">
            <div class="invalid-feedback">Sub-category name is required.</div>
            <!-- Live search results -->
            <div id="subCategoryResults" class="list-group mt-1 position-absolute w-100 d-none" style="z-index: 1000;"></div>
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($sub_category['description']) ?></textarea>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit">
                <?= $is_edit ? '<i class="bi bi-floppy"></i> Update' : '<i class="bi bi-plus-circle"></i> Create' ?> Sub-Category
            </button>
            <a href="/index.php" class="btn btn-outline-secondary">Back</a>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>