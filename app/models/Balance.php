<?php
/**
 * Computed stock balance model
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/models/Category.php';

class Balance
{
    public static function all(array $filters = []): array
    {
        $db = Database::connect();
        $sql = 'SELECT i.id, i.item_no, i.item_name, i.category_id, c.name AS category, i.unit,
                ' . self::balanceExpr() . ' AS balance,
                (SELECT MAX(si.approved_at) FROM stock_in si
                 WHERE si.item_id = i.id AND si.status = \'approved\') AS last_in,
                (SELECT MAX(so.approved_at) FROM stock_out so
                 WHERE so.item_id = i.id AND so.status = \'approved\') AS last_out
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

        $sql .= ' ORDER BY balance ASC, i.item_no ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function categoryTotals(): array
    {
        $db = Database::connect();
        $stmt = $db->query(
            'SELECT c.id, c.name AS category, c.sort_order,
                    SUM(' . self::balanceExpr() . ') AS total_balance
             FROM items i
             INNER JOIN categories c ON c.id = i.category_id
             WHERE i.is_active = 1 AND c.is_active = 1
             GROUP BY c.id, c.name, c.sort_order
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        return $stmt->fetchAll();
    }

    public static function chartData(): array
    {
        $totals = self::categoryTotals();
        $labels = [];
        $values = [];

        foreach ($totals as $row) {
            $labels[] = $row['category'];
            $values[] = (float) $row['total_balance'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private static function balanceExpr(): string
    {
        return '(
            COALESCE((SELECT SUM(qty) FROM stock_in WHERE item_id = i.id AND status = \'approved\'), 0)
            - COALESCE((SELECT SUM(qty) FROM stock_out WHERE item_id = i.id AND status = \'approved\'), 0)
        )';
    }
}
