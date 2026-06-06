<?php
/**
 * Dashboard summary
 */

require_once APP_PATH . '/models/Dashboard.php';
require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';
require_once APP_PATH . '/services/BalanceService.php';

class DashboardController
{
    public function index(): void
    {
        require_login();

        $user = current_user();
        $userId = (int) $user['id'];
        $admin = is_admin();

        $stats = Dashboard::stats($admin, $userId);
        $pending = Dashboard::pendingList($admin, $userId);

        render_app('dashboard/index.php', [
            'pageTitle'    => 'Dashboard — ' . APP_NAME,
            'currentNav'   => 'dashboard',
            'breadcrumbs'  => [['label' => 'Dashboard']],
            'pendingBadge' => $stats['pending_count'],
            'stats'        => $stats,
            'activities'   => Dashboard::recentActivity(),
            'pending'      => $pending,
            'chart'        => BalanceService::getChartData(),
        ]);
    }
}
