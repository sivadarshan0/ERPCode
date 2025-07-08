<?php
// File: modules/inventory/item.php

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Fetch categories for dropdown
$conn = db();
$categoryStmt = $conn->query("SELECT CategoryCode, Category FROM category ORDER BY Category");
$categories = $categoryStmt->fetch_all(MYSQLI_ASSOC);
?>

<div class="login-container">
    <div class="login-box" style="max-width: 600px">
        <div class="login-header">
            <h1>Item Management</h1>
        </div>

        <form action="save_item.php" method="POST" data-validate>
            <div class="form-group">
                <label for="category">Category*</label>
                <div style="display: flex; gap: 0.5rem;">
                    <select name="category" id="category" required style="flex: 1;">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['CategoryCode']) ?>">
                                <?= htmlspecialchars($cat['Category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="promptAdd('category')" class="btn-add">+</button>
                </div>
            </div>

            <div class="form-group">
                <label for="subcategory">Sub-Category*</label>
                <div style="display: flex; gap: 0.5rem;">
                    <select name="subcategory" id="subcategory" required style="flex: 1;">
                        <option value="">Select Sub-Category</option>
                    </select>
                    <button type="button" onclick="promptAdd('subcategory')" class="btn-add">+</button>
                </div>
            </div>

            <div class="form-group">
                <label for="item">Item*</label>
                <input type="text" name="item" id="item" data-autocomplete="itemList" data-type="item" required>
                <div id="itemList" class="autocomplete-box"></div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3" style="width: 100%;"></textarea>
            </div>

            <button type="submit" class="btn-login">Save Item</button>
        </form>
    </div>
</div>

<script>
document.getElementById('category').addEventListener('change', function () {
    const catCode = this.value;
    const subSelect = document.getElementById('subcategory');
    subSelect.innerHTML = '<option value="">Loading…</option>';

    fetch(`/get_subcategories.php?cat=${encodeURIComponent(catCode)}`)
        .then(res => res.json())
        .then(data => {
            subSelect.innerHTML = '<option value="">Select Sub-Category</option>';
            data.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.SubCategoryCode;
                opt.textContent = sub.SubCategory;
                subSelect.appendChild(opt);
            });
        })
        .catch(() => {
            subSelect.innerHTML = '<option value="">Error loading</option>';
        });
});

function promptAdd(type) {
    const label = type === 'category' ? 'Category' : 'Sub-Category';
    const name = prompt(`Enter new ${label} name:`);
    if (!name) return;

    fetch(`/add_${type}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(name)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`${label} added successfully`);
            location.reload();
        } else {
            alert(data.error || `Failed to add ${label}`);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
