<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// AJAX Handler for Live Search
if (isset($_GET['phone_lookup'])) {
    header('Content-Type: application/json');
    echo json_encode(search_customers_by_phone($_GET['phone_lookup']));
    exit;
}

// Authentication Check
if (!is_logged_in()) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: /login.php');
    exit;
}

// Initialize Customer Data
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

// Load Existing Customer
if (isset($_GET['customer_id'])) {
    $customer = get_customer($_GET['customer_id']);
    if (!$customer) {
        $_SESSION['error_message'] = "Customer not found";
        header("Location: list_customers.php");
        exit;
    }
    $is_edit = true;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and process form data
        $customer_id = $_POST['customer_id'] ?? null;
        $required = ['phone', 'name'];
        
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }
        
        // Check for duplicate phone
        $db = db();
        $stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ?" . 
                            ($customer_id ? " AND customer_id != ?" : ""));
        $stmt->bind_param($customer_id ? "ss" : "s", 
                         $_POST['phone'], 
                         ...($customer_id ? [$customer_id] : []));
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Phone number already exists");
        }
        
        // Save customer
        if ($customer_id) {
            // Update existing
            $stmt = $db->prepare("UPDATE customers SET 
                phone = ?, name = ?, address = ?, city = ?, postal_code = ?,
                email = ?, first_order_date = ?, description = ?,
                updated_at = NOW(), updated_by = ?
                WHERE customer_id = ?");
            $stmt->bind_param("ssssssssis", ...[
                $_POST['phone'], $_POST['name'], $_POST['address'], 
                $_POST['city'], $_POST['postal_code'], $_POST['email'],
                $_POST['first_order_date'], $_POST['description'],
                $_SESSION['user_id'], $customer_id
            ]);
            $action = 'updated';
        } else {
            // Create new
            $customer_id = generate_sequence_id('customer_id');
            $stmt = $db->prepare("INSERT INTO customers 
                (customer_id, phone, name, address, city, postal_code,
                 email, first_order_date, description,
                 created_at, created_by, updated_at, updated_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), ?)");
            $stmt->bind_param("ssssssssii", ...[
                $customer_id, $_POST['phone'], $_POST['name'], 
                $_POST['address'], $_POST['city'], $_POST['postal_code'],
                $_POST['email'], $_POST['first_order_date'], $_POST['description'],
                $_SESSION['user_id'], $_SESSION['user_id']
            ]);
            $action = 'created';
        }
        
        if ($stmt->execute()) {
            $message = "Customer $action successfully";
            $message_type = 'success';
            $customer = get_customer($customer_id);
            $is_edit = true;
        } else {
            throw new Exception("Database error: " . $db->error);
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
        $customer = array_merge($customer, $_POST);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <!-- Form UI remains exactly as you had it -->
    <!-- Include all your form fields exactly as before -->
    
    <script>
    // Enhanced Live Search
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('phone');
        const phoneResults = document.getElementById('phoneResults');

        if (!phoneInput) return;

        function doPhoneLookup() {
            const phone = phoneInput.value.trim();
            if (phone.length < 3) {
                phoneResults.classList.add('d-none');
                return;
            }
            
            fetch(`?phone_lookup=${encodeURIComponent(phone)}`)
                .then(response => response.json())
                .then(data => {
                    phoneResults.innerHTML = '';
                    if (data.length) {
                        data.forEach(customer => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'list-group-item list-group-item-action py-2';
                            item.innerHTML = `
                                <div class="d-flex justify-content-between">
                                    <span><strong>${customer.name}</strong><br>
                                    <small>${customer.phone}</small></span>
                                    <span class="badge bg-primary">${customer.customer_id}</span>
                                </div>
                            `;
                            item.onclick = () => {
                                window.location.href = `?customer_id=${customer.customer_id}`;
                            };
                            phoneResults.appendChild(item);
                        });
                        phoneResults.classList.remove('d-none');
                    } else {
                        phoneResults.classList.add('d-none');
                    }
                });
        }

        phoneInput.addEventListener('input', debounce(doPhoneLookup, 300));
        
        document.addEventListener('click', (e) => {
            if (!phoneResults.contains(e.target) && e.target !== phoneInput) {
                phoneResults.classList.add('d-none');
            }
        });
    });
    </script>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>