<?php
/**
 * Stock Out transaction model
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/helpers/pagination.php';
require_once APP_PATH . '/models/Item.php';
require_once APP_PATH . '/models/Customer.php';

class StockOut
{
    public const STATUSES = ['pending', 'approved', 'rejected'];
    public const EDITABLE_STATUSES = ['pending', 'approved', 'rejected'];
    public const REASONS = ['Sales', 'Sample', 'Sale & Marketing', 'Other'];

    public static function all(array $filters = []): array
    {
        $db = Database::connect();
        $params = [];
        $sql = self::listSelect()
            . self::listFromJoin()
            . self::listWhere($filters, $params)
            . ' ORDER BY so.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return array_map([self::class, 'formatRow'], $stmt->fetchAll());
    }

    public static function paginate(array $filters, int $page): array
    {
        $db = Database::connect();
        $params = [];
        $where = self::listWhere($filters, $params);
        $from = self::listFromJoin();

        $countStmt = $db->prepare('SELECT COUNT(*)' . $from . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = Pagination::PER_PAGE;
        $offset = Pagination::offset($page, $perPage);
        $sql = self::listSelect() . $from . $where
            . " ORDER BY so.created_at DESC LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = array_map([self::class, 'formatRow'], $stmt->fetchAll());

        return Pagination::result($rows, $total, $page, $perPage);
    }

    private static function listSelect(): string
    {
        return 'SELECT so.*, i.item_no, i.item_name, c.customer_name, u.display_name AS created_by_name,
                (SELECT COUNT(*) FROM stock_out so2
                 WHERE so.batch_ref IS NOT NULL AND so2.batch_ref = so.batch_ref) AS batch_size';
    }

    private static function listFromJoin(): string
    {
        return ' FROM stock_out so
                INNER JOIN items i ON i.id = so.item_id
                INNER JOIN customers c ON c.id = so.customer_id
                INNER JOIN users u ON u.id = so.created_by
                WHERE 1=1';
    }

    private static function listWhere(array $filters, array &$params): string
    {
        $sql = '';

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

        return $sql;
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

    public static function findBatchRecords(int $id): array
    {
        $record = self::find($id);
        if (!$record) {
            return [];
        }

        if (empty($record['batch_ref'])) {
            return [$record];
        }

        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT so.*, i.item_no, i.item_name, c.customer_name, c.id AS customer_id,
                    u.display_name AS created_by_name
             FROM stock_out so
             INNER JOIN items i ON i.id = so.item_id
             INNER JOIN customers c ON c.id = so.customer_id
             INNER JOIN users u ON u.id = so.created_by
             WHERE so.batch_ref = :batch_ref
             ORDER BY so.id ASC'
        );
        $stmt->execute(['batch_ref' => $record['batch_ref']]);

        return array_map([self::class, 'formatRow'], $stmt->fetchAll());
    }

    public static function findForEdit(int $id): ?array
    {
        $batch = self::findBatchRecords($id);
        if (empty($batch) || !self::canModify($batch[0])) {
            return null;
        }

        $first = $batch[0];
        $lines = [];
        foreach ($batch as $row) {
            $lines[] = array_merge(self::normalizeLine($row), ['id' => (int) $row['id']]);
        }

        return [
            'anchor_id'   => $id,
            'id'          => $id,
            'batch_ref'   => $first['batch_ref'] ?? null,
            'is_batch'    => count($batch) > 1,
            'status'      => $first['status'],
            'customer_id' => (int) $first['customer_id'],
            'reason'      => $first['reason'],
            'remark'      => $first['remark'],
            'lines'       => $lines,
        ];
    }

    public static function updateSubmission(int $anchorId, array $input): bool
    {
        $batch = self::findBatchRecords($anchorId);
        if (empty($batch) || !self::canModify($batch[0])) {
            return false;
        }

        $errors = self::validateEditSubmission($anchorId, $input);
        if ($errors) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $header = [
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'reason'      => $input['reason'] ?? '',
            'remark'      => trim($input['remark'] ?? '') ?: null,
        ];
        $lines = self::parseLines($input);
        $byId = [];
        foreach ($batch as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $db = Database::connect();
        $db->beginTransaction();

        try {
            foreach ($lines as $line) {
                $lineId = (int) ($line['id'] ?? 0);
                $existing = $byId[$lineId] ?? null;
                if (!$existing) {
                    throw new RuntimeException('Invalid batch line reference.');
                }

                $data = array_merge(self::normalizeLine($line), $header);
                $statusPatch = self::statusPatchAfterEdit($existing['status']);
                if (!self::updateRecord($lineId, $data, $statusPatch)) {
                    throw new RuntimeException('Unable to update stock out record.');
                }
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool
    {
        $record = self::find($id);
        if (!$record) {
            return false;
        }

        $statusPatch = self::statusPatchAfterEdit($record['status']);
        return self::updateRecord($id, $data, $statusPatch);
    }

    public static function deleteBatch(int $anchorId): int
    {
        $batch = self::findBatchRecords($anchorId);
        if (empty($batch) || !self::canModify($batch[0])) {
            return 0;
        }

        $ids = array_map(static fn ($row) => (int) $row['id'], $batch);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $db = Database::connect();
        $stmt = $db->prepare("DELETE FROM stock_out WHERE id IN ({$placeholders})");
        $stmt->execute($ids);

        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        return self::deleteBatch($id) > 0;
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
        if (!in_array($record['status'] ?? '', self::EDITABLE_STATUSES, true)) {
            return false;
        }
        if (is_admin()) {
            return true;
        }

        return (int) ($record['raw_created_by'] ?? 0) === (int) (current_user()['id'] ?? 0);
    }

    public static function validateEditSubmission(int $anchorId, array $input): array
    {
        $errors = self::validateSubmission($input);
        $batch = self::findBatchRecords($anchorId);

        if (empty($batch)) {
            $errors[] = 'Record not found.';
            return $errors;
        }

        $lines = self::parseLines($input);
        if (count($lines) !== count($batch)) {
            $errors[] = 'Edit the full batch together — line count cannot change.';
        }

        $batchIds = array_map(static fn ($row) => (int) $row['id'], $batch);
        sort($batchIds);
        $submittedIds = array_values(array_filter(array_map(
            static fn ($line) => (int) ($line['id'] ?? 0),
            $lines
        )));
        sort($submittedIds);

        if ($submittedIds !== $batchIds) {
            $errors[] = 'Invalid batch line references.';
        }

        $errors = array_merge($errors, self::validateApprovedBalanceDelta($batch, $lines));

        return $errors;
    }

    public static function validateApprovedBalanceDelta(array $batch, array $lines): array
    {
        require_once APP_PATH . '/services/BalanceService.php';

        $errors = [];
        $byId = [];
        foreach ($batch as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $deltaByItem = [];
        foreach ($lines as $line) {
            $lineId = (int) ($line['id'] ?? 0);
            $existing = $byId[$lineId] ?? null;
            if (!$existing || ($existing['status'] ?? '') !== 'approved') {
                continue;
            }

            $oldItemId = (int) $existing['item_id'];
            $newItemId = (int) ($line['item_id'] ?? 0);
            $oldQty = (float) $existing['qty'];
            $newQty = (float) ($line['qty'] ?? 0);

            $deltaByItem[$oldItemId] = ($deltaByItem[$oldItemId] ?? 0) - $oldQty;
            $deltaByItem[$newItemId] = ($deltaByItem[$newItemId] ?? 0) + $newQty;
        }

        foreach ($deltaByItem as $itemId => $delta) {
            if ($delta <= 0) {
                continue;
            }

            $balance = BalanceService::getItemBalance($itemId);
            if ($delta > $balance) {
                $item = Item::find($itemId);
                $label = $item ? $item['item_name'] : 'Item';
                $errors[] = sprintf(
                    'Insufficient stock for %s. Available: %s, additional out required: %s.',
                    $label,
                    format_number($balance, 2),
                    format_number($delta, 2)
                );
            }
        }

        return $errors;
    }

    private static function statusPatchAfterEdit(string $status): array
    {
        if ($status === 'rejected') {
            return [
                'status'           => 'pending',
                'rejection_reason' => null,
                'approved_by'      => null,
                'approved_at'      => null,
            ];
        }

        return [];
    }

    private static function updateRecord(int $id, array $data, array $statusPatch = []): bool
    {
        $db = Database::connect();
        $sql = 'UPDATE stock_out SET
                item_id = :item_id,
                customer_id = :customer_id,
                mfd_date = :mfd_date,
                qty = :qty,
                unit = :unit,
                reason = :reason,
                remark = :remark';

        if ($statusPatch) {
            $sql .= ',
                status = :status,
                rejection_reason = :rejection_reason,
                approved_by = :approved_by,
                approved_at = :approved_at';
        }

        $sql .= ' WHERE id = :id';

        $stmt = $db->prepare($sql);

        return $stmt->execute(array_merge($data, $statusPatch, ['id' => $id]));
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
        $lines = [];
        foreach (self::parseLines($input) as $line) {
            $normalized = self::normalizeLine($line);
            if (!empty($line['id'])) {
                $normalized['id'] = (int) $line['id'];
            }
            $lines[] = $normalized;
        }

        return [
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'reason'      => $input['reason'] ?? '',
            'remark'      => trim($input['remark'] ?? '') ?: null,
            'lines'       => $lines,
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
