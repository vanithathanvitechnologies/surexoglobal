<?php
/**
 * Admin Dashboard Analytics API
 *
 * GET api/admin/dashboard.php
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

if (getRequestMethod() !== 'GET') {
    jsonError('Method not allowed. Use GET.', 405);
}

try {
    $pdo = Database::getConnection();
    requireAdmin($pdo);

    $totalRevenue   = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'Paid'")->fetchColumn();
    $totalOrders    = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $pendingOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Processing','Pending')")->fetchColumn();
    $totalProducts  = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $outOfStock     = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE in_stock = 0')->fetchColumn();
    $totalCustomers = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $pendingReqs    = (int)$pdo->query("SELECT COUNT(*) FROM custom_requests WHERE status IN ('Pending','Under Review')")->fetchColumn();
    $newMessages    = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'New'")->fetchColumn();

    $recentOrders  = array_map('formatOrder', $pdo->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 5')->fetchAll());
    $recentReviews = array_map('formatReview', $pdo->query('SELECT * FROM reviews ORDER BY id DESC LIMIT 4')->fetchAll());

    jsonSuccess([
        'kpis' => [
            'totalRevenue'    => $totalRevenue,
            'totalOrders'     => $totalOrders,
            'pendingOrders'   => $pendingOrders,
            'totalProducts'   => $totalProducts,
            'outOfStock'      => $outOfStock,
            'totalCustomers'  => $totalCustomers,
            'pendingRequests' => $pendingReqs,
            'unreadMessages'  => $newMessages,
        ],
        'recentOrders'  => $recentOrders,
        'recentReviews' => $recentReviews,
    ], 'Dashboard analytics loaded');
} catch (Throwable $e) {
    jsonError('Dashboard error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
