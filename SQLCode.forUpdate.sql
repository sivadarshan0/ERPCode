-- This script will revert the status and delete the financial entries for ORD000009.

-- Start a transaction to ensure both steps happen together.
START TRANSACTION;

-- 1. Delete the incorrect accounting journal entries associated with this specific order.
DELETE FROM acc_transactions 
WHERE source_type = 'sales_order' AND source_id = 'ORD000009';

-- 2. Update the main order record to reset its statuses.
-- We are setting the main status to 'With Courier' and the payment status to 'Pending'.
UPDATE orders
SET 
    status = 'With Courier',
    payment_status = 'Pending'
WHERE 
    order_id = 'ORD000009';

-- If both commands succeeded, commit the changes.
COMMIT;

SELECT 'Order ORD000009 has been reverted to "With Courier" and "Pending" payment. Financial entries have been deleted. You may now re-test.' AS Status;