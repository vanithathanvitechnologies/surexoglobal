<?php
/**
 * Shared API Response, CORS, Input Parsing, and Model Formatters
 */

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Send standard CORS and JSON headers, and handle OPTIONS preflight requests.
 */
function setupApiHeaders(): void
{
    // Prevent PHP float precision noise (e.g. 4.599999999999999644...) in JSON responses on cPanel
    @ini_set('serialize_precision', '-1');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin !== '') {
        $allowed = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];
        if (in_array($origin, $allowed, true) || APP_DEBUG) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
        } else {
            header('Access-Control-Allow-Origin: *');
        }
    } else {
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Token, X-Customer-Token, X-Auth-Token, X-Session-Key, X-HTTP-Method-Override');
    header('Access-Control-Max-Age: 86400');
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Resolve HTTP method with support for method override headers/params (for shared cPanel hosts).
 */
function getRequestMethod(): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'POST') {
        $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']
            ?? $_GET['_method']
            ?? $_POST['_method']
            ?? '';
        $override = strtoupper(trim((string)$override));
        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            return $override;
        }
    }
    return $method;
}

/**
 * Decode JSON request body or return $_POST array.
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }
    return is_array($_POST) ? $_POST : [];
}

/**
 * Send standardized success JSON response and terminate.
 */
