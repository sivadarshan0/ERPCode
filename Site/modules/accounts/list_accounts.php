<?php
// File: /modules/accounts/list_accounts.php
// Page to display and filter the Chart of Accounts.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php'; 
require_once __DIR__ . '/../../includes/functions_acc.php'; // Include the new accounts functions file


require_login();

// --- AJAX Endpoint for Live Search ---
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    try {
        $filters = [
            'account_name' => $_GET['account_name'] ?? null,
            'account_type' => $_GET['account_type'] ?? null,
        ];
        echo json_encode(search_chart_of_accounts($filters));
    } catch (Exception $e) { 
        http_response_code(500); 
        echo json_encode(['error' => $e->getMessage()]); 
    }
    exit;
}

// Initial page load - get all accounts
$initial_accounts = search_chart_of_accounts();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Chart of Accounts</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/index.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
                    </a>
                    <a href="/modules/accounts/entry_account.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> New Account
                    </a>
                </div>
            </div>

            <!-- Search Filters -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-search"></i> Find Accounts</div>
                <div class="card-body">
                    <form id="accountSearchForm" class="row gx-3 gy-2 align-items-center">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="search_account_name" placeholder="Search by Account Name...">
                        </div>
                        <div class="col-md-6">
                            <select id="search_account_type" class="form-select">
                                <option value="">All Account Types</option>
                                <option value="Asset">Asset</option>
                                <option value="Liability">Liability</option>
                                <option value="Equity">Equity</option>
                                <option value="Revenue">Revenue</option>
                                <option value="Expense">Expense</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Accounts List Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Account Name</th>
                                    <th>Account Type</th>
                                    <th>Normal Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="accountListTableBody">
                                <?php if (empty($initial_accounts)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No accounts found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($initial_accounts as $account): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($account['account_name']) ?></td>
                                            <td><?= htmlspecialchars($account['account_type']) ?></td>
                                            <td><span class="text-capitalize"><?= htmlspecialchars($account['normal_balance']) ?></span></td>
                                            <td>
                                                <span class="badge bg-<?= $account['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $account['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="/modules/accounts/entry_account.php?account_id=<?= $account['account_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>