<?php
// File: scripts/fix_order_totals.php
// Purpose: Recalculate total_amount for all orders based on order_items and update the database.

define('_IN_APP_', true);
require_once __DIR__ . '/../config/db.php';

// Ensure CLI run or safe execution
if (php_sapi_name() !== 'cli' && !isset($_GET['run'])) {
    die("This script should be run from CLI or with ?run=1");
}

$db = db();
if (!$db) {
    die("Database connection failed.\n");
}

echo "Starting Order Total Recalculation...\n";
echo "--------------------------------------\n";

// 1. Fetch all orders
$stmt = $db->query("SELECT order_id FROM orders ORDER BY order_id");
$orders = $stmt->fetch_all(MYSQLI_ASSOC);

$updated_count = 0;
$error_count = 0;

foreach ($orders as $order) {
    $order_id = $order['order_id'];

    // 2. Calculate correct total from items
    $stmt_items = $db->prepare("SELECT SUM(price * quantity) as correct_total FROM order_items WHERE order_id = ?");
    $stmt_items->bind_param("s", $order_id);
    $stmt_items->execute();
    $result = $stmt_items->get_result()->fetch_assoc();
    $correct_total = $result['correct_total'] ?? 0.00;

    // 3. Update the order record
    // Note: We deliberately exclude 'other_expenses' from this total as per the new business logic verified in previous tasks.
    $stmt_update = $db->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
    $stmt_update->bind_param("ds", $correct_total, $order_id);
    
    if ($stmt_update->execute()) {
        // Optional: Only count if it actually changed? MySQL returns rows matched, not changed, for simple update unless specific flags used.
        // We'll just count successes.
        $updated_count++;
        // echo "Updated $order_id to " . number_format($correct_total, 2) . "\n";
    } else {
        echo "ERROR updating $order_id: " . $db->error . "\n";
        $error_count++;
    }
}

echo "--------------------------------------\n";
echo "Done.\n";
echo "Total Orders Processed: " . count($orders) . "\n";
echo "Updated Successfully: $updated_count\n";
echo "Errors: $error_count\n";
