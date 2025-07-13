<?php
define('_IN_APP_', true);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();

$db = db();

$filters = [
    'customer_id' => $_GET['customer_id'] ?? '',
    'name'        => $_GET['name'] ?? '',
    'phone'       => $_GET['phone'] ?? '',
    'city'        => $_GET['city'] ?? '',
];

$where = [];
$params = [];
$types = '';

foreach ($filters as $column => $value) {
    if (!empty($value)) {
        $where[] = "$column LIKE ?";
        $params[] = "%" . $value . "%";
        $types .= 's';
    }
}

$where_sql = $where ? "WHERE " . implode(' AND ', $where) : "";

$sql = "SELECT customer_id, name, phone, city FROM customers $where_sql ORDER BY created_at DESC LIMIT 50";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$customers = $result->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($customers);
