<?php
/**
 * Item category model
 */

require_once APP_PATH . '/helpers/Database.php';

class Category
{
    public static function all(array $filters = []): array
    {
        $db = Database::connect();
        $sql = 'SELECT c.*,
                (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id AND i.is_active = 1) AS item_count
                FROM categories c
                WHERE c.is_active = 1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND c.name LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql .= ' ORDER BY c.sort_order ASC, c.name ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function activeList(): array
    {
        $db = Database::connect();
        $stmt = $db->query(
            'SELECT id, name, sort_order FROM categories
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC'
        );

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM categories WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByName(string $name, ?int $excludeId = null): ?array
    {
        $db = Database::connect();
        $sql = 'SELECT * FROM categories WHERE name = :name AND is_active = 1';
        $params = ['name' => $name];

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
            'INSERT INTO categories (name, sort_order) VALUES (:name, :sort_order)'
        );
        $stmt->execute([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE categories SET name = :name, sort_order = :sort_order
             WHERE id = :id AND is_active = 1'
        );

        return $stmt->execute([
            'id'         => $id,
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'],
        ]);
    }

    public static function softDelete(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE categories SET is_active = 0 WHERE id = :id AND is_active = 1');
        return $stmt->execute(['id' => $id]);
    }

    public static function hasItems(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT COUNT(*) FROM items WHERE category_id = :id AND is_active = 1');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];
        $name = trim($input['name'] ?? '');
        $sortOrder = $input['sort_order'] ?? '';

        if ($name === '') {
            $errors[] = 'Category name is required.';
        } elseif (strlen($name) > 50) {
            $errors[] = 'Category name must be 50 characters or less.';
        } elseif (self::findByName($name, $excludeId)) {
            $errors[] = 'Category name already exists.';
        }

        if ($sortOrder === '' || !is_numeric($sortOrder) || (int) $sortOrder < 0) {
            $errors[] = 'Sort order must be a positive number.';
        }

        return $errors;
    }

    public static function normalize(array $input): array
    {
        return [
            'name'       => trim($input['name'] ?? ''),
            'sort_order' => ($input['sort_order'] ?? '') !== '' ? (int) $input['sort_order'] : 0,
        ];
    }
}
