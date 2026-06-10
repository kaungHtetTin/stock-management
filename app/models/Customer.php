<?php
/**
 * Customer model
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/helpers/pagination.php';

class Customer
{
    public const TYPES = ['Retail', 'Whole Sale'];

    public static function all(array $filters = []): array
    {
        $db = Database::connect();
        $params = [];
        $sql = 'SELECT * FROM customers WHERE is_active = 1'
            . self::listWhere($filters, $params)
            . ' ORDER BY customer_code ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function paginate(array $filters, int $page): array
    {
        $db = Database::connect();
        $params = [];
        $where = self::listWhere($filters, $params);

        $countStmt = $db->prepare('SELECT COUNT(*) FROM customers WHERE is_active = 1' . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = Pagination::PER_PAGE;
        $offset = Pagination::offset($page, $perPage);
        $sql = 'SELECT * FROM customers WHERE is_active = 1'
            . $where
            . " ORDER BY customer_code ASC LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return Pagination::result($stmt->fetchAll(), $total, $page, $perPage);
    }

    private static function listWhere(array $filters, array &$params): string
    {
        $sql = '';

        if (!empty($filters['q'])) {
            $sql .= ' AND (customer_code LIKE :q1 OR customer_name LIKE :q2 OR contact_person LIKE :q3 OR phone LIKE :q4)';
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['type']) && in_array($filters['type'], self::TYPES, true)) {
            $sql .= ' AND customer_type = :type';
            $params['type'] = $filters['type'];
        }

        return $sql;
    }

    public static function find(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM customers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByCode(string $code, ?int $excludeId = null): ?array
    {
        $db = Database::connect();
        $sql = 'SELECT * FROM customers WHERE customer_code = :code';
        $params = ['code' => $code];

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
            'INSERT INTO customers (customer_code, customer_name, contact_person, phone, address, remark, customer_type, created_by)
             VALUES (:customer_code, :customer_name, :contact_person, :phone, :address, :remark, :customer_type, :created_by)'
        );
        $stmt->execute([
            'customer_code'  => $data['customer_code'],
            'customer_name'  => $data['customer_name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'address'        => $data['address'] ?? null,
            'remark'         => $data['remark'] ?? null,
            'customer_type'  => $data['customer_type'],
            'created_by'     => $data['created_by'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE customers SET
                customer_code = :customer_code,
                customer_name = :customer_name,
                contact_person = :contact_person,
                phone = :phone,
                address = :address,
                remark = :remark,
                customer_type = :customer_type
             WHERE id = :id AND is_active = 1'
        );

        return $stmt->execute([
            'id'             => $id,
            'customer_code'  => $data['customer_code'],
            'customer_name'  => $data['customer_name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'address'        => $data['address'] ?? null,
            'remark'         => $data['remark'] ?? null,
            'customer_type'  => $data['customer_type'],
        ]);
    }

    public static function softDelete(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE customers SET is_active = 0 WHERE id = :id AND is_active = 1');
        return $stmt->execute(['id' => $id]);
    }

    public static function hasStockOutRecords(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT COUNT(*) FROM stock_out WHERE customer_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];
        $code = trim($input['customer_code'] ?? '');
        $name = trim($input['customer_name'] ?? '');
        $type = $input['customer_type'] ?? '';

        if ($code === '') {
            $errors[] = 'Customer Code is required.';
        } elseif (self::findByCode($code, $excludeId)) {
            $errors[] = 'Customer Code already exists.';
        }

        if ($name === '') {
            $errors[] = 'Customer Name is required.';
        }

        if (!in_array($type, self::TYPES, true)) {
            $errors[] = 'Please select a valid customer type.';
        }

        $contactPerson = trim($input['contact_person'] ?? '');
        if ($contactPerson !== '' && strlen($contactPerson) > 100) {
            $errors[] = 'Contact person name must be 100 characters or less.';
        }

        $phone = trim($input['phone'] ?? '');
        if ($phone !== '' && strlen($phone) > 30) {
            $errors[] = 'Phone must be 30 characters or less.';
        }

        return $errors;
    }

    public static function normalize(array $input): array
    {
        return [
            'customer_code'  => trim($input['customer_code'] ?? ''),
            'customer_name'  => trim($input['customer_name'] ?? ''),
            'contact_person' => trim($input['contact_person'] ?? '') ?: null,
            'phone'          => trim($input['phone'] ?? '') ?: null,
            'address'       => trim($input['address'] ?? '') ?: null,
            'remark'        => trim($input['remark'] ?? '') ?: null,
            'customer_type' => $input['customer_type'] ?? '',
        ];
    }
}
