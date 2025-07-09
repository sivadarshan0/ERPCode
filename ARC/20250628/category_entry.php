<?php
// File: category_entry.php

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
$categoryCode = null;
$category = '';
$descriptionValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category    = trim($_POST['Category'] ?? '');
    $description = trim($_POST['Description'] ?? '');

    if ($category === '') {
        $msg = "❌ Category name is required.";
    } else {
        // Check if category exists (case-insensitive)
        $stmt = $conn->prepare("SELECT CategoryCode FROM category WHERE LOWER(Category) = LOWER(?)");
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Category exists - update description
            $stmt->bind_result($categoryCode);
            $stmt->fetch();
            $stmt->close();

            $update = $conn->prepare("UPDATE category SET Description=? WHERE CategoryCode=?");
            $update->bind_param("ss", $description, $categoryCode);
            if ($update->execute()) {
                // Changed warning icon ⚠️ to green checkmark ✅ here:
                $msg = "✅ Category already exists (Code: <b>$categoryCode</b>). Description updated.";
            } else {
                $msg = "❌ Error updating description: " . $conn->error;
            }
            $update->close();

            $descriptionValue = $description; // Show updated description in form
        } else {
            // Insert new category
            $stmt->close();
            $codeResult = $conn->query("SELECT LPAD(IFNULL(MAX(CAST(SUBSTRING(CategoryCode, 4) AS UNSIGNED)), 0) + 1, 6, '0') AS NextCode FROM category");
            $codeRow = $codeResult->fetch_assoc();
            $categoryCode = 'cat' . $codeRow['NextCode'];

            $insert = $conn->prepare("INSERT INTO category (CategoryCode, Category, Description) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $categoryCode, $category, $description);
            if ($insert->execute()) {
                $msg = "✅ Category added. Code: <b>$categoryCode</b>";
                $descriptionValue = $description;
            } else {
                $msg = "❌ Error inserting category: " . $conn->error;
            }
            $insert->close();
        }
    }
} else if ($category !== '') {
    // For GET or other cases, fetch description for the category if needed (optional)
    $stmt = $conn->prepare("SELECT Description FROM category WHERE LOWER(Category) = LOWER(?)");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $stmt->bind_result($desc);
    if ($stmt->fetch()) {
        $descriptionValue = $desc;
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Category Entry</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Category Entry</h1>

    <?php if ($msg): ?>
        <p class="msg <?= str_starts_with($msg, '✅') ? 'ok' : 'err' ?>"><?= $msg ?></p>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <label for="Category">Category*</label>
        <div class="autocomplete-wrapper">
            <input type="text" name="Category" id="Category" required value="<?= htmlspecialchars($category) ?>">
            <ul id="suggestions" class="autocomplete-list"></ul>
        </div>

        <label for="Description">Description</label>
        <textarea name="Description" id="Description" rows="3"><?= htmlspecialchars($descriptionValue) ?></textarea>

        <input type="submit" value="Save Category">
    </form>

    <p><a href="item_entry.php" class="back-link">&larr; Back to Item Entry</a></p>
</div>

<script>
const categoryInput = document.getElementById('Category');
const descriptionInput = document.getElementById('Description');
const suggestionsList = document.getElementById('suggestions');

categoryInput.addEventListener('input', async () => {
    const query = categoryInput.value.trim();
    suggestionsList.innerHTML = '';

    if (query.length < 1) {
        descriptionInput.value = '';
        return;
    }

    const res = await fetch(`search_category.php?q=${encodeURIComponent(query)}`);
    if (!res.ok) return;

    const categories = await res.json();
    categories.forEach(row => {
        const li = document.createElement('li');
        li.textContent = row.Category;
        li.title = row.Description || '';
        li.addEventListener('click', () => {
            categoryInput.value = row.Category;
            descriptionInput.value = row.Description || '';
            suggestionsList.innerHTML = '';
        });
        suggestionsList.appendChild(li);
    });
});

document.addEventListener('click', (e) => {
    if (!suggestionsList.contains(e.target) && e.target !== categoryInput) {
        suggestionsList.innerHTML = '';
    }
});
</script>
</body>
</html>
