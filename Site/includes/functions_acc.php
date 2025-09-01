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

    // --- NEW: Add logic for the status filter ---
    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $sql .= " AND is_active = ?";
        $params[] = $filters['is_active'];
        $types .= 'i'; // 'i' for integer (0 or 1)
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
// ------------------------------------End------------------------------------------

/**
 * Retrieves a simple list of all active accounts for use in dropdown menus.
 *
 * @return array A list of accounts, each with 'account_id' and 'account_name'.
 */
function get_all_active_accounts() {
    $db = db();
    if (!$db) return [];

    $sql = "SELECT account_id, account_name FROM acc_chartofaccounts WHERE is_active = 1 ORDER BY account_name ASC";
    $result = $db->query($sql);
    
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
// ------------------------------------End------------------------------------------

// -----------------------------------------
// ----- Journal Entry Functions -----
// -----------------------------------------

/**
 * Processes a double-entry journal transaction (can be manual or automated).
 * Now includes the 'remarks' field.
 *
 * @param array $details An associative array with transaction details.
 * @param string $source_type The type of record that generated this transaction.
 * @param string|null $source_id The ID of the source record.
 * @param mysqli $db An existing database connection to use within a transaction.
 * @return string The transaction_group_id for the new entry.
 * @throws Exception On validation or database errors.
 */

function process_journal_entry($details, $source_type, $source_id, $db) {
    // --- Data Validation ---
    if (empty($details['transaction_date']) || empty($details['description']) || empty($details['debit_account_id']) || empty($details['credit_account_id']) || !isset($details['amount'])) {
        throw new Exception("Date, Description, Debit Account, Credit Account, and Amount are all required.");
    }
    if (!is_numeric($details['amount']) || $details['amount'] <= 0) {
        throw new Exception("Amount must be a positive number.");
    }
    if ($details['debit_account_id'] == $details['credit_account_id']) {
        throw new Exception("Debit and Credit accounts cannot be the same.");
    }

    // Generate a unique ID for this pair of transactions
    $transaction_group_id = generate_sequence_id('transaction_id', 'acc_transactions', 'transaction_group_id');
    
    // MODIFIED: Added the `remarks` column to the INSERT statement
    $sql = "INSERT INTO acc_transactions 
            (transaction_group_id, account_id, transaction_date, financial_year, description, remarks, debit_amount, credit_amount, source_type, source_id, created_by_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $db->error);

    // --- Prepare Shared Data ---
    $user_name = $_SESSION['username'] ?? 'Unknown';
    $transaction_date = $details['transaction_date'] . ' ' . date('H:i:s');
    $amount = (float)$details['amount'];
    $description = trim($details['description']);
    $remarks = trim($details['remarks'] ?? ''); // Get remarks, default to empty string if not set

    // Determine financial year
    $year = date('Y', strtotime($details['transaction_date']));
    $month = date('m', strtotime($details['transaction_date']));
    $financial_year = ($month >= 4) ? $year . '-' . ($year + 1) : ($year - 1) . '-' . $year;

    // --- 1. The DEBIT Entry ---
    $debit_amount = $amount;
    $credit_amount = null;
    // MODIFIED: Added 's' for remarks and the $remarks variable to bind_param
    $stmt->bind_param("sissssddsss", $transaction_group_id, $details['debit_account_id'], $transaction_date, $financial_year, $description, $remarks, $debit_amount, $credit_amount, $source_type, $source_id, $user_name);
    $stmt->execute();
    
    // --- 2. The CREDIT Entry ---
    $debit_amount = null;
    $credit_amount = $amount;
    // MODIFIED: Added 's' for remarks and the $remarks variable to bind_param
    $stmt->bind_param("sissssddsss", $transaction_group_id, $details['credit_account_id'], $transaction_date, $financial_year, $description, $remarks, $debit_amount, $credit_amount, $source_type, $source_id, $user_name);
    $stmt->execute();
    
    return $transaction_group_id;
}

// -----------------------------------------
// ----- Automated Transaction Functions -----
// -----------------------------------------

/**
 * Creates BOTH the revenue and COGS accounting entries for a paid sales order.
 * This function assumes no prior entries exist for this order_id.
 *
 * @param string $order_id The ID of the sales order.
 * @param mysqli $db The existing database connection for transaction integrity.
 * @return bool True on success.
 * @throws Exception On failure.
 */
function record_sales_transaction($order_id, $db) {
    // Step 1: Fetch the main order details
    $stmt_order = $db->prepare("SELECT total_amount, other_expenses, payment_method, order_date FROM orders WHERE order_id = ?");
    if (!$stmt_order) throw new Exception("DB prepare failed for fetching order details.");
    $stmt_order->bind_param("s", $order_id);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception("Sales Order #$order_id not found for accounting entry.");
    }
    
    // --- Step 2: Record the Sales Revenue Transaction ---
    $revenue_amount = (float)$order['total_amount'] + (float)$order['other_expenses'];
    if ($revenue_amount > 0) {
        $debit_account_name = ($order['payment_method'] === 'COD') ? 'Cash in Hand' : 'Bank Account';
        
        $stmt_acc = $db->prepare("SELECT account_id, account_name FROM acc_chartofaccounts WHERE account_name IN (?, 'Sales Revenue')");
        $stmt_acc->bind_param("s", $debit_account_name);
        $stmt_acc->execute();
        $accounts_res = $stmt_acc->get_result()->fetch_all(MYSQLI_ASSOC);
        $accounts = array_column($accounts_res, 'account_id', 'account_name');
        
        if (!isset($accounts[$debit_account_name]) || !isset($accounts['Sales Revenue'])) {
            throw new Exception("Could not find necessary system accounts ('$debit_account_name', 'Sales Revenue') in the Chart of Accounts.");
        }

        $revenue_details = [
            'transaction_date'  => $order['order_date'],
            'description'       => 'Sales revenue from Order #' . $order_id,
            'debit_account_id'  => $accounts[$debit_account_name],
            'credit_account_id' => $accounts['Sales Revenue'],
            'amount'            => $revenue_amount,
        ];
        process_journal_entry($revenue_details, 'sales_order', $order_id, $db);
    }

    // --- Step 3: Record the Cost of Goods Sold (COGS) Transaction ---
    $stmt_items = $db->prepare("SELECT SUM(quantity * cost_price) as total_cogs FROM order_items WHERE order_id = ?");
    if (!$stmt_items) throw new Exception("DB prepare failed for fetching order items for COGS.");
    $stmt_items->bind_param("s", $order_id);
    $stmt_items->execute();
    $cogs_data = $stmt_items->get_result()->fetch_assoc();
    
    $cogs_amount = (float)($cogs_data['total_cogs'] ?? 0);
    if ($cogs_amount > 0) {
        $stmt_acc_cogs = $db->prepare("SELECT account_id, account_name FROM acc_chartofaccounts WHERE account_name IN ('Cost of Goods Sold', 'Inventory')");
        $stmt_acc_cogs->execute();
        $accounts_cogs_res = $stmt_acc_cogs->get_result()->fetch_all(MYSQLI_ASSOC);
        $cogs_accounts = array_column($accounts_cogs_res, 'account_id', 'account_name');

        if (!isset($cogs_accounts['Cost of Goods Sold']) || !isset($cogs_accounts['Inventory'])) {
            throw new Exception("Could not find necessary system accounts ('Cost of Goods Sold', 'Inventory') in the Chart of Accounts.");
        }

        $cogs_details = [
            'transaction_date'  => $order['order_date'],
            'description'       => 'Cost of goods sold for Order #' . $order_id,
            'debit_account_id'  => $cogs_accounts['Cost of Goods Sold'],
            'credit_account_id' => $cogs_accounts['Inventory'],
            'amount'            => $cogs_amount,
        ];
        process_journal_entry($cogs_details, 'sales_order', $order_id, $db);
    }
    
    return true;
}
// ----------------End----------------------

