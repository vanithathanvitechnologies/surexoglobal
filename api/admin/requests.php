<?php
/**
 * Admin Custom Product Requests Master CRUD API
 *
 * GET    api/admin/requests.php          (List all custom requests or single by ?id=PRQ-1001)
 * PUT    api/admin/requests.php?id=...   (Update status, quotation, notes)
 * DELETE api/admin/requests.php?id=...   (Delete request)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    requireAdmin($pdo);

    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM custom_requests WHERE id = ? LIMIT 1');
            $stmt->execute([trim((string)$_GET['id'])]);
            $row = $stmt->fetch();
            if (!$row) {
                jsonError('Custom request not found.', 404);
            }
            jsonSuccess(formatCustomRequest($row), 'Custom request fetched');
        }

        $requests = array_map('formatCustomRequest', $pdo->query('SELECT * FROM custom_requests ORDER BY created_at DESC')->fetchAll());
        jsonSuccess($requests, 'Custom requests fetched', 200, ['total' => count($requests)]);
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Request ID is required.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM custom_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonError('Custom request not found.', 404);
        }

        $status = isset($input['status']) ? trim((string)$input['status']) : (string)$existing['status'];
        $quotation = array_key_exists('quotation', $input)
            ? (($input['quotation'] !== '' && $input['quotation'] !== null) ? (float)$input['quotation'] : null)
            : ($existing['quotation'] !== null ? (float)$existing['quotation'] : null);
        $notes = array_key_exists('notes', $input) ? trim((string)$input['notes']) : (string)($existing['notes'] ?? '');

        $upd = $pdo->prepare('UPDATE custom_requests SET status = ?, quotation = ?, notes = ? WHERE id = ?');
        $upd->execute([$status, $quotation, $notes, $id]);

        $stmt->execute([$id]);
        jsonSuccess(formatCustomRequest($stmt->fetch()), 'Product request status updated!');
    }

    if ($method === 'DELETE') {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Request ID is required.', 422);
        }

        $del = $pdo->prepare('DELETE FROM custom_requests WHERE id = ?');
        $del->execute([$id]);
        if ($del->rowCount() === 0) {
            jsonError('Custom request not found.', 404);
        }

        jsonSuccess(['id' => $id], 'Product request removed.');
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Admin requests error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
