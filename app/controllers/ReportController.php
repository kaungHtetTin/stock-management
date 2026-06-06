<?php
/**
 * Reports with filters
 */

require_once APP_PATH . '/models/Report.php';
require_once APP_PATH . '/models/Customer.php';
require_once APP_PATH . '/models/Category.php';
require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';
require_once APP_PATH . '/services/BalanceService.php';

class ReportController
{
    public function index(): void
    {
        require_login();

        $filters = Report::normalizeFilters($_GET);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $generated = isset($_GET['generate']);

        $report = $generated ? Report::generate($filters, $page) : null;

        render_app('reports/index.php', [
            'pageTitle'    => 'Reports — ' . APP_NAME,
            'currentNav'   => 'reports',
            'breadcrumbs'  => [['label' => 'Reports']],
            'pendingBadge' => is_admin() ? StockIn::countPending() + StockOut::countPending() : 0,
            'filters'      => $filters,
            'generated'    => $generated,
            'report'       => $report,
            'customers'    => Customer::all(),
            'categories'   => Category::activeList(),
            'chart'        => BalanceService::getChartData(),
        ]);
    }
}
