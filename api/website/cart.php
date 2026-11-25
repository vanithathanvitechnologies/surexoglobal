<?php
/**
 * Website Cart Sync API
 *
 * GET    api/website/cart.php              (Fetch cart items for current customer/session)
 * POST   api/website/cart.php              (Add item to cart)
 * PUT    api/website/cart.php              (Update qty OR bulk sync `{ items: [...] }`)
 * DELETE api/website/cart.php?productId=.. (Remove item or clear entire cart)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    $sessionKey = resolveSessionKey($pdo);

    if ($method === 'GET') {
        $items = fetchSessionCart($pdo, $sessionKey);
        jsonSuccess($items, 'Cart fetched');
    }

    if ($method === 'PUT') {
        $input = getJsonInput();

        // Bulk sync from frontend state
        if (isset($input['items']) && is_array($input['items'])) {
            $pdo->beginTransaction();
            $del = $pdo->prepare('DELETE FROM cart_items WHERE session_key = ?');
            $del->execute([$sessionKey]);

            $ins = $pdo->prepare(
                'INSERT INTO cart_items (session_key, product_id, quantity, selected_color, selected_material, selected_size)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), selected_color = VALUES(selected_color), selected_material = VALUES(selected_material)'
            );
            foreach ($input['items'] as $it) {
                $pid = trim((string)($it['id'] ?? $it['productId'] ?? ''));
                $qty = max(1, (int)($it['quantity'] ?? 1));
                if ($pid === '') {
                    continue;
                }
                $ins->execute([
                    $sessionKey,
                    $pid,
                    $qty,
                    (string)($it['selectedColor'] ?? ''),
                    (string)($it['selectedMaterial'] ?? ''),
                    (string)($it['selectedSize'] ?? ''),
                ]);
            }
            $pdo->commit();
            jsonSuccess(fetchSessionCart($pdo, $sessionKey), 'Cart synced');
        }

        // Single item quantity update
        $pid = trim((string)($input['productId'] ?? $input['id'] ?? ''));
        $qty = (int)($input['quantity'] ?? 1);
        if ($pid === '') {
            jsonError('productId is required.', 422);
        }
        if ($qty <= 0) {
            $del = $pdo->prepare('DELETE FROM cart_items WHERE session_key = ? AND product_id = ?');
            $del->execute([$sessionKey, $pid]);
        } else {
            $upd = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE session_key = ? AND product_id = ?');
            $upd->execute([$qty, $sessionKey, $pid]);
        }
        jsonSuccess(fetchSessionCart($pdo, $sessionKey), 'Cart updated');
    }

    if ($method === 'POST') {
        $input = getJsonInput();
        $pid = trim((string)($input['productId'] ?? $input['id'] ?? ''));
        $qty = max(1, (int)($input['quantity'] ?? 1));
        if ($pid === '') {
            jsonError('productId is required.', 422);
        }

        $ins = $pdo->prepare(
            'INSERT INTO cart_items (session_key, product_id, quantity, selected_color, selected_material, selected_size)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
        );
        $ins->execute([
            $sessionKey,
            $pid,
            $qty,
            (string)($input['selectedColor'] ?? ''),
            (string)($input['selectedMaterial'] ?? ''),
            (string)($input['selectedSize'] ?? ''),
        ]);

        jsonSuccess(fetchSessionCart($pdo, $sessionKey), 'Added to cart', 201);
    }

    if ($method === 'DELETE') {
        $pid = trim((string)($_GET['productId'] ?? $_GET['id'] ?? ''));
        if ($pid !== '') {
            $del = $pdo->prepare('DELETE FROM cart_items WHERE session_key = ? AND product_id = ?');
            $del->execute([$sessionKey, $pid]);
        } else {
            $del = $pdo->prepare('DELETE FROM cart_items WHERE session_key = ?');
            $del->execute([$sessionKey]);
        }
        jsonSuccess(fetchSessionCart($pdo, $sessionKey), 'Cart updated');
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Cart API error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}

function fetchSessionCart(PDO $pdo, string $sessionKey): array
{
    $stmt = $pdo->prepare(
        'SELECT p.*, c.quantity AS cart_qty, c.selected_color, c.selected_material, c.selected_size
         FROM cart_items c
         INNER JOIN products p ON p.id = c.product_id
         WHERE c.session_key = ?
         ORDER BY c.id ASC'
    );
    $stmt->execute([$sessionKey]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $item = formatProduct($row);
        $item['quantity'] = (int)$row['cart_qty'];
        $item['selectedColor'] = (string)$row['selected_color'];
        $item['selectedMaterial'] = (string)$row['selected_material'];
        $item['selectedSize'] = (string)$row['selected_size'];
        $out[] = $item;
    }
    return $out;
}
