<?php
// File: includes/functions.php

defined('_IN_APP_') or die('Unauthorized access');

require_once __DIR__ . '/../config/db.php';

// ───── Auth-related functions ─────

function is_account_locked($username) {
    $db = db();
    $stmt = $db->prepare("SELECT locked_until FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return $user['locked_until'];
    }
    return false;
}

function get_user_by_username($username) {
    $db = db();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("Database prepare error: " . $db->error);
        return null;
    }
    
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return null;
    }
    
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function update_user_login($user_id, $success, $ip_address) {
    if (!is_numeric($user_id) || $user_id <= 0) {
        error_log("Invalid user_id in update_user_login: $user_id");
        return false;
    }

    $db = db();

    try {
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
            $result = $stmt_check->get_result();
            $user = $result->fetch_assoc();
            
            if ($user && $user['failed_attempts'] >= 5) {
                $lock_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $stmt_lock = $db->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
                $stmt_lock->bind_param("si", $lock_time, $user_id);
                $stmt_lock->execute();
            }
        }

        log_login_attempt($user_id, $ip_address, $success);

        return true;
    } catch (Exception $e) {
        error_log("Error in update_user_login: " . $e->getMessage());
        return false;
    }
}

function log_login_attempt($user_id, $ip_address, $success) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO user_login_audit (user_id, login_time, ip_address, success) VALUES (?, NOW(), ?, ?)");
    $stmt->bind_param("isi", $user_id, $ip_address, $success);
    $stmt->execute();
}

// ───── Session handling ─────

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) { // 30-minute timeout
        session_unset();
        session_destroy();
        header('Location: /login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

// ───── Sequence ID Generator (Robust & Generic Version) ─────

/**
 * Generates a unique, sequential ID from the system_sequences table.
 * This function is safe, generic, and handles potential sequence desynchronization.
 *
 * @param string $sequence_name The name of the sequence (e.g., 'customer_id', 'category_id').
 * @param string $table The database table where the ID will be used (e.g., 'customers', 'categories').
 * @param string $column The column in that table that holds the ID (e.g., 'customer_id', 'category_id').
 * @return string The newly generated unique ID.
 * @throws Exception If the sequence is not found or ID generation fails.
 */
function generate_sequence_id($sequence_name, $table, $column) {
    $db = db();
    $db->begin_transaction();

    try {
        // Step 1: Lock the sequence row to prevent race conditions and get its details.
        $stmt = $db->prepare("SELECT prefix, next_value, digit_length FROM system_sequences WHERE sequence_name = ? FOR UPDATE");
        $stmt->bind_param("s", $sequence_name);
        $stmt->execute();
        $seq = $stmt->get_result()->fetch_assoc();

        if (!$seq) {
            throw new Exception("Sequence '$sequence_name' not found in system_sequences.");
        }

        $new_id = $seq['prefix'] . str_pad($seq['next_value'], $seq['digit_length'], '0', STR_PAD_LEFT);
        $next_value_for_update = $seq['next_value'] + 1;

        // Step 2: SAFETY CHECK. Verify the generated ID doesn't already exist in the target table.
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new Exception("Invalid table or column name provided for sequence check.");
        }
        
        $check_sql = "SELECT `$column` FROM `$table` WHERE `$column` = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("s", $new_id);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            // ID is already in use! Recover by finding the actual max value.
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

        // Step 3: Update the sequence table with the correct next value.
        $update = $db->prepare("UPDATE system_sequences SET next_value = ?, last_used_at = NOW(), last_used_by = ? WHERE sequence_name = ?");
        $user_id = $_SESSION['user_id'] ?? null;
        $update->bind_param("iss", $next_value_for_update, $user_id, $sequence_name);
        $update->execute();

        // Step 4: Commit the transaction.
        $db->commit();
        
        return $new_id;

    } catch (Exception $e) {
        $db->rollback();
        error_log("SEQUENCE-ERROR: " . $e->getMessage());
        throw new Exception("Could not generate a unique ID. A system error occurred.");
    }
}

// ───── Customer-related functions ─────

function get_customer($customer_id) {
    $db = db();
    if (!$db) return false;
    
    $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error);
        return false;
    }

    $stmt->bind_param("s", $customer_id);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }

    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : false;
}

