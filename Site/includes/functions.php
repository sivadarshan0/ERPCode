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

/**
 * Gets the latest stock and cost details for a single item ID.
 *
 * @param string $item_id The exact ID of the item to look up.
 * @return array|null The item details or null if not found.
 */
function get_item_stock_details($item_id) {
    $db = db();
    if (!$db || empty($item_id)) return null;

    $stmt = $db->prepare("
        SELECT 
            i.item_id, 
            COALESCE(sl.quantity, 0.00) AS stock_on_hand,
            (
                SELECT gi.cost 
                FROM grn_items gi
                JOIN grn g ON gi.grn_id = g.grn_id
                WHERE gi.item_id = i.item_id 
                ORDER BY g.grn_date DESC, gi.grn_item_id DESC 
                LIMIT 1
            ) as last_cost
        FROM items i
        LEFT JOIN stock_levels sl ON i.item_id = sl.item_id
        WHERE i.item_id = ?
        LIMIT 1
    ");
    if (!$stmt) return null;

    $stmt->bind_param("s", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
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

function adjust_stock_level($item_id, $type, $quantity, $reason, $stock_check_type = 'Ex-Stock', $existing_db = null) { // <-- Modified
    $db = $existing_db ?? db(); // <-- Modified
    if (empty($item_id) || !in_array($type, ['IN', 'OUT']) || !is_numeric($quantity) || $quantity <= 0) {
        throw new Exception("Invalid arguments for stock adjustment.");
    }
    //... (the rest of the function remains exactly the same)
    $transaction_id = generate_sequence_id('transaction_id', 'stock_transactions', 'transaction_id');
    $stmt_trans = $db->prepare("INSERT INTO stock_transactions (transaction_id, item_id, transaction_type, quantity_change, reason, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_trans->bind_param("sssdsis", $transaction_id, $item_id, $type, $quantity, $reason, $_SESSION['user_id'], $_SESSION['username']);
    $stmt_trans->execute();

    $update_quantity = ($type === 'IN') ? $quantity : -$quantity;
    $sql_update = "INSERT INTO stock_levels (item_id, quantity) VALUES (?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?";
    $stmt_level = $db->prepare($sql_update);
    $stmt_level->bind_param("sdd", $item_id, $update_quantity, $update_quantity);
    $stmt_level->execute();

    if ($type === 'OUT' && $stock_check_type === 'Ex-Stock') {
        $check_stmt = $db->prepare("SELECT quantity FROM stock_levels WHERE item_id = ?");
        $check_stmt->bind_param("s", $item_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        if ($result && $result['quantity'] < 0) {
            throw new Exception("Operation failed: Stock level for item $item_id cannot go below zero for an 'Ex-Stock' order.");
        }
    }
    
    return true;
}

/**
 * Searches and filters stock levels for all items.
 * Joins items with categories, sub-categories, and stock levels.
 *
 * @param array $filters An associative array of filters. 
 *                       Keys can include 'item_name', 'category_id', 'sub_category_id', 'stock_status'.
 * @return array The list of items with their stock levels.
 */
function search_stock_levels($filters = []) {
    $db = db();
    if (!$db) return [];

    $sql = "
        SELECT 
            i.item_id, 
            i.name AS item_name, 
            c.name AS category_name, 
            cs.name AS sub_category_name, 
            COALESCE(sl.quantity, 0.00) AS quantity 
        FROM items i
        LEFT JOIN stock_levels sl ON i.item_id = sl.item_id 
        JOIN categories_sub cs ON i.category_sub_id = cs.category_sub_id 
        JOIN categories c ON cs.category_id = c.category_id
        WHERE 1=1
    ";

    $params = [];
    $types = '';

    if (!empty($filters['item_name'])) {
        $sql .= " AND i.name LIKE ?";
        $params[] = '%' . $filters['item_name'] . '%';
        $types .= 's';
    }
    if (!empty($filters['category_id'])) {
        $sql .= " AND c.category_id = ?";
        $params[] = $filters['category_id'];
        $types .= 's';
    }
    if (!empty($filters['sub_category_id'])) {
        $sql .= " AND cs.category_sub_id = ?";
        $params[] = $filters['sub_category_id'];
        $types .= 's';
    }
    
    // --- ADDED: Stock Status filter logic ---
    if (!empty($filters['stock_status'])) {
        if ($filters['stock_status'] === 'in_stock') {
            $sql .= " AND COALESCE(sl.quantity, 0) > 0";
        } elseif ($filters['stock_status'] === 'out_of_stock') {
            $sql .= " AND COALESCE(sl.quantity, 0) = 0";
        }
    }
    // --- END ---

    $sql .= " ORDER BY c.name, cs.name, i.name";
    
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Stock Level Search Prepare Failed: " . $db->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Helper function to automatically generate a GRN from a completed Purchase Order.
 *
 * @param string $purchase_order_id The ID of the PO to process.
 * @param mysqli $db The existing database connection to use within a transaction.
 * @return string The ID of the newly created GRN.
 * @throws Exception On failure.
 */
function auto_generate_grn_from_po($purchase_order_id, $db) {
    // Step 1: Fetch all items from the purchase order
    $stmt_items = $db->prepare("SELECT poi.item_id, i.uom, poi.quantity, poi.cost_price FROM purchase_order_items poi JOIN items i ON poi.item_id = i.item_id WHERE poi.purchase_order_id = ?");
    if (!$stmt_items) throw new Exception("Failed to prepare statement for fetching PO items.");
    $stmt_items->bind_param("s", $purchase_order_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    $items_to_process = $items_result->fetch_all(MYSQLI_ASSOC);

    if (empty($items_to_process)) {
        throw new Exception("Cannot generate GRN: The Purchase Order has no items.");
    }

    // Prepare items array in the format process_grn() expects
    // Note: We are setting 'weight' to 0 as it's not in the PO.
    $grn_items = array_map(function($item) {
        return [
            'item_id'  => $item['item_id'],
            'uom'      => $item['uom'],
            'quantity' => $item['quantity'],
            'cost'     => $item['cost_price'],
            'weight'   => 0 
        ];
    }, $items_to_process);

    // Step 2: Set GRN details
    $grn_date = date('Y-m-d');
    $remarks = "Auto-generated from completed PO #" . $purchase_order_id;
    
    // Step 3: Call the existing process_grn function to create the GRN and update stock
    // We pass the existing database connection ($db) to ensure it's part of the same transaction
    // Pass the correctly formatted actor name to the process_grn function
    $actor_name = 'System for ' . ($_SESSION['username'] ?? 'Unknown');
    $new_grn_id = process_grn($grn_date, $grn_items, $remarks, $db, $actor_name);
    
    return $new_grn_id;
}

// -----------------------------------------
// ----- GRN (Goods Received Note) Functions -----
// -----------------------------------------

function process_grn($grn_date, $items, $remarks, $existing_db = null, $actor_name = null) { // <-- Modified this line
    $db = $existing_db ?? db();
    if (!$db) throw new Exception("Database connection failed.");
    
    if (empty($grn_date) || !is_array($items) || empty($items)) {
        throw new Exception("GRN date and at least one item are required.");
    }
    foreach ($items as $item) {
        if (empty($item['item_id']) || !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) { throw new Exception("Invalid data in item rows. Each item needs an ID and valid quantity."); }
        if (!isset($item['cost']) || !is_numeric($item['cost']) || !isset($item['weight']) || !is_numeric($item['weight'])) { throw new Exception("Invalid data in item rows. Cost and Weight must be valid numbers."); }
    }

    if (!$existing_db) {
        $db->begin_transaction();
    }
    
    try {
        // --- CORRECTED USER LOGIC ---
        $user_id = $_SESSION['user_id'];
        // If an actor_name is passed (from automation), use it. Otherwise, use the session username.
        $user_name = $actor_name ?? ($_SESSION['username'] ?? 'Unknown');
        // --- END CORRECTION ---
        
        $grn_id = generate_sequence_id('grn_id', 'grn', 'grn_id');
        $stmt_grn = $db->prepare("INSERT INTO grn (grn_id, grn_date, remarks, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
        $stmt_grn->bind_param("sssis", $grn_id, $grn_date, $remarks, $user_id, $user_name);
        $stmt_grn->execute();

        $stmt_items = $db->prepare("INSERT INTO grn_items (grn_id, item_id, uom, quantity, cost, weight) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt_items->bind_param("sssddd", $grn_id, $item['item_id'], $item['uom'], $item['quantity'], $item['cost'], $item['weight']);
            $stmt_items->execute();
            $stock_reason = "Received via GRN #" . $grn_id;
            // Note: We don't need to change adjust_stock_level, as it correctly uses the logged-in user's name, which is what we want.
            adjust_stock_level($item['item_id'], 'IN', $item['quantity'], $stock_reason, 'Ex-Stock', $db);
        }

        if (!$existing_db) {
            $db->commit();
        }
        return $grn_id;
    } catch (Exception $e) {
        if (!$existing_db) {
            $db->rollback();
        }
        throw new Exception("Failed to process GRN: " . $e->getMessage());
    }
}

/**
 * Cancels a GRN and reverses the stock adjustments.
 * This function performs all actions within a single database transaction.
 *
 * @param string $grn_id The ID of the GRN to cancel.
 * @return bool True on success.
 * @throws Exception On failure, including if stock would go negative.
 */
function cancel_grn($grn_id) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");

    $db->begin_transaction();
    try {
        // Step 1: Lock the GRN row and verify its status is 'Posted'
        $stmt_check = $db->prepare("SELECT status FROM grn WHERE grn_id = ? FOR UPDATE");
        if (!$stmt_check) throw new Exception("DB prepare failed for GRN status check.");
        $stmt_check->bind_param("s", $grn_id);
        $stmt_check->execute();
        $grn = $stmt_check->get_result()->fetch_assoc();

        if (!$grn) {
            throw new Exception("GRN #$grn_id not found.");
        }
        if ($grn['status'] !== 'Posted') {
            throw new Exception("This GRN cannot be canceled because its status is not 'Posted'.");
        }

        // Step 2: Fetch all items that were on this GRN
        $stmt_items = $db->prepare("SELECT item_id, quantity FROM grn_items WHERE grn_id = ?");
        if (!$stmt_items) throw new Exception("DB prepare failed for fetching GRN items.");
        $stmt_items->bind_param("s", $grn_id);
        $stmt_items->execute();
        $items_to_reverse = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($items_to_reverse)) {
            // This is a safety check; if there are no items, we can just cancel the GRN.
        } else {
            // Step 3: Loop through each item and REVERSE the stock adjustment
            foreach ($items_to_reverse as $item) {
                $stock_reason = "Stock reversed from canceled GRN #" . $grn_id;
                // Use 'OUT' to deduct the stock that was previously added.
                // The 'Ex-Stock' check will prevent stock from going negative, which is a critical safety feature.
                adjust_stock_level($item['item_id'], 'OUT', $item['quantity'], $stock_reason, 'Ex-Stock', $db);
            }
        }

        // Step 4: Update the GRN's status to 'Canceled'
        $stmt_update = $db->prepare("UPDATE grn SET status = 'Canceled' WHERE grn_id = ?");
        if (!$stmt_update) throw new Exception("DB prepare failed for updating GRN status.");
        $stmt_update->bind_param("s", $grn_id);
        $stmt_update->execute();

        // If all steps were successful, commit the transaction
        $db->commit();
        return true;

    } catch (Exception $e) {
        // If any step failed, roll back all changes
        $db->rollback();
        // Pass the specific error message up (e.g., the stock-level error)
        throw new Exception("Failed to cancel GRN: " . $e->getMessage());
    }
}

/**
 * Retrieves a single, complete GRN with its items for viewing.
 *
 * @param string $grn_id The ID of the GRN to fetch.
 * @return array|false The complete GRN data, or false if not found.
 */
function get_grn_details($grn_id) {
    $db = db();
    if (!$db) return false;

    // 1. Get the main GRN details
    $stmt_grn = $db->prepare("SELECT * FROM grn WHERE grn_id = ?");
    $stmt_grn->bind_param("s", $grn_id);
    $stmt_grn->execute();
    $grn = $stmt_grn->get_result()->fetch_assoc();

    if (!$grn) {
        return false; // GRN not found
    }

    // 2. Get all line items for the GRN
    $stmt_items = $db->prepare("
        SELECT gi.*, i.name as item_name 
        FROM grn_items gi 
        JOIN items i ON gi.item_id = i.item_id 
        WHERE gi.grn_id = ?
    ");
    $stmt_items->bind_param("s", $grn_id);
    $stmt_items->execute();
    $grn['items'] = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    return $grn;
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
 * Fulfills a linked Pre-Book sales order when stock arrives from a PO.
 * This function now also updates the cost price and margin on the original order items.
 *
 * @param string $sales_order_id The ID of the sales order to fulfill.
 * @param string $triggering_po_id The PO that triggered this fulfillment.
 * @param mysqli $db The existing database connection.
 * @return void
 * @throws Exception On failure.
 */
function fulfill_linked_sales_order($sales_order_id, $triggering_po_id, $db) {
    // --- Step 1: Fetch items and their TRUE costs from the Purchase Order ---
    $stmt_po_items = $db->prepare("SELECT item_id, cost_price FROM purchase_order_items WHERE purchase_order_id = ?");
    if (!$stmt_po_items) throw new Exception("Failed to prepare statement for fetching PO costs.");
    $stmt_po_items->bind_param("s", $triggering_po_id);
    $stmt_po_items->execute();
    $po_items_result = $stmt_po_items->get_result();
    // Create a simple map of [item_id => cost_price] for easy lookup
    $actual_costs = [];
    while ($item = $po_items_result->fetch_assoc()) {
        $actual_costs[$item['item_id']] = $item['cost_price'];
    }

    // --- Step 2: Fetch all items from the sales order ---
    $stmt_so_items = $db->prepare("SELECT item_id, quantity, price FROM order_items WHERE order_id = ?");
    if (!$stmt_so_items) throw new Exception("Failed to prepare statement for fetching SO items.");
    $stmt_so_items->bind_param("s", $sales_order_id);
    $stmt_so_items->execute();
    $items_result = $stmt_so_items->get_result();
    $items_to_fulfill = $items_result->fetch_all(MYSQLI_ASSOC);

    if (empty($items_to_fulfill)) {
        throw new Exception("Cannot fulfill Sales Order #$sales_order_id: It has no items.");
    }

    // --- Step 3: Loop through sales order items to deduct stock AND update costs ---
    $stock_reason = "Fulfilled from stock received via PO #" . $triggering_po_id;
    $stmt_update_item = $db->prepare("UPDATE order_items SET cost_price = ?, profit_margin = ? WHERE order_id = ? AND item_id = ?");
    if (!$stmt_update_item) throw new Exception("Failed to prepare statement for updating order items.");

    foreach ($items_to_fulfill as $item) {
        // A. Deduct stock using the existing function
        adjust_stock_level($item['item_id'], 'OUT', $item['quantity'], $stock_reason, 'Ex-Stock', $db);

        // B. Update the cost and margin for this item
        $cost_price = $actual_costs[$item['item_id']] ?? 0; // Get cost from our PO map
        $sell_price = $item['price'];
        $profit_margin = 0;

        if ($cost_price > 0 && $sell_price > 0) {
            $profit_margin = (($sell_price / $cost_price) - 1) * 100;
        }

        $stmt_update_item->bind_param("ddss", $cost_price, $profit_margin, $sales_order_id, $item['item_id']);
        $stmt_update_item->execute();
    }

    // --- Step 4: Update the main sales order record (status and stock type) ---
    $new_order_status = 'Processing';
    $stmt_order = $db->prepare("UPDATE orders SET stock_type = 'Ex-Stock', status = ? WHERE order_id = ?");
    if (!$stmt_order) throw new Exception("Failed to prepare statement for updating SO status.");
    $stmt_order->bind_param("ss", $new_order_status, $sales_order_id);
    $stmt_order->execute();

    // --- Step 5: Create the audit trail records ---
    $user_id = $_SESSION['user_id'];
    $user_name = 'System for ' . ($_SESSION['username'] ?? 'Unknown');
    $history_remark = "Stock fulfilled from PO #" . $triggering_po_id;

    $stmt_status_history = $db->prepare("INSERT INTO order_status_history (order_id, status, remarks, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
    $stmt_status_history->bind_param("sssis", $sales_order_id, $new_order_status, $history_remark, $user_id, $user_name);
    $stmt_status_history->execute();

    $stmt_stock_type_history = $db->prepare("INSERT INTO order_stock_type_history (order_id, stock_type, change_reason, created_by, created_by_name) VALUES (?, 'Ex-Stock', ?, ?, ?)");
    $stmt_stock_type_history->bind_param("ssis", $sales_order_id, $history_remark, $user_id, $user_name);
    $stmt_stock_type_history->execute();
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
    $stmt_history = $db->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY COALESCE(event_date, created_at) ASC");
    $stmt_history->bind_param("s", $order_id);
    $stmt_history->execute();
    $order['status_history'] = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);

    // 5. NEW: Get the payment status history for the order
    $stmt_payment_history = $db->prepare("SELECT * FROM payment_status_history WHERE order_id = ? ORDER BY COALESCE(event_date, created_at) ASC");
    $stmt_payment_history->bind_param("s", $order_id);
    $stmt_payment_history->execute();
    $order['payment_history'] = $stmt_payment_history->get_result()->fetch_all(MYSQLI_ASSOC);

    return $order;
}

/**
 * Updates the status and details of an existing order and tracks history.
 * - Handles optional event dates for status changes.
 * - Handles manual fulfillment of Pre-Book orders.
 * - NEW: Handles stock reversal when an Ex-Stock order is Canceled.
 * - NEW: Prevents changing an Ex-Stock order back to Pre-Book.
 *
 * @param string $order_id The ID of the order to update.
 * @param array $details An array of details to update.
 * @param array $post_data The raw POST data.
 * @return bool True on success.
 * @throws Exception On validation or database errors.
 */
function update_order_details($order_id, $details, $post_data) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");
    if (empty($order_id) || !is_array($details)) throw new Exception("Invalid arguments for updating order.");

    $db->begin_transaction();
    try {
        // --- Get the current state of the order from the database BEFORE updating ---
        $stmt_check = $db->prepare("SELECT status, payment_status, stock_type FROM orders WHERE order_id = ?");
        $stmt_check->bind_param("s", $order_id);
        $stmt_check->execute();
        $current_state = $stmt_check->get_result()->fetch_assoc();
        if (!$current_state) {
            throw new Exception("Order not found.");
        }
        $old_order_status = $current_state['status'];
        $old_payment_status = $current_state['payment_status'];
        $old_stock_type = $current_state['stock_type'];

        // Get the NEW stock_type from the submitted form data
        $new_stock_type = $post_data['stock_type'] ?? $old_stock_type;

        // --- NEW VALIDATION: Prevent illogical stock type change ---
        if ($old_stock_type === 'Ex-Stock' && $new_stock_type === 'Pre-Book') {
            throw new Exception("Cannot change a fulfilled Ex-Stock order back to Pre-Book.");
        }

        $is_manual_fulfillment = ($old_stock_type === 'Pre-Book' && $new_stock_type === 'Ex-Stock');
        
        // --- NEW CANCELLATION LOGIC ---
        $is_cancellation = ($details['order_status'] === 'Canceled' && $old_order_status !== 'Canceled');

        if ($is_cancellation && $old_stock_type === 'Ex-Stock') {
            // This was an Ex-Stock order that is now being canceled. We must return the stock.
            $stmt_items = $db->prepare("SELECT item_id, quantity FROM order_items WHERE order_id = ?");
            if (!$stmt_items) throw new Exception("DB prepare failed for fetching items for cancellation.");
            $stmt_items->bind_param("s", $order_id);
            $stmt_items->execute();
            $items_to_return = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($items_to_return as $item) {
                $stock_reason = "Stock returned from canceled Order #" . $order_id;
                // Use 'IN' to add the stock back to inventory
                adjust_stock_level($item['item_id'], 'IN', $item['quantity'], $stock_reason, 'Ex-Stock', $db);
            }
        }
        
        // --- End of new logic, proceed with standard update ---

        $stmt = $db->prepare("UPDATE orders SET status = ?, payment_method = ?, payment_status = ?, other_expenses = ?, remarks = ?, stock_type = ? WHERE order_id = ?");
        if (!$stmt) throw new Exception("Database error: Failed to prepare statement for order update.");
        $stmt->bind_param("sssdsss", $details['order_status'], $details['payment_method'], $details['payment_status'], $details['other_expenses'], $details['remarks'], $new_stock_type, $order_id);
        $stmt->execute();
        
        if ($is_manual_fulfillment) {
            $stmt_items = $db->prepare("SELECT item_id, quantity FROM order_items WHERE order_id = ?");
            if (!$stmt_items) throw new Exception("DB prepare failed for fetching order items.");
            $stmt_items->bind_param("s", $order_id);
            $stmt_items->execute();
            $items_to_deduct = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($items_to_deduct as $item) {
                $stock_reason = "Manually fulfilled from Pre-Book for Order #" . $order_id;
                adjust_stock_level($item['item_id'], 'OUT', $item['quantity'], $stock_reason, 'Ex-Stock', $db);
            }
        }

        // History logging logic
        if ($old_order_status !== $details['order_status']) {
            $order_event_date = !empty($post_data['order_status_event_date']) ? $post_data['order_status_event_date'] : null;
            $stmt_history = $db->prepare("INSERT INTO order_status_history (order_id, status, event_date, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
            $stmt_history->bind_param("sssis", $order_id, $details['order_status'], $order_event_date, $_SESSION['user_id'], $_SESSION['username']);
            $stmt_history->execute();
        }
        if ($old_payment_status !== $details['payment_status']) {
            $payment_event_date = !empty($post_data['payment_status_event_date']) ? $post_data['payment_status_event_date'] : null;
            $stmt_payment_history = $db->prepare("INSERT INTO payment_status_history (order_id, payment_status, event_date, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
            $stmt_payment_history->bind_param("sssis", $order_id, $details['payment_status'], $payment_event_date, $_SESSION['user_id'], $_SESSION['username']);
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
function process_purchase_order($po_date, $supplier_name, $items, $remarks, $status = 'Draft', $linked_sales_order_id = null) { // Added linked_sales_order_id
    $db = db();
    if (!$db) {
        throw new Exception("Database connection failed.");
    }

    if (empty($po_date) || !is_array($items) || empty($items)) {
        throw new Exception("PO date and at least one item are required.");
    }
    // (Validation for items remains the same)
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
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['username'] ?? 'Unknown';
        $purchase_order_id = generate_sequence_id('purchase_order_id', 'purchase_orders', 'purchase_order_id');
        
        // MODIFIED: Added `linked_sales_order_id` and `status` to the INSERT statement
        $stmt_po = $db->prepare(
            "INSERT INTO purchase_orders (purchase_order_id, po_date, supplier_name, linked_sales_order_id, status, remarks, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        // Use empty string if linked_sales_order_id is null for bind_param
        $linked_id_for_db = empty($linked_sales_order_id) ? null : $linked_sales_order_id;
        $stmt_po->bind_param("ssssssis", $purchase_order_id, $po_date, $supplier_name, $linked_id_for_db, $status, $remarks, $user_id, $user_name);
        $stmt_po->execute();

        // (The rest of the function remains the same)
        $stmt_history = $db->prepare("INSERT INTO purchase_order_status_history (purchase_order_id, status, created_by, created_by_name) VALUES (?, ?, ?, ?)");
        $stmt_history->bind_param("ssis", $purchase_order_id, $status, $user_id, $user_name);
        $stmt_history->execute();

        $stmt_items = $db->prepare("INSERT INTO purchase_order_items (purchase_order_id, item_id, quantity, cost_price) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt_items->bind_param("ssdd", $purchase_order_id, $item['item_id'], $item['quantity'], $item['cost_price']);
            $stmt_items->execute();
        }

        $db->commit();
        return $purchase_order_id;

    } catch (Exception $e) {
        $db->rollback();
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

// -----------------------------------------
// ----- GRN Listing/Search Functions -----
// -----------------------------------------

/**
 * Searches for GRNs based on various criteria.
 * Now includes the status field and allows filtering by status.
 *
 * @param array $filters An associative array of filters (e.g., ['grn_id' => 'GRN001', 'status' => 'Posted']).
 * @return array An array of matching GRNs.
 */
function search_grns($filters = []) {
    $db = db();
    if (!$db) return [];

    $sql = "
        SELECT 
            g.grn_id,
            g.grn_date,
            g.remarks,
            g.created_by_name,
            g.status -- ADDED: Select the new status column
        FROM grn g
        WHERE 1=1
    ";

    $params = [];
    $types = '';

    // Filter by GRN ID
    if (!empty($filters['grn_id'])) {
        $sql .= " AND g.grn_id LIKE ?";
        $params[] = '%' . $filters['grn_id'] . '%';
        $types .= 's';
    }

    // --- NEW: Add logic for the status filter ---
    if (!empty($filters['status'])) {
        $sql .= " AND g.status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    // --- END NEW ---

    // Filter by Date Range
    if (!empty($filters['date_from'])) {
        $sql .= " AND g.grn_date >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND g.grn_date <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }

    $sql .= " ORDER BY g.grn_date DESC, g.grn_id DESC";
    
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("GRN Search Prepare Failed: " . $db->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// -----------------------------------------
// ----- PO Listing/Search Functions -----
// -----------------------------------------

/**
 * Searches for purchase orders in the database based on filters.
 *
 * @param array $filters An associative array of filters. 
 *                       Keys can include 'purchase_order_id', 'date_from', 'date_to', 'status'.
 * @return array The list of purchase orders found.
 */
function search_purchase_orders($filters = []) {
    $db = db();
    if (!$db) return [];

    $sql = "
        SELECT 
            purchase_order_id, 
            po_date, 
            supplier_name, 
            linked_sales_order_id, -- ADDED THIS LINE
            status, 
            created_by_name 
        FROM purchase_orders 
        WHERE 1=1
    ";
            
    $params = [];
    $types = '';

    if (!empty($filters['purchase_order_id'])) {
        $sql .= " AND purchase_order_id LIKE ?";
        $params[] = '%' . $filters['purchase_order_id'] . '%';
        $types .= 's';
    }
    if (!empty($filters['status'])) {
        $sql .= " AND status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    if (!empty($filters['date_from'])) {
        $sql .= " AND po_date >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND po_date <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }

    $sql .= " ORDER BY po_date DESC, purchase_order_id DESC LIMIT 100";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Purchase Order Search Prepare Failed: " . $db->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Retrieves a single, complete purchase order with its items and history.
 *
 * @param string $purchase_order_id The ID of the PO to fetch.
 * @return array|false The complete PO data, or false if not found.
 */
function get_purchase_order_details($purchase_order_id) {
    $db = db();
    if (!$db) return false;

    // 1. Get the main PO details
    $stmt_po = $db->prepare("SELECT * FROM purchase_orders WHERE purchase_order_id = ?");
    $stmt_po->bind_param("s", $purchase_order_id);
    $stmt_po->execute();
    $po = $stmt_po->get_result()->fetch_assoc();

    if (!$po) {
        return false; // PO not found
    }

    // 2. Get all line items for the PO
    $stmt_items = $db->prepare("SELECT poi.*, i.name as item_name FROM purchase_order_items poi JOIN items i ON poi.item_id = i.item_id WHERE poi.purchase_order_id = ?");
    $stmt_items->bind_param("s", $purchase_order_id);
    $stmt_items->execute();
    $po['items'] = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    // 3. Get the status history for the PO
    $stmt_history = $db->prepare("SELECT * FROM purchase_order_status_history WHERE purchase_order_id = ? ORDER BY COALESCE(event_date, created_at) ASC");
    $stmt_history->bind_param("s", $purchase_order_id);
    $stmt_history->execute();
    $po['status_history'] = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);

    return $po;
}

/**
 * Updates the status and details of an existing PO and tracks history.
 * Now handles optional event dates for status changes.
 *
 * @param string $purchase_order_id The ID of the PO to update.
 * @param array $details An array of details to update (status, supplier_name, remarks).
 * @param array $post_data The raw POST data to access the new event date field.
 * @return array An array containing a success boolean and a feedback message.
 * @throws Exception On validation or database errors.
 */
function update_purchase_order_details($purchase_order_id, $details, $post_data) { // Added $post_data
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");

    $feedback_message = "✅ Purchase Order #$purchase_order_id successfully updated.";

    $db->begin_transaction();
    try {
        $stmt_check = $db->prepare("SELECT status, linked_sales_order_id FROM purchase_orders WHERE purchase_order_id = ?");
        $stmt_check->bind_param("s", $purchase_order_id);
        $stmt_check->execute();
        $current_state = $stmt_check->get_result()->fetch_assoc();
        
        if (!$current_state) throw new Exception("Purchase Order not found.");

        $old_status = $current_state['status'];
        $new_status = $details['status'];
        $linked_order_id = $current_state['linked_sales_order_id'];

        $stmt = $db->prepare("UPDATE purchase_orders SET status = ?, supplier_name = ?, remarks = ? WHERE purchase_order_id = ?");
        $stmt->bind_param("ssss", $new_status, $details['supplier_name'], $details['remarks'], $purchase_order_id);
        $stmt->execute();

        if ($old_status !== $new_status) {
            // Get the event date from POST data, default to NULL if not set or empty
            $po_event_date = !empty($post_data['po_status_event_date']) ? $post_data['po_status_event_date'] : null;
            
            // MODIFIED: Added the new event_date column to the INSERT statement
            $stmt_history = $db->prepare("INSERT INTO purchase_order_status_history (purchase_order_id, status, event_date, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
            $stmt_history->bind_param("sssis", $purchase_order_id, $new_status, $po_event_date, $_SESSION['user_id'], $_SESSION['username']);
            $stmt_history->execute();
        }
        
        $is_completed = ($new_status === 'Received' && $old_status !== 'Received');

        if ($is_completed) {
            $new_grn_id = auto_generate_grn_from_po($purchase_order_id, $db);
            $feedback_message .= " GRN #$new_grn_id was automatically created.";

            if (!empty($linked_order_id)) {
                fulfill_linked_sales_order($linked_order_id, $purchase_order_id, $db);
                $feedback_message .= " Linked Sales Order #$linked_order_id was fulfilled.";
            }
        }
        
        $db->commit();
        
        return ['success' => true, 'message' => $feedback_message];

    } catch (Exception $e) {
        $db->rollback();
        throw new Exception("Failed to update Purchase Order: " . $e->getMessage());
    }
}