function jsonSuccess($data = null, string $message = 'OK', int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    $payload = array_merge([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ], $extra);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send standardized error JSON response and terminate.
 */
function jsonError(string $message, int $statusCode = 400, array $extra = []): void
{
    http_response_code($statusCode);
    $payload = array_merge([
        'success' => false,
        'message' => $message,
    ], $extra);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Decode a JSON text column safely into an array.
 */
function decodeJsonField($value, $default = [])
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || trim($value) === '') {
        return $default;
    }
    $decoded = json_decode($value, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $default;
}

/**
 * Replace known broken/404 seed Unsplash URLs with verified working URLs.
 */
function sanitizeImageUrl(string $url): string
{
    static $brokenMap = [
        'photo-1580481077195-c3a821a58875' => 'photo-1586023492125-27b2c045efd7',
        'photo-1584990347449-399c2794354c' => 'photo-1556911220-e15b29be8c8f',
    ];
    foreach ($brokenMap as $bad => $good) {
        if (strpos($url, $bad) !== false) {
            return str_replace($bad, $good, $url);
        }
    }
    return $url;
}

/**
 * Format a `products` table row to match the React storefront & admin schema.
 */
function formatProduct(array $row): array
{
    $image = sanitizeImageUrl((string)($row['image'] ?? ''));
    $gallery = array_map(static function ($u) {
        return sanitizeImageUrl((string)$u);
    }, decodeJsonField($row['gallery'] ?? null, []));
    if (empty($gallery) && $image !== '') {
        $gallery = [$image];
    }

    $specs = decodeJsonField($row['specifications'] ?? null, []);
    $specsObj = empty($specs) ? new stdClass() : $specs;

    return [
        'id'                      => (string)$row['id'],
        'name'                    => (string)$row['name'],
        'category'                => (string)$row['category'],
        'categoryName'            => (string)($row['category_name'] ?? $row['category']),
        'brand'                   => (string)($row['brand'] ?? 'Surexoglobal'),
        'sku'                     => (string)($row['sku'] ?? ''),
        'price'                   => (float)$row['price'],
        'originalPrice'           => ($row['original_price'] !== null && $row['original_price'] !== '') ? (float)$row['original_price'] : null,
        'discount'                => (string)($row['discount'] ?? ''),
        'rating'                  => round((float)($row['rating'] ?? 4.5), 1),
        'reviewsCount'            => (int)($row['reviews_count'] ?? 0),
        'adminDefaultRating'      => round((float)($row['admin_default_rating'] ?? $row['rating'] ?? 4.5), 1),
        'adminDefaultReviewCount' => (int)($row['admin_default_review_count'] ?? $row['reviews_count'] ?? 100),
        'customerReviewsCount'    => (int)($row['customer_reviews_count'] ?? 0),
        'inStock'                 => (bool)((int)($row['in_stock'] ?? 1)),
        'isTrending'              => (bool)((int)($row['is_trending'] ?? 0)),
        'isBestSeller'            => (bool)((int)($row['is_best_seller'] ?? 0)),
        'image'                   => $image,
        'gallery'                 => array_values($gallery),
        'colors'                  => array_values(decodeJsonField($row['colors'] ?? null, ['#0d9488'])),
        'materials'               => array_values(decodeJsonField($row['materials'] ?? null, ['Standard'])),
        'sizes'                   => array_values(decodeJsonField($row['sizes'] ?? null, ['Standard'])),
        'description'             => (string)($row['description'] ?? ''),
        'features'                => array_values(decodeJsonField($row['features'] ?? null, [])),
        'specifications'          => $specsObj,
    ];
}

/**
 * Format a `categories` table row.
 */
function formatCategory(array $row, ?int $liveCount = null): array
{
    return [
        'id'          => (string)$row['id'],
        'name'        => (string)$row['name'],
        'icon'        => (string)($row['icon'] ?? 'grid'),
        'count'       => $liveCount !== null ? $liveCount : (int)($row['count'] ?? 0),
        'image'       => (string)($row['image'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
    ];
}

/**
 * Format a `reviews` table row.
 */
function formatReview(array $row): array
{
    return [
        'id'          => (int)$row['id'],
        'productId'   => (string)$row['product_id'],
        'productName' => (string)$row['product_name'],
        'name'        => (string)$row['name'],
        'rating'      => round((float)$row['rating'], 1),
        'title'       => (string)$row['title'],
        'comment'     => (string)$row['comment'],
        'avatar'      => (string)($row['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&q=80'),
        'date'        => (string)($row['review_date'] ?: date('M d, Y', strtotime($row['created_at'] ?? 'now'))),
        'status'      => (string)($row['status'] ?? 'Approved'),
    ];
}

/**
 * Format a `customers` table row (never leaks password_hash or auth_token).
 */
function formatCustomer(array $row): array
{
    return [
        'id'          => (string)$row['id'],
        'name'        => (string)$row['name'],
        'email'       => (string)$row['email'],
        'mobile'      => (string)($row['mobile'] ?? ''),
        'avatar'      => (string)($row['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&q=80'),
        'city'        => (string)($row['city'] ?? 'Chennai'),
        'state'       => (string)($row['state'] ?? 'Tamil Nadu'),
        'totalOrders' => (int)($row['total_orders'] ?? 0),
        'totalSpent'  => (float)($row['total_spent'] ?? 0),
        'role'        => (string)($row['role'] ?? 'Customer'),
        'status'      => (string)($row['status'] ?? 'Active'),
        'joinedDate'  => (string)($row['joined_date'] ?: date('d M Y', strtotime($row['created_at'] ?? 'now'))),
    ];
}

/**
 * Format a `coupons` table row.
 */
function formatCoupon(array $row): array
{
    return [
        'id'             => (string)$row['id'],
        'code'           => strtoupper((string)$row['code']),
        'discountType'   => (string)($row['discount_type'] ?? 'fixed'),
        'discountValue'  => (float)($row['discount_value'] ?? 0),
        'minOrderAmount' => (float)($row['min_order_amount'] ?? 0),
        'description'    => (string)($row['description'] ?? ''),
        'usageLimit'     => (int)($row['usage_limit'] ?? 100),
        'timesUsed'      => (int)($row['times_used'] ?? 0),
        'expiryDate'     => (string)($row['expiry_date'] ?? ''),
        'status'         => (string)($row['status'] ?? 'Active'),
    ];
}

/**
 * Format an `orders` table row.
 */
function formatOrder(array $row): array
{
    return [
        'id'             => (string)$row['id'],
        'date'           => (string)($row['order_date'] ?: date('M d, Y', strtotime($row['created_at'] ?? 'now'))),
        'customerId'     => $row['customer_id'] ?? null,
        'customerName'   => (string)$row['customer_name'],
        'customerEmail'  => (string)$row['customer_email'],
        'customerPhone'  => (string)($row['customer_phone'] ?? ''),
        'productName'    => (string)$row['product_name'],
        'category'       => (string)($row['category'] ?? 'General'),
        'quantity'       => (int)($row['quantity'] ?? 1),
        'subtotal'       => (float)($row['subtotal'] ?? $row['total']),
        'discountAmount' => (float)($row['discount_amount'] ?? 0),
        'shippingCost'   => (float)($row['shipping_cost'] ?? 0),
        'taxAmount'      => (float)($row['tax_amount'] ?? 0),
        'total'          => (float)$row['total'],
        'couponCode'     => $row['coupon_code'] ?? null,
        'paymentMethod'  => (string)($row['payment_method'] ?? 'upi'),
        'paymentStatus'  => (string)($row['payment_status'] ?? 'Paid'),
        'orderStatus'    => (string)($row['order_status'] ?? 'Processing'),
        'image'          => sanitizeImageUrl((string)($row['image'] ?? '')),
        'trackingStep'   => (int)($row['tracking_step'] ?? 2),
        'items'          => array_values(decodeJsonField($row['items'] ?? null, [])),
        'address'        => (string)($row['address'] ?? ''),
        'deliveryType'   => (string)($row['delivery_type'] ?? 'standard'),
        'orderNotes'     => (string)($row['order_notes'] ?? ''),
        'adminNotes'     => (string)($row['admin_notes'] ?? ''),
    ];
}

/**
 * Format a `custom_requests` table row.
 */
function formatCustomRequest(array $row): array
{
    return [
        'id'                => (string)$row['id'],
        'date'              => (string)($row['request_date'] ?: date('d-m-Y', strtotime($row['created_at'] ?? 'now'))),
        'fullName'          => (string)($row['full_name'] ?? ''),
        'email'             => (string)($row['email'] ?? ''),
        'mobile'            => (string)($row['mobile'] ?? ''),
        'accountId'         => (string)($row['account_id'] ?? ''),
        'productName'       => (string)$row['product_name'],
        'category'          => (string)($row['category'] ?? 'General'),
        'subcategory'       => (string)($row['subcategory'] ?? ''),
        'brand'             => (string)($row['brand'] ?? ''),
        'status'            => (string)($row['status'] ?? 'Under Review'),
        'quantity'          => (int)($row['quantity'] ?? 1),
        'budget'            => (float)($row['budget'] ?? 0),
        'quotation'         => ($row['quotation'] !== null && $row['quotation'] !== '') ? (float)$row['quotation'] : null,
        'requirements'      => (string)($row['requirements'] ?? ''),
        'location'          => (string)($row['location'] ?? ''),
        'deliveryDate'      => (string)($row['delivery_date'] ?? ''),
        'contactPreference' => (string)($row['contact_preference'] ?? ''),
        'notes'             => (string)($row['notes'] ?? ''),
        'images'            => array_values(decodeJsonField($row['images'] ?? null, [])),
    ];
}

/**
 * Format a `contact_messages` table row.
 */
function formatContactMessage(array $row): array
{
    return [
        'id'        => (int)$row['id'],
        'fullName'  => (string)$row['full_name'],
        'email'     => (string)$row['email'],
        'mobile'    => (string)($row['mobile'] ?? ''),
        'subject'   => (string)$row['subject'],
        'message'   => (string)$row['message'],
        'status'    => (string)($row['status'] ?? 'New'),
        'createdAt' => (string)($row['created_at'] ?? ''),
    ];
}

/**
 * Recalculate a product's combined rating from admin baseline + approved customer reviews.
 */
function recalculateProductRating(PDO $pdo, string $productId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        return null;
    }

    $aggStmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(rating), 0) AS sum_rating FROM reviews WHERE product_id = ? AND status = 'Approved'");
    $aggStmt->execute([$productId]);
    $agg = $aggStmt->fetch();

    $defaultRating = (float)($product['admin_default_rating'] ?? 4.5);
    $defaultCount  = (int)($product['admin_default_review_count'] ?? 50);
    $custCount     = (int)($agg['cnt'] ?? 0);
    $custSum       = (float)($agg['sum_rating'] ?? 0);

    $totalCount = $defaultCount + $custCount;
    $newRating  = $totalCount > 0
        ? round((($defaultRating * $defaultCount) + $custSum) / $totalCount, 1)
        : $defaultRating;

    $upd = $pdo->prepare('UPDATE products SET rating = ?, reviews_count = ?, customer_reviews_count = ? WHERE id = ?');
    $upd->execute([$newRating, $totalCount, $custCount, $productId]);

    $stmt->execute([$productId]);
    $updated = $stmt->fetch();
    return $updated ? formatProduct($updated) : null;
}

/**
 * Normalize order ID (accepts both "#1005" and "1005").
 */
function resolveOrderId(PDO $pdo, string $rawId): ?string
{
    $clean = trim($rawId);
    if ($clean === '') {
        return null;
    }
    $candidates = [$clean];
    if ($clean[0] !== '#') {
        $candidates[] = '#' . $clean;
    } else {
        $candidates[] = ltrim($clean, '#');
    }

    foreach ($candidates as $cand) {
        $stmt = $pdo->prepare('SELECT id FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$cand]);
        $found = $stmt->fetchColumn();
        if ($found !== false) {
            return (string)$found;
        }
    }
    return null;
}

/**
 * Generate a URL-safe slug from text.
 */
function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string)$slug, '-');
    return $slug !== '' ? $slug : 'item-' . time();
}
