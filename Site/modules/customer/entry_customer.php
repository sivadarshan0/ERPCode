<?php

// File: /modules/customer/entry_customer.php

// Start session at the very beginning (no spaces before this!)
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define application constant
define('_IN_APP_', true);

// Include necessary files
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: /login.php');
    exit;
}

// Initialize variables
$customer = [
    'customer_id' => '',
    'phone' => '',
    'name' => '',
    'address' => '',
    'city' => '',
    'postal_code' => '',
    'email' => '',
    'first_order_date' => '',
    'description' => ''
];
$is_edit = false;
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = db();
        
        // Sanitize inputs
        $phone = trim($_POST['phone'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_order_date = trim($_POST['first_order_date'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $customer_id = $_POST['customer_id'] ?? null;
        
        // Validate required fields
        if (empty($phone) || empty($name)) {
            throw new Exception("Phone and name are required");
        }
        
        // Check for duplicate phone only if it's a new customer or phone number is being changed
        if (empty($customer_id)) {
            $stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Phone number already exists");
            }
        }
        
        // Prepare data
        $current_user_id = $_SESSION['user_id'];
        $current_time = date('Y-m-d H:i:s');
        
        if ($customer_id) {
            // Update existing customer
            $stmt = $db->prepare("UPDATE customers SET 
                phone = ?, name = ?, address = ?, city = ?, postal_code = ?,
                email = ?, first_order_date = ?, description = ?,
                updated_at = ?, updated_by = ?
                WHERE customer_id = ?");
            $stmt->bind_param("ssssssssss", 
                $phone, $name, $address, $city, $postal_code,
                $email, $first_order_date, $description,
                $current_time, $current_user_id, $customer_id);
            $action = 'updated';
        } else {
            // Create new customer
            $customer_id = 'CUS' . str_pad(rand(1000, 9999), 5, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("INSERT INTO customers 
                (customer_id, phone, name, address, city, postal_code,
                 email, first_order_date, description,
                 created_at, created_by, updated_at, updated_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssss", 
                $customer_id, $phone, $name, $address, $city, $postal_code,
                $email, $first_order_date, $description,
                $current_time, $current_user_id, $current_time, $current_user_id);
            $action = 'created';
        }
        
        if ($stmt->execute()) {
            $message = "Customer successfully $action!";
            $message_type = 'success';
            $customer = get_customer($customer_id);
            $is_edit = true;
        } else {
            throw new Exception("Database error: " . $db->error);
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
        // Preserve submitted values
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

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><?= $is_edit ? 'Edit' : 'Create' ?> Customer</h1>
        <?php if ($is_edit): ?>
        <span class="badge bg-primary"><?= htmlspecialchars($customer['customer_id']) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <form method="POST" class="needs-validation" novalidate>
        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer['customer_id']) ?>">
        
        <div class="row g-3">
            <div class="col-md-6">
                <label for="phone" class="form-label">Phone Number *</label>
                <input type="tel" class="form-control" id="phone" name="phone" 
                       value="<?= htmlspecialchars($customer['phone']) ?>" 
                       pattern="[0-9]{10,15}" required>
                <div class="invalid-feedback">Please enter a valid phone number (10-15 digits)</div>
            </div>
            
            <div class="col-md-6">
                <label for="name" class="form-label">Full Name *</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?= htmlspecialchars($customer['name']) ?>" required>
                <div class="invalid-feedback">Please enter the customer name</div>
            </div>
            
            <div class="col-12">
                <label for="address" class="form-label">Address</label>
                <input type="text" class="form-control" id="address" name="address" 
                       value="<?= htmlspecialchars($customer['address']) ?>">
            </div>
            
            <div class="col-md-6">
                <label for="city" class="form-label">City</label>
                <input type="text" class="form-control" id="city" name="city" 
                       value="<?= htmlspecialchars($customer['city']) ?>">
            </div>
            
            <div class="col-md-6">
                <label for="postal_code" class="form-label">Postal Code</label>
                <input type="text" class="form-control" id="postal_code" name="postal_code" 
                       value="<?= htmlspecialchars($customer['postal_code']) ?>">
            </div>
            
            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?= htmlspecialchars($customer['email']) ?>">
            </div>
            
            <div class="col-md-6">
                <label for="first_order_date" class="form-label">First Order Date</label>
                <input type="date" class="form-control" id="first_order_date" name="first_order_date" 
                       value="<?= htmlspecialchars($customer['first_order_date']) ?>">
            </div>
            
            <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= 
                    htmlspecialchars($customer['description']) 
                ?></textarea>
            </div>
            
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-save"></i> <?= $is_edit ? 'Update' : 'Create' ?> Customer
                </button>
                <a href="list_customers.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
                <?php if ($is_edit): ?>
                <a href="entry_customer.php" class="btn btn-outline-success">
                    <i class="bi bi-plus"></i> New Customer
                </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>