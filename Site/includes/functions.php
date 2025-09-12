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

/**
 * Retrieves a complete, detailed profile for a single item.
 * Gathers data from the items table, filesystem for images, and order history for prices.
 *
 * @param string $item_id The ID of the item to fetch.
 * @return array|false The complete item data array, or false if not found.
 */
function get_item_details($item_id) {
    $db = db();
    if (!$db) return false;

    // --- Task 1: Fetch Core Item Data from Database ---
    $stmt = $db->prepare("
        SELECT 
            i.*, 
            c.name as category_name, 
            cs.name as sub_category_name
        FROM items i
        JOIN categories_sub cs ON i.category_sub_id = cs.category_sub_id
        JOIN categories c ON cs.category_id = c.category_id
        WHERE i.item_id = ?
    ");
    if (!$stmt) return false;

    $stmt->bind_param("s", $item_id);
    $stmt->execute();
    $item_details = $stmt->get_result()->fetch_assoc();

    if (!$item_details) {
        return false; // Item not found
    }

    // --- Task 2: Find All Associated Images from the Filesystem ---
    $image_dir = __DIR__ . '/../Images/'; // Physical path to the Images directory
    $image_pattern = $image_dir . $item_id . '-*.jpg';
    $image_paths = glob($image_pattern);
    
    $image_urls = [];
    if ($image_paths) {
        foreach ($image_paths as $path) {
            // Convert the full server path to a web-accessible URL
            $image_urls[] = '/Images/' . basename($path);
        }
    }
    $item_details['images'] = $image_urls;

    // --- Task 3: Fetch Price History from Orders ---
    $stmt_price = $db->prepare("
        SELECT 
            o.order_date,
            o.order_id,
            oi.price,
            oi.cost_price,
            c.name as customer_name
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN customers c on o.customer_id = c.customer_id
        WHERE oi.item_id = ?
          AND o.status != 'Canceled'
        ORDER BY o.order_date DESC
    ");
    if (!$stmt_price) return false;
    
    $stmt_price->bind_param("s", $item_id);
    $stmt_price->execute();
    $item_details['price_history'] = $stmt_price->get_result()->fetch_all(MYSQLI_ASSOC);

    return $item_details;
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
    // Step 1: Fetch all items from the purchase order, NOW INCLUDING WEIGHT
    $stmt_items = $db->prepare("
        SELECT 
            poi.item_id, 
            i.uom, 
            poi.quantity, 
            poi.cost_price,
            poi.weight_grams 
        FROM purchase_order_items poi 
        JOIN items i ON poi.item_id = i.item_id 
        WHERE poi.purchase_order_id = ?
    ");
    if (!$stmt_items) throw new Exception("Failed to prepare statement for fetching PO items.");
    $stmt_items->bind_param("s", $purchase_order_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    $items_to_process = $items_result->fetch_all(MYSQLI_ASSOC);

    if (empty($items_to_process)) {
        throw new Exception("Cannot generate GRN: The Purchase Order has no items.");
    }

    // Prepare items array in the format process_grn() expects
    // CORRECTED: 'weight' now correctly uses the 'weight_grams' value from the PO.
    $grn_items = array_map(function($item) {
        return [
            'item_id'  => $item['item_id'],
            'uom'      => $item['uom'],
            'quantity' => $item['quantity'],
            'cost'     => $item['cost_price'],
            'weight'   => $item['weight_grams'] // This line is now correct
        ];
    }, $items_to_process);

    // Step 2: Set GRN details
    $grn_date = date('Y-m-d');
    $remarks = "Auto-generated from completed PO #" . $purchase_order_id;
    
    // Step 3: Call the existing process_grn function to create the GRN and update stock
    $actor_name = 'System for ' . ($_SESSION['username'] ?? 'Unknown');
    $new_grn_id = process_grn($grn_date, $grn_items, $remarks, $db, $actor_name);
    
    return $new_grn_id;
}

// -----------------------------------------
// ----- GRN (Goods Received Note) Functions -----
// -----------------------------------------

function process_grn($grn_date, $items, $remarks, $existing_db = null, $actor_name = null) {
    $db = $existing_db ?? db();
    if (!$db) throw new Exception("Database connection failed.");
    
    if (empty($grn_date) || !is_array($items) || empty($items)) {
        throw new Exception("GRN date and at least one item are required.");
    }
    foreach ($items as $item) {
        if (empty($item['item_id']) || !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) { throw new Exception("Invalid data in item rows. Each item needs an ID and valid quantity."); }
        if (!isset($item['cost']) || !is_numeric($item['cost']) || !isset($item['weight']) || !is_numeric($item['weight'])) { throw new Exception("Invalid data in item rows. Cost and Weight must be valid numbers."); }
    }

    $is_external_transaction = ($existing_db !== null);
    if (!$is_external_transaction) {
        $db->begin_transaction();
    }
    
    try {
        $user_id = $_SESSION['user_id'];
        $user_name = $actor_name ?? ($_SESSION['username'] ?? 'Unknown');
        
        $grn_id = generate_sequence_id('grn_id', 'grn', 'grn_id');
        $stmt_grn = $db->prepare("INSERT INTO grn (grn_id, grn_date, remarks, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
        $stmt_grn->bind_param("sssis", $grn_id, $grn_date, $remarks, $user_id, $user_name);
        $stmt_grn->execute();

        // UPDATED: The INSERT query now includes the `cost` and `weight` columns.
        $stmt_items = $db->prepare("INSERT INTO grn_items (grn_id, item_id, uom, quantity, cost, weight) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            // UPDATED: The bind_param now includes the types and variables for cost and weight.
            $stmt_items->bind_param("sssddd", $grn_id, $item['item_id'], $item['uom'], $item['quantity'], $item['cost'], $item['weight']);
            $stmt_items->execute();
            $stock_reason = "Received via GRN #" . $grn_id;
            adjust_stock_level($item['item_id'], 'IN', $item['quantity'], $stock_reason, 'Ex-Stock', $db);
        }

        if (!$is_external_transaction) {
            $db->commit();
        }
        return $grn_id;
    } catch (Exception $e) {
        if (!$is_external_transaction) {
            $db->rollback();
        }
        throw new Exception("Failed to process GRN: " . $e->getMessage());
    }
}

/**
 * Cancels a GRN, reverses stock, reverses associated PO accounting entries, and reverts the PO status.
 *
 * @param string $grn_id The ID of the GRN to cancel.
 * @return bool True on success.
 * @throws Exception On failure.
 */
function cancel_grn($grn_id) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");

    $db->begin_transaction();
    try {
        // Step 1: Lock the GRN row and get its details
        $stmt_check = $db->prepare("SELECT status, remarks FROM grn WHERE grn_id = ? FOR UPDATE");
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

        // Step 2: Reverse stock adjustments
        $stmt_items = $db->prepare("SELECT item_id, quantity FROM grn_items WHERE grn_id = ?");
        if (!$stmt_items) throw new Exception("DB prepare failed for fetching GRN items.");
        $stmt_items->bind_param("s", $grn_id);
        $stmt_items->execute();
        $items_to_reverse = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!empty($items_to_reverse)) {
            foreach ($items_to_reverse as $item) {
                $stock_reason = "Stock reversed from canceled GRN #" . $grn_id;
                // CORRECTED: The function call now passes all 6 arguments, including the crucial $db object.
                adjust_stock_level($item['item_id'], 'OUT', $item['quantity'], $stock_reason, 'Ex-Stock', $db);
            }
        }

        // Step 3: Update the GRN's status to 'Canceled'
        $stmt_update = $db->prepare("UPDATE grn SET status = 'Canceled' WHERE grn_id = ?");
        if (!$stmt_update) throw new Exception("DB prepare failed for updating GRN status.");
        $stmt_update->bind_param("s", $grn_id);
        $stmt_update->execute();

        // Step 4: Find the parent PO ID from the GRN remarks
        $parent_po_id = null;
        if (preg_match('/PO #(PUR[0-9]+)/', $grn['remarks'], $matches)) {
            $parent_po_id = $matches[1];
        }

        if ($parent_po_id) {
            // Step 5: Find and reverse all 'Posted' financial transactions for this PO
            $stmt_find_txns = $db->prepare("SELECT * FROM acc_transactions WHERE source_id = ? AND source_type = 'purchase_order' AND status = 'Posted'");
            $stmt_find_txns->bind_param("s", $parent_po_id);
            $stmt_find_txns->execute();
            $posted_txns = $stmt_find_txns->get_result()->fetch_all(MYSQLI_ASSOC);

            if (!empty($posted_txns)) {
                $reversal_desc = "Reversal for canceled GRN #" . $grn_id;
                $grouped_txns = [];
                foreach ($posted_txns as $txn) { $grouped_txns[$txn['transaction_group_id']][] = $txn; }

                foreach ($grouped_txns as $group_id => $txns) {
                    $original_debit = null; $original_credit = null;
                    foreach ($txns as $t) {
                        if ($t['debit_amount'] !== null) $original_debit = $t;
                        if ($t['credit_amount'] !== null) $original_credit = $t;
                    }
                    if ($original_debit && $original_credit) {
                        $reversal_details = ['transaction_date' => date('Y-m-d'), 'description' => $reversal_desc, 'remarks' => 'Automated reversal for GRN cancellation.', 'debit_account_id' => $original_credit['account_id'], 'credit_account_id' => $original_debit['account_id'], 'amount' => $original_debit['debit_amount']];
                        $reversal_group_id = process_journal_entry($reversal_details, 'purchase_order', $parent_po_id, $db);
                        $stmt_update_txn = $db->prepare("UPDATE acc_transactions SET status = 'Canceled' WHERE transaction_group_id IN (?, ?)");
                        $stmt_update_txn->bind_param("ss", $group_id, $reversal_group_id);
                        $stmt_update_txn->execute();
                    }
                }
            }

            // Step 6: Revert the parent PO's status to 'Ordered'
            $new_po_status = 'Ordered';
            $stmt_update_po = $db->prepare("UPDATE purchase_orders SET status = ? WHERE purchase_order_id = ?");
            $stmt_update_po->bind_param("ss", $new_po_status, $parent_po_id);
            $stmt_update_po->execute();
            
            // Step 7: Log the PO status change
            $history_remark = "Status reverted to 'Ordered' due to cancellation of GRN #" . $grn_id;
            $stmt_po_history = $db->prepare("INSERT INTO purchase_order_status_history (purchase_order_id, status, created_by, created_by_name) VALUES (?, ?, ?, ?)");
            $created_by_name_history = 'System for ' . ($_SESSION['username'] ?? 'System');
            $stmt_po_history->bind_param("ssis", $parent_po_id, $new_po_status, $_SESSION['user_id'], $created_by_name_history);
            $stmt_po_history->execute();
        }

        $db->commit();
        return true;

    } catch (Exception $e) {
        $db->rollback();
        throw new Exception("Failed to cancel GRN: " . $e->getMessage());
    }
}

// NEW: This is the missing function for the "View GRN" page.
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

    // 2. Get all line items for the GRN, joining with the items table to get the name
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

            if ($details['payment_status'] === 'Received') {
            // If the order is paid upon creation, record the accounting transaction immediately.
            record_sales_transaction($order_id, $db);
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
 * - Handles stock reversal when an Ex-Stock order is Canceled.
 * - Handles manual fulfillment of Pre-Book orders, including updating item costs.
 *
 * @param string $order_id The ID of the order to update.
 * @param array $details An array of header details to update.
 * @param array $post_data The raw POST data, containing all form fields.
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
        $stmt_check = $db->prepare("SELECT status, payment_status, stock_type FROM orders WHERE order_id = ? FOR UPDATE");
        if (!$stmt_check) throw new Exception("DB prepare failed for order check.");
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

        // --- VALIDATION: Prevent illogical stock type change ---
        if ($old_stock_type === 'Ex-Stock' && $new_stock_type === 'Pre-Book') {
            throw new Exception("Cannot change a fulfilled Ex-Stock order back to Pre-Book.");
        }
        
        // --- DEFINE EVENT TRIGGERS ---
        $is_manual_fulfillment = ($old_stock_type === 'Pre-Book' && $new_stock_type === 'Ex-Stock');
        $is_cancellation = ($details['order_status'] === 'Canceled' && $old_order_status !== 'Canceled');
        $is_payment_received_event = ($details['payment_status'] === 'Received' && $old_payment_status !== 'Received');

        // --- CANCELLATION LOGIC FOR STOCK REVERSAL ---
        if ($is_cancellation && $old_stock_type === 'Ex-Stock') {
            $stmt_items = $db->prepare("SELECT item_id, quantity FROM order_items WHERE order_id = ?");
            if (!$stmt_items) throw new Exception("DB prepare failed for fetching items for cancellation.");
            $stmt_items->bind_param("s", $order_id);
            $stmt_items->execute();
            $items_to_return = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($items_to_return as $item) {
                $stock_reason = "Stock returned from canceled Order #" . $order_id;
                adjust_stock_level($item['item_id'], 'IN', $item['quantity'], $stock_reason, 'Ex-Stock', $db);
            }
        }

        // --- UPDATE THE MAIN ORDER HEADER ---
        $stmt = $db->prepare("UPDATE orders SET status = ?, payment_method = ?, payment_status = ?, other_expenses = ?, remarks = ?, stock_type = ? WHERE order_id = ?");
        if (!$stmt) throw new Exception("Database error: Failed to prepare statement for order update.");
        $stmt->bind_param("sssdsss", $details['order_status'], $details['payment_method'], $details['payment_status'], $details['other_expenses'], $details['remarks'], $new_stock_type, $order_id);
        $stmt->execute();
        
        // --- MANUAL FULFILLMENT LOGIC ---
        if ($is_manual_fulfillment) {
            // Deduct stock for all items
            $stmt_items_deduct = $db->prepare("SELECT item_id, quantity FROM order_items WHERE order_id = ?");
            if (!$stmt_items_deduct) throw new Exception("DB prepare failed for fetching items to deduct.");
            $stmt_items_deduct->bind_param("s", $order_id);
            $stmt_items_deduct->execute();
            $items_to_deduct = $stmt_items_deduct->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($items_to_deduct as $item) {
                $stock_reason = "Manually fulfilled from Pre-Book for Order #" . $order_id;
                adjust_stock_level($item['item_id'], 'OUT', $item['quantity'], $stock_reason, 'Ex-Stock', $db);
            }

            // --- THIS IS THE CRITICAL NEW PART ---
            // Update cost_price and margin for each item from the hidden inputs
            if (isset($post_data['items']['cost']) && isset($post_data['items']['margin'])) {
                $item_ids = get_order_details($order_id)['items']; // Fetch items again to get their IDs in order
                $costs = $post_data['items']['cost'];
                $margins = $post_data['items']['margin'];
                
                if (count($item_ids) === count($costs) && count($item_ids) === count($margins)) {
                    $stmt_update_items = $db->prepare("UPDATE order_items SET cost_price = ?, profit_margin = ? WHERE order_item_id = ?");
                    if (!$stmt_update_items) throw new Exception("DB prepare failed for updating item costs.");
                    
                    for ($i = 0; $i < count($item_ids); $i++) {
                        $order_item_id = $item_ids[$i]['order_item_id'];
                        $cost = $costs[$i];
                        $margin = $margins[$i];
                        $stmt_update_items->bind_param("ddi", $cost, $margin, $order_item_id);
                        $stmt_update_items->execute();
                    }
                }
            }
            // --- END CRITICAL PART ---
        }

        // History logging
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
        
        // Accounting integration for received payments
        if ($is_payment_received_event) {
            record_sales_transaction($order_id, $db);
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
 * Processes a new Purchase Order.
 *
 * @param array $details An array of header details for the PO.
 * @param array $items An array of items for the PO.
 * @param array $linked_sales_orders An array of Sales Order IDs to link.
 * @return string The ID of the newly created Purchase Order.
 * @throws Exception On validation or database errors.
 */
function process_purchase_order($details, $items, $linked_sales_orders = []) {
    $db = db();
    if (!$db) {
        throw new Exception("Database connection failed.");
    }

    // --- Validation ---
    if (empty($details['po_date']) || !is_array($items) || empty($items)) {
        throw new Exception("PO date and at least one item are required.");
    }
    foreach ($items as $item) {
        if (empty($item['item_id']) || !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
            throw new Exception("Invalid data in item rows: Each item needs an ID and a valid quantity.");
        }
        if (!isset($item['supplier_price']) || !is_numeric($item['supplier_price'])) {
            throw new Exception("Invalid data in item rows: Each item needs a valid supplier price.");
        }
        if (!isset($item['weight_grams']) || !is_numeric($item['weight_grams'])) {
            throw new Exception("Invalid data in item rows: Each item needs a valid weight.");
        }
    }

    $db->begin_transaction();
    try {
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['username'] ?? 'Unknown';
        $purchase_order_id = generate_sequence_id('purchase_order_id', 'purchase_orders', 'purchase_order_id');
        
        $stmt_po = $db->prepare(
            "INSERT INTO purchase_orders (purchase_order_id, po_date, supplier_name, status, remarks, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt_po) throw new Exception("Database prepare failed for PO header: " . $db->error);
        
        $stmt_po->bind_param("sssssis", $purchase_order_id, $details['po_date'], $details['supplier_name'], $details['status'], $details['remarks'], $user_id, $user_name);
        $stmt_po->execute();

        // --- THIS IS THE COMPLETE LINK INSERTION LOGIC ---
        if (!empty($linked_sales_orders)) {
            $stmt_links = $db->prepare("INSERT INTO po_so_links (purchase_order_id, sales_order_id) VALUES (?, ?)");
            if (!$stmt_links) throw new Exception("Database prepare failed for PO links: " . $db->error);
            foreach ($linked_sales_orders as $sales_order_id) {
                $trimmed_so_id = trim($sales_order_id);
                if (!empty($trimmed_so_id)) {
                    $stmt_links->bind_param("ss", $purchase_order_id, $trimmed_so_id);
                    $stmt_links->execute();
                }
            }
        }
        // --- END OF LINK INSERTION LOGIC ---

        $stmt_history = $db->prepare("INSERT INTO purchase_order_status_history (purchase_order_id, status, created_by, created_by_name) VALUES (?, ?, ?, ?)");
        if (!$stmt_history) throw new Exception("Database prepare failed for PO history: " . $db->error);
        $stmt_history->bind_param("ssis", $purchase_order_id, $details['status'], $user_id, $user_name);
        $stmt_history->execute();

        $stmt_items = $db->prepare(
            "INSERT INTO purchase_order_items (purchase_order_id, item_id, supplier_price, weight_grams, quantity, cost_price) VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt_items) throw new Exception("Database prepare failed for PO items: " . $db->error);

        foreach ($items as $item) {
            $cost_price = 0.00;
            $supplier_price = $item['supplier_price'] ?? 0;
            $weight_grams = $item['weight_grams'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $stmt_items->bind_param("ssdddd", $purchase_order_id, $item['item_id'], $supplier_price, $weight_grams, $quantity, $cost_price);
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
 * Searches for open Pre-Orders that are not yet linked to any Purchase Order.
 *
 * @param string $query The Order ID or Customer Name to search for.
 * @return array An array of matching, unlinked Pre-Orders.
 */
function search_open_pre_orders($query) {
    $db = db();
    if (!$db) return [];
    
    $search_term = "%$query%";
    
    // THE CORRECTED QUERY:
    // It now LEFT JOINs with the new po_so_links table.
    // The WHERE l.sales_order_id IS NULL clause is the key: it ensures we only find
    // sales orders that do NOT have an existing link.
    $stmt = $db->prepare("
        SELECT 
            o.order_id,
            o.order_date,
            c.name as customer_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN po_so_links l ON o.order_id = l.sales_order_id
        WHERE o.stock_type = 'Pre-Book' 
          AND o.status IN ('New', 'Awaiting Stock')
          AND l.sales_order_id IS NULL
          AND (o.order_id LIKE ? OR c.name LIKE ?)
        ORDER BY o.order_date DESC, o.order_id DESC
        LIMIT 10
    ");

    if (!$stmt) {
        error_log("Search Open Pre-Orders Prepare Failed: " . $db->error);
        return [];
    }
    
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Retrieves a single, complete purchase order with its items, history, and detailed linked sales orders.
 *
 * @param string $purchase_order_id The ID of the PO to fetch.
 * @return array|false The complete PO data, or false if not found.
 */
function get_purchase_order_details($purchase_order_id) {
    $db = db();
    if (!$db) return false;

    // 1. Get the main PO details
    $stmt_po = $db->prepare("SELECT * FROM purchase_orders WHERE purchase_order_id = ?");
    if (!$stmt_po) return false;
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

    // 4. --- CORRECTED: Fetch linked sales orders WITH customer names ---
    $stmt_links = $db->prepare("
        SELECT 
            l.sales_order_id,
            c.name as customer_name
        FROM po_so_links l
        JOIN orders o ON l.sales_order_id = o.order_id
        JOIN customers c ON o.customer_id = c.customer_id
        WHERE l.purchase_order_id = ?
        ORDER BY l.sales_order_id
    ");
    $stmt_links->bind_param("s", $purchase_order_id);
    $stmt_links->execute();
    $po['linked_sales_orders'] = $stmt_links->get_result()->fetch_all(MYSQLI_ASSOC);
    // --- END CORRECTION ---

    return $po;
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
 * Searches for purchase orders and correctly attaches all linked sales orders.
 *
 * @param array $filters An associative array of filters.
 * @return array An array of matching purchase orders, each with a 'linked_orders' array.
 */
function search_purchase_orders($filters = []) {
    $db = db();
    if (!$db) return [];

    // Step 1: Get the filtered list of main Purchase Order data
    $sql = "
        SELECT 
            p.purchase_order_id, 
            p.po_date, 
            p.supplier_name, 
            p.status, 
            p.created_by_name
        FROM purchase_orders p
        WHERE 1=1
    ";
            
    $params = [];
    $types = '';

    if (!empty($filters['purchase_order_id'])) {
        $sql .= " AND p.purchase_order_id LIKE ?";
        $params[] = '%' . $filters['purchase_order_id'] . '%';
        $types .= 's';
    }
    if (!empty($filters['status'])) {
        $sql .= " AND p.status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    if (!empty($filters['date_from'])) {
        $sql .= " AND p.po_date >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND p.po_date <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }

    $sql .= " ORDER BY p.po_date DESC, p.purchase_order_id DESC LIMIT 100";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Purchase Order Search (Main Query) Prepare Failed: " . $db->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    // If no POs were found, return early.
    if (empty($orders)) {
        return [];
    }

    // Step 2: Now that we have the PO IDs, fetch all their links in one separate, efficient query.
    $order_ids = array_column($orders, 'purchase_order_id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?')); // Creates ?,?,? string
    
    $links_sql = "SELECT purchase_order_id, sales_order_id FROM po_so_links WHERE purchase_order_id IN ($placeholders)";
    $stmt_links = $db->prepare($links_sql);
    if (!$stmt_links) {
        error_log("Purchase Order Search (Links Query) Prepare Failed: " . $db->error);
        return $orders; // Return main data even if links fail
    }

    $stmt_links->bind_param(str_repeat('s', count($order_ids)), ...$order_ids);
    $stmt_links->execute();
    $links_result = $stmt_links->get_result()->fetch_all(MYSQLI_ASSOC);

    // Create a map for easy lookup: [purchase_order_id => [sales_order_id1, sales_order_id2]]
    $links_map = [];
    foreach ($links_result as $link) {
        $links_map[$link['purchase_order_id']][] = $link['sales_order_id'];
    }

    // Step 3: Attach the links array to each corresponding PO.
    foreach ($orders as $key => $order) {
        $po_id = $order['purchase_order_id'];
        // If a link exists in our map for this PO, use it. Otherwise, use an empty array.
        $orders[$key]['linked_orders'] = $links_map[$po_id] ?? [];
    }

    return $orders;
}

/**
 * Updates the status and details of an existing PO and tracks history.
 * - FINAL version with all automation for Payment and Receipt modals.
 *
 * @param string $purchase_order_id The ID of the PO to update.
 * @param array $details An array of header details to update.
 * @param array $post_data The raw POST data.
 * @return array An array containing a success boolean and a feedback message.
 * @throws Exception On validation or database errors.
 */
function update_purchase_order_details($purchase_order_id, $details, $post_data) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");

    $feedback_message = "✅ Purchase Order #$purchase_order_id successfully updated.";

    $db->begin_transaction();
    try {
        $stmt_check = $db->prepare("SELECT status FROM purchase_orders WHERE purchase_order_id = ? FOR UPDATE");
        if (!$stmt_check) throw new Exception("DB prepare failed for PO check.");
        $stmt_check->bind_param("s", $purchase_order_id);
        $stmt_check->execute();
        $current_state = $stmt_check->get_result()->fetch_assoc();
        
        if (!$current_state) throw new Exception("Purchase Order not found.");

        $old_status = $current_state['status'];
        $new_status = $details['status'];
        
        if ($old_status === 'Draft' || $old_status === 'Ordered') {
            $stmt_links_fetch = $db->prepare("SELECT sales_order_id FROM po_so_links WHERE purchase_order_id = ?");
            $stmt_links_fetch->bind_param("s", $purchase_order_id);
            $stmt_links_fetch->execute();
            $existing_links_result = $stmt_links_fetch->get_result()->fetch_all(MYSQLI_ASSOC);
            $existing_links = array_column($existing_links_result, 'sales_order_id');
            $submitted_links = $post_data['linked_sales_orders'] ?? [];
            $links_to_add = array_diff($submitted_links, $existing_links);
            if (!empty($links_to_add)) {
                $stmt_add = $db->prepare("INSERT INTO po_so_links (purchase_order_id, sales_order_id) VALUES (?, ?)");
                if (!$stmt_add) throw new Exception("DB prepare failed for adding PO links.");
                foreach ($links_to_add as $sales_order_id) {
                    $trimmed_so_id = trim($sales_order_id);
                    if (!empty($trimmed_so_id)) {
                        $stmt_add->bind_param("ss", $purchase_order_id, $trimmed_so_id);
                        $stmt_add->execute();
                    }
                }
            }
            $links_to_remove = array_diff($existing_links, $submitted_links);
            if (!empty($links_to_remove)) {
                $stmt_remove = $db->prepare("DELETE FROM po_so_links WHERE purchase_order_id = ? AND sales_order_id = ?");
                if (!$stmt_remove) throw new Exception("DB prepare failed for removing PO links.");
                foreach ($links_to_remove as $sales_order_id) {
                    $trimmed_so_id = trim($sales_order_id);
                    if (!empty($trimmed_so_id)) {
                        $stmt_remove->bind_param("ss", $purchase_order_id, $trimmed_so_id);
                        $stmt_remove->execute();
                    }
                }
            }
        }
        
        $stmt_final_links = $db->prepare("SELECT sales_order_id FROM po_so_links WHERE purchase_order_id = ?");
        $stmt_final_links->bind_param("s", $purchase_order_id);
        $stmt_final_links->execute();
        $final_links_result = $stmt_final_links->get_result()->fetch_all(MYSQLI_ASSOC);
        $linked_order_ids = array_column($final_links_result, 'sales_order_id');

        $is_receiving_event = ($new_status === 'Received' && $old_status !== 'Received');
        $is_cancellation_event = ($new_status === 'Canceled' && $old_status !== 'Canceled');
        $is_payment_event = ($new_status === 'Paid' && $old_status !== 'Paid');

        if ($is_cancellation_event) {
            if (in_array($old_status, ['Received', 'Completed'])) {
                throw new Exception("Cannot cancel this PO because its status is '$old_status'. Please cancel the corresponding GRN first to reverse the stock.");
            }
            $stmt_find_txns = $db->prepare("SELECT * FROM acc_transactions WHERE source_id = ? AND source_type = 'purchase_order' AND status = 'Posted'");
            $stmt_find_txns->bind_param("s", $purchase_order_id);
            $stmt_find_txns->execute();
            $posted_txns = $stmt_find_txns->get_result()->fetch_all(MYSQLI_ASSOC);
            if (!empty($posted_txns)) {
                $reversal_desc = "Reversal for canceled PO #" . $purchase_order_id;
                $grouped_txns = [];
                foreach ($posted_txns as $txn) { $grouped_txns[$txn['transaction_group_id']][] = $txn; }
                foreach ($grouped_txns as $group_id => $txns) {
                    $original_debit = null; $original_credit = null;
                    foreach ($txns as $t) {
                        if ($t['debit_amount'] !== null) $original_debit = $t;
                        if ($t['credit_amount'] !== null) $original_credit = $t;
                    }
                    if ($original_debit && $original_credit) {
                        $reversal_details = ['transaction_date' => date('Y-m-d'),'description' => $reversal_desc,'remarks' => 'Automated reversal for PO cancellation.','debit_account_id' => $original_credit['account_id'],'credit_account_id' => $original_debit['account_id'],'amount' => $original_debit['debit_amount']];
                        $reversal_group_id = process_journal_entry($reversal_details, 'purchase_order', $purchase_order_id, $db);
                        $stmt_update_txn = $db->prepare("UPDATE acc_transactions SET status = 'Canceled' WHERE transaction_group_id IN (?, ?)");
                        $stmt_update_txn->bind_param("ss", $group_id, $reversal_group_id);
                        $stmt_update_txn->execute();
                    }
                }
                $feedback_message .= " Associated financial transactions were reversed.";
            }
            if (!empty($linked_order_ids)) {
                foreach($linked_order_ids as $linked_order_id) {
                    $new_so_status = 'Awaiting Stock';
                    $history_remark = "Fulfillment reverted: Linked PO #$purchase_order_id was canceled.";
                    $stmt_so_update = $db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                    if ($stmt_so_update) { $stmt_so_update->bind_param("ss", $new_so_status, $linked_order_id); $stmt_so_update->execute(); }
                    $stmt_so_history = $db->prepare("INSERT INTO order_status_history (order_id, status, remarks, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt_so_history) { $stmt_so_history->bind_param("sssis", $linked_order_id, $new_so_status, $history_remark, $_SESSION['user_id'], 'System for ' . $_SESSION['username']); $stmt_so_history->execute(); }
                }
                $feedback_message .= " Linked Sales Orders were reverted to 'Awaiting Stock'.";
            }
        }

        $total_goods_cost = $post_data['total_goods_cost'] ?? 0;
        $paid_by_account_id = $post_data['goods_paid_by_account_id'] ?? null;
        $total_logistic_cost = $post_data['total_logistic_cost'] ?? 0;
        $logistic_paid_by_account_id = $post_data['logistic_paid_by_account_id'] ?? null;
        
        // --- DYNAMICALLY BUILD THE MAIN UPDATE QUERY ---
        $sql = "UPDATE purchase_orders SET status = ?, supplier_name = ?, remarks = ?";
        $types = "sss";
        $params = [ $new_status, $details['supplier_name'], $details['remarks'] ];
        
        if ($is_payment_event) {
            if ($total_goods_cost > 0 && $paid_by_account_id) {
                $sql .= ", total_goods_cost = ?, goods_paid_by_account_id = ?";
                $types .= "di";
                $params[] = $total_goods_cost;
                $params[] = $paid_by_account_id;
            } else {
                throw new Exception("To set status to 'Paid', you must provide payment details via the pop-up.");
            }
        }

        if ($is_receiving_event) {
            if ($total_logistic_cost > 0 && !$logistic_paid_by_account_id) {
                throw new Exception("To set status to 'Received' with a logistic cost, you must provide the logistic payment source.");
            }
            $sql .= ", total_logistic_cost = ?, logistic_paid_by_account_id = ?";
            $types .= "di";
            $params[] = $total_logistic_cost;
            $params[] = $logistic_paid_by_account_id;
        }
        
        $sql .= " WHERE purchase_order_id = ?";
        $types .= "s";
        $params[] = $purchase_order_id;
        
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new Exception("DB prepare failed for PO main update.");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        // --- UPDATE HISTORY and then CALL AUTOMATIONS ---
        if ($old_status !== $new_status) {
            $po_event_date = !empty($post_data['po_status_event_date']) ? $post_data['po_status_event_date'] : null;
            $stmt_history = $db->prepare("INSERT INTO purchase_order_status_history (purchase_order_id, status, event_date, created_by, created_by_name) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_history) throw new Exception("DB prepare failed for PO history.");
            $stmt_history->bind_param("sssis", $purchase_order_id, $new_status, $po_event_date, $_SESSION['user_id'], $_SESSION['username']);
            $stmt_history->execute();
        }
        
        if ($is_payment_event) {
            $payment_date = $post_data['po_status_event_date'] ?? null;
            process_po_payment_and_costs($purchase_order_id, $total_goods_cost, $paid_by_account_id, $payment_date);
            $feedback_message .= " Payment was processed and recorded.";
        }
        
        // --- THIS IS THE FINAL FIX ---
        if ($is_receiving_event) {
            $receipt_date = $post_data['po_status_event_date'] ?? null;
            // The process_po_receipt_and_logistics function now handles everything, including GRN creation.
            $new_grn_id = process_po_receipt_and_logistics($purchase_order_id, $total_logistic_cost, $logistic_paid_by_account_id, $db, $receipt_date);
            
            $feedback_message .= " Landed costs were finalized and GRN #$new_grn_id was automatically created.";

            if (!empty($linked_order_ids)) {
                 $feedback_message .= " All linked Sales Orders were fulfilled.";
            }
        }
        
        $db->commit();
        
        return ['success' => true, 'message' => $feedback_message];

    } catch (Exception $e) {
        $db->rollback();
        throw new Exception("Failed to update Purchase Order: " . $e->getMessage());
    }
}
//______________________________________________ End _____________________________________________________

// -----------------------------------------
// ----- Courier Charge Calculation -----
// -----------------------------------------

/**
 * Calculates courier charges based on weight and item value according to the SL POST rate card.
 *
 * @param float $weight_grams The total weight of the parcel in grams.
 * @param float $item_value The total value of the items in the parcel.
 * @return array An array containing the breakdown of charges and the total.
 */
function calculate_courier_charge($weight_grams, $item_value) {
    // --- Define the fixed charge here for easy future updates ---
    define('COURIER_FIXED_CHARGE', 50.00);

    $weight_grams = (float) $weight_grams;
    $item_value = (float) $item_value;
    $weight_charge = 0;
    $value_charge = 0;

    // --- Part 1: Calculate Weight-Based Charge ---
    if ($weight_grams > 0 && $weight_grams <= 250) { $weight_charge = 200.00; }
    elseif ($weight_grams > 250 && $weight_grams <= 500) { $weight_charge = 250.00; }
    elseif ($weight_grams > 500 && $weight_grams <= 1000) { $weight_charge = 350.00; }
    elseif ($weight_grams > 1000 && $weight_grams <= 2000) { $weight_charge = 400.00; }
    elseif ($weight_grams > 2000 && $weight_grams <= 3000) { $weight_charge = 450.00; }
    elseif ($weight_grams > 3000 && $weight_grams <= 4000) { $weight_charge = 500.00; }
    elseif ($weight_grams > 4000 && $weight_grams <= 5000) { $weight_charge = 550.00; }
    elseif ($weight_grams > 5000 && $weight_grams <= 6000) { $weight_charge = 600.00; }
    elseif ($weight_grams > 6000 && $weight_grams <= 7000) { $weight_charge = 650.00; }
    elseif ($weight_grams > 7000 && $weight_grams <= 8000) { $weight_charge = 700.00; }
    elseif ($weight_grams > 8000 && $weight_grams <= 9000) { $weight_charge = 750.00; }
    elseif ($weight_grams > 9000 && $weight_grams <= 10000) { $weight_charge = 800.00; }
    elseif ($weight_grams > 10000 && $weight_grams <= 15000) { $weight_charge = 850.00; }
    elseif ($weight_grams > 15000 && $weight_grams <= 20000) { $weight_charge = 1100.00; }
    elseif ($weight_grams > 20000 && $weight_grams <= 25000) { $weight_charge = 1600.00; }
    elseif ($weight_grams > 25000 && $weight_grams <= 30000) { $weight_charge = 2100.00; }
    elseif ($weight_grams > 30000 && $weight_grams <= 35000) { $weight_charge = 2600.00; }
    elseif ($weight_grams > 35000 && $weight_grams <= 40000) { $weight_charge = 3100.00; }

    // --- Part 2: Calculate Value-Based Charge ---
    if ($item_value > 0 && $item_value <= 2000) {
        $value_charge = ceil($item_value / 100) * 2.00;
    } elseif ($item_value > 2000 && $item_value <= 10000) {
        $base_charge = 40.00; // Rs. 2.00 for every Rs. 100 up to 2000
        $remaining_value = $item_value - 2000;
        $additional_charge = ceil($remaining_value / 2000) * 10.00;
        $value_charge = $base_charge + $additional_charge;
    } elseif ($item_value > 10000 && $item_value <= 50000) {
        $base_charge = 80.00; // Charge for first 10k (40 for first 2k + 4*10 for next 8k)
        $remaining_value = $item_value - 10000;
        $additional_charge = ceil($remaining_value / 40000) * 50.00;
        $value_charge = $base_charge + $additional_charge;
    } elseif ($item_value > 50000 && $item_value <= 100000) {
        $base_charge = 130.00; // Charge for first 50k (80 for first 10k + 50 for next 40k)
        $remaining_value = $item_value - 50000;
        $additional_charge = ceil($remaining_value / 50000) * 100.00;
        $value_charge = $base_charge + $additional_charge;
    }

    // --- Part 3: Return the final calculation with the fixed charge included ---
    return [
        'weight_charge' => $weight_charge,
        'value_charge'  => $value_charge,
        'fixed_charge'  => COURIER_FIXED_CHARGE, // Add the fixed charge to the response
        'total_charge'  => $weight_charge + $value_charge + COURIER_FIXED_CHARGE // Add it to the total
    ];
}
//______________________________________________ End _____________________________________________________