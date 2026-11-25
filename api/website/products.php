<?php
/**
 * Website Products API
 *
 * GET api/website/products.php
 *   Query params: id, category, search, brand, minPrice, maxPrice, inStock, trending, bestSeller, sort
 */

require_once dirname(__DIR__) . '/helpers/response.php';

setupApiHeaders();

if (getRequestMethod() !== 'GET') {
    jsonError('Method not allowed. Use GET.', 405);
}

try {
    $pdo = Database::getConnection();

    // Single product by ID
    if (!empty($_GET['id'])) {
        $id = trim((string)$_GET['id']);
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            jsonError('Product not found.', 404);
        }

        $product = formatProduct($row);

        $revStmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? AND status = 'Approved' ORDER BY id DESC");
        $revStmt->execute([$id]);
        $product['reviews'] = array_map('formatReview', $revStmt->fetchAll());

        jsonSuccess($product, 'Product fetched');
    }

    // Filtered catalog list
    $where = [];
    $params = [];

    if (!empty($_GET['category']) && $_GET['category'] !== 'all') {
        $where[] = 'category = ?';
        $params[] = trim((string)$_GET['category']);
    }

    if (!empty($_GET['search'])) {
        $term = '%' . trim((string)$_GET['search']) . '%';
        $where[] = '(name LIKE ? OR brand LIKE ? OR category_name LIKE ? OR sku LIKE ? OR description LIKE ?)';
        array_push($params, $term, $term, $term, $term, $term);
    }

    if (!empty($_GET['brand'])) {
        $where[] = 'brand = ?';
        $params[] = trim((string)$_GET['brand']);
    }

    if (isset($_GET['minPrice']) && $_GET['minPrice'] !== '') {
        $where[] = 'price >= ?';
        $params[] = (float)$_GET['minPrice'];
    }

    if (isset($_GET['maxPrice']) && $_GET['maxPrice'] !== '') {
        $where[] = 'price <= ?';
        $params[] = (float)$_GET['maxPrice'];
    }

    if (isset($_GET['inStock']) && $_GET['inStock'] !== '') {
        $where[] = 'in_stock = ?';
        $params[] = filter_var($_GET['inStock'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    if (isset($_GET['trending']) && filter_var($_GET['trending'], FILTER_VALIDATE_BOOLEAN)) {
        $where[] = 'is_trending = 1';
    }

    if (isset($_GET['bestSeller']) && filter_var($_GET['bestSeller'], FILTER_VALIDATE_BOOLEAN)) {
        $where[] = 'is_best_seller = 1';
    }

    $sort = (string)($_GET['sort'] ?? 'recommended');
    switch ($sort) {
        case 'price-low':
        case 'price_asc':
            $orderBy = 'price ASC';
            break;
        case 'price-high':
        case 'price_desc':
            $orderBy = 'price DESC';
            break;
        case 'rating':
            $orderBy = 'rating DESC, reviews_count DESC';
            break;
        case 'newest':
            $orderBy = 'created_at DESC';
            break;
        default:
            $orderBy = 'is_best_seller DESC, is_trending DESC, created_at DESC';
            break;
    }

    $sql = 'SELECT * FROM products';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $orderBy;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = array_map('formatProduct', $stmt->fetchAll());

    jsonSuccess($products, 'Products fetched', 200, ['total' => count($products)]);
} catch (Throwable $e) {
    jsonError('Failed to fetch products: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
