<?php
// File: /scripts/sync_accounts.php
// FINAL version. Finds all paid orders and lets the smart helper function handle the logic.

define('_IN_APP_', true);
chdir(dirname(__DIR__));

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_acc.php';

$_SESSION['username'] = 'SystemSync';
$_SESSION['user_id'] = 0;

echo "=================================================\n";
echo "Starting Full Accounting Sync for Sales...\n";
echo "=================================================\n\n";

$db = db();
$orders_processed = 0;

// Get ALL orders that have been marked as paid.
$paid_orders_res = $db->query("SELECT order_id FROM orders WHERE payment_status = 'Received'");
$paid_orders = $paid_orders_res->fetch_all(MYSQLI_ASSOC);

if (empty($paid_orders)) {
    echo " -> No paid sales orders found to process.\n";
} else {
    echo "Found " . count($paid_orders) . " paid orders to check...\n";
    foreach ($paid_orders as $order) {
        $order_id = $order['order_id'];
        // The record_sales_transaction function is now smart enough to be idempotent.
        // It will only create the entries that are missing for this order_id.
        try {
            $db->begin_transaction();
            record_sales_transaction($order_id, $db);
            $db->commit();
            echo " -> Checked/Updated Order #$order_id.\n";
            $orders_processed++;
        } catch (Exception $e) {
            $db->rollback();
            echo " -> ERROR processing Order #$order_id: " . $e->getMessage() . "\n";
        }
    }
}
echo "\n=================================================\n";
echo "Sync Complete!\n";
echo "=================================================\n";
echo "Total Paid Orders Checked/Processed: $orders_processed\n\n";

?>