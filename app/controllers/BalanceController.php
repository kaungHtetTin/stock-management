<?php
/**
 * Balance List + graph
 */

require_once APP_PATH . '/services/BalanceService.php';
require_once APP_PATH . '/models/Category.php';
require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';

class BalanceController
{
    public function index(): void
    {
        require_login();

        $filters = [
            'q'           => trim($_GET['q'] ?? ''),
            'category_id' => $_GET['category_id'] ?? '',
        ];

        render_app('balance/index.php', [
            'pageTitle'    => 'Balance — ' . APP_NAME,
            'currentNav'   => 'balance',
            'breadcrumbs'  => [['label' => 'Balance']],
            'pendingBadge' => is_admin() ? StockIn::countPending() + StockOut::countPending() : 0,
            'balances'     => BalanceService::getAllBalances($filters),
            'chart'        => BalanceService::getChartData(),
            'categories'   => Category::activeList(),
            'filters'      => $filters,
        ]);
    }
}
