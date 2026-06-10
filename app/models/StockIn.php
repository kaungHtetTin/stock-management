<?php
/**
 * Stock In transaction model
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/helpers/pagination.php';
require_once APP_PATH . '/models/Item.php';

class StockIn
{
    public const STATUSES = ['pending', 'approved', 'rejected'];
    public const EDITABLE_STATUSES = ['pending', 'approved', 'rejected'];

    public static function all(array $filters = []): array
    {
        $db = Database::connect();
        $params = [];
        $sql = self::listSelect()
            . self::listFromJoin()
            . self::listWhere($filters, $params)
            . ' ORDER BY si.created_at DESC';

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
            . " ORDER BY si.created_at DESC LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = array_map([self::class, 'formatRow'], $stmt->fetchAll());

        return Pagination::result($rows, $total, $page, $perPage);
    }

    private static function listSelect(): string
    {
        return 'SELECT si.*, i.item_no, i.item_name, u.display_name AS created_by_name,
                (SELECT COUNT(*) FROM stock_in si2
                 WHERE si.batch_ref IS NOT NULL AND si2.batch_ref = si.batch_ref) AS batch_size';
    }

    private static function listFromJoin(): string
    {
        return ' FROM stock_in si
                INNER JOIN items i ON i.id = si.item_id
                INNER JOIN users u ON u.id = si.created_by
                WHERE 1=1';
    }

    private static function listWhere(array $filters, array &$params): string
    {
        $sql = '';

        if (!empty($filters['date_from'])) {
            $sql .= ' AND DATE(si.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND DATE(si.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND si.status = :status';
            $params['status'] = $filters['status'];
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
            'SELECT si.*, i.item_no, i.item_name, u.display_name AS created_by_name
             FROM stock_in si
             INNER JOIN items i ON i.id = si.item_id
             INNER JOIN users u ON u.id = si.created_by
             WHERE si.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::formatRow($row) : null;
    }

    public static function create(array $data): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'INSERT INTO stock_in (
                batch_ref, item_id, mfd_date, expire_date, lot_no, qty, unit, worker_qty,
                in_charge_name, status, created_by, approved_by, approved_at
             ) VALUES (
                :batch_ref, :item_id, :mfd_date, :expire_date, :lot_no, :qty, :unit, :worker_qty,
                :in_charge_name, :status, :created_by, :approved_by, :approved_at
             )'
        );
        $stmt->execute($data);

        return (int) $db->lastInsertId();
    }

    public static function createMany(array $input, int $userId): array
    {
        $lines = self::parseLines($input);
        $header = [
            'in_charge_name' => trim($input['in_charge_name'] ?? $input['in_charge'] ?? ''),
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
            'SELECT si.*, i.item_no, i.item_name, u.display_name AS created_by_name
             FROM stock_in si
             INNER JOIN items i ON i.id = si.item_id
             INNER JOIN users u ON u.id = si.created_by
             WHERE si.batch_ref = :batch_ref
             ORDER BY si.id ASC'
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
            'anchor_id'      => $id,
            'id'             => $id,
            'batch_ref'      => $first['batch_ref'] ?? null,
            'is_batch'       => count($batch) > 1,
            'status'         => $first['status'],
            'in_charge_name' => $first['in_charge_name'],
            'lines'          => $lines,
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
            'in_charge_name' => trim($input['in_charge_name'] ?? $input['in_charge'] ?? ''),
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
                    throw new RuntimeException('Unable to update stock in record.');
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
        $stmt = $db->prepare("DELETE FROM stock_in WHERE id IN ({$placeholders})");
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
        return (int) $db->query("SELECT COUNT(*) FROM stock_in WHERE status = 'pending'")->fetchColumn();
    }

    public static function pendingIdsByBatchRef(string $batchRef): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT id FROM stock_in WHERE batch_ref = :batch_ref AND status = 'pending' ORDER BY id ASC"
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
            'UPDATE stock_in SET
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
                'UPDATE stock_in SET
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
                    throw new RuntimeException('Unable to approve stock in record.');
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
            'UPDATE stock_in SET
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
                'UPDATE stock_in SET
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
                    throw new RuntimeException('Unable to reject stock in record.');
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
        $sql = 'UPDATE stock_in SET
                item_id = :item_id,
                mfd_date = :mfd_date,
                expire_date = :expire_date,
                lot_no = :lot_no,
                qty = :qty,
                unit = :unit,
                worker_qty = :worker_qty,
                in_charge_name = :in_charge_name';

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
        $qty = $input['qty'] ?? '';
        $unit = trim($input['unit'] ?? '');
        $inCharge = trim($input['in_charge_name'] ?? $input['in_charge'] ?? '');
        $mfd = $input['mfd_date'] ?? '';
        $expire = $input['expire_date'] ?? '';

        if ($itemId <= 0 || !Item::find($itemId)) {
            $errors[] = 'Please select a valid item.';
        }

        if (!is_numeric($qty) || (float) $qty <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }

        if ($unit === '') {
            $errors[] = 'Unit is required.';
        }

        if ($inCharge === '') {
            $errors[] = 'In Charge Name is required.';
        }

        if ($mfd && $expire && strtotime($expire) < strtotime($mfd)) {
            $errors[] = 'Expire Date must be on or after MFD Date.';
        }

        if (isset($input['worker_qty']) && $input['worker_qty'] !== '' && (!is_numeric($input['worker_qty']) || (float) $input['worker_qty'] < 0)) {
            $errors[] = 'Worker Qty must be a positive number.';
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

        $inCharge = trim($input['in_charge_name'] ?? $input['in_charge'] ?? '');
        if ($inCharge === '') {
            $errors[] = 'In Charge Name is required.';
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
        $mfd = $line['mfd_date'] ?? '';
        $expire = $line['expire_date'] ?? '';

        if ($itemId <= 0 || !Item::find($itemId)) {
            $errors[] = $prefix . 'Please select a valid item.';
        }

        if (!is_numeric($qty) || (float) $qty <= 0) {
            $errors[] = $prefix . 'Quantity must be greater than zero.';
        }

        if ($unit === '') {
            $errors[] = $prefix . 'Unit is required.';
        }

        if ($mfd && $expire && strtotime($expire) < strtotime($mfd)) {
            $errors[] = $prefix . 'Expire Date must be on or after MFD Date.';
        }

        if (isset($line['worker_qty']) && $line['worker_qty'] !== '' && (!is_numeric($line['worker_qty']) || (float) $line['worker_qty'] < 0)) {
            $errors[] = $prefix . 'Worker Qty must be a positive number.';
        }

        return $errors;
    }

    public static function normalize(array $input): array
    {
        return array_merge(self::normalizeLine($input), [
            'in_charge_name' => trim($input['in_charge_name'] ?? $input['in_charge'] ?? ''),
        ]);
    }

    public static function normalizeLine(array $line): array
    {
        return [
            'item_id'     => (int) ($line['item_id'] ?? 0),
            'mfd_date'    => ($line['mfd_date'] ?? '') ?: null,
            'expire_date' => ($line['expire_date'] ?? '') ?: null,
            'lot_no'      => trim($line['lot_no'] ?? '') ?: null,
            'qty'         => (float) ($line['qty'] ?? 0),
            'unit'        => trim($line['unit'] ?? ''),
            'worker_qty'  => ($line['worker_qty'] ?? '') !== '' ? (float) $line['worker_qty'] : null,
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
            'in_charge_name' => trim($input['in_charge_name'] ?? $input['in_charge'] ?? ''),
            'lines'          => $lines,
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
            'batch_ref'    => $data['batch_ref'] ?? null,
            'status'       => $status,
            'created_by'   => $userId,
            'approved_by'  => $approved ? $userId : null,
            'approved_at'  => $approved ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private static function formatRow(array $row): array
    {
        $row['raw_created_by'] = $row['created_by'];
        $row['in_charge'] = $row['in_charge_name'];
        $row['created_by'] = $row['created_by_name'];
        $row['batch_size'] = (int) ($row['batch_size'] ?? 1);
        return $row;
    }
}
