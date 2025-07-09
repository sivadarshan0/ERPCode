<?php
// search_customer.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$DB_HOST = "localhost";
$DB_USER = "dbauser";
$DB_PASS = "dbauser";
$DB_NAME = "erpdb";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

$phone = trim($_GET['phone'] ?? '');
if (strlen($phone) < 3) {
    // Return empty array if search term too short
    echo json_encode([]);
    exit;
}

// Use LIKE for partial matching, case-insensitive
$stmt = $conn->prepare("
    SELECT PhoneNumber, Name, Email, Address, City, District, FirstOrderDate
    FROM customers
    WHERE PhoneNumber LIKE CONCAT('%', ?, '%')
    ORDER BY PhoneNumber
    LIMIT 10
");
$stmt->bind_param("s", $phone);
$stmt->execute();

$result = $stmt->get_result();
$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

echo json_encode($customers);

$stmt->close();
$conn->close();
