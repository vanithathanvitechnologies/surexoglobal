<?php
/**
 * Website Bootstrap API
 * GET api/website/bootstrap.php
 *
 * Returns all initial storefront datasets in one request:
 * products, categories (with live counts), approved reviews, active coupons,
 * faqs, customer orders, custom requests, synced cart/wishlist, and current user.
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

if (getRequestMethod() !== 'GET') {
    jsonError('Method not allowed. Use GET.', 405);
}

try {
    $pdo = Database::getConnection();
    $customer = getAuthenticatedCustomer($pdo);
    $sessionKey = resolveSessionKey($pdo);

    // 1. Products
    $prodStmt = $pdo->query('SELECT * FROM products ORDER BY created_at DESC, id ASC');
    $productRows = $prodStmt->fetchAll();
    $products = array_map('formatProduct', $productRows);
    $productsById = [];
    $countsByCategory = [];
    foreach ($products as $p) {
        $productsById[$p['id']] = $p;
        $cat = $p['category'];
        $countsByCategory[$cat] = ($countsByCategory[$cat] ?? 0) + 1;
    }

    // 2. Categories (with live product counts)
    $catStmt = $pdo->query('SELECT * FROM categories ORDER BY sort_order ASC, name ASC');
    $categories = [];
    foreach ($catStmt->fetchAll() as $row) {
        $liveCount = ($row['id'] === 'all')
            ? count($products)
            : ($countsByCategory[$row['id']] ?? (int)$row['count']);
        $categories[] = formatCategory($row, $liveCount);
    }

    // 3. Approved Reviews
    $revStmt = $pdo->query("SELECT * FROM reviews WHERE status = 'Approved' ORDER BY id DESC");
    $reviews = array_map('formatReview', $revStmt->fetchAll());

    // 4. Active Coupons
    $cpnStmt = $pdo->query("SELECT * FROM coupons WHERE status = 'Active' ORDER BY created_at DESC");
    $coupons = array_map('formatCoupon', $cpnStmt->fetchAll());

    // 5. FAQs
    $faqStmt = $pdo->query('SELECT id, question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    $faqs = array_map(static function (array $r): array {
        return [
            'id'       => (int)$r['id'],
            'question' => (string)$r['question'],
            'answer'   => (string)$r['answer'],
        ];
    }, $faqStmt->fetchAll());

    // 6. Orders (strictly scoped to the logged-in customer from MySQL)
    if ($customer) {
        $ordStmt = $pdo->prepare('SELECT * FROM orders WHERE LOWER(customer_email) = LOWER(?) OR customer_id = ? ORDER BY created_at DESC');
        $ordStmt->execute([$customer['email'], $customer['id']]);
        $orders = array_map('formatOrder', $ordStmt->fetchAll());
    } else {
        $orders = [];
    }

    // 7. Custom Requests (strictly scoped to the logged-in customer from MySQL)
    if ($customer) {
        $reqStmt = $pdo->prepare('SELECT * FROM custom_requests WHERE LOWER(email) = LOWER(?) OR customer_id = ? ORDER BY created_at DESC');
        $reqStmt->execute([$customer['email'], $customer['id']]);
        $customRequests = array_map('formatCustomRequest', $reqStmt->fetchAll());
    } else {
        $customRequests = [];
    }

    // 8. Synced Cart items (only returned if present in DB for this session/customer)
    $cartStmt = $pdo->prepare('SELECT * FROM cart_items WHERE session_key = ? ORDER BY id ASC');
    $cartStmt->execute([$sessionKey]);
    $cartItems = [];
    foreach ($cartStmt->fetchAll() as $ci) {
        if (isset($productsById[$ci['product_id']])) {
            $item = $productsById[$ci['product_id']];
            $item['quantity'] = (int)$ci['quantity'];
            $item['selectedColor'] = (string)$ci['selected_color'];
            $item['selectedMaterial'] = (string)$ci['selected_material'];
            $item['selectedSize'] = (string)$ci['selected_size'];
            $cartItems[] = $item;
        }
    }

    // 9. Synced Wishlist items
    $wishStmt = $pdo->prepare('SELECT product_id FROM wishlist_items WHERE session_key = ? ORDER BY id ASC');
    $wishStmt->execute([$sessionKey]);
    $wishlistItems = [];
    foreach ($wishStmt->fetchAll() as $wi) {
        if (isset($productsById[$wi['product_id']])) {
            $wishlistItems[] = $productsById[$wi['product_id']];
        }
    }

    jsonSuccess([
        'products'       => $products,
        'categories'     => $categories,
        'reviews'        => $reviews,
        'coupons'        => $coupons,
        'faqs'           => $faqs,
        'orders'         => $orders,
        'customRequests' => $customRequests,
        'cart'           => $cartItems,
        'wishlist'       => $wishlistItems,
        'user'           => $customer,
    ], 'Storefront data loaded');
} catch (Throwable $e) {
    jsonError('Failed to load storefront data: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
