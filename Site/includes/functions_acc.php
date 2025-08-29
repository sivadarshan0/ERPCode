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
