<?php
/**
 * Stock balance calculation
 */

require_once APP_PATH . '/models/Item.php';
require_once APP_PATH . '/models/Balance.php';

class BalanceService
{
    public static function getItemBalance(int $itemId): float
    {
        $item = Item::find($itemId);
        return $item ? (float) $item['balance'] : 0.0;
    }

    public static function getAllBalances(array $filters = []): array
    {
        return Balance::all($filters);
    }

    public static function getCategoryTotals(): array
    {
        return Balance::categoryTotals();
    }

    public static function getChartData(): array
    {
        return Balance::chartData();
    }

    public static function isLowStock(float $balance): bool
    {
        return $balance < (float) LOW_STOCK_THRESHOLD;
    }
}
