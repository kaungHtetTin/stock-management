<?php
/**
 * Expiry tracking screen.
 */

require_once APP_PATH . '/models/ExpiryTracking.php';
require_once APP_PATH . '/models/Category.php';
require_once APP_PATH . '/models/StockIn.php';
require_once APP_PATH . '/models/StockOut.php';

class ExpiryController
{
    public function index(): void
    {
        require_login();

        $filters = ExpiryTracking::filters($_GET);
        $rows = ExpiryTracking::rows($filters);

        render_app('expiry/index.php', [
            'pageTitle'    => 'Expiry Tracking - ' . APP_NAME,
            'currentNav'   => 'expiry',
            'breadcrumbs'  => [['label' => 'Expiry Tracking']],
            'pendingBadge' => is_admin() ? StockIn::countPending() + StockOut::countPending() : 0,
            'rows'         => $rows,
            'summary'      => ExpiryTracking::summary($rows),
            'categories'   => Category::activeList(),
            'filters'      => $filters,
        ]);
    }
}
