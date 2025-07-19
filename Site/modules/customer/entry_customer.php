<?php
// File: /modules/customer/entry_customer.php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Handle AJAX live search
if (isset($_GET['phone_lookup'])) {
    header('Content-Type: application/json');
    try {
        $phone = trim($_GET['phone_lookup']);
        echo json_encode(strlen($phone) >= 3 ? search_customers_by_phone($phone) : []);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: /login.php');
    exit;
}

// Get current user info for created_by_name and updated_by_name
$current_user_id = $_SESSION['user_id'];
$current_user_name = 'Unknown';
$user_stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
$user_stmt->bind_param("i", $current_user_id);
$user_stmt->execute();
$user_stmt->bind_result($username);
if ($user_stmt->fetch()) {
    $current_user_name = $username;
}
$user_stmt->close();

// Load customer if editing
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
    'profile' => '',
    'created_by_name' => $current_user_name,
    'updated_by_name' => null
];
$is_edit = false;

if (isset($_GET['customer_id'])) {
    $customer = get_customer($_GET['customer_id']);
    if (!$customer) {
        $_SESSION['error_message'] = "Customer not found";
        header("Location: list_customers.php");
        exit;
    }
    $is_edit = true;
}

$message = '';
$message_type = '';
$clear_form = false;

// Handle POST (Create / Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = db();

        $phone = trim($_POST['phone'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $known_by = trim($_POST['known_by'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_order_date = trim($_POST['first_order_date'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $profile = trim($_POST['profile'] ?? '');
        $customer_id = $_POST['customer_id'] ?? null;

        if (empty($phone) || empty($name)) {
            throw new Exception("Phone and name are required");
        }

        // Check for duplicate phone
        $check_stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ?" . ($customer_id ? " AND customer_id != ?" : ""));
        if ($customer_id) {
            $check_stmt->bind_param("ss", $phone, $customer_id);
        } else {
            $check_stmt->bind_param("s", $phone);
        }
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Phone number already exists");
        }

        $current_time = date('Y-m-d H:i:s');

        if ($customer_id) {
            // Update existing customer
            $stmt = $db->prepare("UPDATE customers SET 
                phone=?, name=?, address=?, city=?, district=?, postal_code=?, 
                known_by=?, email=?, first_order_date=?, description=?, profile=?, 
                updated_at=?, updated_by=?, updated_by_name=?
                WHERE customer_id=?");
            $stmt->bind_param("ssssssssssssss", 
                $phone, $name, $address, $city, $district, $postal_code, 
                $known_by, $email, $first_order_date, $description, $profile, 
                $current_time, $current_user_id, $current_user_name, $customer_id);
            $action = 'updated';
        } else {
            // Create new customer
            $customer_id = generate_sequence_id('customer_id');
            $stmt = $db->prepare("INSERT INTO customers (
                customer_id, phone, name, address, city, district, postal_code, 
                known_by, email, first_order_date, description, profile, 
                created_at, created_by, created_by_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssssss", 
                $customer_id, $phone, $name, $address, $city, $district, $postal_code, 
                $known_by, $email, $first_order_date, $description, $profile, 
                $current_time, $current_user_id, $current_user_name);
            $action = 'created';
            $clear_form = true;
        }

        if ($stmt->execute()) {
            $message = "✅ Customer successfully $action.";
            $message_type = 'success';
            
            if ($clear_form) {
                // Clear form for new entries
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
                    'profile' => '',
                    'created_by_name' => $current_user_name,
                    'updated_by_name' => null
                ];
                $is_edit = false;
            } else {
                $customer = get_customer($customer_id);
                $is_edit = true;
            }
        } else {
            throw new Exception("❌ Failed to save customer: " . $db->error);
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        $customer = $_POST;
        $is_edit = !empty($_POST['customer_id']);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2><?= $is_edit ? 'Edit' : 'New' ?> Customer <?= $is_edit ? "<span class='badge bg-primary'>{$customer['customer_id']}</span>" : '' ?></h2>

    <?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3 needs-validation" novalidate id="customerForm">
        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer['customer_id']) ?>">

        <div class="col-md-6 position-relative">
            <label for="phone" class="form-label">Phone *</label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone']) ?>" pattern="[0-9]{10,15}" required>
            <div class="invalid-feedback">Enter a valid phone number</div>
            <div id="phoneResults" class="list-group mt-1 d-none" style="position: absolute; z-index: 999; max-height: 300px; overflow-y: auto;"></div>
        </div>

        <div class="col-md-6">
            <label for="name" class="form-label">Full Name *</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($customer['name']) ?>" required>
            <div class="invalid-feedback">Name is required</div>
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
            <textarea class="form-control" name="description"><?= htmlspecialchars($customer['description']) ?></textarea>
        </div>

        <div class="col-12">
            <label for="profile" class="form-label">Profile Notes</label>
            <textarea class="form-control" name="profile"><?= htmlspecialchars($customer['profile']) ?></textarea>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit"><?= $is_edit ? 'Update' : 'Create' ?> Customer</button>
            <a href="/index.php" class="btn btn-outline-secondary">Back</a>
            <?php if ($is_edit): ?>
                <a href="entry_customer.php" class="btn btn-outline-success">+ New Customer</a>
            <?php endif; ?>
        </div>
    </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const phoneInput = document.getElementById('phone');
    const phoneResults = document.getElementById('phoneResults');
    const form = document.getElementById('customerForm');

    if (!phoneInput) return;

    // Escape HTML
    const escapeHtml = text => text.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));

    // Debounce
    const debounce = (func, delay) => {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    };

    // Update UI for edit mode
    const setEditMode = (customerId, customerName) => {
        const header = document.querySelector('main.container h2');
        const submitBtn = document.querySelector('button[type="submit"]');
        
        if (header && submitBtn) {
            header.innerHTML = `Edit Customer <span class='badge bg-primary'>${escapeHtml(customerId)}</span>`;
            submitBtn.textContent = 'Update Customer';
            
            // Show + New Customer button if not present
            if (!document.querySelector('a.btn-outline-success')) {
                const backBtn = document.querySelector('a.btn-outline-secondary');
                if (backBtn) {
                    const newBtn = document.createElement('a');
                    newBtn.href = 'entry_customer.php';
                    newBtn.className = 'btn btn-outline-success ms-2';
                    newBtn.textContent = '+ New Customer';
                    backBtn.insertAdjacentElement('afterend', newBtn);
                }
            }
        }
    };

    // Phone lookup
    const doPhoneLookup = debounce(() => {
        const phone = phoneInput.value.trim();
        if (phone.length < 3) {
            phoneResults.classList.add('d-none');
            return;
        }

        fetch(`entry_customer.php?phone_lookup=${encodeURIComponent(phone)}`)
            .then(response => response.json())
            .then(data => {
                phoneResults.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(c => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `<strong>${escapeHtml(c.name)}</strong><br>
                                         <small>${escapeHtml(c.phone)}</small>
                                         <span class="badge bg-primary float-end">${escapeHtml(c.customer_id)}</span>`;
                        item.onclick = () => {
                            setEditMode(c.customer_id, c.name);
                            window.location.href = `entry_customer.php?customer_id=${c.customer_id}`;
                        };
                        phoneResults.appendChild(item);
                    });
                    phoneResults.classList.remove('d-none');
                } else {
                    phoneResults.classList.add('d-none');
                }
            })
            .catch(() => phoneResults.classList.add('d-none'));
    }, 300);

    phoneInput.addEventListener('input', doPhoneLookup);
    document.addEventListener('click', e => {
        if (!phoneResults.contains(e.target) && e.target !== phoneInput) {
            phoneResults.classList.add('d-none');
        }
    });

    // Initialize form validation
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>