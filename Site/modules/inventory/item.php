<?php
// Enable error display during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Step 1
echo "Step 1: Starting Script<br>";

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

echo "Step 2: Header included<br>";

// Print session for debugging
echo "<pre>Session Data: ";
print_r($_SESSION);
echo "</pre>";

// Step 3: Require login
require_login();
echo "Step 3: User login verified<br>";

// Step 4: DB connection
$conn = db();
echo "Step 4: Database connected<br>";

$msg = '';
$itemData = null;

// Step 5: Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "Step 5: POST Request received<br>";
    try {
        $conn->begin_transaction();
        echo "Step 6: Transaction started<br>";

        $itemCode = $_POST['item_code'] ?? null;
        $itemName = sanitize_input($_POST['item_name']);
        $description = sanitize_input($_POST['description']);
        $categoryCode = $_POST['category_code'];
        $subCategoryCode = $_POST['sub_category_code'];

        if ($itemCode) {
            echo "Step 7a: Updating item<br>";
            $stmt = $conn->prepare("UPDATE item SET Item=?, Description=?, CategoryCode=?, SubCategoryCode=? WHERE ItemCode=?");
            $stmt->bind_param("sssss", $itemName, $description, $categoryCode, $subCategoryCode, $itemCode);
            $action = 'updated';
        } else {
            echo "Step 7b: Inserting item<br>";
            $itemCode = Database::getInstance()->generateCode('item_code', 'itm');
            $stmt = $conn->prepare("INSERT INTO item (ItemCode, Item, Description, CategoryCode, SubCategoryCode) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $itemCode, $itemName, $description, $categoryCode, $subCategoryCode);
            $action = 'created';
        }

        $stmt->execute();
        echo "Step 8: Query executed<br>";

        $conn->commit();
        echo "Step 9: Transaction committed<br>";

        $msg = "Item $action successfully! Code: $itemCode";
        $itemData = compact('itemCode', 'itemName', 'description', 'categoryCode', 'subCategoryCode');
    } catch (Exception $e) {
        $conn->rollback();
        echo "Step 10: Transaction rolled back<br>";
        $msg = "Error: " . $e->getMessage();
        echo "Error Message: $msg<br>";
    }
}

// Step 11: Fetch categories
echo "Step 11: Fetching categories<br>";
$categories = $conn->query("SELECT CategoryCode, Category FROM category ORDER BY Category");
if (!$categories) {
    echo "MySQL Error: " . $conn->error;
    exit;
}
echo "Step 12: Categories fetched<br>";
?>

<!-- Step 13: Render Form -->
<div class="form-container">
    <h2>Item Management</h2>

    <?php if ($msg): ?>
        <div class="alert <?= strpos($msg, 'Error') === false ? 'success' : 'error' ?>">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="itemForm">
        <input type="hidden" name="item_code" id="itemCode" value="<?= $itemData['itemCode'] ?? '' ?>">

        <div class="form-group">
            <label for="item_name">Item Name*</label>
            <input type="text" id="item_name" name="item_name" required value="<?= $itemData['itemName'] ?? '' ?>">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= $itemData['description'] ?? '' ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category_code">Category*</label>
                <select id="category_code" name="category_code" required>
                    <option value="">Select Category</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['CategoryCode'] ?>"
                            <?= ($itemData['categoryCode'] ?? '') === $cat['CategoryCode'] ? 'selected' : '' ?>>
                            <?= $cat['Category'] ?>
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
        </div>

        <button type="submit" class="btn-primary">Save Item</button>
    </form>
</div>

<script>
// Populate sub-categories via AJAX
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
            });
    });

    if (categorySelect.value) {
        categorySelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
