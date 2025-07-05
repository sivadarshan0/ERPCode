<?php
// File: sub_category_entry.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$DB_HOST = "localhost";
$DB_USER = "dbauser";
$DB_PASS = "dbauser";
$DB_NAME = "erpdb";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$msg = '';
$subCategoryCode = null;
$subCategory = '';
$descriptionValue = '';
$selectedCategoryCode = '';

$categories = $conn->query("SELECT CategoryCode, Category FROM category ORDER BY Category") ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subCategory = trim($_POST['SubCategory'] ?? '');
    $description = trim($_POST['Description'] ?? '');
    $selectedCategoryCode = $_POST['CategoryCode'] ?? '';

    if ($subCategory === '') {
        $msg = "❌ Sub-Category name is required.";
    } elseif ($selectedCategoryCode === '') {
        $msg = "❌ Please select a Category.";
    } else {
        // Check if sub-category exists (case-insensitive)
        $stmt = $conn->prepare("SELECT SubCategoryCode FROM sub_category WHERE LOWER(SubCategory) = LOWER(?)");
        $stmt->bind_param("s", $subCategory);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Exists: update description & category
            $stmt->bind_result($subCategoryCode);
            $stmt->fetch();
            $stmt->close();

            $update = $conn->prepare("UPDATE sub_category SET Description=?, CategoryCode=? WHERE SubCategoryCode=?");
            $update->bind_param("sss", $description, $selectedCategoryCode, $subCategoryCode);
            if ($update->execute()) {
                $msg = "✅ Sub-Category already exists (Code: <b>$subCategoryCode</b>). Description and Category updated.";
            } else {
                $msg = "❌ Error updating sub-category: " . $conn->error;
            }
            $update->close();

            $descriptionValue = $description;
        } else {
            // Insert new sub-category with category code
            $stmt->close();
            $codeResult = $conn->query("SELECT LPAD(IFNULL(MAX(CAST(SUBSTRING(SubCategoryCode, 4) AS UNSIGNED)), 0) + 1, 6, '0') AS NextCode FROM sub_category");
            $codeRow = $codeResult->fetch_assoc();
            $subCategoryCode = 'sca' . $codeRow['NextCode'];

            $insert = $conn->prepare("INSERT INTO sub_category (SubCategoryCode, SubCategory, Description, CategoryCode) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $subCategoryCode, $subCategory, $description, $selectedCategoryCode);
            if ($insert->execute()) {
                $msg = "✅ Sub-Category added. Code: <b>$subCategoryCode</b>";
                $descriptionValue = $description;
            } else {
                $msg = "❌ Error inserting sub-category: " . $conn->error;
            }
            $insert->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Sub-Category Entry</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="style.css" />
</head>
<body>
<div class="container">
    <h1>Sub-Category Entry</h1>

    <?php if ($msg): ?>
        <p class="msg <?= str_starts_with($msg, '✅') ? 'ok' : 'err' ?>"><?= $msg ?></p>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <label for="CategoryCode">Category*</label>
        <select name="CategoryCode" id="CategoryCode" required>
            <option value="">-- Select Category --</option>
            <?php while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($cat['CategoryCode']) ?>" <?= ($cat['CategoryCode'] === $selectedCategoryCode) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['Category']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="SubCategory">Sub-Category*</label>
        <div class="autocomplete-wrapper">
            <input type="text" name="SubCategory" id="SubCategory" required value="<?= htmlspecialchars($subCategory) ?>" />
            <ul id="suggestions" class="autocomplete-list"></ul>
        </div>

        <label for="Description">Description</label>
        <textarea name="Description" id="Description" rows="3"><?= htmlspecialchars($descriptionValue) ?></textarea>

        <input type="submit" value="Save Sub-Category" />
    </form>

    <p><a href="item_entry.php" class="back-link">&larr; Back to Item Entry</a></p>
</div>

<script>
const subCategoryInput = document.getElementById('SubCategory');
const descriptionInput = document.getElementById('Description');
const categorySelect = document.getElementById('CategoryCode');
const suggestionsList = document.getElementById('suggestions');

subCategoryInput.addEventListener('input', async () => {
    const query = subCategoryInput.value.trim();
    suggestionsList.innerHTML = '';

    if (query.length < 1) {
        descriptionInput.value = '';
        return;
    }

    const res = await fetch(`search_sub_category.php?q=${encodeURIComponent(query)}`);
    if (!res.ok) return;

    const subCategories = await res.json();
    subCategories.forEach(row => {
        const li = document.createElement('li');
        li.textContent = row.SubCategory;
        li.title = row.Description || '';
        li.addEventListener('click', () => {
            subCategoryInput.value = row.SubCategory;
            descriptionInput.value = row.Description || '';
            categorySelect.value = row.CategoryCode || '';
            suggestionsList.innerHTML = '';
        });
        suggestionsList.appendChild(li);
    });
});

document.addEventListener('click', (e) => {
    if (!suggestionsList.contains(e.target) && e.target !== subCategoryInput) {
        suggestionsList.innerHTML = '';
    }
});
</script>
</body>
</html>
