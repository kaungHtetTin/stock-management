<?php
/**
 * Stock In/Out approval business logic
 */

require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';
require_once APP_PATH . '/models/Item.php';
require_once APP_PATH . '/services/BalanceService.php';

class ApprovalService
{
    public static function approveStockIn(int $id, int $adminId): array
    {
        $record = StockIn::find($id);
        if (!$record) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }
        if ($record['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending records can be approved.'];
        }

        $ids = StockIn::resolvePendingBatchIds($id);
        if (empty($ids)) {
            return ['ok' => false, 'message' => 'Only pending records can be approved.'];
        }

        if (!StockIn::approveBatch($ids, $adminId)) {
            return ['ok' => false, 'message' => 'Unable to approve record.'];
        }

        $count = count($ids);
        $message = $count > 1
            ? "Stock In batch approved successfully ({$count} items)."
            : 'Stock In approved successfully.';

        return ['ok' => true, 'message' => $message];
    }

    public static function rejectStockIn(int $id, int $adminId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'message' => 'Rejection reason is required.'];
        }

        $record = StockIn::find($id);
        if (!$record) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }
        if ($record['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending records can be rejected.'];
        }

        $ids = StockIn::resolvePendingBatchIds($id);
        if (empty($ids)) {
            return ['ok' => false, 'message' => 'Only pending records can be rejected.'];
        }

        if (!StockIn::rejectBatch($ids, $reason)) {
            return ['ok' => false, 'message' => 'Unable to reject record.'];
        }

        $count = count($ids);
        $message = $count > 1
            ? "Stock In batch rejected ({$count} items)."
            : 'Stock In request rejected.';

        return ['ok' => true, 'message' => $message];
    }

    public static function approveStockOut(int $id, int $adminId): array
    {
        $record = StockOut::find($id);
        if (!$record) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }
        if ($record['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending records can be approved.'];
        }

        $ids = StockOut::resolvePendingBatchIds($id);
        if (empty($ids)) {
            return ['ok' => false, 'message' => 'Only pending records can be approved.'];
        }

        $qtyByItem = [];
        $unitByItem = [];

        foreach ($ids as $recordId) {
            $row = StockOut::find($recordId);
            if (!$row) {
                return ['ok' => false, 'message' => 'Record not found.'];
            }

            $itemId = (int) $row['item_id'];
            $qtyByItem[$itemId] = ($qtyByItem[$itemId] ?? 0) + (float) $row['qty'];
            $unitByItem[$itemId] = $row['unit'];
        }

        foreach ($qtyByItem as $itemId => $totalQty) {
            $balance = BalanceService::getItemBalance($itemId);
            if ($totalQty > $balance) {
                $item = Item::find($itemId);
                $label = $item ? $item['item_name'] : 'Item';

                return [
                    'ok' => false,
                    'message' => sprintf(
                        'Insufficient stock for %s. Available: %s %s, requested: %s %s.',
                        $label,
                        format_number($balance, 2),
                        $unitByItem[$itemId] ?? '',
                        format_number($totalQty, 2),
                        $unitByItem[$itemId] ?? ''
                    ),
                ];
            }
        }

        if (!StockOut::approveBatch($ids, $adminId)) {
            return ['ok' => false, 'message' => 'Unable to approve record.'];
        }

        $count = count($ids);
        $message = $count > 1
            ? "Stock Out batch approved successfully ({$count} items)."
            : 'Stock Out approved successfully.';

        return ['ok' => true, 'message' => $message];
    }

    public static function rejectStockOut(int $id, int $adminId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'message' => 'Rejection reason is required.'];
        }

        $record = StockOut::find($id);
        if (!$record) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }
        if ($record['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending records can be rejected.'];
        }

        $ids = StockOut::resolvePendingBatchIds($id);
        if (empty($ids)) {
            return ['ok' => false, 'message' => 'Only pending records can be rejected.'];
        }

        if (!StockOut::rejectBatch($ids, $reason)) {
            return ['ok' => false, 'message' => 'Unable to reject record.'];
        }

        $count = count($ids);
        $message = $count > 1
            ? "Stock Out batch rejected ({$count} items)."
            : 'Stock Out request rejected.';

        return ['ok' => true, 'message' => $message];
    }
}
