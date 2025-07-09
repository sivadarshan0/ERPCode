<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$DB_HOST = "localhost";
$DB_USER = "dbauser";
$DB_PASS = "dbauser";  // Replace with secure value
$DB_NAME = "erpdb";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$subCategory = $_GET['subcategory'] ?? '';
if (strlen($subCategory) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT SubCategoryCode, SubCategory, Description FROM sub_category WHERE SubCategory = ?");
$stmt->bind_param("s", $subCategory);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode([]);
}

$stmt->close();
$conn->close();
?>
