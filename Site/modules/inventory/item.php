<?php
// Enable errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Step 1: Starting Script<br>";

require_once __DIR__.'/../../includes/header.php';
echo "Step 2: Header included<br>";

echo "<pre>Session Data: ";
print_r($_SESSION);
echo "</pre>";


require_login();
echo "Step 3: User login verified<br>";

$conn = db();
echo "Step 4: Database connected<br>";

$msg = '';
$itemData = null;

// Handle form submission
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
            echo "Step 7a: Updating existing item<br>";
            $stmt = $conn->prepare("UPDATE item SET Item=?, Description=?, CategoryCode=?, SubCategoryCode=? WHERE ItemCode=?");
            $stmt->bind_param("sssss", $itemName, $description, $categoryCode, $subCategoryCode, $itemCode);
            $action = 'updated';
        } else {
            echo "Step 7b: Inserting new item<br>";
            $itemCode = Database::getInstance()->generateCode('item_code', 'itm');
            $stmt = $conn->prepare("INSERT INTO item (ItemCode, Item, Description, CategoryCode, SubCategoryCode) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $itemCode, $itemName, $description, $categoryCode, $subCategoryCode);
            $action = 'created';
        }

        $stmt->execute();
        echo "Step 8: SQL executed<br>";

        $conn->commit();
        echo "Step 9: Transaction committed<br>";

        $msg = "Item $action successfully! Code: $itemCode";
        $itemData = compact('itemCode', 'itemName', 'description', 'categoryCode', 'subCategoryCode');
    } catch (Exception $e) {
        $conn->rollback();
        echo "Step 10: Transaction rolled back<br>";
        $msg = "Error: " . $e->getMessage();
        echo "Error Message: " . $msg . "<br>";
    }
}

// Fetch categories
echo "Step 11: Fetching categories<br>";
$categories = $conn->query("SELECT CategoryCode, Category FROM category ORDER BY Category");
if (!$categories) {
    echo "MySQL Error: " . $conn->error;
    exit;
}
echo "Step 12: Categories fetched<br>";
?>
