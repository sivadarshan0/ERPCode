SET @group_id_to_delete = (SELECT transaction_group_id FROM acc_transactions WHERE source_type = 'purchase_order' AND source_id = 'PUR000007' LIMIT 1);

DELETE FROM acc_transactions WHERE transaction_group_id = @group_id_to_delete;

DELETE FROM purchase_order_status_history WHERE purchase_order_id = 'PUR000007' AND status = 'Paid';

UPDATE purchase_orders SET status = 'Ordered' WHERE purchase_order_id = 'PUR000007';

SELECT 'Purchase Order has been reverted to Ordered and the incorrect journal entry has been deleted.' AS status;