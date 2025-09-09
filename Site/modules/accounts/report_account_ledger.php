<?php
// File: /modules/accounts/report_account_ledger.php
// FINAL version with corrected filter logic for Financial Year priority.

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

$fy_query = $db->query("SELECT DISTINCT financial_year FROM acc_transactions ORDER BY financial_year DESC");
$financial_years = $fy_query->fetch_all(MYSQLI_ASSOC);

// --- Determine Filter Values ---
$account_id = $_GET['account_id'] ?? ($all_accounts[0]['account_id'] ?? 0); 
$selected_fy = $_GET['financial_year'] ?? '';

// --- CORRECTED: Smart Date Logic with Financial Year as Priority ---
if (!empty($selected_fy) && $selected_fy !== 'custom') {
    // Priority 1: A specific financial year was chosen. Calculate the dates.
    $startYear = substr($selected_fy, 0, 4);
    $endYear = intval($startYear) + 1;
    $start_date = $startYear . '-04-01';
    $end_date = $endYear . '-03-31';
} elseif (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    // Priority 2: Custom dates were explicitly provided.
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $selected_fy = 'custom'; // Ensure dropdown shows 'Custom Range'
} else {
    // Priority 3: Default to the current financial year on first load.
    $current_month = date('n');
    $current_year = date('Y');
    if ($current_month >= 4) {
        $start_date = $current_year . '-04-01';
        $end_date = ($current_year + 1) . '-03-31';
        $selected_fy = $current_year . '-' . substr($current_year + 1, 2, 2);
    } else {
        $start_date = ($current_year - 1) . '-04-01';
        $end_date = $current_year . '-03-31';
        $selected_fy = ($current_year - 1) . '-' . substr($current_year, 2, 2);
    }
}


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
        <div class="btn-toolbar">
            <a href="/modules/accounts/report_trial_balance.php" class="btn btn-outline-secondary me-2">
                <i class="bi bi-file-earmark-spreadsheet"></i> Trial Balance
            </a>
            <a href="/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-filter"></i> Report Options</div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center" id="ledgerReportForm">
                <div class="col-md-3">
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
                    <label for="financial_year" class="form-label">Financial Year:</label>
                    <select class="form-select" id="financial_year" name="financial_year">
                        <option value="custom" <?= $selected_fy === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        <?php foreach ($financial_years as $fy): ?>
                            <option value="<?= htmlspecialchars($fy['financial_year']) ?>" <?= ($fy['financial_year'] == $selected_fy) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fy['financial_year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">From Date:</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-2">
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
                            <tr class="fw-bold">
                                <td colspan="5">Opening Balance as of <?= htmlspecialchars(date("d-m-Y", strtotime($start_date))) ?></td>
                                <td class="text-end"><?= number_format($ledger_data['opening_balance'], 2) ?></td>
                            </tr>

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