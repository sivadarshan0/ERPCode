<?php
// File: /scripts/sync_accounts.php
// A one-time script to retroactively post PAID SALES ORDERS to the accounting ledger.
// NOTE: Purchase Order syncing has been removed as it is now a manual process.

// Set up the environment
define('_IN_APP_', true);
chdir(dirname(__DIR__)); // Set the working directory to the project root

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_acc.php';

// Mock a session for the 'created_by_name' fields
$_SESSION['username'] = 'SystemSync';
$_SESSION['user_id'] = 0;

echo "=================================================\n";
echo "Starting Retroactive Accounting Sync...\n";
echo "=================================================\n\n";

$db = db();
$sales_posted = 0;

// -----------------------------------------
// ----- 1. Sync Paid Sales Orders -----
// -----------------------------------------
echo "Processing Paid Sales Orders...\n";
$paid_orders_res = $db->query("
    SELECT o.order_id 
    FROM orders o
    WHERE 
        o.payment_status = 'Received'
    AND 
        o.order_id NOT IN (
            SELECT DISTINCT source_id FROM acc_transactions 
            WHERE source_type = 'sales_order' 
            AND description LIKE 'Cost of goods sold for Order #%'
            AND source_id IS NOT NULL
        )
");
$paid_orders = $paid_orders_res->fetch_all(MYSQLI_ASSOC);

if (empty($paid_orders)) {
    echo " -> No new paid sales orders to sync.\n";
} else {
    foreach ($paid_orders as $order) {
        $order_id = $order['order_id'];
        try {
            $db->begin_transaction();
            record_sales_transaction($order_id, $db);
            $db->commit();
            echo " -> Successfully posted transaction for Sales Order #$order_id.\n";
            $sales_posted++;
        } catch (Exception $e) {
            $db->rollback();
            echo " -> ERROR processing Sales Order #$order_id: " . $e->getMessage() . "\n";
        }
    }
}
echo "\n";


// -----------------------------------------
// ----- Summary Report -----
// -----------------------------------------
echo "=================================================\n";
echo "Sync Complete!\n";
echo "=================================================\n";
echo "Sales Transactions Posted: $sales_posted\n\n";

?>