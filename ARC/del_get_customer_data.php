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

/**
 * Returns complete customer data including:
 * - customer_id, phone, name, address, city, district, postal_code
 * - email, first_order_date, description, profile
 * - known_by (source: Instagram/Facebook/SearchEngine/Friends/Other)
 * - created_at, created_by, created_by_name
 * - updated_at, updated_by, updated_by_name
 */
echo json_encode($customer);