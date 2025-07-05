<?php
// customer_entry.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ───── DB connection ─────
$DB_HOST = "localhost";
$DB_USER = "dbauser";
$DB_PASS = "dbauser";
$DB_NAME = "erpdb";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_errno) {
    die("DB connection failed: " . $conn->connect_error);
}

$msg = '';
$lastCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $PhoneNumber    = trim($_POST['PhoneNumber'] ?? '');
    $Name           = trim($_POST['Name'] ?? '');
    $Email          = trim($_POST['Email'] ?? '');
    $Address        = trim($_POST['Address'] ?? '');
    $City           = trim($_POST['City'] ?? '');
    $District       = trim($_POST['District'] ?? '');
    $FirstOrderDate = $_POST['FirstOrderDate'] ?? null;

    // Check if phone number exists
    $check = $conn->prepare("SELECT CustomerCode FROM customers WHERE PhoneNumber = ?");
    $check->bind_param("s", $PhoneNumber);
    $check->execute();
    $result = $check->get_result();
    $exists = $result->fetch_assoc();
    $check->close();

    if ($exists) {
        // Update existing customer
        $stmt = $conn->prepare(
            "UPDATE customers SET
             Name = ?, Email = ?, Address = ?, City = ?, District = ?, FirstOrderDate = ?
             WHERE PhoneNumber = ?"
        );
        $stmt->bind_param("sssssss", $Name, $Email, $Address, $City, $District, $FirstOrderDate, $PhoneNumber);
        if ($stmt->execute()) {
            $msg = "✅ Customer details updated.";
            $lastCode = $exists['CustomerCode'];
        } else {
            $msg = "❌ Update failed: " . $conn->error;
        }
    } else {
        // Insert new customer
        $stmt = $conn->prepare(
            "INSERT INTO customers
             (PhoneNumber, Name, Email, Address, City, District, FirstOrderDate)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssss", $PhoneNumber, $Name, $Email, $Address, $City, $District, $FirstOrderDate);
        if ($stmt->execute()) {
            // Fetch generated CustomerCode
            $sel = $conn->prepare("SELECT CustomerCode FROM customers WHERE PhoneNumber = ?");
            $sel->bind_param("s", $PhoneNumber);
            $sel->execute();
            $res = $sel->get_result();
            $lastCode = $res->fetch_assoc()['CustomerCode'] ?? '';
            $msg = "✅ New customer added. Code: <b>$lastCode</b>";
            $sel->close();
        } else {
            $msg = "❌ Insert failed: " . $conn->error;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Customer Entry</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Positioning for autocomplete */
    .autocomplete-wrapper {
      position: relative;
    }
    .autocomplete-list {
      border: 1px solid #ccc;
      border-top: none;
      border-bottom-left-radius: 6px;
      border-bottom-right-radius: 6px;
      background: white;
      list-style: none;
      margin: 0;
      padding: 0;
      max-height: 150px;
      overflow-y: auto;
      position: absolute;
      width: 100%;
      z-index: 1000;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }
    .autocomplete-list li {
      padding: 8px;
      cursor: pointer;
    }
    .autocomplete-list li:hover {
      background-color: #f0f0f0;
    }
    .autocomplete-list:empty {
      display: none !important;
    }
    /* Remove bottom radius on input when autocomplete visible */
    .autocomplete-wrapper input {
      border-bottom-left-radius: 0;
      border-bottom-right-radius: 0;
    }
  </style>
</head>
<body>
<a href="index.php" class="back-link">← Back to Main Menu</a>
<div class="container">
  <h1>Customer Entry</h1>

  <?php if ($msg): ?>
    <p class="msg <?= str_starts_with($msg, '✅') ? 'ok' : 'err' ?>"><?= $msg ?></p>
  <?php endif; ?>

  <form method="POST" id="customerForm" autocomplete="off">
    <label>Phone Number*</label>
    <div class="autocomplete-wrapper">
      <input type="text" name="PhoneNumber" id="PhoneNumber" required pattern="[0-9+]{7,15}" title="Valid phone number" autocomplete="off">
      <ul id="phoneSuggestions" class="autocomplete-list"></ul>
    </div>

    <label>Name*</label>
    <input type="text" name="Name" required>

    <label>Email</label>
    <input type="email" name="Email">

    <label>Address</label>
    <textarea name="Address" rows="2"></textarea>

    <label>City</label>
    <input type="text" name="City">

    <label>District</label>
    <input type="text" name="District">

    <label>First Order Date</label>
    <input type="date" name="FirstOrderDate">

    <input type="submit" value="Save Customer">
  </form>

  <?php if ($lastCode): ?>
    <p class="msg ok">🆔 Customer Code: <strong><?= $lastCode ?></strong></p>
  <?php endif; ?>
</div>

<script>
const phoneInput = document.getElementById('PhoneNumber');
const suggestionBox = document.getElementById('phoneSuggestions');

async function fetchCustomers(phone) {
  if (!phone || phone.length < 3) {
    suggestionBox.innerHTML = '';
    return;
  }

  try {
    const res = await fetch(`search_customer.php?phone=${encodeURIComponent(phone)}`);
    if (!res.ok) return;
    const customers = await res.json();

    suggestionBox.innerHTML = '';
    customers.forEach(cust => {
      const li = document.createElement('li');
      li.textContent = `${cust.PhoneNumber} - ${cust.Name}`;
      li.addEventListener('click', () => {
        phoneInput.value = cust.PhoneNumber;
        document.querySelector('input[name="Name"]').value = cust.Name || '';
        document.querySelector('input[name="Email"]').value = cust.Email || '';
        document.querySelector('textarea[name="Address"]').value = cust.Address || '';
        document.querySelector('input[name="City"]').value = cust.City || '';
        document.querySelector('input[name="District"]').value = cust.District || '';
        document.querySelector('input[name="FirstOrderDate"]').value = cust.FirstOrderDate || '';

        suggestionBox.innerHTML = '';
      });
      suggestionBox.appendChild(li);
    });
  } catch (e) {
    console.warn('Fetch error:', e);
  }
}

let debounceTimeout;
phoneInput.addEventListener('input', () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    fetchCustomers(phoneInput.value.trim());
  }, 300);
});

// Hide suggestions if clicking outside
document.addEventListener('click', (e) => {
  if (!suggestionBox.contains(e.target) && e.target !== phoneInput) {
    suggestionBox.innerHTML = '';
  }
});
</script>
</body>
</html>
