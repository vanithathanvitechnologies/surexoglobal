<?php
/**
 * Admin Products Master CRUD API
 *
 * GET    api/admin/products.php                  (List all products or single by ?id=...)
 * POST   api/admin/products.php                  (Create new product)
 * PUT    api/admin/products.php?id=...           (Update product)
 * PATCH  api/admin/products.php?id=...&action=toggle_stock  (Toggle inStock flag)
 * DELETE api/admin/products.php?id=...           (Delete product)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    requireAdmin($pdo);

    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
            $stmt->execute([trim((string)$_GET['id'])]);
            $row = $stmt->fetch();
            if (!$row) {
                jsonError('Product not found.', 404);
            }
            jsonSuccess(formatProduct($row), 'Product fetched');
        }

        $products = array_map('formatProduct', $pdo->query('SELECT * FROM products ORDER BY created_at DESC, id ASC')->fetchAll());
        jsonSuccess($products, 'Products fetched', 200, ['total' => count($products)]);
    }

    if ($method === 'POST') {
        $input = getJsonInput();
        $name = trim((string)($input['name'] ?? ''));
        $price = (float)($input['price'] ?? 0);
        $image = trim((string)($input['image'] ?? ''));

        if ($name === '') {
            jsonError('Product name is required.', 422);
        }
        if ($price <= 0) {
            jsonError('Valid price greater than 0 is required.', 422);
        }
        if ($image === '') {
            jsonError('Primary image URL is required.', 422);
        }

        $id = !empty($input['id']) ? slugify((string)$input['id']) : ('prod-' . time() . '-' . random_int(100, 999));

        // Ensure unique ID
        $chk = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
        $chk->execute([$id]);
        if ($chk->fetch()) {
            $id .= '-' . random_int(10, 99);
        }

        $category = trim((string)($input['category'] ?? 'furniture'));
        $categoryName = trim((string)($input['categoryName'] ?? ''));
        if ($categoryName === '') {
            $cStmt = $pdo->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
            $cStmt->execute([$category]);
            $categoryName = (string)($cStmt->fetchColumn() ?: $category);
        }

        $brand = trim((string)($input['brand'] ?? 'Surexoglobal')) ?: 'Surexoglobal';
        $sku = trim((string)($input['sku'] ?? '')) ?: ('SKU-' . substr((string)time(), -6));
        $originalPrice = (isset($input['originalPrice']) && $input['originalPrice'] !== '' && $input['originalPrice'] !== null)
            ? (float)$input['originalPrice']
            : null;

        $discount = trim((string)($input['discount'] ?? ''));
        if ($discount === '' && $originalPrice && $originalPrice > $price) {
            $discount = round((($originalPrice - $price) / $originalPrice) * 100) . '% OFF';
        }

        $adminDefaultRating = max(1.0, min(5.0, (float)($input['adminDefaultRating'] ?? $input['rating'] ?? 4.5)));
        $adminDefaultReviewCount = max(0, (int)($input['adminDefaultReviewCount'] ?? $input['reviewsCount'] ?? 50));
        $inStock = isset($input['inStock']) ? ($input['inStock'] ? 1 : 0) : 1;
        $isTrending = !empty($input['isTrending']) ? 1 : 0;
        $isBestSeller = !empty($input['isBestSeller']) ? 1 : 0;

        $gallery = normalizeStringArray($input['gallery'] ?? null, [$image]);
        if (empty($gallery)) {
            $gallery = [$image];
        }
        $colors = normalizeStringArray($input['colors'] ?? null, ['#0d9488']);
        $materials = normalizeStringArray($input['materials'] ?? null, ['Standard']);
        $sizes = normalizeStringArray($input['sizes'] ?? null, ['Standard']);
        $features = normalizeStringArray($input['features'] ?? null, [], "\n");
        $specifications = is_array($input['specifications'] ?? null) ? $input['specifications'] : new stdClass();
        $description = trim((string)($input['description'] ?? ''));

        $ins = $pdo->prepare(
            'INSERT INTO products (
                id, name, category, category_name, brand, sku, price, original_price, discount,
                rating, reviews_count, admin_default_rating, admin_default_review_count, customer_reviews_count,
                in_stock, is_trending, is_best_seller, image, gallery, colors, materials, sizes,
                description, features, specifications
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $id, $name, $category, $categoryName, $brand, $sku, $price, $originalPrice, $discount,
            round($adminDefaultRating, 1), $adminDefaultReviewCount, round($adminDefaultRating, 1), $adminDefaultReviewCount,
            $inStock, $isTrending, $isBestSeller, $image,
            json_encode($gallery, JSON_UNESCAPED_SLASHES),
            json_encode($colors, JSON_UNESCAPED_UNICODE),
            json_encode($materials, JSON_UNESCAPED_UNICODE),
            json_encode($sizes, JSON_UNESCAPED_UNICODE),
            $description,
            json_encode($features, JSON_UNESCAPED_UNICODE),
            json_encode($specifications, JSON_UNESCAPED_UNICODE),
        ]);

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $created = formatProduct($stmt->fetch());

        jsonSuccess($created, "Product \"{$created['name']}\" created successfully!", 201);
    }

    if ($method === 'PATCH') {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Product ID is required.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonError('Product not found.', 404);
        }

        $newStock = isset($input['inStock']) ? ($input['inStock'] ? 1 : 0) : ((int)$existing['in_stock'] ? 0 : 1);
        $upd = $pdo->prepare('UPDATE products SET in_stock = ? WHERE id = ?');
        $upd->execute([$newStock, $id]);

        $stmt->execute([$id]);
        $updated = formatProduct($stmt->fetch());
        $label = $updated['inStock'] ? 'In Stock' : 'Out of Stock';

        jsonSuccess($updated, "Stock updated: {$updated['name']} is now {$label}.");
    }

    if ($method === 'PUT') {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Product ID is required.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonError('Product not found.', 404);
        }

        $name = isset($input['name']) ? trim((string)$input['name']) : (string)$existing['name'];
        $category = isset($input['category']) ? trim((string)$input['category']) : (string)$existing['category'];
        $categoryName = isset($input['categoryName']) ? trim((string)$input['categoryName']) : (string)$existing['category_name'];
        $brand = isset($input['brand']) ? trim((string)$input['brand']) : (string)$existing['brand'];
        $sku = isset($input['sku']) ? trim((string)$input['sku']) : (string)$existing['sku'];
        $price = isset($input['price']) ? (float)$input['price'] : (float)$existing['price'];
        $originalPrice = array_key_exists('originalPrice', $input)
            ? (($input['originalPrice'] !== '' && $input['originalPrice'] !== null) ? (float)$input['originalPrice'] : null)
            : ($existing['original_price'] !== null ? (float)$existing['original_price'] : null);
        $discount = isset($input['discount']) ? trim((string)$input['discount']) : (string)$existing['discount'];
        $adminDefaultRating = isset($input['adminDefaultRating'])
            ? max(1.0, min(5.0, (float)$input['adminDefaultRating']))
            : (float)$existing['admin_default_rating'];
        $adminDefaultReviewCount = isset($input['adminDefaultReviewCount'])
            ? max(0, (int)$input['adminDefaultReviewCount'])
            : (int)$existing['admin_default_review_count'];
        $inStock = array_key_exists('inStock', $input) ? ($input['inStock'] ? 1 : 0) : (int)$existing['in_stock'];
        $isTrending = array_key_exists('isTrending', $input) ? ($input['isTrending'] ? 1 : 0) : (int)$existing['is_trending'];
        $isBestSeller = array_key_exists('isBestSeller', $input) ? ($input['isBestSeller'] ? 1 : 0) : (int)$existing['is_best_seller'];
        $image = isset($input['image']) ? trim((string)$input['image']) : (string)$existing['image'];
        $description = isset($input['description']) ? trim((string)$input['description']) : (string)$existing['description'];

        $gallery = array_key_exists('gallery', $input)
            ? normalizeStringArray($input['gallery'], [$image])
            : decodeJsonField($existing['gallery'], [$image]);
        $colors = array_key_exists('colors', $input)
            ? normalizeStringArray($input['colors'], ['#0d9488'])
            : decodeJsonField($existing['colors'], ['#0d9488']);
        $materials = array_key_exists('materials', $input)
            ? normalizeStringArray($input['materials'], ['Standard'])
            : decodeJsonField($existing['materials'], ['Standard']);
        $sizes = array_key_exists('sizes', $input)
            ? normalizeStringArray($input['sizes'], ['Standard'])
            : decodeJsonField($existing['sizes'], ['Standard']);
        $features = array_key_exists('features', $input)
            ? normalizeStringArray($input['features'], [], "\n")
            : decodeJsonField($existing['features'], []);
        $specifications = array_key_exists('specifications', $input)
            ? (is_array($input['specifications']) ? $input['specifications'] : new stdClass())
            : (decodeJsonField($existing['specifications'], []) ?: new stdClass());

        $upd = $pdo->prepare(
            'UPDATE products SET
                name = ?, category = ?, category_name = ?, brand = ?, sku = ?,
                price = ?, original_price = ?, discount = ?,
                admin_default_rating = ?, admin_default_review_count = ?,
                in_stock = ?, is_trending = ?, is_best_seller = ?,
                image = ?, gallery = ?, colors = ?, materials = ?, sizes = ?,
                description = ?, features = ?, specifications = ?
             WHERE id = ?'
        );
        $upd->execute([
            $name, $category, $categoryName, $brand, $sku,
            $price, $originalPrice, $discount,
            round($adminDefaultRating, 1), $adminDefaultReviewCount,
            $inStock, $isTrending, $isBestSeller,
            $image,
            json_encode($gallery, JSON_UNESCAPED_SLASHES),
            json_encode($colors, JSON_UNESCAPED_UNICODE),
            json_encode($materials, JSON_UNESCAPED_UNICODE),
            json_encode($sizes, JSON_UNESCAPED_UNICODE),
            $description,
            json_encode($features, JSON_UNESCAPED_UNICODE),
            json_encode($specifications, JSON_UNESCAPED_UNICODE),
            $id,
        ]);

        // Recalculate rating to reflect any change to adminDefaultRating / adminDefaultReviewCount
        $updated = recalculateProductRating($pdo, $id);
        jsonSuccess($updated, 'Product updated successfully!');
    }

    if ($method === 'DELETE') {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Product ID is required.', 422);
        }

        $del = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $del->execute([$id]);
        if ($del->rowCount() === 0) {
            jsonError('Product not found.', 404);
        }

        jsonSuccess(['id' => $id], 'Product deleted from catalog.');
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Admin products error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}

function normalizeStringArray($val, array $default = [], string $sep = ','): array
{
    if (is_array($val)) {
        $clean = array_values(array_filter(array_map(static fn($x) => trim((string)$x), $val), static fn($x) => $x !== ''));
        return !empty($clean) ? $clean : $default;
    }
    if (is_string($val) && trim($val) !== '') {
        $parts = explode($sep, $val);
        $clean = array_values(array_filter(array_map('trim', $parts), static fn($x) => $x !== ''));
        return !empty($clean) ? $clean : $default;
    }
    return $default;
}
