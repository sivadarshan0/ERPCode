<?php
// File: /scripts/sync_accounts.php
// A one-time script to retroactively post existing sales and purchases to the accounting ledger.

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
$inventory_posted = 0;
$payments_posted = 0;

// -----------------------------------------
// ----- 1. Sync Paid Sales Orders -----
// -----------------------------------------
echo "Processing Paid Sales Orders...\n";
$paid_orders_res = $db->query("
    SELECT o.order_id 
    FROM orders o
    LEFT JOIN acc_transactions t ON o.order_id = t.source_id AND t.source_type = 'sales_order'
    WHERE o.payment_status = 'Received'
    AND t.transaction_id IS NULL
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
// ----- 2. Sync Received Purchase Orders (Inventory) -----
// -----------------------------------------
echo "Processing Received Purchase Orders (Inventory)...\n";
$received_pos_res = $db->query("
    SELECT p.purchase_order_id
    FROM purchase_orders p
    LEFT JOIN acc_transactions t ON p.purchase_order_id = t.source_id 
        AND t.source_type = 'purchase_order' 
        AND t.description LIKE 'Inventory received from PO%'
    WHERE p.status IN ('Received', 'Paid')
    AND t.transaction_id IS NULL
");
$received_pos = $received_pos_res->fetch_all(MYSQLI_ASSOC);

if (empty($received_pos)) {
    echo " -> No new received purchase orders to sync.\n";
} else {
    foreach ($received_pos as $po) {
        $po_id = $po['purchase_order_id'];
        try {
            $db->begin_transaction();
            record_inventory_purchase($po_id, $db);
            $db->commit();
            echo " -> Successfully posted inventory transaction for PO #$po_id.\n";
            $inventory_posted++;
        } catch (Exception $e) {
            $db->rollback();
            echo " -> ERROR processing PO Inventory #$po_id: " . $e->getMessage() . "\n";
        }
    }
}
echo "\n";


// -----------------------------------------
// ----- 3. Sync Paid Purchase Orders (Payment) -----
// -----------------------------------------
echo "Processing Paid Purchase Orders (Payment)...\n";
$paid_pos_res = $db->query("
    SELECT p.purchase_order_id
    FROM purchase_orders p
    LEFT JOIN acc_transactions t ON p.purchase_order_id = t.source_id 
        AND t.source_type = 'purchase_order' 
        AND t.description LIKE 'Payment for PO%'
    WHERE p.status = 'Paid'
    AND t.transaction_id IS NULL
");
$paid_pos = $paid_pos_res->fetch_all(MYSQLI_ASSOC);

if (empty($paid_pos)) {
    echo " -> No new paid purchase orders to sync.\n";
} else {
    foreach ($paid_pos as $po) {
        $po_id = $po['purchase_order_id'];
        try {
            $db->begin_transaction();
            record_purchase_payment($po_id, $db);
            $db->commit();
            echo " -> Successfully posted payment transaction for PO #$po_id.\n";
            $payments_posted++;
        } catch (Exception $e) {
            $db->rollback();
            echo " -> ERROR processing PO Payment #$po_id: " . $e->getMessage() . "\n";
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
echo "Sales Transactions Posted: $sales_posted\n";
echo "Inventory Transactions Posted: $inventory_posted\n";
echo "Purchase Payment Transactions Posted: $payments_posted\n\n";

?>