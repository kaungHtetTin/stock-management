<?php
/**
 * Stock In transaction model
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/models/Item.php';

class StockIn
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    public static function all(array $filters = []): array
    {
        $db = Database::connect();
        $sql = 'SELECT si.*, i.item_no, i.item_name, u.display_name AS created_by_name
                FROM stock_in si
                INNER JOIN items i ON i.id = si.item_id
                INNER JOIN users u ON u.id = si.created_by
                WHERE 1=1';
        $params = [];

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

        $sql .= ' ORDER BY si.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return array_map([self::class, 'formatRow'], $stmt->fetchAll());
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
                item_id, mfd_date, expire_date, lot_no, qty, unit, worker_qty,
                in_charge_name, status, created_by, approved_by, approved_at
             ) VALUES (
                :item_id, :mfd_date, :expire_date, :lot_no, :qty, :unit, :worker_qty,
                :in_charge_name, :status, :created_by, :approved_by, :approved_at
             )'
        );
        $stmt->execute($data);

        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE stock_in SET
                item_id = :item_id,
                mfd_date = :mfd_date,
                expire_date = :expire_date,
                lot_no = :lot_no,
                qty = :qty,
                unit = :unit,
                worker_qty = :worker_qty,
                in_charge_name = :in_charge_name
             WHERE id = :id AND status = \'pending\''
        );

        return $stmt->execute(array_merge($data, ['id' => $id]));
    }

    public static function delete(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare('DELETE FROM stock_in WHERE id = :id AND status = \'pending\'');
        return $stmt->execute(['id' => $id]);
    }

    public static function countPending(): int
    {
        $db = Database::connect();
        return (int) $db->query("SELECT COUNT(*) FROM stock_in WHERE status = 'pending'")->fetchColumn();
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

    public static function normalize(array $input): array
    {
        return [
            'item_id'        => (int) ($input['item_id'] ?? 0),
            'mfd_date'       => ($input['mfd_date'] ?? '') ?: null,
            'expire_date'    => ($input['expire_date'] ?? '') ?: null,
            'lot_no'         => trim($input['lot_no'] ?? '') ?: null,
            'qty'            => (float) ($input['qty'] ?? 0),
            'unit'           => trim($input['unit'] ?? ''),
            'worker_qty'     => ($input['worker_qty'] ?? '') !== '' ? (float) $input['worker_qty'] : null,
            'in_charge_name' => trim($input['in_charge_name'] ?? $input['in_charge'] ?? ''),
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
        return $row;
    }
}
