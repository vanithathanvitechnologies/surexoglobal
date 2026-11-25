<?php
/**
 * Website Reviews API
 *
 * GET  api/website/reviews.php?productId=prod-sofa
 * POST api/website/reviews.php   (Submit customer review & recalculate product rating)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();

    if ($method === 'GET') {
        $productId = trim((string)($_GET['productId'] ?? ''));
        if ($productId !== '') {
            $stmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? AND status = 'Approved' ORDER BY id DESC");
            $stmt->execute([$productId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM reviews WHERE status = 'Approved' ORDER BY id DESC");
        }
        $reviews = array_map('formatReview', $stmt->fetchAll());
        jsonSuccess($reviews, 'Reviews fetched', 200, ['total' => count($reviews)]);
    }

    if ($method === 'POST') {
        $input = getJsonInput();
        $customer = getAuthenticatedCustomer($pdo);

        $productId = trim((string)($input['productId'] ?? ''));
        $name = trim((string)($input['name'] ?? ($customer['name'] ?? 'Verified Customer')));
        $rating = (float)($input['rating'] ?? 5);
        $title = trim((string)($input['title'] ?? ''));
        $comment = trim((string)($input['comment'] ?? ''));
        $avatar = trim((string)($input['avatar'] ?? ($customer['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&q=80')));

        if ($productId === '') {
            jsonError('productId is required.', 422);
        }
        if ($title === '' || $comment === '') {
            jsonError('Review title and comment are required.', 422);
        }
        if ($rating < 1 || $rating > 5) {
            $rating = max(1.0, min(5.0, $rating));
        }

        // Look up product name from DB
        $pStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? LIMIT 1');
        $pStmt->execute([$productId]);
        $prod = $pStmt->fetch();
        $productName = $prod ? (string)$prod['name'] : trim((string)($input['productName'] ?? 'Product'));

        $reviewDate = date('M d, Y');

        $ins = $pdo->prepare(
            'INSERT INTO reviews (product_id, product_name, customer_id, name, rating, title, comment, avatar, review_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $productId,
            $productName,
            $customer['id'] ?? null,
            $name !== '' ? $name : 'Verified Customer',
            round($rating, 1),
            $title,
            $comment,
            $avatar,
            $reviewDate,
            'Approved',
        ]);

        $newId = (int)$pdo->lastInsertId();
        $rowStmt = $pdo->prepare('SELECT * FROM reviews WHERE id = ?');
        $rowStmt->execute([$newId]);
        $review = formatReview($rowStmt->fetch());

        // Recalculate product rating from baseline + approved reviews
        $updatedProduct = recalculateProductRating($pdo, $productId);

        jsonSuccess([
            'review'  => $review,
            'product' => $updatedProduct,
        ], 'Thank you! Your review has been submitted.', 201);
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Reviews API error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
