<?php
// File: modules/inventory/item.php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/header.php';

require_login();
$conn = db();

$msg = '';
$itemData = null;

// Fetch categories
$categories = $conn->query("SELECT CategoryCode, Category FROM category ORDER BY Category") ?: [];

// Fetch sub-categories grouped by category
$subCategoryMap = [];
$subCats = $conn->query("SELECT SubCategoryCode, SubCategory, CategoryCode FROM sub_category ORDER BY SubCategory") ?: [];
while($row = $subCats->fetch_assoc()) {
    $subCategoryMap[$row['CategoryCode']][] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        $item        = $_POST['item_name'] ?? '';
        $desc        = $_POST['description'] ?? '';
        $catCode     = $_POST['category_code'] ?? null;
        $subCatCode  = $_POST['sub_category_code'] ?? null;

        // Check if item exists
        $stmt = $conn->prepare("SELECT ItemCode FROM item WHERE Item = ?");
        $stmt->bind_param("s", $item);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Update
            $stmt->bind_result($itemCode);
            $stmt->fetch();
            $stmt->close();

            $update = $conn->prepare("UPDATE item SET Description=?, CategoryCode=?, SubCategoryCode=? WHERE Item=?");
            $update->bind_param("ssss", $desc, $catCode, $subCatCode, $item);
            if ($update->execute()) {
                $msg = "✅ Item updated successfully (Code: <b>$itemCode</b>)";
            } else {
                $msg = "❌ Error updating: " . $conn->error;
            }
            $update->close();
        } else {
            // Insert
            $stmt->close();
            $insert = $conn->prepare("INSERT INTO item (Item, Description, CategoryCode, SubCategoryCode) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $item, $desc, $catCode, $subCatCode);
            if ($insert->execute()) {
                $itemCode = $conn->insert_id;
                $msg = "✅ Item added. Code: <b>$itemCode</b>";
            } else {
                $msg = "❌ Error inserting: " . $conn->error;
            }
            $insert->close();
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "❌ Transaction failed: " . $e->getMessage();
    }
}
?>

<div class="content-container">
    <div class="form-container">
        <div class="form-header">
            <h2>Item Entry</h2>
            <a href="/index.php" class="back-link">&larr; Back to Menu</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert <?= str_starts_with($msg, '✅') ? 'alert-success' : 'alert-error' ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <form id="itemForm" method="POST" autocomplete="off" class="item-form">
            <div class="form-group">
                <label for="category_code">Category*</label>
                <div class="select-wrapper">
                    <select name="category_code" id="category_code" required>
                        <option value="">Select Category</option>
                        <?php if ($categories && $categories instanceof mysqli_result): ?>
                            <?php while($row = $categories->fetch_assoc()): ?>
                                <option value="<?= $row['CategoryCode'] ?>"><?= htmlspecialchars($row['Category']) ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                    <a href="/modules/inventory/category.php" class="add-link">+ Add Category</a>
                </div>
            </div>

            <div class="form-group">
                <label for="sub_category_code">Sub-Category*</label>
                <div class="select-wrapper">
                    <select name="sub_category_code" id="sub_category_code" required>
                        <option value="">Select Sub-Category</option>
                    </select>
                    <a href="/modules/inventory/subcategory.php" class="add-link">+ Add Sub-Category</a>
                </div>
            </div>

            <div class="form-group">
                <label for="item_name">Item Name*</label>
                <div class="autocomplete-wrapper">
                    <input type="text" name="item_name" id="item_name" required placeholder="Enter item name">
                    <ul id="suggestions" class="autocomplete-list"></ul>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="4" placeholder="Enter item description"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Item</button>
            </div>
        </form>
    </div>
</div>

<script>
const subCategoryMap = <?= json_encode($subCategoryMap) ?>;
const catSelect = document.getElementById('category_code');
const subCatSelect = document.getElementById('sub_category_code');
const itemInput = document.getElementById('item_name');
const suggestionsList = document.getElementById('suggestions');

// Update sub-categories when category changes
catSelect.addEventListener('change', function() {
    const selectedCat = this.value;
    subCatSelect.innerHTML = '<option value="">Select Sub-Category</option>';
    if (subCategoryMap[selectedCat]) {
        subCategoryMap[selectedCat].forEach(sc => {
            const opt = document.createElement('option');
            opt.value = sc.SubCategoryCode;
            opt.textContent = sc.SubCategory;
            subCatSelect.appendChild(opt);
        });
    }
});

// Form validation
document.getElementById('itemForm').addEventListener('submit', function(e) {
    const cat = catSelect.value;
    const sub = subCatSelect.value;
    const item = itemInput.value.trim();
    if (!cat || !sub || item.length < 3) {
        alert('Please fill all required fields correctly.');
        e.preventDefault();
    }
});

// Item autocomplete
itemInput.addEventListener('input', async () => {
    const query = itemInput.value.trim();
    suggestionsList.innerHTML = '';

    if (query.length < 1) return;

    const res = await fetch(`/modules/inventory/search_item.php?q=${encodeURIComponent(query)}`);
    if (!res.ok) return;

    const items = await res.json();
    if (items.length > 0) {
        suggestionsList.style.display = 'block';
        items.forEach(data => {
            const li = document.createElement('li');
            li.textContent = data.Item;
            li.addEventListener('click', () => {
                itemInput.value = data.Item;
                document.getElementById('description').value = data.Description;
                catSelect.value = data.CategoryCode;
                catSelect.dispatchEvent(new Event('change'));
                setTimeout(() => {
                    subCatSelect.value = data.SubCategoryCode;
                }, 100);
                suggestionsList.style.display = 'none';
            });
            suggestionsList.appendChild(li);
        });
    } else {
        suggestionsList.style.display = 'none';
    }
});

// Close autocomplete when clicking outside
document.addEventListener('click', (e) => {
    if (!suggestionsList.contains(e.target) && e.target !== itemInput) {
        suggestionsList.style.display = 'none';
    }
});
</script>

<style>
/* Main Container Styles */
.content-container {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
}

.form-container {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 25px;
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.form-header h2 {
    margin: 0;
    color: #333;
}

.back-link {
    color: #3498db;
    text-decoration: none;
    font-size: 14px;
}

.back-link:hover {
    text-decoration: underline;
}

/* Alert Messages */
.alert {
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-size: 14px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Form Elements */
.item-form {
    margin-top: 15px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
}

.select-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #fff;
    font-size: 14px;
    appearance: none;
    flex: 1;
}

.add-link {
    margin-left: 10px;
    color: #3498db;
    text-decoration: none;
    font-size: 14px;
    white-space: nowrap;
}

.add-link:hover {
    text-decoration: underline;
}

input[type="text"],
textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

textarea {
    resize: vertical;
    min-height: 100px;
}

/* Autocomplete Styles */
.autocomplete-wrapper {
    position: relative;
}

.autocomplete-list {
    position: absolute;
    z-index: 1000;
    width: 100%;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    background: white;
    list-style: none;
    padding: 0;
    margin: 0;
    display: none;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.autocomplete-list li {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}

.autocomplete-list li:hover {
    background-color: #f5f5f5;
}

/* Button Styles */
.form-actions {
    margin-top: 25px;
    text-align: right;
}

.btn-primary {
    background: #3498db;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
}

.btn-primary:hover {
    background: #2980b9;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>