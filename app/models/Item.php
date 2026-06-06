<?php
/**
 * Item (product) model
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/models/Category.php';

class Item
{
    public static function all(array $filters = []): array
    {
        $db = Database::connect();
        $sql = 'SELECT i.*, c.name AS category, ' . self::balanceSubquery() . ' AS balance
                FROM items i
                INNER JOIN categories c ON c.id = i.category_id
                WHERE i.is_active = 1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (i.item_no LIKE :q1 OR i.item_name LIKE :q2)';
            $params['q1'] = $params['q2'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['category_id']) && ctype_digit((string) $filters['category_id'])) {
            $sql .= ' AND i.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        $sql .= ' ORDER BY i.item_no ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT i.*, c.name AS category, ' . self::balanceSubquery() . ' AS balance
             FROM items i
             INNER JOIN categories c ON c.id = i.category_id
             WHERE i.id = :id AND i.is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByItemNo(string $itemNo, ?int $excludeId = null): ?array
    {
        $db = Database::connect();
        $sql = 'SELECT * FROM items WHERE item_no = :item_no';
        $params = ['item_no' => $itemNo];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'INSERT INTO items (item_no, item_name, unit, unit_price, category_id, remark, created_by)
             VALUES (:item_no, :item_name, :unit, :unit_price, :category_id, :remark, :created_by)'
        );
        $stmt->execute([
            'item_no'     => $data['item_no'],
            'item_name'   => $data['item_name'],
            'unit'        => $data['unit'],
            'unit_price'  => $data['unit_price'],
            'category_id' => $data['category_id'],
            'remark'      => $data['remark'],
            'created_by'  => $data['created_by'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE items SET
                item_no = :item_no,
                item_name = :item_name,
                unit = :unit,
                unit_price = :unit_price,
                category_id = :category_id,
                remark = :remark
             WHERE id = :id AND is_active = 1'
        );

        return $stmt->execute([
            'id'          => $id,
            'item_no'     => $data['item_no'],
            'item_name'   => $data['item_name'],
            'unit'        => $data['unit'],
            'unit_price'  => $data['unit_price'],
            'category_id' => $data['category_id'],
            'remark'      => $data['remark'],
        ]);
    }

    public static function softDelete(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE items SET is_active = 0 WHERE id = :id AND is_active = 1');
        return $stmt->execute(['id' => $id]);
    }

    public static function hasStockRecords(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT (
                (SELECT COUNT(*) FROM stock_in WHERE item_id = :id1) +
                (SELECT COUNT(*) FROM stock_out WHERE item_id = :id2)
             ) AS total'
        );
        $stmt->execute(['id1' => $id, 'id2' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];
        $itemNo    = trim($input['item_no'] ?? '');
        $itemName  = trim($input['item_name'] ?? '');
        $unit      = trim($input['unit'] ?? '');
        $categoryId = (int) ($input['category_id'] ?? 0);
        $unitPrice = $input['unit_price'] ?? '';

        if ($itemNo === '') {
            $errors[] = 'Item No is required.';
        } elseif (self::findByItemNo($itemNo, $excludeId)) {
            $errors[] = 'Item No already exists.';
        }

        if ($itemName === '') {
            $errors[] = 'Item Name is required.';
        }

        if ($unit === '') {
            $errors[] = 'Unit is required.';
        }

        if ($categoryId <= 0 || !Category::find($categoryId)) {
            $errors[] = 'Please select a valid category.';
        }

        if ($unitPrice !== '' && $unitPrice !== null && (!is_numeric($unitPrice) || (float) $unitPrice < 0)) {
            $errors[] = 'Unit Price must be a positive number.';
        }

        return $errors;
    }

    public static function normalize(array $input): array
    {
        return [
            'item_no'     => trim($input['item_no'] ?? ''),
            'item_name'   => trim($input['item_name'] ?? ''),
            'unit'        => trim($input['unit'] ?? ''),
            'unit_price'  => ($input['unit_price'] ?? '') !== '' ? (float) $input['unit_price'] : null,
            'category_id' => (int) ($input['category_id'] ?? 0),
            'remark'      => trim($input['remark'] ?? '') ?: null,
        ];
    }

    private static function balanceSubquery(): string
    {
        return '(
            COALESCE((SELECT SUM(qty) FROM stock_in WHERE item_id = i.id AND status = \'approved\'), 0)
            - COALESCE((SELECT SUM(qty) FROM stock_out WHERE item_id = i.id AND status = \'approved\'), 0)
        )';
    }
}
