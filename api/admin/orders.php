<?php
/**
 * Admin Orders Master CRUD API
 *
 * GET    api/admin/orders.php          (List all orders or single by ?id=%231005)
 * PUT    api/admin/orders.php?id=...   (Update orderStatus, trackingStep, paymentStatus, adminNotes)
 * DELETE api/admin/orders.php?id=...   (Delete order)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    requireAdmin($pdo);

    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            $orderId = resolveOrderId($pdo, (string)$_GET['id']);
            if (!$orderId) {
                jsonError('Order not found.', 404);
            }
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
            $stmt->execute([$orderId]);
            jsonSuccess(formatOrder($stmt->fetch()), 'Order fetched');
        }

        $orders = array_map('formatOrder', $pdo->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll());
        jsonSuccess($orders, 'Orders fetched', 200, ['total' => count($orders)]);
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $input = getJsonInput();
        $rawId = (string)($_GET['id'] ?? ($input['id'] ?? ''));
        $orderId = resolveOrderId($pdo, $rawId);
        if (!$orderId) {
            jsonError('Order not found.', 404);
        }

        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $existing = $stmt->fetch();

        $orderStatus   = isset($input['orderStatus']) ? trim((string)$input['orderStatus']) : (string)$existing['order_status'];
        $trackingStep  = isset($input['trackingStep']) ? max(1, min(5, (int)$input['trackingStep'])) : (int)$existing['tracking_step'];
        $paymentStatus = isset($input['paymentStatus']) ? trim((string)$input['paymentStatus']) : (string)$existing['payment_status'];
        $adminNotes    = array_key_exists('adminNotes', $input) ? trim((string)$input['adminNotes']) : (string)($existing['admin_notes'] ?? '');

        $upd = $pdo->prepare(
            'UPDATE orders SET order_status = ?, tracking_step = ?, payment_status = ?, admin_notes = ? WHERE id = ?'
        );
        $upd->execute([$orderStatus, $trackingStep, $paymentStatus, $adminNotes, $orderId]);

        $stmt->execute([$orderId]);
        $updated = formatOrder($stmt->fetch());

        jsonSuccess($updated, "Order {$orderId} updated successfully!");
    }

    if ($method === 'DELETE') {
        $input = getJsonInput();
        $rawId = (string)($_GET['id'] ?? ($input['id'] ?? ''));
        $orderId = resolveOrderId($pdo, $rawId);
        if (!$orderId) {
            jsonError('Order not found.', 404);
        }

        $del = $pdo->prepare('DELETE FROM orders WHERE id = ?');
        $del->execute([$orderId]);
        jsonSuccess(['id' => $orderId], "Order {$orderId} removed.");
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Admin orders error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
