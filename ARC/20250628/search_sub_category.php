<?php
// File: search_sub_category.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$DB_HOST = "localhost";
$DB_USER = "dbauser";
$DB_PASS = "dbauser";
$DB_NAME = "erpdb";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$query = $_GET['q'] ?? '';
$query = trim($query);
if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT SubCategoryCode, SubCategory, Description, CategoryCode FROM sub_category WHERE SubCategory LIKE CONCAT(?, '%') ORDER BY SubCategory LIMIT 10");
$stmt->bind_param("s", $query);
$stmt->execute();
$result = $stmt->get_result();

$subs = [];
while ($row = $result->fetch_assoc()) {
    $subs[] = $row;
}

echo json_encode($subs);

$stmt->close();
$conn->close();
