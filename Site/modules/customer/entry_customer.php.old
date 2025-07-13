<?php
// File: /modules/customer/entry_customer.php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// ───── Handle AJAX live search ─────
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

// ───── Ensure logged in ─────
if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: /login.php');
    exit;
}

// ───── Load customer if editing ─────
if (isset($_GET['customer_id'])) {
    $customer = get_customer($_GET['customer_id']);
    if (!$customer) {
        $_SESSION['error_message'] = "Customer not found";
        header("Location: list_customers.php");
        exit;
    }
    $is_edit = true;
} else {
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
}

$message = '';
$message_type = '';

// ───── Handle POST (Create / Update) ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = db();

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

        $current_user_id = $_SESSION['user_id'];
        $current_time = date('Y-m-d H:i:s');

        if ($customer_id) {
            // Update customer
            $stmt = $db->prepare("UPDATE customers SET phone=?, name=?, address=?, city=?, postal_code=?, email=?, first_order_date=?, description=?, updated_at=?, updated_by=? WHERE customer_id=?");
            $stmt->bind_param("sssssssssss", $phone, $name, $address, $city, $postal_code, $email, $first_order_date, $description, $current_time, $current_user_id, $customer_id);
            $action = 'updated';
        } else {
            // Create customer with sequence
            $customer_id = generate_sequence_id('customer_id');
            $stmt = $db->prepare("INSERT INTO customers (customer_id, phone, name, address, city, postal_code, email, first_order_date, description, created_at, created_by, updated_at, updated_by)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssssss", $customer_id, $phone, $name, $address, $city, $postal_code, $email, $first_order_date, $description, $current_time, $current_user_id, $current_time, $current_user_id);
            $action = 'created';
        }

        if ($stmt->execute()) {
            $message = "✅ Customer successfully $action.";
            $message_type = 'success';
            $customer = get_customer($customer_id);
            $is_edit = true;
        } else {
            throw new Exception("❌ Failed to save customer: " . $db->error);
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        $customer = $_POST; // Refill form fields
        $is_edit = !empty($_POST['customer_id']);
    }
}

// ───── Render HTML ─────
require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <h2><?= $is_edit ? 'Edit' : 'New' ?> Customer <?= $is_edit ? "<span class='badge bg-primary'>{$customer['customer_id']}</span>" : '' ?></h2>

    <?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3 needs-validation" novalidate>
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
            <label for="postal_code" class="form-label">Postal Code</label>
            <input type="text" class="form-control" name="postal_code" value="<?= htmlspecialchars($customer['postal_code']) ?>">
        </div>

        <div class="col-md-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($customer['email']) ?>">
        </div>

        <div class="col-md-6">
            <label for="first_order_date" class="form-label">First Order Date</label>
            <input type="date" class="form-control" name="first_order_date" value="<?= htmlspecialchars($customer['first_order_date']) ?>">
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" name="description"><?= htmlspecialchars($customer['description']) ?></textarea>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit"><?= $is_edit ? 'Update' : 'Create' ?> Customer</button>
            <a href="list_customers.php" class="btn btn-outline-secondary">Back</a>
            <?php if ($is_edit): ?>
                <a href="entry_customer.php" class="btn btn-outline-success">+ New Customer</a>
            <?php endif; ?>
        </div>
    </form>
</main>

<script>
// Escape HTML
function escapeHtml(text) {
    return text.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

// Debounce
function debounce(func, delay) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

document.addEventListener('DOMContentLoaded', function () {
    const phoneInput = document.getElementById('phone');
    const phoneResults = document.getElementById('phoneResults');

    if (!phoneInput) return;

    function doPhoneLookup() {
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
                        item.innerHTML = `<strong>${escapeHtml(c.name)}</strong><br><small>${escapeHtml(c.phone)}</small><span class="badge bg-primary float-end">${escapeHtml(c.customer_id)}</span>`;
                        item.onclick = () => window.location.href = `entry_customer.php?customer_id=${c.customer_id}`;
                        phoneResults.appendChild(item);
                    });
                    phoneResults.classList.remove('d-none');
                } else {
                    phoneResults.classList.add('d-none');
                }
            }).catch(() => phoneResults.classList.add('d-none'));
    }

    phoneInput.addEventListener('input', debounce(doPhoneLookup, 300));
    document.addEventListener('click', e => {
        if (!phoneResults.contains(e.target) && e.target !== phoneInput) {
            phoneResults.classList.add('d-none');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
