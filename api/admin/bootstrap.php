<?php
/**
 * Admin Bootstrap API
 *
 * GET api/admin/bootstrap.php
 * Returns all admin master tables + analytics summary in a single authenticated request.
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

if (getRequestMethod() !== 'GET') {
    jsonError('Method not allowed. Use GET.', 405);
}

try {
    $pdo = Database::getConnection();
    $admin = requireAdmin($pdo);

    // Products
    $products = array_map('formatProduct', $pdo->query('SELECT * FROM products ORDER BY created_at DESC, id ASC')->fetchAll());
    $countsByCat = [];
    foreach ($products as $p) {
        $countsByCat[$p['category']] = ($countsByCat[$p['category']] ?? 0) + 1;
    }

    // Categories
    $categories = [];
    foreach ($pdo->query('SELECT * FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll() as $row) {
        $liveCount = ($row['id'] === 'all') ? count($products) : ($countsByCat[$row['id']] ?? (int)$row['count']);
        $categories[] = formatCategory($row, $liveCount);
    }

    // Orders (all)
    $orders = array_map('formatOrder', $pdo->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll());

    // Reviews (all statuses: Approved, Pending, Hidden)
    $reviews = array_map('formatReview', $pdo->query('SELECT * FROM reviews ORDER BY id DESC')->fetchAll());

    // Custom Requests (all)
    $customRequests = array_map('formatCustomRequest', $pdo->query('SELECT * FROM custom_requests ORDER BY created_at DESC')->fetchAll());

    // Customers (all)
    $customers = array_map('formatCustomer', $pdo->query('SELECT * FROM customers ORDER BY created_at DESC')->fetchAll());

    // Coupons (all statuses: Active & Expired)
    $coupons = array_map('formatCoupon', $pdo->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll());

    // Contact messages (all)
    $messages = array_map('formatContactMessage', $pdo->query('SELECT * FROM contact_messages ORDER BY id DESC')->fetchAll());

    jsonSuccess([
        'admin'          => $admin,
        'products'       => $products,
        'categories'     => $categories,
        'orders'         => $orders,
        'reviews'        => $reviews,
        'customRequests' => $customRequests,
        'customers'      => $customers,
        'coupons'        => $coupons,
        'messages'       => $messages,
    ], 'Admin data loaded');
} catch (Throwable $e) {
    jsonError('Admin bootstrap error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
