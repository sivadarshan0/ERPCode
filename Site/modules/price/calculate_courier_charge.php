<?php
// File: /modules/price/calculate_courier_charge.php
// FINAL version with "Fixed Charge" display row.

session_start();
define('_IN_APP_', true);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// --- AJAX ENDPOINT ---
if (isset($_GET['action']) && $_GET['action'] === 'calculate') {
    header('Content-Type: application/json');
    try {
        $weight = $_GET['weight'] ?? 0;
        $value = $_GET['value'] ?? 0;
        $result = calculate_courier_charge($weight, $value);
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

require_login();
require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>SL Post Courier Service Calculator</h2>
        <a href="/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
        </a>
    </div>

    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-box-seam"></i> Enter Parcel Details
                </div>
                <div class="card-body">
                    <form id="courierCalcForm">
                        <div class="mb-3">
                            <label for="weight" class="form-label">Total Weight (in grams) *</label>
                            <input type="number" class="form-control" id="weight" name="weight" placeholder="e.g., 1500" required>
                        </div>
                        <div class="mb-3">
                            <label for="value" class="form-label">Total Item Value (in Rs.) *</label>
                            <input type="number" class="form-control" id="value" name="value" placeholder="e.g., 3500" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-calculator"></i> Calculate Charge
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Section -->
            <div id="resultsCard" class="card mt-4 d-none">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-check-circle-fill"></i> Calculation Result
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Weight-Based Charge:
                            <span class="badge bg-secondary rounded-pill fs-6" id="weightCharge">Rs. 0.00</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Value-Based Charge:
                            <span class="badge bg-secondary rounded-pill fs-6" id="valueCharge">Rs. 0.00</span>
                        </li>
                        <!-- THIS IS THE NEW ROW FOR THE FIXED CHARGE -->
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Fixed Charge:
                            <span class="badge bg-secondary rounded-pill fs-6" id="fixedCharge">Rs. 0.00</span>
                        </li>
                        <!-- END OF NEW ROW -->
                        <li class="list-group-item d-flex justify-content-between align-items-center fw-bold fs-5">
                            Total Courier Charge:
                            <span class="badge bg-primary rounded-pill fs-5" id="totalCharge">Rs. 0.00</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>