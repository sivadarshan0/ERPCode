<?php
// File: includes/functions.php

defined('_IN_APP_') or die('Unauthorized access');

require_once __DIR__ . '/../config/db.php';

// ───── Auth-related functions ─────
function is_account_locked($username) {
    $db = db();
    $stmt = $db->prepare("SELECT locked_until FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
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
            $stmt = $db->prepare("SELECT failed_attempts FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user && $user['failed_attempts'] >= 5) {
                $lock_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $stmt = $db->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
                $stmt->bind_param("si", $lock_time, $user_id);
                $stmt->execute();
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
    $stmt->execute([$user_id, $ip_address, (int)$success]);
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

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: /login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

// ───── Sequence ID Generator ─────
function generate_sequence_id($sequence_name) {
    $db = db();
    $db->begin_transaction();

    try {
        // Get current sequence with lock
        $stmt = $db->prepare("SELECT prefix, next_value, digit_length 
                            FROM system_sequences 
                            WHERE sequence_name = ? FOR UPDATE");
        $stmt->bind_param("s", $sequence_name);
        $stmt->execute();
        $seq = $stmt->get_result()->fetch_assoc();

        if (!$seq) {
            throw new Exception("Sequence '$sequence_name' not found");
        }

        // Generate the ID
        $new_id = $seq['prefix'] . str_pad($seq['next_value'], $seq['digit_length'], '0', STR_PAD_LEFT);

        // Verify this ID doesn't already exist
        $check = $db->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
        $check->bind_param("s", $new_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            // If ID exists, find the next available
            $find = $db->prepare("SELECT MAX(CAST(SUBSTRING(customer_id, 4) AS UNSIGNED) 
                                 FROM customers 
                                 WHERE customer_id LIKE 'CUS%'");
            $find->execute();
            $max_used = $find->get_result()->fetch_row()[0];
            $next_val = $max_used + 1;
            $new_id = $seq['prefix'] . str_pad($next_val, $seq['digit_length'], '0', STR_PAD_LEFT);
            
            // Update sequence to this value + 1
            $update_val = $next_val + 1;
        } else {
            $update_val = $seq['next_value'] + 1;
        }

        // Update sequence
        $update = $db->prepare("UPDATE system_sequences 
                               SET next_value = ?, 
                                   last_used_at = NOW(), 
                                   last_used_by = ? 
                               WHERE sequence_name = ?");
        $update->bind_param("iss", $update_val, $_SESSION['user_id'], $sequence_name);
        $update->execute();

        $db->commit();
        return $new_id;
    } catch (Exception $e) {
        $db->rollback();
        error_log("Sequence generation failed: " . $e->getMessage());
        throw new Exception("Could not generate ID");
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
    $params = [$phone];
    
    if ($exclude_id) {
        $sql .= " AND customer_id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() == 0;
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
    // Using c.name and c.description to be explicit
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

// ───── Sequence ID Generator (IMPROVED VERSION) ─────

/**
 * Generates a unique, sequential ID from the system_sequences table.
 * This is a more generic version that can handle different tables.
 *
 * @param string $sequence_name The name of the sequence (e.g., 'customer_id', 'category_id').
 * @param string $table The table where the ID will be used (e.g., 'customers', 'categories').
 * @param string $column The column in the table that holds the ID.
 * @return string The newly generated ID.
 * @throws Exception If the sequence is not found or ID generation fails.
 */
function generate_sequence_id($sequence_name, $table, $column) {
    $db = db();
    $db->begin_transaction();

    try {
        // Step 1: Lock and retrieve the sequence details.
        $stmt = $db->prepare("SELECT prefix, next_value, digit_length 
                            FROM system_sequences 
                            WHERE sequence_name = ? FOR UPDATE");
        $stmt->bind_param("s", $sequence_name);
        $stmt->execute();
        $seq = $stmt->get_result()->fetch_assoc();

        if (!$seq) {
            throw new Exception("Sequence '$sequence_name' not found in system_sequences.");
        }

        // Step 2: Format the potential new ID.
        $new_id = $seq['prefix'] . str_pad($seq['next_value'], $seq['digit_length'], '0', STR_PAD_LEFT);
        
        // Step 3: Update the sequence's next_value.
        // We increment immediately to reduce race conditions.
        $update_val = $seq['next_value'] + 1;
        $update = $db->prepare("UPDATE system_sequences 
                               SET next_value = ?, 
                                   last_used_at = NOW(), 
                                   last_used_by = ? 
                               WHERE sequence_name = ?");
        $update->bind_param("iss", $update_val, $_SESSION['user_id'], $sequence_name);
        $update->execute();
        
        // Step 4: Commit the transaction.
        $db->commit();
        
        return $new_id;

    } catch (Exception $e) {
        $db->rollback();
        error_log("Sequence generation failed for '$sequence_name': " . $e->getMessage());
        // Re-throw to be caught by the calling script's try-catch block
        throw new Exception("Could not generate a unique ID. Please try again.");
    }
}

?>
