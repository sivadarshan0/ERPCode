<?php
// File: /modules/accounts/entry_transaction.php
// Page to create manual double-entry journal transactions.

session_start();
$custom_js = 'app_acc'; // Load our dedicated accounts javascript
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_acc.php';

require_login();

$message = '';
$message_type = '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $details = [
            'transaction_date'  => $_POST['transaction_date'] ?? '',
            'description'       => $_POST['description'] ?? '',
            'debit_account_id'  => $_POST['debit_account_id'] ?? '',
            'credit_account_id' => $_POST['credit_account_id'] ?? '',
            'amount'            => $_POST['amount'] ?? 0,
        ];
        
        $new_txn_id = process_manual_journal_entry($details);
        
        // Use session message for feedback after redirect
        $_SESSION['success_message'] = "✅ Journal Entry #$new_txn_id successfully created.";
        header("Location: entry_transaction.php");
        exit;

    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle success message from session after redirect
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

// Fetch all active accounts to populate the dropdowns
$all_accounts = search_chart_of_accounts(['is_active' => 1]);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>New Journal Entry</h2>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate id="journalEntryForm">
                <div class="card">
                    <div class="card-header">Transaction Details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="transaction_date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label for="description" class="form-label">Description *</label>
                                <input type="text" class="form-control" id="description" name="description" placeholder="e.g., Paid monthly office rent" required>
                            </div>

                            <div class="col-md-6">
                                <label for="debit_account_id" class="form-label">Account to Debit (Increase ↑) *</label>
                                <select class="form-select" id="debit_account_id" name="debit_account_id" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($all_accounts as $account): ?>
                                        <option value="<?= $account['account_id'] ?>">
                                            <?= htmlspecialchars($account['account_name']) ?> (<?= htmlspecialchars($account['account_type']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Which account is receiving value? (e.g., Expense account, Asset account)</div>
                            </div>

                             <div class="col-md-6">
                                <label for="credit_account_id" class="form-label">Account to Credit (Decrease ↓) *</label>
                                <select class="form-select" id="credit_account_id" name="credit_account_id" required>
                                     <option value="">Choose...</option>
                                    <?php foreach ($all_accounts as $account): ?>
                                        <option value="<?= $account['account_id'] ?>">
                                            <?= htmlspecialchars($account['account_name']) ?> (<?= htmlspecialchars($account['account_type']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Which account is giving value? (e.g., Cash, Bank, Equity account)</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="amount" class="form-label">Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" min="0.01" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-save"></i> Save Transaction
                    </button>
                    <!-- We will create this list page in a future step -->
                    <a href="/modules/accounts/list_transactions.php" class="btn btn-secondary">Transaction List</a>
                </div>
            </form>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>