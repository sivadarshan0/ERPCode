<?php
// File: /modules/accounts/report_account_ledger.php
// FINAL version with clickable links in the Source column.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_acc.php';

require_login();

// --- Get Data for Filters ---
$db = db();
$all_accounts_query = $db->query("SELECT account_id, account_name FROM acc_chartofaccounts WHERE is_active = 1 ORDER BY account_name ASC");
$all_accounts = $all_accounts_query->fetch_all(MYSQLI_ASSOC);

// --- Determine Filter Values ---
$account_id = $_GET['account_id'] ?? ($all_accounts[0]['account_id'] ?? 0); 
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$ledger_data = [];
$account_name = 'N/A';
$error_message = '';

if ($account_id) {
    try {
        $ledger_data = get_account_ledger($account_id, $start_date, $end_date);
        foreach($all_accounts as $acc) {
            if ($acc['account_id'] == $account_id) {
                $account_name = $acc['account_name'];
                break;
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
} else {
    $error_message = "No accounts available. Please add an account in the Chart of Accounts.";
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Account Ledger</h2>
        <a href="/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
        </a>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-filter"></i> Report Options</div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="account_id" class="form-label">Account:</label>
                    <select class="form-select" id="account_id" name="account_id">
                        <?php foreach ($all_accounts as $account): ?>
                            <option value="<?= $account['account_id'] ?>" <?= ($account['account_id'] == $account_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($account['account_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">From Date:</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">To Date:</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary w-100">Run Report</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php else: ?>
        <div class="card">
            <div class="card-header text-center">
                <h4>Account Ledger: <?= htmlspecialchars($account_name) ?></h4>
                <p class="mb-0">For the period from <?= htmlspecialchars(date("d-m-Y", strtotime($start_date))) ?> to <?= htmlspecialchars(date("d-m-Y", strtotime($end_date))) ?></p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Source</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Opening Balance Row -->
                            <tr class="fw-bold">
                                <td colspan="5">Opening Balance as of <?= htmlspecialchars(date("d-m-Y", strtotime($start_date))) ?></td>
                                <td class="text-end"><?= number_format($ledger_data['opening_balance'], 2) ?></td>
                            </tr>

                            <!-- Transaction Rows -->
                            <?php if (empty($ledger_data['transactions'])): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No transactions found in this period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ledger_data['transactions'] as $txn): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date("d-m-Y", strtotime($txn['transaction_date']))) ?></td>
                                    <td><?= htmlspecialchars($txn['description']) ?></td>
                                    <td>
                                        <?php 
                                        // THIS IS THE NEW LOGIC BLOCK
                                        $url = get_source_document_url($txn['source_type'], $txn['source_id']);
                                        if ($url): 
                                        ?>
                                            <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                                                <?= htmlspecialchars($txn['source_id']) ?>
                                            </a>
                                        <?php else: ?>
                                            Manual Entry
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-success"><?= $txn['debit_amount'] ? number_format($txn['debit_amount'], 2) : '' ?></td>
                                    <td class="text-end text-danger"><?= $txn['credit_amount'] ? number_format($txn['credit_amount'], 2) : '' ?></td>
                                    <td class="text-end fw-bold"><?= number_format($txn['running_balance'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Closing Balance Row -->
                            <tr class="fw-bold table-light">
                                <td colspan="5">Closing Balance as of <?= htmlspecialchars(date("d-m-Y", strtotime($end_date))) ?></td>
                                <td class="text-end"><?= number_format($ledger_data['closing_balance'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>