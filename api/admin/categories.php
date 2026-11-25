<?php
/**
 * Admin Categories Master CRUD API
 *
 * GET    api/admin/categories.php
 * POST   api/admin/categories.php
 * PUT    api/admin/categories.php?id=...
 * DELETE api/admin/categories.php?id=...
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    requireAdmin($pdo);

    if ($method === 'GET') {
        $countRows = $pdo->query('SELECT category, COUNT(*) AS cnt FROM products GROUP BY category')->fetchAll();
        $counts = [];
        $total = 0;
        foreach ($countRows as $cr) {
            $counts[$cr['category']] = (int)$cr['cnt'];
            $total += (int)$cr['cnt'];
        }

        $rows = $pdo->query('SELECT * FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
        $categories = [];
        foreach ($rows as $r) {
            $liveCount = ($r['id'] === 'all') ? $total : ($counts[$r['id']] ?? (int)$r['count']);
            $categories[] = formatCategory($r, $liveCount);
        }
        jsonSuccess($categories, 'Categories fetched', 200, ['total' => count($categories)]);
    }

    if ($method === 'POST') {
        $input = getJsonInput();
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            jsonError('Category name is required.', 422);
        }

        $id = !empty($input['id']) ? slugify((string)$input['id']) : slugify($name);
        $icon = trim((string)($input['icon'] ?? 'grid')) ?: 'grid';
        $image = trim((string)($input['image'] ?? 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=300&q=80'));
        $description = trim((string)($input['description'] ?? ''));

        $chk = $pdo->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
        $chk->execute([$id]);
        if ($chk->fetch()) {
            jsonError("Category with slug \"{$id}\" already exists.", 409);
        }

        $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM categories')->fetchColumn();
        $ins = $pdo->prepare('INSERT INTO categories (id, name, icon, count, image, description, sort_order) VALUES (?, ?, ?, 0, ?, ?, ?)');
        $ins->execute([$id, $name, $icon, $image, $description, $maxOrder + 1]);

        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $created = formatCategory($stmt->fetch(), 0);

        jsonSuccess($created, "Category \"{$created['name']}\" added successfully!", 201);
    }

    if ($method === 'PUT') {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Category ID is required.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonError('Category not found.', 404);
        }

        $name = isset($input['name']) ? trim((string)$input['name']) : (string)$existing['name'];
        $icon = isset($input['icon']) ? trim((string)$input['icon']) : (string)$existing['icon'];
        $image = isset($input['image']) ? trim((string)$input['image']) : (string)$existing['image'];
        $description = isset($input['description']) ? trim((string)$input['description']) : (string)$existing['description'];

        $upd = $pdo->prepare('UPDATE categories SET name = ?, icon = ?, image = ?, description = ? WHERE id = ?');
        $upd->execute([$name, $icon, $image, $description, $id]);

        // Keep products.category_name synchronized when category is renamed
        if ($name !== (string)$existing['name']) {
            $sync = $pdo->prepare('UPDATE products SET category_name = ? WHERE category = ?');
            $sync->execute([$name, $id]);
        }

        $stmt->execute([$id]);
        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category = ?');
        $cntStmt->execute([$id]);
        $liveCount = ($id === 'all') ? (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() : (int)$cntStmt->fetchColumn();

        jsonSuccess(formatCategory($stmt->fetch(), $liveCount), 'Category updated successfully!');
    }

    if ($method === 'DELETE') {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Category ID is required.', 422);
        }
        if ($id === 'all') {
            jsonError('Cannot delete system category "All Products".', 400);
        }

        $del = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $del->execute([$id]);
        if ($del->rowCount() === 0) {
            jsonError('Category not found.', 404);
        }

        jsonSuccess(['id' => $id], 'Category deleted successfully.');
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Admin categories error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
