<?php
header('Content-Type: application/json');

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
if (!$phone) {
    echo json_encode(null);
    exit;
}

$stmt = $conn->prepare("SELECT Name, Email, Address, City, District, FirstOrderDate FROM customers WHERE PhoneNumber = ?");
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();
echo json_encode($result->fetch_assoc() ?: null);
$stmt->close();
