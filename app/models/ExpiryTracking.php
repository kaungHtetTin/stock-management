<?php
/**
 * Expiry tracking for stock batches with remaining quantity.
 */

require_once APP_PATH . '/helpers/Database.php';
require_once APP_PATH . '/models/Category.php';

class ExpiryTracking
{
    public const STATUSES = ['expired', 'urgent', 'warning', 'healthy'];

    public static function filters(array $input): array
    {
        $status = $input['status'] ?? '';
        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        return [
            'q'           => trim($input['q'] ?? ''),
            'category_id' => $input['category_id'] ?? '',
            'status'      => $status,
            'window'      => self::normalizeWindow($input['window'] ?? 'all'),
        ];
    }

    public static function rows(array $filters = []): array
    {
        $db = Database::connect();
        $params = [];
        $sql = "SELECT tracked.*
                FROM (
                    SELECT
                        b.item_id,
                        i.item_no,
                        i.item_name,
                        i.category_id,
                        c.name AS category,
                        b.unit,
                        b.mfd_date,
                        b.expire_date,
                        b.lot_numbers,
                        b.lot_count,
                        b.received_qty,
                        COALESCE((
                            SELECT SUM(so.qty)
                            FROM stock_out so
                            WHERE so.status = 'approved'
                              AND so.item_id = b.item_id
                              AND so.unit = b.unit
                              AND so.mfd_date <=> b.mfd_date
                              AND so.expire_date <=> b.expire_date
                        ), 0) AS issued_qty,
                        b.received_qty - COALESCE((
                            SELECT SUM(so.qty)
                            FROM stock_out so
                            WHERE so.status = 'approved'
                              AND so.item_id = b.item_id
                              AND so.unit = b.unit
                              AND so.mfd_date <=> b.mfd_date
                              AND so.expire_date <=> b.expire_date
                        ), 0) AS remaining_qty,
                        DATEDIFF(b.expire_date, CURDATE()) AS days_to_expire,
                        b.last_received_at
                    FROM (
                        SELECT
                            si.item_id,
                            si.unit,
                            si.mfd_date,
                            si.expire_date,
                            SUM(si.qty) AS received_qty,
                            COUNT(*) AS lot_count,
                            GROUP_CONCAT(DISTINCT NULLIF(si.lot_no, '') ORDER BY si.lot_no SEPARATOR ', ') AS lot_numbers,
                            MAX(si.approved_at) AS last_received_at
                        FROM stock_in si
                        WHERE si.status = 'approved'
                          AND si.expire_date IS NOT NULL
                        GROUP BY si.item_id, si.unit, si.mfd_date, si.expire_date
                    ) b
                    INNER JOIN items i ON i.id = b.item_id
                    INNER JOIN categories c ON c.id = i.category_id
                    WHERE i.is_active = 1
                ) tracked
                WHERE tracked.remaining_qty > 0";

        if (!empty($filters['q'])) {
            $sql .= ' AND (tracked.item_no LIKE :q1 OR tracked.item_name LIKE :q2 OR tracked.lot_numbers LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['category_id']) && ctype_digit((string) $filters['category_id'])) {
            $sql .= ' AND tracked.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= self::statusWhere($filters['status']);
        }

        $window = self::normalizeWindow($filters['window'] ?? 'all');
        if ($window === '0') {
            $sql .= ' AND tracked.days_to_expire < 0';
        } elseif ($window !== 'all') {
            $sql .= ' AND tracked.days_to_expire <= :window_days';
            $params['window_days'] = (int) $window;
        }

        $sql .= ' ORDER BY tracked.expire_date ASC, tracked.item_no ASC, tracked.mfd_date ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return array_map([self::class, 'formatRow'], $stmt->fetchAll());
    }

    public static function summary(array $rows): array
    {
        $summary = [
            'total_batches' => count($rows),
            'total_qty'     => 0.0,
            'expired'       => 0,
            'urgent'        => 0,
            'warning'       => 0,
            'healthy'       => 0,
            'risk_qty'      => 0.0,
        ];

        foreach ($rows as $row) {
            $qty = (float) $row['remaining_qty'];
            $summary['total_qty'] += $qty;
            $summary[$row['expiry_status']]++;

            if (in_array($row['expiry_status'], ['expired', 'urgent', 'warning'], true)) {
                $summary['risk_qty'] += $qty;
            }
        }

        return $summary;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'expired' => 'Expired',
            'urgent'  => 'Due in 7 days',
            'warning' => 'Due in 30 days',
            'healthy' => 'Healthy',
            default   => 'Unknown',
        };
    }

    private static function normalizeWindow(string $window): string
    {
        return in_array($window, ['all', '0', '7', '30', '60', '90'], true) ? $window : 'all';
    }

    private static function statusWhere(string $status): string
    {
        return match ($status) {
            'expired' => ' AND tracked.days_to_expire < 0',
            'urgent'  => ' AND tracked.days_to_expire BETWEEN 0 AND 7',
            'warning' => ' AND tracked.days_to_expire BETWEEN 8 AND 30',
            'healthy' => ' AND tracked.days_to_expire > 30',
            default   => '',
        };
    }

    private static function formatRow(array $row): array
    {
        $days = (int) $row['days_to_expire'];
        $row['received_qty'] = (float) $row['received_qty'];
        $row['issued_qty'] = (float) $row['issued_qty'];
        $row['remaining_qty'] = (float) $row['remaining_qty'];
        $row['expiry_status'] = self::statusForDays($days);
        $row['expiry_label'] = self::statusLabel($row['expiry_status']);

        return $row;
    }

    private static function statusForDays(int $days): string
    {
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 7) {
            return 'urgent';
        }
        if ($days <= 30) {
            return 'warning';
        }

        return 'healthy';
    }
}
