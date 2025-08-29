<?php
// File: /includes/function_acc.php
// All backend functions for the Accounts module.

defined('_IN_APP_') or die('Unauthorized access');

// -----------------------------------------
// ----- Chart of Accounts (CoA) Functions -----
// -----------------------------------------

/**
 * Searches and filters the Chart of Accounts.
 *
 * @param array $filters An associative array of filters.
 * @return array The list of accounts.
 */

function search_chart_of_accounts($filters = []) {
    $db = db();
    if (!$db) return [];

    $sql = "SELECT account_id, account_name, account_type, normal_balance, is_active FROM acc_chartofaccounts WHERE 1=1";
    
    $params = [];
    $types = '';

    if (!empty($filters['account_name'])) {
        $sql .= " AND account_name LIKE ?";
        $params[] = '%' . $filters['account_name'] . '%';
        $types .= 's';
    }

    if (!empty($filters['account_type'])) {
        $sql .= " AND account_type = ?";
        $params[] = $filters['account_type'];
        $types .= 's';
    }

    $sql .= " ORDER BY account_type, account_name";
    
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Chart of Accounts Search Prepare Failed: " . $db->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
// ----------------End----------------------

/**
 * Retrieves a single account by its ID.
 *
 * @param int $account_id The ID of the account to fetch.
 * @return array|null The account data or null if not found.
 */
function get_account($account_id) {
    $db = db();
    if (!$db) return null;

    $stmt = $db->prepare("SELECT * FROM acc_chartofaccounts WHERE account_id = ?");
    if (!$stmt) return null;

    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}
// ----------------End----------------------

/**
 * Adds a new account to the Chart of Accounts.
 *
 * @param array $details An associative array of account details.
 * @return int The ID of the newly inserted account.
 * @throws Exception On failure.
 */
function add_account($details) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");

    // Basic validation
    if (empty($details['account_name']) || empty($details['account_type']) || empty($details['normal_balance'])) {
        throw new Exception("Account Name, Type, and Normal Balance are required.");
    }

    $sql = "INSERT INTO acc_chartofaccounts (account_name, account_type, normal_balance, description, is_active) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $db->error);

    $stmt->bind_param(
        "ssssi",
        $details['account_name'],
        $details['account_type'],
        $details['normal_balance'],
        $details['description'],
        $details['is_active']
    );

    if (!$stmt->execute()) {
        // Check for duplicate name error
        if ($db->errno === 1062) {
            throw new Exception("An account with this name already exists.");
        }
        throw new Exception("Failed to create new account: " . $stmt->error);
    }

    return $db->insert_id;
}
// ----------------End----------------------

/**
 * Updates an existing account in the Chart of Accounts.
 *
 * @param int $account_id The ID of the account to update.
 * @param array $details An associative array of account details.
 * @return bool True on success.
 * @throws Exception On failure.
 */
function update_account($account_id, $details) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");

    // Basic validation
    if (empty($account_id) || empty($details['account_name']) || empty($details['account_type']) || empty($details['normal_balance'])) {
        throw new Exception("Account Name, Type, and Normal Balance are required.");
    }

    $sql = "UPDATE acc_chartofaccounts SET account_name = ?, account_type = ?, normal_balance = ?, description = ?, is_active = ? WHERE account_id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $db->error);

    $stmt->bind_param(
        "ssssii",
        $details['account_name'],
        $details['account_type'],
        $details['normal_balance'],
        $details['description'],
        $details['is_active'],
        $account_id
    );

    if (!$stmt->execute()) {
        // Check for duplicate name error
        if ($db->errno === 1062) {
            throw new Exception("An account with this name already exists.");
        }
        throw new Exception("Failed to update account: " . $stmt->error);
    }

    return true;
}
// ----------------End----------------------