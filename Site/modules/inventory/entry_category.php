<?php
// File: /modules/category/entry_category.php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Initialize database connection
$db = db();
if (!$db) {
    die("Database connection failed");
}

// Handle AJAX live search (powered by functions.php)
if (isset($_GET['category_lookup'])) {
    header('Content-Type: application/json');
    try {
        $name = trim($_GET['category_lookup']);
        echo json_encode(strlen($name) >= 2 ? search_categories_by_name($name) : []);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Security: Ensure user is logged in for all other operations
require_login();

// Get current user info
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['username'] ?? 'Unknown'; // Assuming username is stored in session

// Initialize default category structure
$category = [
    'category_id' => '',
    'name' => '',
    'description' => '',
    'created_by_name' => $current_user_name,
    'updated_by_name' => null
];
$is_edit = false;

// Load category data if editing
if (isset($_GET['category_id'])) {
    $category_data = get_category(trim($_GET['category_id']));
    if ($category_data) {
        $category = array_merge($category, $category_data);
        $is_edit = true;
    } else {
        $_SESSION['error_message'] = "Category not found.";
        header("Location: /index.php"); // Or a category list page
        exit;
    }
}

$message = '';
$message_type = '';

// Handle POST request (Create or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            throw new Exception("Category name is required.");
        }

        // Check for duplicate category name
        // THIS IS THE CORRECTED LINE: Changed "SELECT id" to "SELECT category_id"
        $check_stmt = $db->prepare("SELECT category_id FROM categories WHERE name = ? AND category_id != ?");
        $posted_id = $_POST['category_id'] ?? '';
        $check_stmt->bind_param("ss", $name, $posted_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("This category name already exists.");
        }
        $check_stmt->close();

        $description = trim($_POST['description'] ?? '');
        
        if ($is_edit) {
            // ----- UPDATE existing category -----
            $stmt = $db->prepare("UPDATE categories SET name = ?, description = ?, updated_at = NOW(), updated_by = ?, updated_by_name = ? WHERE category_id = ?");
            $stmt->bind_param("ssisss", $name, $description, $current_user_id, $current_user_name, $category['category_id']);
            $action = 'updated';

        } else {
            // ----- CREATE new category -----
            $category_id = generate_sequence_id('category_id', 'categories', 'category_id');

            $stmt = $db->prepare("INSERT INTO categories (category_id, name, description, created_at, created_by, created_by_name) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmt->bind_param("sssis", $category_id, $name, $description, $current_user_id, $current_user_name);
            $action = 'created';
        }

        if ($stmt->execute()) {
            $message = "✅ Category successfully $action.";
            $message_type = 'success';
            
            // On successful creation, redirect to the new edit page to prevent re-submission
            if ($action === 'created') {
                header("Location: entry_category.php?category_id=$category_id&created=1");
                exit;
            }
            
            // Reload data after update
            $category = get_category($category['category_id']);
            
        } else {
            throw new Exception("Database error: Failed to save category.");
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        // Preserve user's submitted data on error
        $category = array_merge($category, $_POST);
    }
}

// Display a one-time message on successful creation
if (isset($_GET['created']) && $_GET['created'] == 1) {
    $message = "✅ Category successfully created.";
    $message_type = 'success';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2><?= $is_edit ? 'Edit Category' : 'New Category' ?>
        <?php if ($is_edit): ?>
            <span class='badge bg-primary'><?= htmlspecialchars($category['category_id']) ?></span>
        <?php endif; ?>
    </h2>
    <p><strong>Mode:</strong> <?= $is_edit ? 'EDIT' : 'CREATE' ?></p>

    <?php if ($message): ?>
    <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <form method="POST" class="row g-3 needs-validation" novalidate id="categoryForm" action="entry_category.php<?= $is_edit ? '?category_id=' . htmlspecialchars($category['category_id']) : '' ?>">

        <input type="hidden" name="category_id" value="<?= htmlspecialchars($category['category_id']) ?>">

        <div class="col-md-12 position-relative">
            <label for="name" class="form-label">Category Name *</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($category['name']) ?>" required autocomplete="off">
            <div class="invalid-feedback">Category name is required.</div>
            <!-- Live search results will be injected here by app.js -->
            <div id="categoryResults" class="list-group mt-1 position-absolute w-100 d-none" style="z-index: 1000;"></div>
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($category['description']) ?></textarea>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit">
                <?= $is_edit ? '<i class="bi bi-floppy"></i> Update' : '<i class="bi bi-plus-circle"></i> Create' ?> Category
            </button>
            <a href="/index.php" class="btn btn-outline-secondary">Back</a>
            <?php if ($is_edit): ?>
                <a href="entry_category.php" class="btn btn-outline-success">+ New Category</a>
            <?php endif; ?>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>