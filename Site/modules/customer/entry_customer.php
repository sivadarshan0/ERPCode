<?php
// ─── Initialization ────────────────────────────────
session_start();

define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Debugging - Log session (optional, remove in production)
error_log("Accessing entry_customer.php. Session: " . print_r($_SESSION, true));

// ─── Error Reporting (for dev) ──────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ─── Authentication ────────────────────────────────
require_login();

// ─── Page Meta ──────────────────────────────────────
$page_title = "Customer Entry";
$breadcrumbs = [
    'Dashboard' => '/index.php',
    'Customers' => '/modules/customer/list_customers.php',
    'Entry' => ''
];

// ─── Defaults ───────────────────────────────────────
$customer = [
    'customer_id' => '',
    'phone' => '',
    'name' => '',
    'address' => '',
    'city' => '',
    'postal_code' => '',
    'email' => '',
    'first_order_date' => '',
    'description' => '',
    'created_at' => '',
    'created_by' => '',
    'updated_at' => '',
    'updated_by' => ''
];
$is_edit = false;
$message = '';
$message_type = '';

// ─── Handle POST (Save Customer) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = db();
        $db->begin_transaction();

        $phone = trim($_POST['phone'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_order_date = trim($_POST['first_order_date'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $customer_id = $_POST['customer_id'] ?? null;

        if (empty($phone) || empty($name)) {
            throw new Exception("Phone and name are required");
        }

        if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            throw new Exception("Invalid phone number format (10-15 digits)");
        }

        $stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ? AND customer_id != ?");
        $stmt->bind_param("ss", $phone, $customer_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Phone number already exists");
        }

        $current_user_id = $_SESSION['user_id'];
        $current_time = date('Y-m-d H:i:s');

        if ($customer_id) {
            $stmt = $db->prepare("UPDATE customers SET 
                phone = ?, name = ?, address = ?, city = ?, postal_code = ?,
                email = ?, first_order_date = ?, description = ?,
                updated_at = ?, updated_by = ?
                WHERE customer_id = ?");
            $stmt->bind_param("sssssssssss",
                $phone, $name, $address, $city, $postal_code,
                $email, $first_order_date, $description,
                $current_time, $current_user_id, $customer_id);
            $action = 'updated';
        } else {
            $customer_id = generate_sequence_id('customer_id');
            $stmt = $db->prepare("INSERT INTO customers 
                (customer_id, phone, name, address, city, postal_code,
                 email, first_order_date, description,
                 created_at, created_by, updated_at, updated_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssssss",
                $customer_id, $phone, $name, $address, $city, $postal_code,
                $email, $first_order_date, $description,
                $current_time, $current_user_id, $current_time, $current_user_id);
            $action = 'created';
        }

        if ($stmt->execute()) {
            $db->commit();
            $message = "✅ Customer $customer_id successfully $action!";
            $message_type = 'success';
            $customer = get_customer($customer_id);
            $is_edit = true;
        } else {
            throw new Exception("Database error: " . $db->error);
        }
    } catch (Exception $e) {
        $db->rollback();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        $customer = [
            'customer_id' => $_POST['customer_id'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'name' => $_POST['name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'postal_code' => $_POST['postal_code'] ?? '',
            'email' => $_POST['email'] ?? '',
            'first_order_date' => $_POST['first_order_date'] ?? '',
            'description' => $_POST['description'] ?? ''
        ];
        $is_edit = !empty($customer['customer_id']);
    }
}

// ─── Handle Phone Lookup (AJAX) ─────────────────────
if (isset($_GET['phone_lookup'])) {
    header('Content-Type: application/json');
    try {
        echo json_encode(search_customers_by_phone($_GET['phone_lookup']));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/
