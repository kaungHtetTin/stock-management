<?php
/**
 * Stock Out transaction model
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/models/Item.php';
require_once APP_PATH . '/models/Customer.php';

class StockOut
{
    public const STATUSES = ['pending', 'approved', 'rejected'];
    public const REASONS = ['Sales', 'Sample', 'Sale & Marketing', 'Other'];

    public static function all(array $filters = []): array
    {
        $db = Database::connect();
        $sql = 'SELECT so.*, i.item_no, i.item_name, c.customer_name, u.display_name AS created_by_name,
                (SELECT COUNT(*) FROM stock_out so2
                 WHERE so.batch_ref IS NOT NULL AND so2.batch_ref = so.batch_ref) AS batch_size
                FROM stock_out so
                INNER JOIN items i ON i.id = so.item_id
                INNER JOIN customers c ON c.id = so.customer_id
                INNER JOIN users u ON u.id = so.created_by
                WHERE 1=1';
        $params = [];

        if (!empty($filters['date_from'])) {
            $sql .= ' AND DATE(so.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND DATE(so.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND so.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['reason']) && in_array($filters['reason'], self::REASONS, true)) {
            $sql .= ' AND so.reason = :reason';
            $params['reason'] = $filters['reason'];
        }

        if (!empty($filters['customer'])) {
            $sql .= ' AND c.customer_name LIKE :customer';
            $params['customer'] = '%' . $filters['customer'] . '%';
        }

        if (!empty($filters['item'])) {
            $sql .= ' AND (i.item_no LIKE :item1 OR i.item_name LIKE :item2)';
            $params['item1'] = $params['item2'] = '%' . $filters['item'] . '%';
        }

        $sql .= ' ORDER BY so.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return array_map([self::class, 'formatRow'], $stmt->fetchAll());
    }

    public static function find(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT so.*, i.item_no, i.item_name, c.customer_name, c.id AS customer_id,
                    u.display_name AS created_by_name
             FROM stock_out so
             INNER JOIN items i ON i.id = so.item_id
             INNER JOIN customers c ON c.id = so.customer_id
             INNER JOIN users u ON u.id = so.created_by
             WHERE so.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::formatRow($row) : null;
    }

    public static function create(array $data): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'INSERT INTO stock_out (
                batch_ref, item_id, customer_id, mfd_date, qty, unit, reason, remark,
                status, created_by, approved_by, approved_at
             ) VALUES (
                :batch_ref, :item_id, :customer_id, :mfd_date, :qty, :unit, :reason, :remark,
                :status, :created_by, :approved_by, :approved_at
             )'
        );
        $stmt->execute($data);

        return (int) $db->lastInsertId();
    }

    public static function createMany(array $input, int $userId): array
    {
        $lines = self::parseLines($input);
        $header = [
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'reason'      => $input['reason'] ?? '',
            'remark'      => trim($input['remark'] ?? '') ?: null,
        ];
        $batchRef = count($lines) > 1 ? generate_batch_ref() : null;
        $db = Database::connect();
        $db->beginTransaction();

        try {
            $ids = [];
            foreach ($lines as $line) {
                $payload = self::createPayload(
                    array_merge(self::normalizeLine($line), $header, ['batch_ref' => $batchRef]),
                    $userId
                );
                $ids[] = self::create($payload);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return ['batch_ref' => $batchRef, 'ids' => $ids];
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE stock_out SET
                item_id = :item_id,
                customer_id = :customer_id,
                mfd_date = :mfd_date,
                qty = :qty,
                unit = :unit,
                reason = :reason,
                remark = :remark
             WHERE id = :id AND status = \'pending\''
        );

        return $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public static function delete(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare('DELETE FROM stock_out WHERE id = :id AND status = \'pending\'');
        return $stmt->execute(['id' => $id]);
    }

    public static function countPending(): int
    {
        $db = Database::connect();
        return (int) $db->query("SELECT COUNT(*) FROM stock_out WHERE status = 'pending'")->fetchColumn();
    }

    public static function pendingIdsByBatchRef(string $batchRef): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT id FROM stock_out WHERE batch_ref = :batch_ref AND status = 'pending' ORDER BY id ASC"
        );
        $stmt->execute(['batch_ref' => $batchRef]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function resolvePendingBatchIds(int $id): array
    {
        $record = self::find($id);
        if (!$record || ($record['status'] ?? '') !== 'pending') {
            return [];
        }

        if (!empty($record['batch_ref'])) {
            return self::pendingIdsByBatchRef($record['batch_ref']);
        }

        return [$id];
    }

    public static function approve(int $id, int $adminId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE stock_out SET
                status = \'approved\',
                rejection_reason = NULL,
                approved_by = :approved_by,
                approved_at = :approved_at
             WHERE id = :id AND status = \'pending\''
        );

        return $stmt->execute([
            'id'          => $id,
            'approved_by' => $adminId,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function approveBatch(array $ids, int $adminId): bool
    {
        if (empty($ids)) {
            return false;
        }

        $db = Database::connect();
        $db->beginTransaction();
        $now = date('Y-m-d H:i:s');

        try {
            $stmt = $db->prepare(
                'UPDATE stock_out SET
                    status = \'approved\',
                    rejection_reason = NULL,
                    approved_by = :approved_by,
                    approved_at = :approved_at
                 WHERE id = :id AND status = \'pending\''
            );

            foreach ($ids as $id) {
                $stmt->execute([
                    'id'          => $id,
                    'approved_by' => $adminId,
                    'approved_at' => $now,
                ]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('Unable to approve stock out record.');
                }
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            return false;
        }
    }

    public static function reject(int $id, string $reason): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE stock_out SET
                status = \'rejected\',
                rejection_reason = :rejection_reason,
                approved_by = NULL,
                approved_at = NULL
             WHERE id = :id AND status = \'pending\''
        );

        return $stmt->execute([
            'id'               => $id,
            'rejection_reason' => $reason,
        ]);
    }

    public static function rejectBatch(array $ids, string $reason): bool
    {
        if (empty($ids)) {
            return false;
        }

        $db = Database::connect();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'UPDATE stock_out SET
                    status = \'rejected\',
                    rejection_reason = :rejection_reason,
                    approved_by = NULL,
                    approved_at = NULL
                 WHERE id = :id AND status = \'pending\''
            );

            foreach ($ids as $id) {
                $stmt->execute([
                    'id'               => $id,
                    'rejection_reason' => $reason,
                ]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('Unable to reject stock out record.');
                }
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            return false;
        }
    }

    public static function canModify(array $record): bool
    {
        if (($record['status'] ?? '') !== 'pending') {
            return false;
        }
        if (is_admin()) {
            return true;
        }
        return (int) ($record['raw_created_by'] ?? 0) === (int) (current_user()['id'] ?? 0);
    }

    public static function parseLines(array $input): array
    {
        if (!empty($input['lines']) && is_array($input['lines'])) {
            $lines = [];
            foreach ($input['lines'] as $line) {
                if (!is_array($line) || empty($line['item_id'])) {
                    continue;
                }
                $lines[] = $line;
            }

            return $lines;
        }

        if (!empty($input['item_id'])) {
            return [$input];
        }

        return [];
    }

    public static function validate(array $input): array
    {
        $errors = [];
        $itemId = (int) ($input['item_id'] ?? 0);
        $customerId = (int) ($input['customer_id'] ?? 0);
        $qty = $input['qty'] ?? '';
        $unit = trim($input['unit'] ?? '');
        $reason = $input['reason'] ?? '';
        $remark = trim($input['remark'] ?? '');

        if ($itemId <= 0 || !Item::find($itemId)) {
            $errors[] = 'Please select a valid item.';
        }

        if ($customerId <= 0 || !Customer::find($customerId)) {
            $errors[] = 'Please select a valid customer.';
        }

        if (!is_numeric($qty) || (float) $qty <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }

        if ($unit === '') {
            $errors[] = 'Unit is required.';
        }

        if (!in_array($reason, self::REASONS, true)) {
            $errors[] = 'Please select a valid reason.';
        }

        if ($reason === 'Other' && $remark === '') {
            $errors[] = 'Remark is required when reason is Other.';
        }

        return $errors;
    }

    public static function validateSubmission(array $input): array
    {
        $errors = [];
        $lines = self::parseLines($input);

        if (empty($lines)) {
            $errors[] = 'Add at least one item line.';
            return $errors;
        }

        $customerId = (int) ($input['customer_id'] ?? 0);
        if ($customerId <= 0 || !Customer::find($customerId)) {
            $errors[] = 'Please select a valid customer.';
        }

        $reason = $input['reason'] ?? '';
        $remark = trim($input['remark'] ?? '');

        if (!in_array($reason, self::REASONS, true)) {
            $errors[] = 'Please select a valid reason.';
        }

        if ($reason === 'Other' && $remark === '') {
            $errors[] = 'Remark is required when reason is Other.';
        }

        foreach ($lines as $index => $line) {
            $errors = array_merge($errors, self::validateLine($line, $index + 1));
        }

        return $errors;
    }

    public static function validateLine(array $line, int $lineNo): array
    {
        $errors = [];
        $prefix = "Line {$lineNo}: ";

        $itemId = (int) ($line['item_id'] ?? 0);
        $qty = $line['qty'] ?? '';
        $unit = trim($line['unit'] ?? '');

        if ($itemId <= 0 || !Item::find($itemId)) {
            $errors[] = $prefix . 'Please select a valid item.';
        }

        if (!is_numeric($qty) || (float) $qty <= 0) {
            $errors[] = $prefix . 'Quantity must be greater than zero.';
        }

        if ($unit === '') {
            $errors[] = $prefix . 'Unit is required.';
        }

        return $errors;
    }

    public static function normalize(array $input): array
    {
        return array_merge(self::normalizeLine($input), [
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'reason'      => $input['reason'] ?? '',
            'remark'      => trim($input['remark'] ?? '') ?: null,
        ]);
    }

    public static function normalizeLine(array $line): array
    {
        return [
            'item_id'  => (int) ($line['item_id'] ?? 0),
            'mfd_date' => ($line['mfd_date'] ?? '') ?: null,
            'qty'      => (float) ($line['qty'] ?? 0),
            'unit'     => trim($line['unit'] ?? ''),
        ];
    }

    public static function normalizeSubmission(array $input): array
    {
        return [
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'reason'      => $input['reason'] ?? '',
            'remark'      => trim($input['remark'] ?? '') ?: null,
            'lines'       => array_map([self::class, 'normalizeLine'], self::parseLines($input)),
        ];
    }

    public static function initialStatus(): string
    {
        return is_admin() ? 'approved' : 'pending';
    }

    public static function createPayload(array $data, int $userId): array
    {
        $status = self::initialStatus();
        $approved = $status === 'approved';

        return array_merge($data, [
            'batch_ref'   => $data['batch_ref'] ?? null,
            'status'      => $status,
            'created_by'  => $userId,
            'approved_by' => $approved ? $userId : null,
            'approved_at' => $approved ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private static function formatRow(array $row): array
    {
        $row['raw_created_by'] = $row['created_by'];
        $row['created_by'] = $row['created_by_name'];
        $row['batch_size'] = (int) ($row['batch_size'] ?? 1);
        return $row;
    }
}
