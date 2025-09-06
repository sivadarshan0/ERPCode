<?php
// File: /modules/purchase/list_grns.php
// FINAL version with Status column and filter.

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('_IN_APP_', true);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// --- AJAX Endpoint for Live Search ---
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    try {
        $filters = [
            'grn_id'    => $_GET['grn_id'] ?? null,
            'status'    => $_GET['status'] ?? null, // ADDED: Status filter
            'date_from' => $_GET['date_from'] ?? null,
            'date_to'   => $_GET['date_to'] ?? null,
        ];
        $grns = search_grns($filters);
        echo json_encode($grns);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

require_login();

// Initial page load - get all recent GRNs
$initial_grns = search_grns();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">GRN List</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/index.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
                    </a>
                    <a href="/modules/purchase/entry_grn.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> New GRN
                    </a>
                </div>
            </div>

            <!-- Search Filters -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-search"></i> Find GRNs</div>
                <div class="card-body">
                    <form id="grnSearchForm" class="row gx-3 gy-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="search_grn_id" placeholder="Search by GRN ID...">
                        </div>
                        <!-- NEW: Status Filter Dropdown -->
                        <div class="col-md-4">
                            <select id="search_status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="Posted">Posted</option>
                                <option value="Canceled">Canceled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="search_date_range" placeholder="Select Date Range">
                        </div>
                    </form>
                </div>
            </div>

            <!-- GRN List Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>GRN ID</th>
                                    <th>Date</th>
                                    <th>Remarks</th>
                                    <th>Status</th> <!-- NEW: Column Header -->
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="grnListTableBody">
                                <?php if (empty($initial_grns)): ?>
                                    <tr><td colspan="6" class="text-center text-muted">No GRNs found.</td></tr> <!-- Colspan updated -->
                                <?php else: ?>
                                    <?php foreach ($initial_grns as $grn): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($grn['grn_id']) ?></td>
                                            <td><?= htmlspecialchars(date("d-m-Y", strtotime($grn['grn_date']))) ?></td>
                                            <td><?= htmlspecialchars($grn['remarks']) ?></td>
                                            <!-- NEW: Status Badge Display -->
                                            <td>
                                                <span class="badge bg-<?= $grn['status'] == 'Posted' ? 'success' : 'danger' ?>">
                                                    <?= htmlspecialchars($grn['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($grn['created_by_name']) ?></td>
                                            <td>
                                                <a href="/modules/purchase/entry_grn.php?grn_id=<?= htmlspecialchars($grn['grn_id']) ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
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