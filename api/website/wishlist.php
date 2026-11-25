<?php
/**
 * Website Wishlist Sync API
 *
 * GET    api/website/wishlist.php              (Fetch wishlist items)
 * POST   api/website/wishlist.php              (Toggle product in wishlist)
 * PUT    api/website/wishlist.php              (Bulk sync `{ productIds: [...] }`)
 * DELETE api/website/wishlist.php?productId=.. (Remove product from wishlist)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    $sessionKey = resolveSessionKey($pdo);

    if ($method === 'GET') {
        jsonSuccess(fetchSessionWishlist($pdo, $sessionKey), 'Wishlist fetched');
    }

    if ($method === 'PUT') {
        $input = getJsonInput();
        $ids = is_array($input['productIds'] ?? null) ? $input['productIds'] : [];

        $pdo->beginTransaction();
        $del = $pdo->prepare('DELETE FROM wishlist_items WHERE session_key = ?');
        $del->execute([$sessionKey]);

        $ins = $pdo->prepare('INSERT IGNORE INTO wishlist_items (session_key, product_id) VALUES (?, ?)');
        foreach ($ids as $pid) {
            $clean = trim((string)$pid);
            if ($clean !== '') {
                $ins->execute([$sessionKey, $clean]);
            }
        }
        $pdo->commit();

        jsonSuccess(fetchSessionWishlist($pdo, $sessionKey), 'Wishlist synced');
    }

    if ($method === 'POST') {
        $input = getJsonInput();
        $pid = trim((string)($input['productId'] ?? $input['id'] ?? ''));
        if ($pid === '') {
            jsonError('productId is required.', 422);
        }

        $chk = $pdo->prepare('SELECT id FROM wishlist_items WHERE session_key = ? AND product_id = ? LIMIT 1');
        $chk->execute([$sessionKey, $pid]);
        if ($chk->fetch()) {
            $del = $pdo->prepare('DELETE FROM wishlist_items WHERE session_key = ? AND product_id = ?');
            $del->execute([$sessionKey, $pid]);
            jsonSuccess(fetchSessionWishlist($pdo, $sessionKey), 'Removed from wishlist');
        }

        $ins = $pdo->prepare('INSERT INTO wishlist_items (session_key, product_id) VALUES (?, ?)');
        $ins->execute([$sessionKey, $pid]);
        jsonSuccess(fetchSessionWishlist($pdo, $sessionKey), 'Added to wishlist', 201);
    }

    if ($method === 'DELETE') {
        $pid = trim((string)($_GET['productId'] ?? $_GET['id'] ?? ''));
        if ($pid !== '') {
            $del = $pdo->prepare('DELETE FROM wishlist_items WHERE session_key = ? AND product_id = ?');
            $del->execute([$sessionKey, $pid]);
        } else {
            $del = $pdo->prepare('DELETE FROM wishlist_items WHERE session_key = ?');
            $del->execute([$sessionKey]);
        }
        jsonSuccess(fetchSessionWishlist($pdo, $sessionKey), 'Wishlist updated');
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Wishlist API error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}

function fetchSessionWishlist(PDO $pdo, string $sessionKey): array
{
    $stmt = $pdo->prepare(
        'SELECT p.*
         FROM wishlist_items w
         INNER JOIN products p ON p.id = w.product_id
         WHERE w.session_key = ?
         ORDER BY w.id ASC'
    );
    $stmt->execute([$sessionKey]);
    return array_map('formatProduct', $stmt->fetchAll());
}
