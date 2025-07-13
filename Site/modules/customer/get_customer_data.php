<?php

// File: /modules/customer/get_customer_data.php

header('Content-Type: application/json');
define('_IN_APP_', true);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_GET['customer_id'])) {
    echo json_encode(['error' => 'Missing customer_id']);
    exit;
}

$customer_id = $_GET['customer_id'];
$customer = get_customer($customer_id);

if (!$customer) {
    echo json_encode(['error' => 'Customer not found']);
    exit;
}

echo json_encode($customer);
