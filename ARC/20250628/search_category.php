<?php
// File: search_category.php
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
if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT CategoryCode, Category, Description FROM category WHERE Category LIKE CONCAT(?, '%') ORDER BY Category LIMIT 10");
$stmt->bind_param("s", $query);
$stmt->execute();
$result = $stmt->get_result();

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

echo json_encode($categories);

$stmt->close();
$conn->close();
