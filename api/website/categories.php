<?php
/**
 * Website Categories API
 *
 * GET api/website/categories.php
 */

require_once dirname(__DIR__) . '/helpers/response.php';

setupApiHeaders();

if (getRequestMethod() !== 'GET') {
    jsonError('Method not allowed. Use GET.', 405);
}

try {
    $pdo = Database::getConnection();

    $countRows = $pdo->query('SELECT category, COUNT(*) AS cnt FROM products GROUP BY category')->fetchAll();
    $counts = [];
    $totalProducts = 0;
    foreach ($countRows as $cr) {
        $counts[$cr['category']] = (int)$cr['cnt'];
        $totalProducts += (int)$cr['cnt'];
    }

    $stmt = $pdo->query('SELECT * FROM categories ORDER BY sort_order ASC, name ASC');
    $categories = [];
    foreach ($stmt->fetchAll() as $row) {
        $liveCount = ($row['id'] === 'all') ? $totalProducts : ($counts[$row['id']] ?? (int)$row['count']);
        $categories[] = formatCategory($row, $liveCount);
    }

    jsonSuccess($categories, 'Categories fetched', 200, ['total' => count($categories)]);
} catch (Throwable $e) {
    jsonError('Failed to fetch categories: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
