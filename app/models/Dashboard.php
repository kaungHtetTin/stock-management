<?php
/**
 * Dashboard aggregates and activity
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';
require_once APP_PATH . '/models/Balance.php';

class Dashboard
{
    public static function stats(bool $isAdmin, int $userId): array
    {
        $db = Database::connect();

        $totalItems = (int) $db->query('SELECT COUNT(*) FROM items WHERE is_active = 1')->fetchColumn();

        $balanceRows = Balance::all();
        $totalStock = array_sum(array_map(fn ($r) => (float) $r['balance'], $balanceRows));

        $pendingCount = $isAdmin
            ? StockIn::countPending() + StockOut::countPending()
            : self::countOwnPending($userId);

        $customers = (int) $db->query('SELECT COUNT(*) FROM customers WHERE is_active = 1')->fetchColumn();

        $today = date('Y-m-d');
        $stmtIn = $db->prepare(
            "SELECT COUNT(*) FROM stock_in WHERE status = 'approved' AND DATE(approved_at) = :today"
        );
        $stmtIn->execute(['today' => $today]);
        $stockInToday = (int) $stmtIn->fetchColumn();

        $stmtOut = $db->prepare(
            "SELECT COUNT(*) FROM stock_out WHERE status = 'approved' AND DATE(approved_at) = :today"
        );
        $stmtOut->execute(['today' => $today]);
        $stockOutToday = (int) $stmtOut->fetchColumn();

        return [
            'total_items'     => $totalItems,
            'total_stock'     => $totalStock,
            'pending_count'   => $pendingCount,
            'customers'       => $customers,
            'stock_in_today'  => $stockInToday,
            'stock_out_today' => $stockOutToday,
        ];
    }

    public static function pendingList(bool $isAdmin, int $userId, int $limit = 10): array
    {
        $db = Database::connect();
        $params = [];
        $userClause = '';

        $inUserClause = '';
        $outUserClause = '';
        if (!$isAdmin) {
            $inUserClause = ' AND si.created_by = :user_id_in';
            $outUserClause = ' AND so.created_by = :user_id_out';
            $params['user_id_in'] = $userId;
            $params['user_id_out'] = $userId;
        }

        $sql = "
            SELECT * FROM (
                SELECT 'in' AS record_type, si.id, i.item_name, si.qty, si.unit,
                       u.display_name AS user_name, si.created_at
                FROM stock_in si
                INNER JOIN items i ON i.id = si.item_id
                INNER JOIN users u ON u.id = si.created_by
                WHERE si.status = 'pending'{$inUserClause}

                UNION ALL

                SELECT 'out' AS record_type, so.id, i.item_name, so.qty, so.unit,
                       u.display_name AS user_name, so.created_at
                FROM stock_out so
                INNER JOIN items i ON i.id = so.item_id
                INNER JOIN users u ON u.id = so.created_by
                WHERE so.status = 'pending'{$outUserClause}
            ) AS pending_rows
            ORDER BY created_at DESC
            LIMIT " . (int) $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return array_map([self::class, 'formatPendingRow'], $stmt->fetchAll());
    }

    public static function recentActivity(int $limit = 10): array
    {
        $db = Database::connect();

        $sql = "
            SELECT * FROM (
                SELECT 'in' AS type, i.item_name AS item, si.qty, si.unit,
                       u.display_name AS user_name, si.approved_at AS activity_time, si.status
                FROM stock_in si
                INNER JOIN items i ON i.id = si.item_id
                INNER JOIN users u ON u.id = si.created_by
                WHERE si.status = 'approved'

                UNION ALL

                SELECT 'out' AS type, i.item_name AS item, so.qty, so.unit,
                       u.display_name AS user_name, so.approved_at AS activity_time, so.status
                FROM stock_out so
                INNER JOIN items i ON i.id = so.item_id
                INNER JOIN users u ON u.id = so.created_by
                WHERE so.status = 'approved'
            ) AS activity
            ORDER BY activity_time DESC
            LIMIT " . (int) $limit;

        $stmt = $db->query($sql);

        return array_map(static function (array $row): array {
            return [
                'type'   => $row['type'],
                'item'   => $row['item'],
                'qty'    => (float) $row['qty'],
                'unit'   => $row['unit'],
                'user'   => $row['user_name'],
                'time'   => $row['activity_time'],
                'status' => $row['status'],
            ];
        }, $stmt->fetchAll());
    }

    private static function countOwnPending(int $userId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM stock_in WHERE status = 'pending' AND created_by = :uid1)
                + (SELECT COUNT(*) FROM stock_out WHERE status = 'pending' AND created_by = :uid2)"
        );
        $stmt->execute(['uid1' => $userId, 'uid2' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    private static function formatPendingRow(array $row): array
    {
        $typeLabel = $row['record_type'] === 'in' ? 'Stock In' : 'Stock Out';
        $basePath = $row['record_type'] === 'in' ? 'pages/stock-in/index.php' : 'pages/stock-out/index.php';

        return [
            'type'         => $row['record_type'],
            'id'           => (int) $row['id'],
            'title'        => $typeLabel . ': ' . $row['item_name'],
            'meta'         => format_number($row['qty'], 2) . ' ' . $row['unit'] . ' · ' . $row['user_name'] . ' · Pending',
            'approve_url'  => base_url($basePath . '?approve=' . $row['id']),
            'reject_url'   => base_url($basePath),
            'reject_id'    => (int) $row['id'],
        ];
    }
}
