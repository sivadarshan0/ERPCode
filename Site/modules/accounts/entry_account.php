<?php
// File: /modules/accounts/entry_account.php
// Page to create and manage accounts in the Chart of Accounts.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_acc.php'; // Include the accounts functions

require_login();

$message = '';
$message_type = '';
$is_edit = false;
$account = null;
$account_id = null;

// --- Check for Edit Mode ---
if (isset($_GET['account_id'])) {
    $account_id = filter_var($_GET['account_id'], FILTER_VALIDATE_INT);
    if ($account_id) {
        $account = get_account($account_id);
        if ($account) {
            $is_edit = true;
        } else {
            $_SESSION['error_message'] = "Account not found.";
            header("Location: /modules/accounts/list_accounts.php");
            exit;
        }
    }
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $details = [
            'account_name'   => trim($_POST['account_name'] ?? ''),
            'account_type'   => $_POST['account_type'] ?? '',
            'normal_balance' => $_POST['normal_balance'] ?? '',
            'description'    => trim($_POST['description'] ?? ''),
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($is_edit) {
            // --- Update Logic ---
            update_account($account_id, $details);
            $_SESSION['success_message'] = "✅ Account '" . htmlspecialchars($details['account_name']) . "' successfully updated.";
        } else {
            // --- Create Logic ---
            $new_account_id = add_account($details);
            $_SESSION['success_message'] = "✅ Account '" . htmlspecialchars($details['account_name']) . "' successfully created.";
            // Redirect to the new edit page
            header("Location: /modules/accounts/entry_account.php?account_id=" . $new_account_id);
            exit;
        }

        // Refresh data after update
        $account = get_account($account_id);
        $message = $_SESSION['success_message'];
        $message_type = 'success';
        unset($_SESSION['success_message']);

    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        // Repopulate form with submitted data on error
        $account = array_merge($account ?? [], $_POST);
    }
}

// Handle session messages on redirect
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'danger';
    unset($_SESSION['error_message']);
}


require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2><?= $is_edit ? 'Manage Account' : 'Create New Account' ?></h2>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate id="accountForm">
                <div class="card">
                    <div class="card-header">Account Details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="account_name" class="form-label">Account Name *</label>
                                <input type="text" class="form-control" id="account_name" name="account_name" value="<?= htmlspecialchars($account['account_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="account_type" class="form-label">Account Type *</label>
                                <select class="form-select" id="account_type" name="account_type" required>
                                    <option value="">Choose...</option>
                                    <option value="Asset" <?= ($account['account_type'] ?? '') == 'Asset' ? 'selected' : '' ?>>Asset</option>
                                    <option value="Liability" <?= ($account['account_type'] ?? '') == 'Liability' ? 'selected' : '' ?>>Liability</option>
                                    <option value="Equity" <?= ($account['account_type'] ?? '') == 'Equity' ? 'selected' : '' ?>>Equity</option>
                                    <option value="Revenue" <?= ($account['account_type'] ?? '') == 'Revenue' ? 'selected' : '' ?>>Revenue</option>
                                    <option value="Expense" <?= ($account['account_type'] ?? '') == 'Expense' ? 'selected' : '' ?>>Expense</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="normal_balance" class="form-label">Normal Balance *</label>
                                <select class="form-select" id="normal_balance" name="normal_balance" required>
                                    <option value="">Choose...</option>
                                    <option value="debit" <?= ($account['normal_balance'] ?? '') == 'debit' ? 'selected' : '' ?>>Debit</option>
                                    <option value="credit" <?= ($account['normal_balance'] ?? '') == 'credit' ? 'selected' : '' ?>>Credit</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($account['description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= !isset($account['is_active']) || $account['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        Account is Active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-<?= $is_edit ? 'floppy' : 'save' ?>"></i> <?= $is_edit ? 'Update Account' : 'Save Account' ?>
                    </button>
                    <a href="/modules/accounts/list_accounts.php" class="btn btn-secondary">Back to List</a>
                </div>
            </form>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>