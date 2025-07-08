<?php
// File: modules/inventory/item.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/header.php';

require_login(); // Ensure user is authenticated

$conn = db();
$msg = '';
$itemData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception("Invalid CSRF token.");
        }

        $conn->begin_transaction();

        $itemCode = $_POST['item_code'] ?? null;
        $itemName = trim($_POST['item_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryCode = $_POST['category_code'] ?? '';
        $subCategoryCode = $_POST['sub_category_code'] ?? '';

        if (!$itemName || !$categoryCode || !$subCategoryCode) {
            throw new Exception("Item Name, Category and Sub-Category are required.");
        }

        if ($itemCode) {
            // Update item
            $stmt = $conn->prepare("UPDATE item SET Item=?, Description=?, CategoryCode=?, SubCategoryCode=? WHERE ItemCode=?");
            $stmt->bind_param("sssss", $itemName, $description, $categoryCode, $subCategoryCode, $itemCode);
            $action = 'updated';
        } else {
            // Create new item
            $itemCode = Database::getInstance()->generateCode('item_code', 'itm');
            $stmt = $conn->prepare("INSERT INTO item (ItemCode, Item, Description, CategoryCode, SubCategoryCode) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $itemCode, $itemName, $description, $categoryCode, $subCategoryCode);
            $action = 'created';
        }

        $stmt->execute();
        if ($stmt->affected_rows < 1) {
            throw new Exception("No changes made.");
        }

        $conn->commit();
        $msg = "✅ Item $action successfully! Code: $itemCode";
        $itemData = compact('itemCode', 'itemName', 'description', 'categoryCode', 'subCategoryCode');

        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "❌ Error: " . $e->getMessage();
    }
}

// Fetch categories for dropdown
$categories = $conn->query("SELECT CategoryCode, Category FROM category ORDER BY Category");

// Set page title
$pageTitle = "Manage Item";
?>

<div class="login-container">
    <div class="login-box" style="max-width: 600px">
        <div class="login-header">
            <h1>Item Management</h1>
            <h2>Create or Update Items</h2>
        </div>

        <?php if ($msg): ?>
            <div class="alert <?= strpos($msg, 'Error') === false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="itemForm" data-validate>
            <input type="hidden" name="item_code" value="<?= htmlspecialchars($itemData['itemCode'] ?? '') ?>">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="form-group">
                <label for="item_name">Item Name*</label>
                <input type="text" id="item_name" name="item_name" required value="<?= htmlspecialchars($itemData['itemName'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?= htmlspecialchars($itemData['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="category_code">Category*</label>
                <select id="category_code" name="category_code" required>
                    <option value="">Select Category</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($cat['CategoryCode']) ?>"
                            <?= (isset($itemData['categoryCode']) && $itemData['categoryCode'] === $cat['CategoryCode']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['Category']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="sub_category_code">Sub-Category*</label>
                <select id="sub_category_code" name="sub_category_code" required>
                    <option value="">Select Sub-Category</option>
                </select>
            </div>

            <button type="submit" class="btn-login">Save Item</button>
        </form>

        <div class="login-footer">
            <a href="/index.php">← Back to Dashboard</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.getElementById('category_code');
    const subCategorySelect = document.getElementById('sub_category_code');

    categorySelect.addEventListener('change', function () {
        const categoryCode = this.value;
        subCategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';

        if (!categoryCode) return;

        fetch(`/search.php?type=subcategory&category=${encodeURIComponent(categoryCode)}`)
            .then(res => res.json())
            .then(data => {
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.text;
                    subCategorySelect.appendChild(option);
                });

                <?php if (isset($itemData['subCategoryCode'])): ?>
                    subCategorySelect.value = "<?= htmlspecialchars($itemData['subCategoryCode']) ?>";
                <?php endif; ?>
            })
            .catch(() => {
                subCategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';
            });
    });

    if (categorySelect.value) {
        categorySelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
