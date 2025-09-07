<?php
// File: /modules/accounts/report_trial_balance.php
// FINAL version with links to the Account Ledger report.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_acc.php';

require_login();

// Determine the date for the report. Default to today if not specified.
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$data = [];
$error_message = '';

try {
    $data = get_trial_balance($end_date);
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Separate accounts into debit and credit balances for two-column display
$debit_accounts = [];
$credit_accounts = [];

if (!empty($data['accounts'])) {
    foreach ($data['accounts'] as $account) {
        if ($account['normal_balance'] === 'debit') {
            if ($account['balance'] != 0) { // Only show accounts with a balance
                $debit_accounts[] = $account;
            }
        } else { // credit
            if ($account['balance'] != 0) { // Only show accounts with a balance
                 $credit_accounts[] = $account;
            }
        }
    }
}


require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Trial Balance</h2>
        <a href="/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
        </a>
    </div>

    <!-- Date Filter Form -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-filter"></i> Report Options</div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="end_date" class="form-label">Balance as of Date:</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-4 align-self-end">
                    <button type="submit" class="btn btn-primary">Run Report</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php else: ?>
        <div class="card">
            <div class="card-header text-center">
                <h4>Trial Balance</h4>
                <p class="mb-0">As of <?= htmlspecialchars(date("F j, Y", strtotime($end_date))) ?></p>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Debit Column -->
                    <div class="col-md-6">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th class="text-end">Debit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($debit_accounts as $account): ?>
                                <tr>
                                    <td>
                                        <!-- THIS IS THE NEW LINK -->
                                        <a href="report_account_ledger.php?account_id=<?= $account['account_id'] ?>&end_date=<?= htmlspecialchars($end_date) ?>">
                                            <?= htmlspecialchars($account['account_name']) ?>
                                        </a>
                                    </td>
                                    <td class="text-end"><?= number_format($account['balance'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Credit Column -->
                    <div class="col-md-6">
                        <table class="table table-sm table-hover">
                             <thead>
                                <tr>
                                    <th>Account</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($credit_accounts as $account): ?>
                                <tr>
                                    <td>
                                        <!-- THIS IS THE NEW LINK -->
                                        <a href="report_account_ledger.php?account_id=<?= $account['account_id'] ?>&end_date=<?= htmlspecialchars($end_date) ?>">
                                            <?= htmlspecialchars($account['account_name']) ?>
                                        </a>
                                    </td>
                                    <td class="text-end"><?= number_format($account['balance'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <!-- Debit Total -->
                    <div class="col-md-6 d-flex justify-content-between border-top pt-2">
                        <strong class="fs-5">Total Debits:</strong>
                        <strong class="fs-5"><?= number_format($data['total_debits'], 2) ?></strong>
                    </div>

                    <!-- Credit Total -->
                    <div class="col-md-6 d-flex justify-content-between border-top pt-2">
                        <strong class="fs-5">Total Credits:</strong>
                        <strong class="fs-5"><?= number_format($data['total_credits'], 2) ?></strong>
                    </div>
                </div>

                <!-- Balance Check -->
                <div class="text-center mt-3">
                    <?php
                    $is_balanced = abs($data['total_debits'] - $data['total_credits']) < 0.001;
                    ?>
                    <span class="badge fs-6 <?= $is_balanced ? 'bg-success' : 'bg-danger' ?>">
                        <?= $is_balanced ? '<i class="bi bi-check-circle-fill"></i> In Balance' : '<i class="bi bi-x-octagon-fill"></i> Out of Balance' ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>