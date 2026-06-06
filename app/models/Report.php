<?php
/**
 * Report queries with shared filters
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/models/Item.php';
require_once APP_PATH . '/models/Category.php';
require_once APP_PATH . '/models/Customer.php';
require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';
require_once APP_PATH . '/models/Balance.php';

class Report
{
    public const TYPES = ['stock_in', 'stock_out', 'current_stock', 'activity'];
    public const PER_PAGE = 25;

    public static function normalizeFilters(array $input): array
    {
        $type = $input['report_type'] ?? 'current_stock';
        if (!in_array($type, self::TYPES, true)) {
            $type = 'current_stock';
        }

        $stockType = $input['stock_type'] ?? 'both';
        if (!in_array($stockType, ['in', 'out', 'both'], true)) {
            $stockType = 'both';
        }

        return [
            'report_type'   => $type,
            'date_from'     => $input['date_from'] ?? '',
            'date_to'       => $input['date_to'] ?? '',
            'category_id'   => $input['category_id'] ?? '',
            'item'          => trim($input['item'] ?? ''),
            'customer'      => trim($input['customer'] ?? ''),
            'reason'        => $input['reason'] ?? '',
            'status'        => $input['status'] ?? '',
            'customer_type' => $input['customer_type'] ?? '',
            'stock_type'    => $stockType,
        ];
    }

    public static function generate(array $filters, int $page = 1): array
    {
        $page = max(1, $page);

        return match ($filters['report_type']) {
            'stock_in'      => self::stockIn($filters, $page),
            'stock_out'     => self::stockOut($filters, $page),
            'current_stock' => self::currentStock($filters, $page),
            'activity'      => self::activity($filters, $page),
            default         => self::currentStock($filters, $page),
        };
    }

    public static function title(string $type): string
    {
        return match ($type) {
            'stock_in'      => 'Stock In Report',
            'stock_out'     => 'Stock Out Report',
            'current_stock' => 'Current Stock Report',
            'activity'      => 'Activity Summary Report',
            default         => 'Report',
        };
    }

    public static function filterSummary(array $filters): string
    {
        $parts = [];

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $from = $filters['date_from'] ? format_date($filters['date_from']) : '—';
            $to = $filters['date_to'] ? format_date($filters['date_to']) : '—';
            $parts[] = "Period: {$from} — {$to}";
        }

        if (!empty($filters['category_id']) && ctype_digit((string) $filters['category_id'])) {
            $cat = Category::find((int) $filters['category_id']);
            $parts[] = 'Category: ' . ($cat['name'] ?? $filters['category_id']);
        }

        if (!empty($filters['item'])) {
            $parts[] = 'Item: ' . $filters['item'];
        }

        if (!empty($filters['customer'])) {
            $customer = Customer::find((int) $filters['customer']);
            $parts[] = 'Customer: ' . ($customer['customer_name'] ?? $filters['customer']);
        }

        if (!empty($filters['reason'])) {
            $parts[] = 'Reason: ' . $filters['reason'];
        }

        if (!empty($filters['status'])) {
            $parts[] = 'Status: ' . ucfirst($filters['status']);
        }

        if (!empty($filters['customer_type'])) {
            $parts[] = 'Customer type: ' . $filters['customer_type'];
        }

        if ($filters['report_type'] === 'activity' && $filters['stock_type'] !== 'both') {
            $parts[] = 'Type: Stock ' . ucfirst($filters['stock_type']);
        }

        return $parts ? implode(' · ', $parts) : 'All records';
    }

    public static function stockIn(array $filters, int $page): array
    {
        $db = Database::connect();
        $params = [];
        $where = 'WHERE 1=1' . self::stockInWhere($filters, $params);

        $total = self::countQuery(
            "SELECT COUNT(*) FROM stock_in si
             INNER JOIN items i ON i.id = si.item_id
             INNER JOIN users u ON u.id = si.created_by
             {$where}",
            $params
        );

        $offset = ($page - 1) * self::PER_PAGE;
        $sql = "SELECT si.*, i.item_no, i.item_name, cat.name AS category, u.display_name AS created_by_name
                FROM stock_in si
                INNER JOIN items i ON i.id = si.item_id
                INNER JOIN categories cat ON cat.id = i.category_id
                INNER JOIN users u ON u.id = si.created_by
                {$where}
                ORDER BY si.created_at DESC
                LIMIT " . self::PER_PAGE . " OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = array_map(static function (array $row): array {
            $row['in_charge'] = $row['in_charge_name'];
            $row['created_by'] = $row['created_by_name'];
            return $row;
        }, $stmt->fetchAll());

        return self::paginatedResult($rows, $total, $page);
    }

    public static function stockOut(array $filters, int $page): array
    {
        $db = Database::connect();
        $params = [];
        $where = 'WHERE 1=1' . self::stockOutWhere($filters, $params);

        $total = self::countQuery(
            "SELECT COUNT(*) FROM stock_out so
             INNER JOIN items i ON i.id = so.item_id
             INNER JOIN customers c ON c.id = so.customer_id
             INNER JOIN users u ON u.id = so.created_by
             {$where}",
            $params
        );

        $offset = ($page - 1) * self::PER_PAGE;
        $sql = "SELECT so.*, i.item_no, i.item_name, cat.name AS category, c.customer_name, c.customer_type,
                       u.display_name AS created_by_name
                FROM stock_out so
                INNER JOIN items i ON i.id = so.item_id
                INNER JOIN categories cat ON cat.id = i.category_id
                INNER JOIN customers c ON c.id = so.customer_id
                INNER JOIN users u ON u.id = so.created_by
                {$where}
                ORDER BY so.created_at DESC
                LIMIT " . self::PER_PAGE . " OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = array_map(static function (array $row): array {
            $row['created_by'] = $row['created_by_name'];
            return $row;
        }, $stmt->fetchAll());

        return self::paginatedResult($rows, $total, $page);
    }

    public static function currentStock(array $filters, int $page): array
    {
        $all = Balance::all([
            'q'           => $filters['item'],
            'category_id' => $filters['category_id'],
        ]);

        $total = count($all);
        $offset = ($page - 1) * self::PER_PAGE;
        $rows = array_slice($all, $offset, self::PER_PAGE);

        $result = self::paginatedResult($rows, $total, $page);
        $result['total_units'] = array_sum(array_map(fn ($r) => (float) $r['balance'], $all));

        return $result;
    }

    public static function activity(array $filters, int $page): array
    {
        return self::activityUnion($filters, $page);
    }

    private static function activityUnion(array $filters, int $page): array
    {
        $db = Database::connect();
        $parts = [];
        $params = [];
        $includeIn = $filters['stock_type'] !== 'out';
        $includeOut = $filters['stock_type'] !== 'in';

        if ($includeIn) {
            $inParams = [];
            $inWhere = self::stockInWhere($filters, $inParams, 'in_');
            $parts[] = "SELECT 'in' AS activity_type, si.id, i.item_name, i.item_no, cat.name AS category,
                               si.qty, si.unit, NULL AS customer_name, NULL AS reason,
                               si.status, si.created_at, u.display_name AS user_name
                        FROM stock_in si
                        INNER JOIN items i ON i.id = si.item_id
                        INNER JOIN categories cat ON cat.id = i.category_id
                        INNER JOIN users u ON u.id = si.created_by
                        WHERE 1=1{$inWhere}";
            $params = array_merge($params, $inParams);
        }

        if ($includeOut) {
            $outParams = [];
            $outWhere = self::stockOutWhere($filters, $outParams, 'out_');
            $parts[] = "SELECT 'out' AS activity_type, so.id, i.item_name, i.item_no, cat.name AS category,
                               so.qty, so.unit, c.customer_name, so.reason,
                               so.status, so.created_at, u.display_name AS user_name
                        FROM stock_out so
                        INNER JOIN items i ON i.id = so.item_id
                        INNER JOIN categories cat ON cat.id = i.category_id
                        INNER JOIN customers c ON c.id = so.customer_id
                        INNER JOIN users u ON u.id = so.created_by
                        WHERE 1=1{$outWhere}";
            $params = array_merge($params, $outParams);
        }

        if (empty($parts)) {
            return self::paginatedResult([], 0, $page, [
                'summary' => ['in_count' => 0, 'in_qty' => 0, 'out_count' => 0, 'out_qty' => 0],
            ]);
        }

        $unionSql = implode(' UNION ALL ', $parts);

        $summarySql = "SELECT
            SUM(CASE WHEN activity_type = 'in' THEN 1 ELSE 0 END) AS in_count,
            SUM(CASE WHEN activity_type = 'in' THEN qty ELSE 0 END) AS in_qty,
            SUM(CASE WHEN activity_type = 'out' THEN 1 ELSE 0 END) AS out_count,
            SUM(CASE WHEN activity_type = 'out' THEN qty ELSE 0 END) AS out_qty
            FROM ({$unionSql}) AS activity_data";

        $stmt = $db->prepare($summarySql);
        $stmt->execute($params);
        $summaryRow = $stmt->fetch() ?: [];

        $total = self::countQuery("SELECT COUNT(*) FROM ({$unionSql}) AS activity_data", $params);
        $offset = ($page - 1) * self::PER_PAGE;

        $sql = "SELECT * FROM ({$unionSql}) AS activity_data
                ORDER BY created_at DESC
                LIMIT " . self::PER_PAGE . " OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return self::paginatedResult($rows, $total, $page, [
            'summary' => [
                'in_count'  => (int) ($summaryRow['in_count'] ?? 0),
                'in_qty'    => (float) ($summaryRow['in_qty'] ?? 0),
                'out_count' => (int) ($summaryRow['out_count'] ?? 0),
                'out_qty'   => (float) ($summaryRow['out_qty'] ?? 0),
            ],
        ]);
    }

    private static function stockInWhere(array $filters, array &$params, string $prefix = ''): string
    {
        $sql = '';

        if (!empty($filters['date_from'])) {
            $key = $prefix . 'date_from';
            $sql .= " AND DATE(si.created_at) >= :{$key}";
            $params[$key] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $key = $prefix . 'date_to';
            $sql .= " AND DATE(si.created_at) <= :{$key}";
            $params[$key] = $filters['date_to'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], StockIn::STATUSES, true)) {
            $key = $prefix . 'status';
            $sql .= " AND si.status = :{$key}";
            $params[$key] = $filters['status'];
        }

        $sql .= self::itemWhere($filters, $params, $prefix);

        return $sql;
    }

    private static function stockOutWhere(array $filters, array &$params, string $prefix = ''): string
    {
        $sql = '';

        if (!empty($filters['date_from'])) {
            $key = $prefix . 'date_from';
            $sql .= " AND DATE(so.created_at) >= :{$key}";
            $params[$key] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $key = $prefix . 'date_to';
            $sql .= " AND DATE(so.created_at) <= :{$key}";
            $params[$key] = $filters['date_to'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], StockOut::STATUSES, true)) {
            $key = $prefix . 'status';
            $sql .= " AND so.status = :{$key}";
            $params[$key] = $filters['status'];
        }

        if (!empty($filters['reason']) && in_array($filters['reason'], StockOut::REASONS, true)) {
            $key = $prefix . 'reason';
            $sql .= " AND so.reason = :{$key}";
            $params[$key] = $filters['reason'];
        }

        if (!empty($filters['customer']) && ctype_digit((string) $filters['customer'])) {
            $key = $prefix . 'customer_id';
            $sql .= " AND so.customer_id = :{$key}";
            $params[$key] = (int) $filters['customer'];
        }

        if (!empty($filters['customer_type']) && in_array($filters['customer_type'], Customer::TYPES, true)) {
            $key = $prefix . 'customer_type';
            $sql .= " AND c.customer_type = :{$key}";
            $params[$key] = $filters['customer_type'];
        }

        $sql .= self::itemWhere($filters, $params, $prefix);

        return $sql;
    }

    private static function itemWhere(array $filters, array &$params, string $prefix = ''): string
    {
        $sql = '';

        if (!empty($filters['category_id']) && ctype_digit((string) $filters['category_id'])) {
            $key = $prefix . 'category_id';
            $sql .= " AND i.category_id = :{$key}";
            $params[$key] = (int) $filters['category_id'];
        }

        if (!empty($filters['item'])) {
            $key1 = $prefix . 'item1';
            $key2 = $prefix . 'item2';
            $sql .= " AND (i.item_no LIKE :{$key1} OR i.item_name LIKE :{$key2})";
            $params[$key1] = $params[$key2] = '%' . $filters['item'] . '%';
        }

        return $sql;
    }

    private static function countQuery(string $sql, array $params): int
    {
        $db = Database::connect();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private static function paginatedResult(array $rows, int $total, int $page, array $extra = []): array
    {
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        return array_merge([
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => self::PER_PAGE,
            'total_pages' => $totalPages,
        ], $extra);
    }
}
