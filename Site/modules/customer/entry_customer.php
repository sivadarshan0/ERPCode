<?php
define('_IN_APP_', true);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Authentication check
require_login();

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
    'description' => '',
    'created_at' => '',
    'created_by' => '',
    'updated_at' => '',
    'updated_by' => ''
];
$is_edit = false;
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = db();
        $db->begin_transaction();

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

        // Validate phone format
        if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            throw new Exception("Invalid phone number format (10-15 digits)");
        }

        // Check for duplicate phone
        $stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ? AND customer_id != ?");
        $stmt->bind_param("ss", $phone, $customer_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Phone number already exists");
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
            $customer_id = generate_sequence_id('customer_id');
            
            $stmt = $db->prepare("INSERT INTO customers 
                (customer_id, phone, name, address, city, postal_code,
                 email, first_order_date, description,
                 created_at, created_by, updated_at, updated_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssis", 
                $customer_id, $phone, $name, $address, $city, $postal_code,
                $email, $first_order_date, $description,
                $current_time, $current_user_id, $current_time, $current_user_id);
            $action = 'created';
        }

        if ($stmt->execute()) {
            $db->commit();
            $message = "Customer $customer_id successfully $action!";
            $message_type = 'success';
            $customer = get_customer($customer_id);
            $is_edit = true;
        } else {
            throw new Exception("Database error: " . $db->error);
        }
    } catch (Exception $e) {
        $db->rollback();
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

// Handle phone lookup (AJAX endpoint)
if (isset($_GET['phone_lookup'])) {
    header('Content-Type: application/json');
    try {
        echo json_encode(search_customers_by_phone($_GET['phone_lookup']));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Load customer if ID provided in URL
if (isset($_GET['customer_id']) && !$is_edit) {
    try {
        $customer = get_customer($_GET['customer_id']);
        if ($customer) {
            $is_edit = true;
        }
    } catch (Exception $e) {
        $message = "Error loading customer: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Include header template
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- HTML Form -->
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

    <form id="customerForm" method="POST" class="needs-validation" novalidate>
        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer['customer_id']) ?>">
        
        <div class="row g-3">
            <!-- Phone Number -->
            <div class="col-md-6">
                <label for="phone" class="form-label">Phone Number *</label>
                <div class="input-group">
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?= htmlspecialchars($customer['phone']) ?>" 
                           pattern="[0-9]{10,15}" required>
                    <button class="btn btn-outline-secondary" type="button" id="phoneLookupBtn">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <div class="invalid-feedback">Please enter a valid 10-15 digit phone number</div>
                <div id="phoneResults" class="list-group mt-2 d-none"></div>
            </div>
            
            <!-- Name -->
            <div class="col-md-6">
                <label for="name" class="form-label">Full Name *</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?= htmlspecialchars($customer['name']) ?>" required>
                <div class="invalid-feedback">Please enter the customer name</div>
            </div>
            
            <!-- Address -->
            <div class="col-12">
                <label for="address" class="form-label">Address</label>
                <input type="text" class="form-control" id="address" name="address" 
                       value="<?= htmlspecialchars($customer['address']) ?>">
            </div>
            
            <!-- City and Postal Code -->
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
            
            <!-- Email -->
            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?= htmlspecialchars($customer['email']) ?>">
            </div>
            
            <!-- First Order Date -->
            <div class="col-md-6">
                <label for="first_order_date" class="form-label">First Order Date</label>
                <input type="date" class="form-control" id="first_order_date" name="first_order_date" 
                       value="<?= htmlspecialchars($customer['first_order_date']) ?>">
            </div>
            
            <!-- Description -->
            <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= 
                    htmlspecialchars($customer['description']) 
                ?></textarea>
            </div>
            
            <?php if ($is_edit): ?>
            <!-- Audit Information -->
            <div class="col-md-6">
                <div class="card bg-light mt-3">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Created</h6>
                        <p class="card-text">
                            <?= date('M j, Y g:i A', strtotime($customer['created_at'])) ?><br>
                            by User #<?= $customer['created_by'] ?>
                        </p>
                        
                        <h6 class="card-subtitle mb-2 text-muted">Last Updated</h6>
                        <p class="card-text">
                            <?= date('M j, Y g:i A', strtotime($customer['updated_at'])) ?><br>
                            by User #<?= $customer['updated_by'] ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Form Actions -->
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-save"></i> <?= $is_edit ? 'Update' : 'Create' ?> Customer
                </button>
                <?php if ($is_edit): ?>
                <a href="entry_customer.php" class="btn btn-outline-secondary">
                    <i class="bi bi-plus"></i> New Customer
                </a>
                <?php endif; ?>
                <a href="list_customers.php" class="btn btn-outline-dark float-end">
                    <i class="bi bi-list-ul"></i> View All Customers
                </a>
            </div>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- JavaScript -->
<script src="/assets/js/app.js"></script>
<script>
// Initialize customer entry functionality
if (typeof initCustomerEntry !== 'undefined') {
    initCustomerEntry();
}
</script>