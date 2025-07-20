<?php
// File: modules/customer/entry_customer.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = "Customer Entry";
$breadcrumbs = [
    'Dashboard' => '/index.php',
    'Customers' => '/modules/customer/list_customers.php',
    'Entry' => ''
];

$customer = [
    'customer_id' => '',
    'phone' => '',
    'name' => '',
    'address' => '',
    'city' => '',
    'district' => '',
    'postal_code' => '',
    'known_by' => '',
    'email' => '',
    'first_order_date' => '',
    'description' => '',
    'profile' => ''
];

$is_edit = false;
$message = '';
$message_type = '';

// Editing existing customer
if (isset($_GET['customer_id']) && $_GET['customer_id'] !== '') {
    $customer = get_customer($_GET['customer_id']);
    if ($customer) {
        $is_edit = true;
    } else {
        $message = "Customer not found.";
        $message_type = "danger";
    }
}

// On form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer = array_merge($customer, $_POST);

    $current_user_id = $_SESSION['user']['id'];
    $current_user_name = $_SESSION['user']['username'];

    if ($is_edit) {
        try {
            // Fetch original customer
            $original = get_customer($_POST['customer_id']);
            if (!$original) {
                throw new Exception("Customer not found.");
            }

            // If phone changed, check for duplicate
            if ($_POST['phone'] !== $original['phone']) {
                $check = $db->prepare("SELECT customer_id FROM customers WHERE phone = ? AND customer_id != ?");
                $check->bind_param("ss", $_POST['phone'], $_POST['customer_id']);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    throw new Exception("Phone number belongs to another customer.");
                }
            }

            // Update existing customer
            $stmt = $db->prepare("UPDATE customers SET 
                phone=?, name=?, address=?, city=?, district=?, postal_code=?, 
                known_by=?, email=?, first_order_date=?, description=?, profile=?, 
                updated_at=NOW(), updated_by=?, updated_by_name=?
                WHERE customer_id=?");

            $stmt->bind_param("ssssssssssssss", 
                $_POST['phone'], $_POST['name'], $_POST['address'], $_POST['city'],
                $_POST['district'], $_POST['postal_code'], $_POST['known_by'], 
                $_POST['email'], $_POST['first_order_date'], $_POST['description'], 
                $_POST['profile'], $current_user_id, $current_user_name, $_POST['customer_id']);

            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $db->error);
            }

            $message = "✅ Customer successfully updated.";
            $message_type = "success";
            $customer = get_customer($_POST['customer_id']);
            $is_edit = true;

        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }

    } else {
        try {
            // Check for duplicate phone on create
            $check = $db->prepare("SELECT customer_id FROM customers WHERE phone = ?");
            $check->bind_param("s", $_POST['phone']);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception("Phone number already exists.");
            }

            $stmt = $db->prepare("INSERT INTO customers (
                phone, name, address, city, district, postal_code, known_by, email, 
                first_order_date, description, profile, created_at, created_by, created_by_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");

            $stmt->bind_param("sssssssssssss", 
                $_POST['phone'], $_POST['name'], $_POST['address'], $_POST['city'],
                $_POST['district'], $_POST['postal_code'], $_POST['known_by'],
                $_POST['email'], $_POST['first_order_date'], $_POST['description'],
                $_POST['profile'], $current_user_id, $current_user_name);

            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $db->error);
            }

            $message = "✅ Customer successfully created.";
            $message_type = "success";
            $customer = []; // Clear form

        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <h2><?= $is_edit ? "Edit Customer" : "New Customer" ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
    <?php endif; ?>

    <form method="post">
        <?php if ($is_edit): ?>
            <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer['customer_id']) ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label>Phone <span class="text-danger">*</span></label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($customer['name']) ?>">
        </div>

        <div class="mb-3">
            <label>Address</label>
            <textarea name="address" class="form-control"><?= htmlspecialchars($customer['address']) ?></textarea>
        </div>

        <div class="mb-3">
            <label>City</label>
            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($customer['city']) ?>">
        </div>

        <div class="mb-3">
            <label>District</label>
            <input type="text" name="district" class="form-control" value="<?= htmlspecialchars($customer['district']) ?>">
        </div>

        <div class="mb-3">
            <label>Postal Code</label>
            <input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($customer['postal_code']) ?>">
        </div>

        <div class="mb-3">
            <label>Known By</label>
            <input type="text" name="known_by" class="form-control" value="<?= htmlspecialchars($customer['known_by']) ?>">
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email']) ?>">
        </div>

        <div class="mb-3">
            <label>First Order Date</label>
            <input type="date" name="first_order_date" class="form-control" value="<?= htmlspecialchars($customer['first_order_date']) ?>">
        </div>

        <div class="mb-3">
            <label>Description</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($customer['description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label>Profile</label>
            <input type="text" name="profile" class="form-control" value="<?= htmlspecialchars($customer['profile']) ?>">
        </div>

        <button type="submit" class="btn btn-primary"><?= $is_edit ? "Update" : "Create" ?> Customer</button>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
