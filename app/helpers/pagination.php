<?php
/**
 * Shared list pagination helpers
 */

class Pagination
{
    public const PER_PAGE = 25;

    public static function pageFromRequest(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    public static function offset(int $page, int $perPage = self::PER_PAGE): int
    {
        return ($page - 1) * $perPage;
    }

    public static function result(array $rows, int $total, int $page, int $perPage = self::PER_PAGE): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);

        return [
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }
}

function list_query_string(array $filters): string
{
    $params = array_filter($filters, static fn ($value) => $value !== '' && $value !== null);

    return $params ? '?' . http_build_query($params) : '';
}
