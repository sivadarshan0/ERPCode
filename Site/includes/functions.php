<?php
// File: /includes/functions.php
// This version contains enhanced error reporting in generate_sequence_id to find the root cause.

defined('_IN_APP_') or die('Unauthorized access');

require_once __DIR__ . '/../config/db.php';

// -----------------------------------------
// ----- Auth-related functions -----
// -----------------------------------------

function is_account_locked($username) {
    $db = db();
    $stmt = $db->prepare("SELECT locked_until FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    return ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) ? $user['locked_until'] : false;
}

function get_user_by_username($username) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function update_user_login($user_id, $success, $ip_address) {
    $db = db();
    if ($success) {
        $stmt = $db->prepare("UPDATE users SET last_login = NOW(), failed_attempts = 0, locked_until = NULL WHERE id = ?");
    } else {
        $stmt = $db->prepare("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    if (!$success) {
        $stmt_check = $db->prepare("SELECT failed_attempts FROM users WHERE id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $user = $stmt_check->get_result()->fetch_assoc();
        if ($user && $user['failed_attempts'] >= 5) {
            $lock_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt_lock = $db->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
            $stmt_lock->bind_param("si", $lock_time, $user_id);
            $stmt_lock->execute();
        }
    }
    log_login_attempt($user_id, $ip_address, $success);
}

function log_login_attempt($user_id, $ip_address, $success) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO user_login_audit (user_id, login_time, ip_address, success) VALUES (?, NOW(), ?, ?)");
    $stmt->bind_param("isi", $user_id, $ip_address, $success);
    $stmt->execute();
}

// -----------------------------------------
// ----- Session handling -----
// -----------------------------------------

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: /login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// -----------------------------------------
// ----- Sequence ID Generator (DEBUG VERSION) -----
// -----------------------------------------

function generate_sequence_id($sequence_name, $table, $column) {
    $db = db();
    
    // Step 1: Lock and select the sequence row
    $stmt = $db->prepare("SELECT prefix, next_value, digit_length FROM system_sequences WHERE sequence_name = ? FOR UPDATE");
    if (!$stmt) { throw new Exception("ID Generation Error: Failed to prepare SELECT statement. DB error: " . $db->error); }
    
    $stmt->bind_param("s", $sequence_name);
    if (!$stmt->execute()) { throw new Exception("ID Generation Error: Failed to execute SELECT statement. Stmt error: " . $stmt->error); }

    $seq = $stmt->get_result()->fetch_assoc();
    if (!$seq) { throw new Exception("ID Generation Error: Sequence '$sequence_name' not found in system_sequences table."); }

    // Step 2: Calculate the new ID
    $new_id = $seq['prefix'] . str_pad($seq['next_value'], $seq['digit_length'], '0', STR_PAD_LEFT);
    $next_value_for_update = $seq['next_value'] + 1;
    
    // Step 3: Safety check if the ID already exists in the target table
    $check_sql = "SELECT `$column` FROM `$table` WHERE `$column` = ?";
    $check_stmt = $db->prepare($check_sql);
    if (!$check_stmt) { throw new Exception("ID Generation Error: Failed to prepare safety check statement. DB error: " . $db->error); }
    
    $check_stmt->bind_param("s", $new_id);
    if (!$check_stmt->execute()) { throw new Exception("ID Generation Error: Failed to execute safety check statement. Stmt error: " . $check_stmt->error); }

    // Step 4: Optional recovery logic if a duplicate is found
    if ($check_stmt->get_result()->num_rows > 0) {
        error_log("SEQUENCE-RECOVERY: ID '$new_id' for '$sequence_name' already exists. Recalculating...");
        $prefix_len = strlen($seq['prefix']);
        $find_max_sql = "SELECT MAX(CAST(SUBSTRING(`$column`, " . ($prefix_len + 1) . ") AS UNSIGNED)) FROM `$table` WHERE `$column` LIKE ?";
        $find_max_stmt = $db->prepare($find_max_sql);
        if (!$find_max_stmt) { throw new Exception("ID Generation Error: Failed to prepare recovery statement. DB error: " . $db->error); }
        
        $like_prefix = $seq['prefix'] . '%';
        $find_max_stmt->bind_param("s", $like_prefix);
        if (!$find_max_stmt->execute()) { throw new Exception("ID Generation Error: Failed to execute recovery statement. Stmt error: " . $find_max_stmt->error); }

        $max_used = (int) $find_max_stmt->get_result()->fetch_row()[0];
        $recalculated_num = $max_used + 1;
        $new_id = $seq['prefix'] . str_pad($recalculated_num, $seq['digit_length'], '0', STR_PAD_LEFT);
        $next_value_for_update = $recalculated_num + 1;
    }

    // Step 5: Update the sequence table with the new next_value
    $update = $db->prepare("UPDATE system_sequences SET next_value = ?, last_used_at = NOW(), last_used_by = ? WHERE sequence_name = ?");
    if (!$update) { throw new Exception("ID Generation Error: Failed to prepare UPDATE statement. DB error: " . $db->error); }
    
    // Force user_id to string to match varchar column, preventing type issues
    $user_id = (string) ($_SESSION['user_id'] ?? ''); 
    
    $update->bind_param("iss", $next_value_for_update, $user_id, $sequence_name);
    
    if (!$update->execute()) {
        // This is the most important part - it will give us the specific MySQLi error
        throw new Exception("ID Generation Error: FAILED TO EXECUTE UPDATE. Stmt error: " . $update->error);
    }
    
    return $new_id;
}


// -----------------------------------------
// ----- Customer-related functions -----
// -----------------------------------------

function get_customer($customer_id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param("s", $customer_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function search_customers_by_phone($phone) {
    $db = db();
    $search_term = "%$phone%";
    $stmt = $db->prepare("SELECT customer_id, name, phone FROM customers WHERE phone LIKE ? ORDER BY name LIMIT 10");
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function validate_customer_phone($phone, $exclude_id = null) {
    $db = db();
    $sql = "SELECT COUNT(*) FROM customers WHERE phone = ?";
    $params = [$phone];
    $types = "s";
    if ($exclude_id) {
        $sql .= " AND customer_id != ?";
        $params[] = $exclude_id;
        $types .= "s";
    }
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_row()[0] == 0;
}

// -----------------------------------------
// ----- Category-related functions -----
// -----------------------------------------

function get_category($category_id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->bind_param("s", $category_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function search_categories_by_name($name) {
    $db = db();
    $search_term = "%$name%";
    $stmt = $db->prepare("SELECT category_id, name, description FROM categories WHERE name LIKE ? ORDER BY name LIMIT 10");
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_all_categories() {
    $db = db();
    $result = $db->query("SELECT category_id, name FROM categories ORDER BY name ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// -----------------------------------------
// ----- Sub-Category-related functions -----
// -----------------------------------------

function get_sub_category($category_sub_id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM categories_sub WHERE category_sub_id = ?");
    $stmt->bind_param("s", $category_sub_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function search_sub_categories_by_name($name) {
    $db = db();
    $search_term = "%$name%";
    $stmt = $db->prepare("SELECT cs.category_sub_id, cs.name, cs.description, c.name as parent_category_name FROM categories_sub cs JOIN categories c ON cs.category_id = c.category_id WHERE cs.name LIKE ? ORDER BY cs.name LIMIT 10");
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_sub_categories_by_category_id($category_id) {
    $db = db();
    $stmt = $db->prepare("SELECT category_sub_id, name FROM categories_sub WHERE category_id = ? ORDER BY name ASC");
    $stmt->bind_param("s", $category_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// -----------------------------------------
// ----- Item-related functions -----
// -----------------------------------------

function get_item($item_id) {
    $db = db();
    $stmt = $db->prepare("SELECT i.*, cs.category_id FROM items i JOIN categories_sub cs ON i.category_sub_id = cs.category_sub_id WHERE i.item_id = ?");
    $stmt->bind_param("s", $item_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function search_items_by_name($name) {
    $db = db();
    if (!$db) return [];

    $search_term = "%$name%";
    $stmt = $db->prepare("
        SELECT 
            i.item_id, 
            i.name, 
            i.uom, -- ADDED: Include the UOM in the search result
            cs.name as sub_category_name,
            c.name as parent_category_name
        FROM items i
        JOIN categories_sub cs ON i.category_sub_id = cs.category_sub_id
        JOIN categories c ON cs.category_id = c.category_id
        WHERE i.name LIKE ? 
        ORDER BY i.name 
        LIMIT 10
    ");
    if (!$stmt) return [];

    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// -----------------------------------------
// ----- Stock Management Functions -----
// -----------------------------------------

function get_all_stock_levels() {
    $db = db();
    $sql = "SELECT i.item_id, i.name AS item_name, c.name AS category_name, cs.name AS sub_category_name, COALESCE(sl.quantity, 0.00) AS quantity FROM items i LEFT JOIN stock_levels sl ON i.item_id = sl.item_id JOIN categories_sub cs ON i.category_sub_id = cs.category_sub_id JOIN categories c ON cs.category_id = c.category_id ORDER BY c.name, cs.name, i.name";
    $result = $db->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function adjust_stock_level($item_id, $type, $quantity, $reason) {
    $db = db();
    if (empty($item_id) || !in_array($type, ['IN', 'OUT']) || !is_numeric($quantity) || $quantity <= 0) {
        throw new Exception("Invalid arguments for stock adjustment.");
    }

    $transaction_id = generate_sequence_id('transaction_id', 'stock_transactions', 'transaction_id');
    $stmt_trans = $db->prepare("INSERT INTO stock_transactions (transaction_id, item_id, transaction_type, quantity_change, reason, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_trans->bind_param("sssdsis", $transaction_id, $item_id, $type, $quantity, $reason, $_SESSION['user_id'], $_SESSION['username']);
    $stmt_trans->execute();

    $update_quantity = ($type === 'IN') ? $quantity : -$quantity;
    $sql_update = "INSERT INTO stock_levels (item_id, quantity) VALUES (?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?";
    $stmt_level = $db->prepare($sql_update);
    $stmt_level->bind_param("sdd", $item_id, $update_quantity, $update_quantity);
    $stmt_level->execute();

    $check_stmt = $db->prepare("SELECT quantity FROM stock_levels WHERE item_id = ?");
    $check_stmt->bind_param("s", $item_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    if ($result && $result['quantity'] < 0) {
        throw new Exception("Operation failed: Stock level for item $item_id cannot go below zero.");
    }
    return true;
}

// -----------------------------------------
// ----- GRN (Goods Received Note) Functions -----
// -----------------------------------------

function process_grn($grn_date, $items, $remarks) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");

    // --- Validation ---
    if (empty($grn_date) || !is_array($items) || empty($items)) {
        throw new Exception("GRN date and at least one item are required.");
    }
    foreach ($items as $item) {
        if (empty($item['item_id']) || !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
            throw new Exception("Invalid data in item rows. Each item needs an ID and valid quantity.");
        }
        // Also validate that cost and weight are numeric, even if they are zero
        if (!isset($item['cost']) || !is_numeric($item['cost']) || !isset($item['weight']) || !is_numeric($item['weight'])) {
             throw new Exception("Invalid data in item rows. Cost and Weight must be valid numbers.");
        }
    }

    $db->begin_transaction();

    try {
        // Step 1: Get user details from session
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['username'] ?? 'Unknown';

        // Step 2: Create the main GRN record
        $grn_id = generate_sequence_id('grn_id', 'grn', 'grn_id');
        $stmt_grn = $db->prepare("INSERT INTO grn (grn_id, grn_date, remarks, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
        $stmt_grn->bind_param("sssis", $grn_id, $grn_date, $remarks, $user_id, $user_name);
        $stmt_grn->execute();

        // Step 3: Loop through each item, add it to grn_items, and adjust stock
        $stmt_items = $db->prepare("INSERT INTO grn_items (grn_id, item_id, uom, quantity, cost, weight) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($items as $item) {
            // Add to grn_items table with new cost and weight fields
            $stmt_items->bind_param("sssddd", $grn_id, $item['item_id'], $item['uom'], $item['quantity'], $item['cost'], $item['weight']);
            $stmt_items->execute();

            // Adjust stock level using our existing robust function
            $stock_reason = "Received via GRN #" . $grn_id;
            adjust_stock_level($item['item_id'], 'IN', $item['quantity'], $stock_reason);
        }

        // If everything succeeded, commit the transaction
        $db->commit();
        
        return $grn_id; // Return the new GRN ID on success

    } catch (Exception $e) {
        // If any part of the process failed, roll back everything
        $db->rollback();
        // Pass the error message up to the calling page
        throw new Exception("Failed to process GRN: " . $e->getMessage());
    }
}

// -----------------------------------------
// ----- Sales Order Functions -----
// -----------------------------------------

/**
 * Searches for items and returns details needed for an order line:
 * last cost price, uom, and current stock level.
 *
 * @param string $name The item name to search for.
 * @return array An array of matching items.
 */
function search_items_for_order($name) {
    $db = db();
    if (!$db) return [];

    $search_term = "%$name%";
    $stmt = $db->prepare("
        SELECT 
            i.item_id, 
            i.name, 
            i.uom,
            COALESCE(sl.quantity, 0.00) AS stock_on_hand,
            (SELECT gi.cost FROM grn_items gi WHERE gi.item_id = i.item_id ORDER BY gi.grn_item_id DESC LIMIT 1) as last_cost
        FROM items i
        LEFT JOIN stock_levels sl ON i.item_id = sl.item_id
        WHERE i.name LIKE ? 
        ORDER BY i.name 
        LIMIT 10
    ");
    if (!$stmt) return [];

    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    // CORRECTED: Use fetch_all to ensure the result is always an array.
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Processes a complete Sales Order with all the new detailed fields.
 *
 * @param string $customer_id The ID of the customer placing the order.
 * @param string $order_date The date of the order.
 * @param array $items An array of items, each with 'item_id', 'quantity', 'price', 'cost_price', 'profit_margin'.
 * @param array $details An array containing other order details like statuses and remarks.
 * @return array The ID and formatted total amount of the newly created order.
 * @throws Exception On validation or database errors.
 */
function process_order($customer_id, $order_date, $items, $details) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");

    // --- Validation ---
    if (empty($customer_id) || empty($order_date) || !is_array($items) || empty($items)) {
        throw new Exception("Customer, date, and at least one item are required.");
    }

    $total_amount = 0;
    foreach ($items as $item) {
        if (empty($item['item_id']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0 || !is_numeric($item['price'])) {
            throw new Exception("Invalid data in item rows. Each item needs an ID, quantity, and price.");
        }
        $total_amount += $item['quantity'] * $item['price'];
    }

    $db->begin_transaction();
    try {
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['username'] ?? 'Unknown';

        // Step 1: Create the main order record with all the new status fields
        $order_id = generate_sequence_id('order_id', 'orders', 'order_id');
        $stmt_order = $db->prepare("
            INSERT INTO orders (order_id, customer_id, order_date, total_amount, payment_method, payment_status, status, remarks, created_by, created_by_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_order->bind_param("sssdsissis", 
            $order_id, $customer_id, $order_date, $total_amount, 
            $details['payment_method'], $details['payment_status'], $details['order_status'], 
            $details['remarks'], $user_id, $user_name
        );
        $stmt_order->execute();

        // Step 2: Loop through items, add them to order_items, and deduct from stock
        $stmt_items = $db->prepare("
            INSERT INTO order_items (order_id, item_id, quantity, price, cost_price, profit_margin) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $item) {
            // Add the item to the order_items table
            $stmt_items->bind_param("ssdddd", 
                $order_id, $item['item_id'], $item['quantity'], 
                $item['price'], $item['cost_price'], $item['profit_margin']
            );
            $stmt_items->execute();

            // Deduct the quantity from stock using our existing robust function
            $stock_reason = "Sold via Order #" . $order_id;
            adjust_stock_level($item['item_id'], 'OUT', $item['quantity'], $stock_reason);
        }

        $db->commit();
        // Return the new Order ID and the formatted total amount on success
        return ['id' => $order_id, 'total' => number_format($total_amount, 2, '.', ',')];
    } catch (Exception $e) {
        $db->rollback();
        // Pass the error up to the next level so the user can see it
        throw new Exception("Failed to process order. Reason: " . $e->getMessage());
    }
}