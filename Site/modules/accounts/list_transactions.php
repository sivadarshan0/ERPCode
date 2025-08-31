<?php
// File: /modules/accounts/list_transactions.php
// FINAL version with status column, cancel button, and cancel AJAX endpoint.

session_start();
$custom_js = 'app_acc';
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_acc.php'; // Corrected filename

require_login();

// --- AJAX Endpoints ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_GET['action']) {
            case 'search':
                $filters = [
                    'date_from'      => $_GET['date_from'] ?? null,
                    'date_to'        => $_GET['date_to'] ?? null,
                    'account_id'     => $_GET['account_id'] ?? null,
                    'description'    => $_GET['description'] ?? null,
                    'financial_year' => $_GET['financial_year'] ?? null,
                ];
                echo json_encode(get_account_transactions($filters));
                break;
            
            case 'cancel_transaction':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method.');
                }
                $group_id = $_POST['group_id'] ?? null;
                if (!$group_id) {
                    throw new Exception('Transaction Group ID is missing.');
                }
                
                $reversal_id = cancel_manual_journal_entry($group_id);
                
                echo json_encode(['success' => true, 'message' => "Transaction #$group_id canceled. Reversing entry #$reversal_id created."]);
                break;
        }
    } catch (Exception $e) { 
        http_response_code(400); 
        echo json_encode(['success' => false, 'error' => $e->getMessage()]); 
    }
    exit; // Use exit instead of die() for consistency
}

// Initial page load data
$all_accounts = get_all_active_accounts();
$financial_years = get_distinct_financial_years();

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
            
            <?php
            if (isset($_SESSION['success_message'])) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            ?>

            <!-- Search Filters -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-search"></i> Find Transactions</div>
                <div class="card-body">
                    <form id="transactionSearchForm" class="row gx-3 gy-2 align-items-center">
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="search_date_range" placeholder="Select Date Range">
                        </div>
                        <div class="col-md-2">
                            <select id="search_financial_year" class="form-select">
                                <option value="">All Years</option>
                                <?php foreach ($financial_years as $fy): ?>
                                    <option value="<?= htmlspecialchars($fy['financial_year']) ?>">
                                        <?= htmlspecialchars($fy['financial_year']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                             <select id="search_account_id" class="form-select">
                                <option value="">Filter by Account...</option>
                                <?php foreach ($all_accounts as $account): ?>
                                    <option value="<?= $account['account_id'] ?>">
                                        <?= htmlspecialchars($account['account_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
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
                                    <th>Financial Year</th>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th>Status</th>
                                    <th>Source</th>
                                    <th>Actions</th>
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