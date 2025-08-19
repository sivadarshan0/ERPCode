<?php
// File: /includes/functions.php
// Final validated and production-ready version.

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
// ----- Sequence ID Generator (Production Version) -----
// -----------------------------------------

function generate_sequence_id($sequence_name, $table, $column) {
    $db = db();
    // Using a try...catch block is safer for production to prevent exposing detailed DB errors.
    try {
        $stmt = $db->prepare("SELECT prefix, next_value, digit_length FROM system_sequences WHERE sequence_name = ? FOR UPDATE");
        $stmt->bind_param("s", $sequence_name);
        $stmt->execute();
        $seq = $stmt->get_result()->fetch_assoc();
        if (!$seq) { throw new Exception("Sequence '$sequence_name' not found."); }

        $new_id = $seq['prefix'] . str_pad($seq['next_value'], $seq['digit_length'], '0', STR_PAD_LEFT);
        $next_value_for_update = $seq['next_value'] + 1;
        
        $check_sql = "SELECT `$column` FROM `$table` WHERE `$column` = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("s", $new_id);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            error_log("SEQUENCE-RECOVERY: ID '$new_id' for '$sequence_name' already exists. Recalculating...");
            $prefix_len = strlen($seq['prefix']);
            $find_max_sql = "SELECT MAX(CAST(SUBSTRING(`$column`, " . ($prefix_len + 1) . ") AS UNSIGNED)) FROM `$table` WHERE `$column` LIKE ?";
            $find_max_stmt = $db->prepare($find_max_sql);
            $like_prefix = $seq['prefix'] . '%';
            $find_max_stmt->bind_param("s", $like_prefix);
            $find_max_stmt->execute();
            $max_used = (int) $find_max_stmt->get_result()->fetch_row()[0];
            
            $recalculated_num = $max_used + 1;
            $new_id = $seq['prefix'] . str_pad($recalculated_num, $seq['digit_length'], '0', STR_PAD_LEFT);
            $next_value_for_update = $recalculated_num + 1;
        }

        $update = $db->prepare("UPDATE system_sequences SET next_value = ?, last_used_at = NOW(), last_used_by = ? WHERE sequence_name = ?");
        $user_id = (string) ($_SESSION['user_id'] ?? ''); 
        $update->bind_param("iss", $next_value_for_update, $user_id, $sequence_name);
        if (!$update->execute()) {
             throw new Exception("Failed to update sequence table.");
        }
        
        return $new_id;
    } catch (Exception $e) {
        // Log the detailed error for the developer
        error_log("SEQUENCE-ERROR: " . $e->getMessage());
        // Return a generic error to the user-facing script
        throw new Exception("Could not generate a unique ID. A system error occurred.");
    }
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

