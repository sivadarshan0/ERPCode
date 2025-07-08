<?php
// File: modules/inventory/item.php

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Load categories for dropdown
$conn = db();
$categoryStmt = $conn->query("SELECT CategoryCode, Category FROM category ORDER BY Category");
$categories = $categoryStmt->fetch_all(MYSQLI_ASSOC);
?>

<div class="form-container">
    <h2>Item Management</h2>

    <form action="save_item.php" method="POST" data-validate>
        <div class="form-group">
            <label for="category">Category*</label>
            <div class="input-row">
                <select name="category" id="category" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['CategoryCode']) ?>">
                            <?= htmlspecialchars($cat['Category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn-small" onclick="promptAdd('category')">+</button>
            </div>
        </div>

        <div class="form-group">
            <label for="subcategory">Sub-Category*</label>
            <div class="input-row">
                <select name="subcategory" id="subcategory" required>
                    <option value="">Select Sub-Category</option>
                    <!-- Will be dynamically populated -->
                </select>
                <button type="button" class="btn-small" onclick="promptAdd('subcategory')">+</button>
            </div>
        </div>

        <div class="form-group">
            <label for="item">Item*</label>
            <input type="text" name="item" id="item" data-autocomplete="itemList" data-type="item" required>
            <div id="itemList" class="autocomplete-box"></div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" rows="3"></textarea>
        </div>

        <button type="submit" class="btn-primary">Save Item</button>
    </form>
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
