<?php
// FINAL SCRIPT for retroactive sync.

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

// Find all PAID orders that are missing EITHER a revenue OR a cogs entry.
$sql = "
    SELECT o.order_id 
    FROM orders o
    WHERE 
        o.payment_status = 'Received'
    AND NOT EXISTS (
        SELECT 1 FROM acc_transactions t 
        WHERE t.source_id = o.order_id 
        AND t.source_type = 'sales_order'
    )
";

$orders_to_process_res = $db->query($sql);
$orders_to_process = $orders_to_process_res->fetch_all(MYSQLI_ASSOC);

if (empty($orders_to_process)) {
    echo " -> No new paid sales orders to sync.\n";
} else {
    echo "Found " . count($orders_to_process) . " paid orders to process...\n";
    foreach ($orders_to_process as $order) {
        $order_id = $order['order_id'];
        try {
            // Because the records don't exist, we can use a single transaction
            $db->begin_transaction();
            // This will create BOTH the revenue and COGS entries
            record_sales_transaction($order_id, $db);
            $db->commit();
            echo " -> Successfully posted ALL transactions for Order #$order_id.\n";
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
echo "Total Paid Orders Processed: $orders_processed\n\n";

?>