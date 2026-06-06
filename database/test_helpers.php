<?php
/**
 * Shared helpers for database smoke tests
 */

function test_category_id(string $name = 'Fruits'): int
{
    require_once APP_PATH . '/models/Category.php';
    $cat = Category::findByName($name);

    if (!$cat) {
        throw new RuntimeException("Category not found: {$name}");
    }

    return (int) $cat['id'];
}
