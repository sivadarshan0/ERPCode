<?php
// File: modules/inventory/item.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/db.php';   // Database class & db() function
require_once __DIR__ . '/../../includes/header.php'; // Header + session start + HTML head

require_login(); // Your auth check function

$conn = db();

$msg = '';
$itemData = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
            // Update existing item
            $stmt = $conn->prepare("UPDATE item SET Item=?, Description=?, CategoryCode=?, SubCategoryCode=? WHERE ItemCode=?");
            $stmt->bind_param("sssss", $itemName, $description, $categoryCode, $subCategoryCode, $itemCode);
            $action = 'updated';
        } else {
            // Create new item code using sequence generator
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

        $msg = "Item $action successfully! Code: $itemCode";
        $itemData = compact('itemCode', 'itemName', 'description', 'categoryCode', 'subCategoryCode');

        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
    }
}

// Fetch categories for dropdown
$categories = $conn->query("SELECT CategoryCode, Category FROM category ORDER BY Category");

?>

<div class="form-container">
    <h2>Item Management</h2>

    <?php if ($msg): ?>
        <div class="alert <?= strpos($msg, 'Error') === false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="itemForm">
        <input type="hidden" name="item_code" id="itemCode" value="<?= htmlspecialchars($itemData['itemCode'] ?? '') ?>">

        <div class="form-group">
            <label for="item_name">Item Name*</label>
            <input type="text" id="item_name" name="item_name" required value="<?= htmlspecialchars($itemData['itemName'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= htmlspecialchars($itemData['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
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
                    <!-- Sub-categories will be populated via JS -->
                </select>
            </div>
        </div>

        <button type="submit" class="btn-primary">Save Item</button>
    </form>
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

                // Optionally select existing sub-category if editing
                <?php if (isset($itemData['subCategoryCode'])): ?>
                    subCategorySelect.value = "<?= htmlspecialchars($itemData['subCategoryCode']) ?>";
                <?php endif; ?>
            })
            .catch(() => {
                // On error, clear options except default
                subCategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';
            });
    });

    // Trigger change if category is pre-selected to load subcategories
    if (categorySelect.value) {
        categorySelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
