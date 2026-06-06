<?php
/**
 * Stock In/Out approval business logic
 */

require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';
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

        if (!StockIn::approve($id, $adminId)) {
            return ['ok' => false, 'message' => 'Unable to approve record.'];
        }

        return ['ok' => true, 'message' => 'Stock In approved successfully.'];
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

        if (!StockIn::reject($id, $reason)) {
            return ['ok' => false, 'message' => 'Unable to reject record.'];
        }

        return ['ok' => true, 'message' => 'Stock In request rejected.'];
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

        $balance = BalanceService::getItemBalance((int) $record['item_id']);
        $qty = (float) $record['qty'];
        if ($qty > $balance) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'Insufficient stock. Available: %s %s, requested: %s %s.',
                    format_number($balance, 2),
                    $record['unit'],
                    format_number($qty, 2),
                    $record['unit']
                ),
            ];
        }

        if (!StockOut::approve($id, $adminId)) {
            return ['ok' => false, 'message' => 'Unable to approve record.'];
        }

        return ['ok' => true, 'message' => 'Stock Out approved successfully.'];
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

        if (!StockOut::reject($id, $reason)) {
            return ['ok' => false, 'message' => 'Unable to reject record.'];
        }

        return ['ok' => true, 'message' => 'Stock Out request rejected.'];
    }
}
