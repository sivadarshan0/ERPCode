<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';

if (!is_ajax()) {
    json_response(['error' => 'Invalid request'], 400);
}

$type = $_GET['type'] ?? '';
$query = sanitize_input($_GET['q'] ?? '');

if (strlen($query) < 2) {
    json_response([]);
}

try {
    $conn = db();
    $results = [];
    
    switch ($type) {
        case 'item':
            $stmt = $conn->prepare("SELECT ItemCode as id, Item as text FROM item WHERE Item LIKE CONCAT(?, '%') LIMIT 10");
            break;
        case 'customer':
            $stmt = $conn->prepare("SELECT PhoneNumber as id, CONCAT(Name, ' (', PhoneNumber, ')') as text FROM customers WHERE PhoneNumber LIKE CONCAT(?, '%') OR Name LIKE CONCAT(?, '%') LIMIT 10");
            $query = "%$query%";
            break;
        case 'category':
            $stmt = $conn->prepare("SELECT CategoryCode as id, Category as text FROM category WHERE Category LIKE CONCAT(?, '%') LIMIT 10");
            break;
        default:
            json_response(['error' => 'Invalid search type'], 400);
    }
    
    $stmt->bind_param("s", $query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    
    json_response($results);
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 500);
}