<?php
// File: /modules/customer/entry_customer.php
// Revalidated and refactored for consistency and correctness.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// --- Flash message handling ---
$message = '';
$message_type = '';
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

// Initialize database connection
$db = db();
if (!$db) {
    die("Database connection failed");
}

// Handle AJAX live search for phone numbers
if (isset($_GET['phone_lookup'])) {
    header('Content-Type: application/json');
    try {
        $phone = trim($_GET['phone_lookup']);
        echo json_encode(strlen($phone) >= 3 ? search_customers_by_phone($phone) : []);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Security: Ensure user is logged in for all other operations
require_login();

// Get current user info from the session
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['username'] ?? 'Unknown';

// Initialize default customer structure with all fields
$customer = [
    'customer_id' => '',
    'name' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'district' => '',
    'postal_code' => '',
    'known_by' => '',
    'email' => '',
    'first_order_date' => '',
    'description' => '',
    'profile' => '',
    'created_by_name' => $current_user_name,
    'updated_by_name' => null
];
$is_edit = false;

// Load customer data if editing
if (isset($_GET['customer_id'])) {
    $customer_data = get_customer(trim($_GET['customer_id']));
    if ($customer_data) {
        $customer = array_merge($customer, $customer_data);
        $is_edit = true;
    } else {
        // Use a flash message for the error and redirect
        $_SESSION['error_message'] = "Customer not found.";
        header("Location: entry_customer.php");
        exit;
    }
}

// Handle POST request (Create or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $is_edit_post = !empty($_POST['customer_id']);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) throw new Exception("Customer name is required.");
        if (empty($phone)) throw new Exception("Phone number is required.");
        
        $posted_id = $_POST['customer_id'] ?? null;
        if (!validate_customer_phone($phone, $posted_id)) {
            throw new Exception("This phone number is already registered to another customer.");
        }

        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $known_by = trim($_POST['known_by'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_order_date = !empty($_POST['first_order_date']) ? trim($_POST['first_order_date']) : null;
        $description = trim($_POST['description'] ?? '');
        $profile = trim($_POST['profile'] ?? '');
        
        if ($is_edit_post) {
            // ----- UPDATE existing customer -----
            $stmt = $db->prepare(
                "UPDATE customers SET name=?, phone=?, address=?, city=?, district=?, postal_code=?, known_by=?, email=?, first_order_date=?, description=?, profile=?, updated_at=NOW(), updated_by=?, updated_by_name=? WHERE customer_id=?"
            );
            // CORRECTED LINE: The type string now has 14 characters ("sssssssssssiss") to match the 14 placeholders.
            $stmt->bind_param("sssssssssssiss", $name, $phone, $address, $city, $district, $postal_code, $known_by, $email, $first_order_date, $description, $profile, $current_user_id, $current_user_name, $posted_id);
            $action = 'updated';

        } else {
            // ----- CREATE new customer -----
            $customer_id = generate_sequence_id('customer_id', 'customers', 'customer_id');

            $stmt = $db->prepare(
                "INSERT INTO customers (customer_id, name, phone, address, city, district, postal_code, known_by, email, first_order_date, description, profile, created_at, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)"
            );
            $stmt->bind_param("ssssssssssssis", $customer_id, $name, $phone, $address, $city, $district, $postal_code, $known_by, $email, $first_order_date, $description, $profile, $current_user_id, $current_user_name);
            $action = 'created';
        }

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "✅ Customer successfully $action.";
            header("Location: entry_customer.php");
            exit;
        } else {
            throw new Exception("Database error: Failed to save customer.");
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        $customer = array_merge($customer, $_POST);
        $is_edit = !empty($_POST['customer_id']); 
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2><?= $is_edit ? 'Edit Customer' : 'New Customer' ?>
        <?php if ($is_edit): ?>
            <span class='badge bg-primary'><?= htmlspecialchars($customer['customer_id']) ?></span>
        <?php endif; ?>
    </h2>
    <p><strong>Mode:</strong> <?= $is_edit ? 'EDIT' : 'CREATE' ?></p>

    <?php if ($message): ?>
    <div id="alert-message" class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3 needs-validation" novalidate id="customerForm" action="entry_customer.php<?= $is_edit ? '?customer_id=' . htmlspecialchars($customer['customer_id']) : '' ?>">

        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer['customer_id']) ?>">
        
        <div class="col-md-6">
            <label for="name" class="form-label">Full Name *</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($customer['name']) ?>" required>
            <div class="invalid-feedback">Name is required.</div>
        </div>

        <div class="col-md-6 position-relative">
            <label for="phone" class="form-label">Phone *</label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone']) ?>" required>
            <div class="invalid-feedback">A unique phone number is required.</div>
            <div id="phoneResults" class="list-group mt-1 position-absolute w-100 d-none" style="z-index: 1000;"></div>
        </div>

        <div class="col-12">
            <label for="address" class="form-label">Address</label>
            <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($customer['address']) ?>">
        </div>

        <div class="col-md-4">
            <label for="city" class="form-label">City</label>
            <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($customer['city']) ?>">
        </div>

        <div class="col-md-4">
            <label for="district" class="form-label">District</label>
            <input type="text" class="form-control" name="district" value="<?= htmlspecialchars($customer['district']) ?>">
        </div>

        <div class="col-md-4">
            <label for="postal_code" class="form-label">Postal Code</label>
            <input type="text" class="form-control" name="postal_code" value="<?= htmlspecialchars($customer['postal_code']) ?>">
        </div>

        <div class="col-md-4">
            <label for="known_by" class="form-label">How did they find us?</label>
            <select class="form-select" name="known_by">
                <option value="">-- Select --</option>
                <option value="Instagram" <?= $customer['known_by'] === 'Instagram' ? 'selected' : '' ?>>Instagram</option>
                <option value="Facebook" <?= $customer['known_by'] === 'Facebook' ? 'selected' : '' ?>>Facebook</option>
                <option value="SearchEngine" <?= $customer['known_by'] === 'SearchEngine' ? 'selected' : '' ?>>Search Engine</option>
                <option value="Friends" <?= $customer['known_by'] === 'Friends' ? 'selected' : '' ?>>Friends/Family</option>
                <option value="Other" <?= $customer['known_by'] === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>

        <div class="col-md-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($customer['email']) ?>">
        </div>

        <div class="col-md-4">
            <label for="first_order_date" class="form-label">First Order Date</label>
            <input type="date" class="form-control" name="first_order_date" value="<?= htmlspecialchars($customer['first_order_date']) ?>">
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($customer['description']) ?></textarea>
        </div>

        <div class="col-12">
            <label for="profile" class="form-label">Profile Notes</label>
            <textarea class="form-control" name="profile" rows="3"><?= htmlspecialchars($customer['profile']) ?></textarea>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit"><i class="bi bi-<?= $is_edit ? 'floppy' : 'plus-circle' ?>"></i> <?= $is_edit ? 'Update' : 'Create' ?> Customer</button>
            <a href="/index.php" class="btn btn-outline-secondary">Back</a>
            <?php if ($is_edit): ?>
                <a href="entry_customer.php" class="btn btn-outline-success">+ New Customer</a>
            <?php endif; ?>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>