/**
 * Creates the accounting entries for receiving goods from a PO.
 * Debits Inventory, Credits Accounts Payable.
 *
 * @param string $po_id The ID of the Purchase Order.
 * @param mysqli $db The existing database connection.
 * @return bool True on success.
 * @throws Exception On failure.
 */
function record_inventory_purchase($po_id, $db) {
    // CORRECTED: The query now joins with the main purchase_orders table to get the po_date
    $stmt_po = $db->prepare("
        SELECT p.po_date, SUM(pi.quantity * pi.cost_price) as total_cost 
        FROM purchase_orders p
        JOIN purchase_order_items pi ON p.purchase_order_id = pi.purchase_order_id
        WHERE p.purchase_order_id = ?
        GROUP BY p.purchase_order_id, p.po_date
    ");
    if (!$stmt_po) throw new Exception("DB prepare failed for fetching PO total.");
    $stmt_po->bind_param("s", $po_id);
    $stmt_po->execute();
    $po = $stmt_po->get_result()->fetch_assoc();

    if (!$po || !isset($po['total_cost']) || $po['total_cost'] <= 0) {
        return true; // No cost to record
    }

    $stmt_acc = $db->prepare("SELECT account_id, account_name FROM acc_chartofaccounts WHERE account_name IN ('Inventory', 'Accounts Payable')");
    $stmt_acc->execute();
    $accounts_res = $stmt_acc->get_result()->fetch_all(MYSQLI_ASSOC);
    $accounts = array_column($accounts_res, 'account_id', 'account_name');

    if (!isset($accounts['Inventory']) || !isset($accounts['Accounts Payable'])) {
        throw new Exception("Could not find 'Inventory' or 'Accounts Payable' in the Chart of Accounts.");
    }

    $details = [
        'transaction_date'  => $po['po_date'],
        'description'       => 'Inventory received from PO #' . $po_id,
        'debit_account_id'  => $accounts['Inventory'],
        'credit_account_id' => $accounts['Accounts Payable'],
        'amount'            => $po['total_cost'],
    ];

    return process_journal_entry($details, 'purchase_order', $po_id, $db);
}
// ----------------End----------------------

/**
 * Creates the accounting entries for paying a supplier for a PO.
 * Debits Accounts Payable, Credits Cash/Bank.
 *
 * @param string $po_id The ID of the Purchase Order.
 * @param mysqli $db The existing database connection.
 * @return bool True on success.
 * @throws Exception On failure.
 */
function record_purchase_payment($po_id, $db, $event_date = null) {
    // CORRECTED: The query now joins with the main purchase_orders table to get the po_date
    $stmt_po = $db->prepare("
        SELECT p.po_date, SUM(pi.quantity * pi.cost_price) as total_cost
        FROM purchase_orders p
        JOIN purchase_order_items pi ON p.purchase_order_id = pi.purchase_order_id
        WHERE p.purchase_order_id = ?
        GROUP BY p.purchase_order_id, p.po_date
    ");
    if (!$stmt_po) throw new Exception("DB prepare failed for fetching PO total.");
    $stmt_po->bind_param("s", $po_id);
    $stmt_po->execute();
    $po = $stmt_po->get_result()->fetch_assoc();

    if (!$po || !isset($po['total_cost']) || $po['total_cost'] <= 0) {
        return true; // No cost to record
    }
    
    $stmt_acc = $db->prepare("SELECT account_id, account_name FROM acc_chartofaccounts WHERE account_name IN ('Bank Account', 'Accounts Payable')");
    $stmt_acc->execute();
    $accounts_res = $stmt_acc->get_result()->fetch_all(MYSQLI_ASSOC);
    $accounts = array_column($accounts_res, 'account_id', 'account_name');

    if (!isset($accounts['Bank Account']) || !isset($accounts['Accounts Payable'])) {
        throw new Exception("Could not find 'Bank Account' or 'Accounts Payable' in the Chart of Accounts.");
    }

    $details = [
        'transaction_date'  => $event_date ? date('Y-m-d', strtotime($event_date)) : $po['po_date'],
        'description'       => 'Payment for PO #' . $po_id,
        'debit_account_id'  => $accounts['Accounts Payable'],
        'credit_account_id' => $accounts['Bank Account'],
        'amount'            => $po['total_cost'],
    ];

    return process_journal_entry($details, 'purchase_order', $po_id, $db);
}
// ----------------------------------End-----------------------------------------

/**
 * Retrieves a list of distinct financial years from the transactions table.
 *
 * @return array A list of financial year strings.
 */
function get_distinct_financial_years() {
    $db = db();
    if (!$db) return [];

    $sql = "SELECT DISTINCT financial_year FROM acc_transactions ORDER BY financial_year DESC";
    $result = $db->query($sql);
    
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
// ----------------------------------End-----------------------------------------

// -----------------------------------------
// ----- Transaction Reporting Functions -----
// -----------------------------------------

/**
 * Searches and filters the account transactions (General Ledger).
 * Now includes filtering by Financial Year.
 *
 * @param array $filters An associative array of filters.
 * @return array The list of transactions.
 */
function get_account_transactions($filters = []) {
    $db = db();
    if (!$db) return [];

    $sql = "
        SELECT 
            t.transaction_id,
            t.transaction_group_id,
            t.transaction_date,
            t.financial_year,
            t.description,
            t.remarks,
            t.debit_amount,
            t.credit_amount,
            t.status, -- THIS LINE WAS MISSING
            t.source_type,
            t.source_id,
            a.account_name,
            a.account_type
        FROM acc_transactions t
        JOIN acc_chartofaccounts a ON t.account_id = a.account_id
        WHERE 1=1
    ";

    $params = [];
    $types = '';

    // Filter by Date Range
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(t.transaction_date) >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(t.transaction_date) <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }

    // Filter by a specific Account
    if (!empty($filters['account_id'])) {
        $sql .= " AND t.account_id = ?";
        $params[] = $filters['account_id'];
        $types .= 'i';
    }

    // Filter by Description text search
    if (!empty($filters['description'])) {
        $sql .= " AND t.description LIKE ?";
        $params[] = '%' . $filters['description'] . '%';
        $types .= 's';
    }
    
    // --- NEW: Filter by Financial Year ---
    if (!empty($filters['financial_year'])) {
        $sql .= " AND t.financial_year = ?";
        $params[] = $filters['financial_year'];
        $types .= 's';
    }
    // --- END NEW ---

    $sql .= " ORDER BY t.transaction_date DESC, t.transaction_group_id DESC, t.credit_amount ASC";
    
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Account Transaction Search Prepare Failed: " . $db->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
// ----------------------------------End-----------------------------------------

/**
 * Cancels a manual journal entry by creating a reversing entry.
 *
 * @param string $transaction_group_id The group ID of the transaction to cancel.
 * @return string The group ID of the new reversing transaction.
 * @throws Exception On failure.
 */
function cancel_manual_journal_entry($transaction_group_id) {
    $db = db();
    if (!$db) throw new Exception("Database connection failed.");

    $db->begin_transaction();
    try {
        // Step 1: Fetch the original debit and credit entries
        $stmt_fetch = $db->prepare("SELECT * FROM acc_transactions WHERE transaction_group_id = ? AND status = 'Posted'");
        $stmt_fetch->bind_param("s", $transaction_group_id);
        $stmt_fetch->execute();
        $original_txns = $stmt_fetch->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($original_txns) !== 2) {
            throw new Exception("Transaction not found or is already canceled/invalid.");
        }

        // Identify original debit and credit
        $original_debit = null;
        $original_credit = null;
        foreach($original_txns as $txn) {
            if ($txn['debit_amount'] !== null) $original_debit = $txn;
            if ($txn['credit_amount'] !== null) $original_credit = $txn;
        }

        if (!$original_debit || !$original_credit) {
            throw new Exception("Original transaction is malformed.");
        }

        // Step 2: Create a new reversing journal entry
        $reversal_details = [
            'transaction_date'  => date('Y-m-d'),
            'description'       => 'Reversal of transaction ' . $transaction_group_id,
            'remarks'           => 'Automated reversal of manual entry.',
            // The debit/credit accounts are now flipped
            'debit_account_id'  => $original_credit['account_id'],
            'credit_account_id' => $original_debit['account_id'],
            'amount'            => $original_debit['debit_amount'],
        ];
        
        $reversal_group_id = process_journal_entry($reversal_details, 'manual_entry', $transaction_group_id, $db);

        // Step 3: Mark BOTH the original and the new reversal transaction as 'Canceled'
        $stmt_update = $db->prepare("UPDATE acc_transactions SET status = 'Canceled' WHERE transaction_group_id IN (?, ?)");
        $stmt_update->bind_param("ss", $transaction_group_id, $reversal_group_id);
        $stmt_update->execute();
        
        $db->commit();
        
        return $reversal_group_id;

    } catch (Exception $e) {
        $db->rollback();
        throw new Exception("Failed to cancel journal entry: " . $e->getMessage());
    }
}
// ----------------------------------End-----------------------------------------
