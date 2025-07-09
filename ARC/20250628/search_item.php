<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$DB_HOST = "localhost";
$DB_USER = "dbauser";
$DB_PASS = "dbauser";
$DB_NAME = "erpdb";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT Item, ItemCode, Description, CategoryCode, SubCategoryCode FROM item WHERE Item LIKE CONCAT(?, '%') ORDER BY Item LIMIT 10");
$stmt->bind_param("s", $q);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while ($row = $result->fetch_assoc()) {
    $suggestions[] = $row;
}

echo json_encode($suggestions);

$stmt->close();
$conn->close();
?>