function search_customers_by_phone($phone) {
    $db = db();
    if (!$db) {
        error_log("Database connection failed");
        return [];
    }

    $search_term = "%$phone%";
    $stmt = $db->prepare("SELECT customer_id, name, phone FROM customers WHERE phone LIKE ? ORDER BY name LIMIT 10");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error);
        return [];
    }

    $stmt->bind_param("s", $search_term);

    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return [];
    }

    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function validate_customer_phone($phone, $exclude_id = null) {
    $db = db();
    $sql = "SELECT COUNT(*) FROM customers WHERE phone = ?";
    $types = "s";
    $params = [$phone];
    
    if ($exclude_id) {
        $sql .= " AND customer_id != ?";
        $types .= "s";
        $params[] = $exclude_id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    return $count == 0;
}

// ───── Category-related functions ─────

/**
 * Retrieves a single category from the database by its category_id.
 *
 * @param string $category_id The ID of the category to fetch.
 * @return array|false The category data as an associative array, or false if not found.
 */
function get_category($category_id) {
    $db = db();
    if (!$db) return false;
    
    $stmt = $db->prepare("SELECT * FROM categories WHERE category_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error);
        return false;
    }

    $stmt->bind_param("s", $category_id);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }

    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : false;
}

/**
 * Searches for categories by name (case-insensitive).
 *
 * @param string $name The name to search for.
 * @return array An array of matching categories.
 */
function search_categories_by_name($name) {
    $db = db();
    if (!$db) {
        error_log("Database connection failed");
        return [];
    }

    $search_term = "%$name%";
    $stmt = $db->prepare("SELECT c.category_id, c.name, c.description FROM categories c WHERE c.name LIKE ? ORDER BY c.name LIMIT 10");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error);
        return [];
    }

    $stmt->bind_param("s", $search_term);

    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return [];
    }

    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// ---------------------------------------------
// ----- Sub-Category-related functions -----
// ---------------------------------------------

/**
 * Retrieves all parent categories for use in dropdowns.
 *
 * @return array An array of all categories.
 */
function get_all_categories() {
    $db = db();
    if (!$db) return [];
    $result = $db->query("SELECT category_id, name FROM categories ORDER BY name ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Retrieves a single sub-category from the database by its ID.
 *
 * @param string $category_sub_id The ID of the sub-category to fetch.
 * @return array|false The sub-category data, or false if not found.
 */
function get_sub_category($category_sub_id) {
    $db = db();
    if (!$db) return false;
    
    $stmt = $db->prepare("SELECT * FROM categories_sub WHERE category_sub_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error);
        return false;
    }

    $stmt->bind_param("s", $category_sub_id);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }

    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : false;
}

/**
 * Searches for sub-categories by name (case-insensitive).
 *
 * @param string $name The name to search for.
 * @return array An array of matching sub-categories.
 */
function search_sub_categories_by_name($name) {
    $db = db();
    if (!$db) {
        error_log("Database connection failed");
        return [];
    }

    $search_term = "%$name%";
    // Join with categories to show the parent category name in search results
    $stmt = $db->prepare("
        SELECT 
            cs.category_sub_id, 
            cs.name, 
            cs.description,
            c.name as parent_category_name
        FROM categories_sub cs
        JOIN categories c ON cs.category_id = c.category_id
        WHERE cs.name LIKE ? 
        ORDER BY cs.name 
        LIMIT 10
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error);
        return [];
    }

    $stmt->bind_param("s", $search_term);

    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return [];
    }

    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

?>