// IMPROVED: This function now returns the item's UOM as well.
function search_items_by_name($name) {
    $db = db();
    if (!$db) return [];
    $search_term = "%$name%";
    $stmt = $db->prepare("SELECT i.item_id, i.name, i.uom, cs.name as sub_category_name, c.name as parent_category_name FROM items i JOIN categories_sub cs ON i.category_sub_id = cs.category_sub_id JOIN categories c ON cs.category_id = c.category_id WHERE i.name LIKE ? ORDER BY i.name LIMIT 10");
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

function adjust_stock_level($item_id, $type, $quantity, $reason, $stock_check_type = 'Ex-Stock') {
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

    // MODIFIED LOGIC:
    // Only throw a fatal error if the stock type is 'Ex-Stock' and the level goes negative.
    if ($type === 'OUT' && $stock_check_type === 'Ex-Stock') {
        $check_stmt = $db->prepare("SELECT quantity FROM stock_levels WHERE item_id = ?");
        $check_stmt->bind_param("s", $item_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        if ($result && $result['quantity'] < 0) {
            throw new Exception("Operation failed: Stock level for item $item_id cannot go below zero for an 'Ex-Stock' order.");
        }
    }
    // For 'Pre-Book' orders, we allow the stock to go negative.
    
    return true;
}


// -----------------------------------------
// ----- GRN (Goods Received Note) Functions -----
// -----------------------------------------

function process_grn($grn_date, $items, $remarks) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");
    if (empty($grn_date) || !is_array($items) || empty($items)) {
        throw new Exception("GRN date and at least one item are required.");
    }
    foreach ($items as $item) {
        if (empty($item['item_id']) || !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) { throw new Exception("Invalid data in item rows. Each item needs an ID and valid quantity."); }
        if (!isset($item['cost']) || !is_numeric($item['cost']) || !isset($item['weight']) || !is_numeric($item['weight'])) { throw new Exception("Invalid data in item rows. Cost and Weight must be valid numbers."); }
    }
    $db->begin_transaction();
    try {
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['username'] ?? 'Unknown';
        $grn_id = generate_sequence_id('grn_id', 'grn', 'grn_id');
        $stmt_grn = $db->prepare("INSERT INTO grn (grn_id, grn_date, remarks, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
        $stmt_grn->bind_param("sssis", $grn_id, $grn_date, $remarks, $user_id, $user_name);
        $stmt_grn->execute();
        $stmt_items = $db->prepare("INSERT INTO grn_items (grn_id, item_id, uom, quantity, cost, weight) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt_items->bind_param("sssddd", $grn_id, $item['item_id'], $item['uom'], $item['quantity'], $item['cost'], $item['weight']);
            $stmt_items->execute();
            $stock_reason = "Received via GRN #" . $grn_id;
            adjust_stock_level($item['item_id'], 'IN', $item['quantity'], $stock_reason);
        }
        $db->commit();
        return $grn_id;
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception("Failed to process GRN: " . $e->getMessage());
    }
}

// -----------------------------------------
// ----- Sales Order Functions -----
// -----------------------------------------

/**
 * Searches for items for an 'Ex-Stock' order, returning price, uom, and stock.
 * @param string $name The item name to search for.
 * @return array An array of matching items.
 */
function search_items_for_order($name) {
    $db = db();
    if (!$db) return [];
    $search_term = "%$name%";
    $stmt = $db->prepare("
        SELECT 
            i.item_id, i.name, i.uom,
            COALESCE(sl.quantity, 0.00) AS stock_on_hand,
            (SELECT gi.cost FROM grn_items gi WHERE gi.item_id = i.item_id ORDER BY gi.grn_item_id DESC LIMIT 1) as last_cost
        FROM items i
        LEFT JOIN stock_levels sl ON i.item_id = sl.item_id
        WHERE i.name LIKE ? ORDER BY i.name LIMIT 10
    ");
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Searches for items for a 'Pre-Book' order, returning only basic details.
 * @param string $name The item name to search for.
 * @return array An array of matching items.
 */
function search_items_for_prebook($name) {
    $db = db();
    if (!$db) return [];
    $search_term = "%$name%";
    $stmt = $db->prepare("SELECT item_id, name, uom FROM items WHERE name LIKE ? ORDER BY name LIMIT 10");
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Processes a complete Sales Order.
 * Crucially, it only adjusts stock levels for 'Ex-Stock' orders.
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
            throw new Exception("Invalid data in item rows.");
        }
        $total_amount += $item['quantity'] * $item['price'];
    }

    $db->begin_transaction();
    try {
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['username'] ?? 'Unknown';

        $order_id = generate_sequence_id('order_id', 'orders', 'order_id');
        
        $stmt_order = $db->prepare("
            INSERT INTO orders (order_id, customer_id, order_date, total_amount, other_expenses, payment_method, payment_status, status, stock_type, remarks, created_by, created_by_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_order->bind_param("sssddsssssis", $order_id, $customer_id, $order_date, $total_amount, $details['other_expenses'], $details['payment_method'], $details['payment_status'], $details['order_status'], $details['stock_type'], $details['remarks'], $user_id, $user_name);
        $stmt_order->execute();

        $stmt_items = $db->prepare("INSERT INTO order_items (order_id, item_id, quantity, price, cost_price, profit_margin) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt_items->bind_param("ssdddd", $order_id, $item['item_id'], $item['quantity'], $item['price'], $item['cost_price'], $item['profit_margin']);
            $stmt_items->execute();

            // --- CRITICAL LOGIC CHANGE ---
            // Only deduct from stock if the order type is 'Ex-Stock'.
            if ($details['stock_type'] === 'Ex-Stock') {
                $stock_reason = "Sold via Order #" . $order_id;
                adjust_stock_level($item['item_id'], 'OUT', $item['quantity'], $stock_reason, 'Ex-Stock'); // Pass 'Ex-Stock' to enforce the check
            }
        }

        $db->commit();
        return ['id' => $order_id, 'total' => number_format($total_amount, 2, '.', ',')];
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception("Failed to process order. Reason: " . $e->getMessage());
    }
}

/**
 * Retrieves a single, complete order with its items for viewing/editing.
 *
 * @param string $order_id The ID of the order to fetch.
 * @return array|false The complete order data, or false if not found.
 */
function get_order_details($order_id) {
    $db = db();
    if (!$db) return false;

    // 1. Get the main order details
    $stmt_order = $db->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt_order->bind_param("s", $order_id);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();

    if (!$order) {
        return false; // Order not found
    }

    // 2. Get associated customer details
    $order['customer'] = get_customer($order['customer_id']);

    // 3. Get all line items for the order
    $stmt_items = $db->prepare("SELECT oi.*, i.name as item_name, i.uom, COALESCE(sl.quantity, 0) as stock_on_hand FROM order_items oi JOIN items i ON oi.item_id = i.item_id LEFT JOIN stock_levels sl ON oi.item_id = sl.item_id WHERE oi.order_id = ?");
    $stmt_items->bind_param("s", $order_id);
    $stmt_items->execute();
    $order['items'] = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    // 4. Get the order status history for the order
    $stmt_history = $db->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
    $stmt_history->bind_param("s", $order_id);
    $stmt_history->execute();
    $order['status_history'] = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);

    // 5. NEW: Get the payment status history for the order
    $stmt_payment_history = $db->prepare("SELECT * FROM payment_status_history WHERE order_id = ? ORDER BY created_at ASC");
    $stmt_payment_history->bind_param("s", $order_id);
    $stmt_payment_history->execute();
    $order['payment_history'] = $stmt_payment_history->get_result()->fetch_all(MYSQLI_ASSOC);

    return $order;
}

/**
 * Updates the status and details of an existing order and tracks history.
 *
 * @param string $order_id The ID of the order to update.
 * @param array $details An array of details to update (status, payment_method, etc.).
 * @return bool True on success, false on failure.
 * @throws Exception On validation or database errors.
 */
function update_order_details($order_id, $details) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");
    if (empty($order_id) || !is_array($details)) throw new Exception("Invalid arguments for updating order.");

    $db->begin_transaction();
    try {
        // --- Get the current state of the order from the database BEFORE updating ---
        $stmt_check = $db->prepare("SELECT status, payment_status FROM orders WHERE order_id = ?");
        $stmt_check->bind_param("s", $order_id);
        $stmt_check->execute();
        $current_state = $stmt_check->get_result()->fetch_assoc();
        $old_order_status = $current_state['status'];
        $old_payment_status = $current_state['payment_status'];

        // --- Update the main order table ---
        $stmt = $db->prepare("UPDATE orders SET status = ?, payment_method = ?, payment_status = ?, other_expenses = ?, remarks = ? WHERE order_id = ?");
        if (!$stmt) throw new Exception("Database error: Failed to prepare statement.");
        $stmt->bind_param("sssdss", $details['order_status'], $details['payment_method'], $details['payment_status'], $details['other_expenses'], $details['remarks'], $order_id);
        $stmt->execute();

        // --- Create history records ONLY if the status has actually changed ---
        if ($old_order_status !== $details['order_status']) {
            $stmt_history = $db->prepare("INSERT INTO order_status_history (order_id, status, created_by, created_by_name) VALUES (?, ?, ?, ?)");
            $stmt_history->bind_param("ssis", $order_id, $details['order_status'], $_SESSION['user_id'], $_SESSION['username']);
            $stmt_history->execute();
        }
        
        // NEW: Check and record payment status history
        if ($old_payment_status !== $details['payment_status']) {
            $stmt_payment_history = $db->prepare("INSERT INTO payment_status_history (order_id, payment_status, created_by, created_by_name) VALUES (?, ?, ?, ?)");
            $stmt_payment_history->bind_param("ssis", $order_id, $details['payment_status'], $_SESSION['user_id'], $_SESSION['username']);
            $stmt_payment_history->execute();
        }
        
        $db->commit();
        return true;

    } catch (Exception $e) {
        $db->rollback();
        throw new Exception("Failed to update order: " . $e->getMessage());
    }
}

// -----------------------------------------
// ----- Order Listing/Search Functions -----
// -----------------------------------------

/**
 * Searches for orders based on various criteria.
 *
 * @param array $filters An associative array of filters (e.g., ['order_id' => 'ORD001', 'customer' => 'John', 'date_from' => 'Y-m-d', 'date_to' => 'Y-m-d']).
 * @return array An array of matching orders.
 */

function search_orders($filters = []) {
    $db = db();
    if (!$db) return [];

    $sql = "
        SELECT 
            o.order_id,
            o.order_date,
            o.total_amount,
            o.status,
            o.payment_status,
            o.stock_type, -- Added stock_type to the SELECT statement
            c.name AS customer_name,
            c.phone AS customer_phone
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        WHERE 1=1
    ";

    $params = [];
    $types = '';

    if (!empty($filters['order_id'])) {
        $sql .= " AND o.order_id LIKE ?";
        $params[] = '%' . $filters['order_id'] . '%';
        $types .= 's';
    }
    if (!empty($filters['customer'])) {
        $sql .= " AND (c.name LIKE ? OR c.phone LIKE ?)";
        $customer_query = '%' . $filters['customer'] . '%';
        $params[] = $customer_query;
        $params[] = $customer_query;
        $types .= 'ss';
    }
    if (!empty($filters['status'])) {
        $sql .= " AND o.status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    if (!empty($filters['payment_status'])) {
        $sql .= " AND o.payment_status = ?";
        $params[] = $filters['payment_status'];
        $types .= 's';
    }
    // ADDED: Logic for the new stock_type filter
    if (!empty($filters['stock_type'])) {
        $sql .= " AND o.stock_type = ?";
        $params[] = $filters['stock_type'];
        $types .= 's';
    }
    if (!empty($filters['date_from'])) {
        $sql .= " AND o.order_date >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND o.order_date <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }

    $sql .= " ORDER BY o.order_date DESC, o.order_id DESC";
    
    $stmt = $db->prepare($sql);
    if (!$stmt) return [];
    
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// -----------------------------------------
// ----- Purchase Order Functions -----
// -----------------------------------------

/**
 * Processes a complete Purchase Order, creating the main record and all its item lines
 * in a single database transaction.
 *
 * @param string $po_date The date of the Purchase Order.
 * @param string $supplier_name The name of the supplier.
 * @param array $items An array of items, each being an array with 'item_id', 'quantity', and 'cost_price'.
 * @param string $remarks Optional remarks for the PO.
 * @return string The ID of the newly created Purchase Order.
 * @throws Exception On validation or database errors.
 */
function process_purchase_order($po_date, $supplier_name, $items, $remarks) {
    $db = db();
    if (!$db) {
        throw new Exception("Database connection failed.");
    }

    // --- Validation ---
    if (empty($po_date) || !is_array($items) || empty($items)) {
        throw new Exception("PO date and at least one item are required.");
    }
    foreach ($items as $item) {
        if (empty($item['item_id']) || !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
            throw new Exception("Invalid data in item rows. Each item needs an ID and a valid quantity.");
        }
        if (!isset($item['cost_price']) || !is_numeric($item['cost_price'])) {
            throw new Exception("Invalid data in item rows. Each item needs a valid cost price.");
        }
    }

    $db->begin_transaction();

    try {
        // Step 1: Get user details from session
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['username'] ?? 'Unknown';

        // Step 2: Create the main Purchase Order record
        $purchase_order_id = generate_sequence_id('purchase_order_id', 'purchase_orders', 'purchase_order_id');
        
        $stmt_po = $db->prepare(
            "INSERT INTO purchase_orders (purchase_order_id, po_date, supplier_name, remarks, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt_po) throw new Exception("Database prepare failed for PO header: " . $db->error);
        
        $stmt_po->bind_param("ssssis", $purchase_order_id, $po_date, $supplier_name, $remarks, $user_id, $user_name);
        $stmt_po->execute();

        // Step 3: Loop through each item and add it to the purchase_order_items table
        $stmt_items = $db->prepare(
            "INSERT INTO purchase_order_items (purchase_order_id, item_id, quantity, cost_price) VALUES (?, ?, ?, ?)"
        );
        if (!$stmt_items) throw new Exception("Database prepare failed for PO items: " . $db->error);

        foreach ($items as $item) {
            $stmt_items->bind_param("ssdd", $purchase_order_id, $item['item_id'], $item['quantity'], $item['cost_price']);
            $stmt_items->execute();
        }

        // If everything was successful, commit the transaction
        $db->commit();
        
        return $purchase_order_id; // Return the new PO ID

    } catch (Exception $e) {
        // If any part of the process failed, roll back everything
        $db->rollback();
        // Pass the error message up to the calling page
        throw new Exception("Failed to process Purchase Order: " . $e->getMessage());
    }
}

/**
 * Searches for open Pre-Orders to link them to a Purchase Order.
 *
 * @param string $query The Order ID or Customer Name to search for.
 * @return array An array of matching Pre-Orders.
 */
function search_open_pre_orders($query) {
    $db = db();
    if (!$db) return [];
    
    $search_term = "%$query%";
    // We search for orders that are of type 'Pre-Book' and have a status that indicates they are waiting for fulfillment.
    $stmt = $db->prepare("
        SELECT 
            o.order_id,
            o.order_date,
            c.name as customer_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.stock_type = 'Pre-Book' 
          AND o.status IN ('New', 'Awaiting Stock')
          AND (o.order_id LIKE ? OR c.name LIKE ?)
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}