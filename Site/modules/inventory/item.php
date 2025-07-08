<?php
// File: modules/inventory/item.php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/header.php';

require_login();
$conn = db();

$msg = '';
$itemData = null;

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        $itemCode = $_POST['item_code'] ?? null;
        $itemName = sanitize_input($_POST['item_name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $categoryCode = sanitize_input($_POST['category_code'] ?? '');
        $subCategoryCode = sanitize_input($_POST['sub_category_code'] ?? '');

        if (!$itemName || !$categoryCode || !$subCategoryCode) {
            throw new Exception("All fields are required.");
        }

        if ($itemCode) {
            $stmt = $conn->prepare("UPDATE item SET Item=?, Description=?, CategoryCode=?, SubCategoryCode=? WHERE ItemCode=?");
            $stmt->bind_param("sssss", $itemName, $description, $categoryCode, $subCategoryCode, $itemCode);
            $action = 'updated';
        } else {
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
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
    }
}

// Fetch category list
$categories = $conn->query("SELECT CategoryCode, Category FROM category ORDER BY Category");
?>

<div class="login-container">
    <div class="login-box" style="max-width: 600px">
        <div class="login-header">
            <h1>Item Management</h1>
        </div>

        <?php if ($msg): ?>
            <div class="alert <?= strpos($msg, 'Error') === false ? 'success' : 'alert-error' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="itemForm">
            <input type="hidden" name="item_code" id="itemCode" value="<?= htmlspecialchars($itemData['itemCode'] ?? '') ?>">

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
                <button type="button" onclick="promptAddCategory()">➕</button>
            </div>

            <div class="form-group">
                <label for="sub_category_code">Sub-Category*</label>
                <select id="sub_category_code" name="sub_category_code" required>
                    <option value="">Select Sub-Category</option>
                </select>
                <button type="button" onclick="promptAddSubCategory()">➕</button>
            </div>

            <div class="form-group">
                <label for="item_name">Item*</label>
                <input type="text" id="item_name" name="item_name" required data-autocomplete="itemDropdown" data-type="item">
                <div id="itemDropdown" class="autocomplete-dropdown"></div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?= htmlspecialchars($itemData['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-login">Save Item</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.getElementById('category_code');
    const subCategorySelect = document.getElementById('sub_category_code');

    categorySelect.addEventListener('change', () => {
        fetch(`/search.php?type=subcategory&category=${categorySelect.value}`)
            .then(res => res.json())
            .then(data => {
                subCategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';
                data.forEach(sc => {
                    const opt = document.createElement('option');
                    opt.value = sc.id;
                    opt.textContent = sc.text;
                    subCategorySelect.appendChild(opt);
                });
            });
    });

    if (categorySelect.value) categorySelect.dispatchEvent(new Event('change'));
});

function promptAddCategory() {
    const newCat = prompt("Enter new category name:");
    if (!newCat) return;
    fetch('/modules/inventory/category.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=create&name=' + encodeURIComponent(newCat)
    }).then(() => location.reload());
}

function promptAddSubCategory() {
    const newSub = prompt("Enter new sub-category name:");
    const catCode = document.getElementById('category_code').value;
    if (!newSub || !catCode) return;
    fetch('/modules/inventory/subcategory.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=create&category_code=' + encodeURIComponent(catCode) + '&name=' + encodeURIComponent(newSub)
    }).then(() => document.getElementById('category_code').dispatchEvent(new Event('change')));
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
