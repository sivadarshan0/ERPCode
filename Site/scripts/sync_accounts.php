<?php
// File: /scripts/sync_accounts.php
// A simple diagnostic script to find the point of failure.

define('_IN_APP_', true);
chdir(dirname(__DIR__));

require_once __DIR__ . '/../config/db.php';

echo "--- STARTING DIAGNOSTIC ---\n";

$db = db();
if (!$db) {
    die("FATAL: Could not connect to the database.\n");
}
echo "Step 1: Database connection successful.\n";

$sql = "SELECT order_id, payment_status FROM orders WHERE payment_status = 'Received'";
echo "Step 2: Running SQL query: " . $sql . "\n";

$result = $db->query($sql);

if (!$result) {
    die("FATAL: The query failed. Error: " . $db->error . "\n");
}
echo "Step 3: Query executed successfully.\n";

$orders = $result->fetch_all(MYSQLI_ASSOC);

echo "Step 4: Fetching all rows.\n";
echo "Number of rows found: " . count($orders) . "\n\n";

echo "--- RAW DATA --- \n";
print_r($orders);
echo "\n--- END OF DIAGNOSTIC ---\n";

?>