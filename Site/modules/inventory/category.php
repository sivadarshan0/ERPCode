<?php
// File: modules/inventory/category.php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/header.php';

require_login();
$conn = db();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category    = sanitize_input($_POST['category'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');

    if (strlen($category) < 3) {
        $msg = "❌ Category must be at least 3 characters.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO category (Category, Description) VALUES (?, ?)");
            $stmt->bind_param("ss", $category, $description);
            if ($stmt->execute()) {
                $msg = "✅ Category added successfully.";
            } else {
                $msg = "❌ Error: " . $conn->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $msg = "❌ Exception: " . $e->getMessage();
        }
    }
}
?>

<div class="content-container">
    <div class="form-container">
        <div class="form-header">
            <h2>Category Entry</h2>
            <a href="/index.php" class="back-link">&larr; Back to Menu</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert <?= str_starts_with($msg, '✅') ? 'alert-success' : 'alert-error' ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="item-form" data-validate>
            <div class="form-group">
                <label for="category">Category Name*</label>
                <div class="autocomplete-wrapper">
                    <input type="text" name="category" id="category" required placeholder="Enter category name"
                           data-autocomplete="categoryList" data-type="category">
                    <ul id="categoryList" class="autocomplete-list"></ul>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="4" placeholder="Enter category description"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
