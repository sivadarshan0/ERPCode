<?php
// File: /modules/customer/get_customer_data.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['customer_id'])) {
    echo json_encode(['error' => 'Customer ID required']);
    exit;
}

$customer = get_customer($_GET['customer_id']);
if (!$customer) {
    echo json_encode(['error' => 'Customer not found']);
    exit;
}

echo json_encode($customer);