<?php
/**
 * Admin Contact Messages API
 *
 * GET    api/admin/messages.php
 * PUT    api/admin/messages.php?id=...   (Update status: New / Read / Replied)
 * DELETE api/admin/messages.php?id=...
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    requireAdmin($pdo);

    if ($method === 'GET') {
        $messages = array_map('formatContactMessage', $pdo->query('SELECT * FROM contact_messages ORDER BY id DESC')->fetchAll());
        jsonSuccess($messages, 'Contact messages fetched', 200, ['total' => count($messages)]);
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $input = getJsonInput();
        $id = (int)($_GET['id'] ?? ($input['id'] ?? 0));
        if ($id <= 0) {
            jsonError('Message ID is required.', 422);
        }

        $status = trim((string)($input['status'] ?? 'Read'));
        if (!in_array($status, ['New', 'Read', 'Replied'], true)) {
            $status = 'Read';
        }

        $upd = $pdo->prepare('UPDATE contact_messages SET status = ? WHERE id = ?');
        $upd->execute([$status, $id]);

        $stmt = $pdo->prepare('SELECT * FROM contact_messages WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonError('Message not found.', 404);
        }

        jsonSuccess(formatContactMessage($row), 'Message status updated.');
    }

    if ($method === 'DELETE') {
        $input = getJsonInput();
        $id = (int)($_GET['id'] ?? ($input['id'] ?? 0));
        if ($id <= 0) {
            jsonError('Message ID is required.', 422);
        }

        $del = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
        $del->execute([$id]);
        if ($del->rowCount() === 0) {
            jsonError('Message not found.', 404);
        }

        jsonSuccess(['id' => $id], 'Message deleted.');
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Admin messages error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
