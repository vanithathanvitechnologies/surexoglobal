<?php
/**
 * Admin Reviews & Ratings Master CRUD API
 *
 * GET    api/admin/reviews.php
 * POST   api/admin/reviews.php          (Create admin default review/rating + recalculate product score)
 * PUT    api/admin/reviews.php?id=...   (Edit/moderate review + recalculate product score)
 * DELETE api/admin/reviews.php?id=...   (Delete review + recalculate product score)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    requireAdmin($pdo);

    if ($method === 'GET') {
        $reviews = array_map('formatReview', $pdo->query('SELECT * FROM reviews ORDER BY id DESC')->fetchAll());
        jsonSuccess($reviews, 'Reviews fetched', 200, ['total' => count($reviews)]);
    }

    if ($method === 'POST') {
        $input = getJsonInput();
        $productId = trim((string)($input['productId'] ?? ''));
        $name      = trim((string)($input['name'] ?? 'Admin Default Reviewer'));
        $rating    = max(1.0, min(5.0, (float)($input['rating'] ?? 5)));
        $title     = trim((string)($input['title'] ?? ''));
        $comment   = trim((string)($input['comment'] ?? ''));
        $status    = trim((string)($input['status'] ?? 'Approved'));
        $avatar    = trim((string)($input['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&q=80'));

        if ($productId === '' || $title === '' || $comment === '') {
            jsonError('productId, title, and comment are required.', 422);
        }

        if (!in_array($status, ['Approved', 'Pending', 'Hidden'], true)) {
            $status = 'Approved';
        }

        $pStmt = $pdo->prepare('SELECT name FROM products WHERE id = ? LIMIT 1');
        $pStmt->execute([$productId]);
        $productName = (string)($pStmt->fetchColumn() ?: ($input['productName'] ?? 'Product'));

        $reviewDate = date('M d, Y');

        $ins = $pdo->prepare(
            'INSERT INTO reviews (product_id, product_name, name, rating, title, comment, avatar, review_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$productId, $productName, $name, round($rating, 1), $title, $comment, $avatar, $reviewDate, $status]);

        $newId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM reviews WHERE id = ? LIMIT 1');
        $stmt->execute([$newId]);
        $review = formatReview($stmt->fetch());

        $updatedProduct = recalculateProductRating($pdo, $productId);

        jsonSuccess([
            'review'  => $review,
            'product' => $updatedProduct,
        ], 'Review created successfully!', 201);
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $input = getJsonInput();
        $id = (int)($_GET['id'] ?? ($input['id'] ?? 0));
        if ($id <= 0) {
            jsonError('Review ID is required.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM reviews WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonError('Review not found.', 404);
        }

        $oldProductId = (string)$existing['product_id'];
        $productId    = isset($input['productId']) ? trim((string)$input['productId']) : $oldProductId;
        $productName  = isset($input['productName']) ? trim((string)$input['productName']) : (string)$existing['product_name'];
        $name         = isset($input['name']) ? trim((string)$input['name']) : (string)$existing['name'];
        $rating       = isset($input['rating']) ? max(1.0, min(5.0, (float)$input['rating'])) : (float)$existing['rating'];
        $title        = isset($input['title']) ? trim((string)$input['title']) : (string)$existing['title'];
        $comment      = isset($input['comment']) ? trim((string)$input['comment']) : (string)$existing['comment'];
        $avatar       = isset($input['avatar']) ? trim((string)$input['avatar']) : (string)$existing['avatar'];
        $status       = isset($input['status']) ? trim((string)$input['status']) : (string)$existing['status'];

        if (!in_array($status, ['Approved', 'Pending', 'Hidden'], true)) {
            $status = (string)$existing['status'];
        }

        $upd = $pdo->prepare(
            'UPDATE reviews SET product_id = ?, product_name = ?, name = ?, rating = ?, title = ?, comment = ?, avatar = ?, status = ? WHERE id = ?'
        );
        $upd->execute([$productId, $productName, $name, round($rating, 1), $title, $comment, $avatar, $status, $id]);

        $stmt->execute([$id]);
        $review = formatReview($stmt->fetch());

        $updatedProduct = recalculateProductRating($pdo, $productId);
        if ($oldProductId !== $productId) {
            recalculateProductRating($pdo, $oldProductId);
        }

        jsonSuccess([
            'review'  => $review,
            'product' => $updatedProduct,
        ], 'Review updated successfully!');
    }

    if ($method === 'DELETE') {
        $input = getJsonInput();
        $id = (int)($_GET['id'] ?? ($input['id'] ?? 0));
        if ($id <= 0) {
            jsonError('Review ID is required.', 422);
        }

        $stmt = $pdo->prepare('SELECT product_id FROM reviews WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $productId = $stmt->fetchColumn();
        if ($productId === false) {
            jsonError('Review not found.', 404);
        }

        $del = $pdo->prepare('DELETE FROM reviews WHERE id = ?');
        $del->execute([$id]);

        $updatedProduct = recalculateProductRating($pdo, (string)$productId);

        jsonSuccess([
            'id'      => $id,
            'product' => $updatedProduct,
        ], 'Review deleted.');
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Admin reviews error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
