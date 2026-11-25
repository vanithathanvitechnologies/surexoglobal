<?php
/**
 * Admin Coupons & Discounts Master CRUD API
 *
 * GET    api/admin/coupons.php
 * POST   api/admin/coupons.php
 * PUT    api/admin/coupons.php?id=...
 * DELETE api/admin/coupons.php?id=...
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    requireAdmin($pdo);

    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = ? LIMIT 1');
            $stmt->execute([trim((string)$_GET['id'])]);
            $row = $stmt->fetch();
            if (!$row) {
                jsonError('Coupon not found.', 404);
            }
            jsonSuccess(formatCoupon($row), 'Coupon fetched');
        }

        $coupons = array_map('formatCoupon', $pdo->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll());
        jsonSuccess($coupons, 'Coupons fetched', 200, ['total' => count($coupons)]);
    }

    if ($method === 'POST') {
        $input = getJsonInput();
        $code = strtoupper(preg_replace('/\s+/', '', trim((string)($input['code'] ?? ''))));
        $discountValue = (float)($input['discountValue'] ?? 0);

        if ($code === '') {
            jsonError('Coupon code is required.', 422);
        }
        if ($discountValue <= 0) {
            jsonError('Valid discount value is required.', 422);
        }

        $chk = $pdo->prepare('SELECT id FROM coupons WHERE UPPER(code) = ? LIMIT 1');
        $chk->execute([$code]);
        if ($chk->fetch()) {
            jsonError("Coupon code \"{$code}\" already exists.", 409);
        }

        $maxNum = 4;
        $ids = $pdo->query("SELECT id FROM coupons WHERE id LIKE 'CPN-%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $cid) {
            $n = (int)preg_replace('/[^0-9]/', '', (string)$cid);
            if ($n > $maxNum) {
                $maxNum = $n;
            }
        }
        $id = sprintf('CPN-%03d', $maxNum + 1);

        $discountType   = in_array($input['discountType'] ?? 'fixed', ['fixed', 'percentage'], true) ? $input['discountType'] : 'fixed';
        $minOrderAmount = max(0.0, (float)($input['minOrderAmount'] ?? 0));
        $description    = trim((string)($input['description'] ?? ''));
        $usageLimit     = max(1, (int)($input['usageLimit'] ?? 100));
        $expiryDate     = !empty($input['expiryDate']) ? trim((string)$input['expiryDate']) : '2026-12-31';
        $status         = in_array($input['status'] ?? 'Active', ['Active', 'Expired'], true) ? $input['status'] : 'Active';

        $ins = $pdo->prepare(
            'INSERT INTO coupons (id, code, discount_type, discount_value, min_order_amount, description, usage_limit, times_used, expiry_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
        );
        $ins->execute([$id, $code, $discountType, $discountValue, $minOrderAmount, $description, $usageLimit, $expiryDate, $status]);

        $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $created = formatCoupon($stmt->fetch());

        jsonSuccess($created, "Coupon \"{$created['code']}\" generated successfully!", 201);
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Coupon ID is required.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonError('Coupon not found.', 404);
        }

        $code = isset($input['code'])
            ? strtoupper(preg_replace('/\s+/', '', trim((string)$input['code'])))
            : (string)$existing['code'];
        $discountType = (isset($input['discountType']) && in_array($input['discountType'], ['fixed', 'percentage'], true))
            ? $input['discountType']
            : (string)$existing['discount_type'];
        $discountValue  = isset($input['discountValue']) ? (float)$input['discountValue'] : (float)$existing['discount_value'];
        $minOrderAmount = isset($input['minOrderAmount']) ? max(0.0, (float)$input['minOrderAmount']) : (float)$existing['min_order_amount'];
        $description    = isset($input['description']) ? trim((string)$input['description']) : (string)$existing['description'];
        $usageLimit     = isset($input['usageLimit']) ? max(1, (int)$input['usageLimit']) : (int)$existing['usage_limit'];
        $expiryDate     = isset($input['expiryDate']) && $input['expiryDate'] !== '' ? trim((string)$input['expiryDate']) : $existing['expiry_date'];
        $status         = (isset($input['status']) && in_array($input['status'], ['Active', 'Expired'], true))
            ? $input['status']
            : (string)$existing['status'];

        $upd = $pdo->prepare(
            'UPDATE coupons SET code = ?, discount_type = ?, discount_value = ?, min_order_amount = ?, description = ?, usage_limit = ?, expiry_date = ?, status = ? WHERE id = ?'
        );
        $upd->execute([$code, $discountType, $discountValue, $minOrderAmount, $description, $usageLimit, $expiryDate, $status, $id]);

        $stmt->execute([$id]);
        jsonSuccess(formatCoupon($stmt->fetch()), 'Coupon details updated!');
    }

    if ($method === 'DELETE') {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Coupon ID is required.', 422);
        }

        $del = $pdo->prepare('DELETE FROM coupons WHERE id = ?');
        $del->execute([$id]);
        if ($del->rowCount() === 0) {
            jsonError('Coupon not found.', 404);
        }

        jsonSuccess(['id' => $id], 'Coupon deleted.');
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Admin coupons error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
