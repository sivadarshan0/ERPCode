<?php
// File: /modules/accounts/list_transactions.php
// Page to display and filter all account transactions (General Ledger).

session_start();
$custom_js = 'app_acc'; // Load our dedicated accounts javascript
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_acc.php';

require_login();

// --- AJAX Endpoint for Live Search ---
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    try {
        $filters = [
            'date_from'    => $_GET['date_from'] ?? null,
            'date_to'      => $_GET['date_to'] ?? null,
            'account_id'   => $_GET['account_id'] ?? null,
            'description'  => $_GET['description'] ?? null,
        ];
        echo json_encode(get_account_transactions($filters));
    } catch (Exception $e) { 
        http_response_code(500); 
        echo json_encode(['error' => $e->getMessage()]); 
    }
    exit;
}

// Initial page load
$initial_transactions = get_account_transactions();
$all_accounts = get_all_active_accounts();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Transaction Journal (General Ledger)</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/modules/accounts/entry_transaction.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> New Journal Entry
                    </a>
                </div>
            </div>

            <!-- Search Filters -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-search"></i> Find Transactions</div>
                <div class="card-body">
                    <form id="transactionSearchForm" class="row gx-3 gy-2 align-items-center">
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="search_date_range" placeholder="Select Date Range">
                        </div>
                        <div class="col-md-4">
                             <select id="search_account_id" class="form-select">
                                <option value="">Filter by Account...</option>
                                <?php foreach ($all_accounts as $account): ?>
                                    <option value="<?= $account['account_id'] ?>">
                                        <?= htmlspecialchars($account['account_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="search_description" placeholder="Search in Description...">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transaction List Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody id="transactionListTableBody">
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>