<?php
/**
 * Website Coupons API
 *
 * GET  api/website/coupons.php   (List active promotional coupons)
 * POST api/website/coupons.php   (Validate & calculate discount for a coupon code + subtotal)
 */

require_once dirname(__DIR__) . '/helpers/response.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM coupons WHERE status = 'Active' ORDER BY created_at DESC");
        $coupons = array_map('formatCoupon', $stmt->fetchAll());
        jsonSuccess($coupons, 'Active coupons fetched');
    }

    if ($method === 'POST') {
        $input = getJsonInput();
        $code = strtoupper(trim((string)($input['code'] ?? '')));
        $subtotal = (float)($input['subtotal'] ?? 0);

        if ($code === '') {
            jsonError('Please enter a coupon code.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        if (!$row || $row['status'] !== 'Active') {
            jsonError('Invalid or expired coupon code.', 404);
        }

        if (!empty($row['expiry_date']) && strtotime($row['expiry_date'] . ' 23:59:59') < time()) {
            jsonError('This coupon code has expired.', 400);
        }

        $usageLimit = (int)($row['usage_limit'] ?? 0);
        $timesUsed  = (int)($row['times_used'] ?? 0);
        if ($usageLimit > 0 && $timesUsed >= $usageLimit) {
            jsonError('This coupon has reached its redemption limit.', 400);
        }

        $minOrder = (float)($row['min_order_amount'] ?? 0);
        if ($subtotal > 0 && $minOrder > 0 && $subtotal < $minOrder) {
            jsonError('Minimum order amount of ₹' . number_format($minOrder, 0) . ' required for this coupon.', 400);
        }

        $discountValue = (float)$row['discount_value'];
        $discountAmount = ($row['discount_type'] === 'percentage')
            ? round(($subtotal * $discountValue) / 100)
            : $discountValue;

        if ($subtotal > 0 && $discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }

        jsonSuccess([
            'coupon'         => formatCoupon($row),
            'code'           => $code,
            'discountAmount' => (float)$discountAmount,
        ], "Coupon \"{$code}\" applied! You saved ₹" . number_format($discountAmount, 0));
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Coupons API error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
