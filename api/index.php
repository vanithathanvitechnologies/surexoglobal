<?php
/**
 * Surexoglobal REST API Entry Point & Clean URL Router
 *
 * Works BOTH via:
 *   1. Direct PHP paths:   ./api/website/products.php, ./api/admin/products.php
 *   2. Clean REST routes:  ./api/website/products, ./api/admin/products/prod-sofa
 *   3. Health check:       ./api/  or  ./api/index.php
 */

require_once __DIR__ . '/helpers/response.php';

setupApiHeaders();

$route = trim((string)($_GET['route'] ?? ''), '/');

if ($route === '' || $route === 'health') {
    $dbConnected = false;
    try {
        Database::getConnection()->query('SELECT 1');
        $dbConnected = true;
    } catch (Throwable $e) {
        $dbConnected = false;
    }

    jsonSuccess([
        'service'     => 'Surexoglobal PHP + MySQL API',
        'version'     => '1.0.0',
        'environment' => APP_ENV,
        'database'    => $dbConnected ? 'connected' : 'not_connected',
        'endpoints'   => [
            'website' => [
                'GET  ./api/website/bootstrap.php',
                'GET  ./api/website/products.php',
                'GET  ./api/website/categories.php',
                'GET|POST ./api/website/reviews.php',
                'GET|POST ./api/website/orders.php',
                'GET|POST ./api/website/coupons.php',
                'GET|POST ./api/website/custom_requests.php',
                'POST ./api/website/contact.php',
                'GET|POST|PUT ./api/website/auth.php',
                'GET|POST|PUT|DELETE ./api/website/cart.php',
                'GET|POST|PUT|DELETE ./api/website/wishlist.php',
                'POST ./api/website/upload.php',
            ],
            'admin' => [
                'GET|POST ./api/admin/auth.php',
                'GET ./api/admin/bootstrap.php',
                'GET ./api/admin/dashboard.php',
                'GET|POST|PUT|PATCH|DELETE ./api/admin/products.php',
                'GET|POST|PUT|DELETE ./api/admin/categories.php',
                'GET|PUT|DELETE ./api/admin/orders.php',
                'GET|POST|PUT|DELETE ./api/admin/reviews.php',
                'GET|PUT|DELETE ./api/admin/requests.php',
                'GET|POST|PUT|DELETE ./api/admin/customers.php',
                'GET|POST|PUT|DELETE ./api/admin/coupons.php',
                'GET|PUT|DELETE ./api/admin/messages.php',
                'POST ./api/admin/upload.php',
            ],
        ],
    ], 'Surexoglobal API is running');
}

// Dispatch clean REST URLs like /api/website/products/prod-sofa or /api/admin/orders/%231005
$parts = explode('/', $route);
$scope = $parts[0] ?? '';
$resource = $parts[1] ?? '';
$itemId = $parts[2] ?? null;

$allowedScopes = ['website', 'admin'];
if (!in_array($scope, $allowedScopes, true) || $resource === '') {
    jsonError('API endpoint not found.', 404);
}

// Normalize dashed resource names (e.g. custom-requests -> custom_requests)
$normalized = str_replace('-', '_', preg_replace('/[^a-zA-Z0-9_\-]/', '', $resource));
$targetFile = __DIR__ . DIRECTORY_SEPARATOR . $scope . DIRECTORY_SEPARATOR . $normalized . '.php';

if (!is_file($targetFile)) {
    jsonError("Endpoint '{$scope}/{$resource}' not found.", 404);
}

if ($itemId !== null && $itemId !== '' && empty($_GET['id'])) {
    $_GET['id'] = urldecode($itemId);
}

require $targetFile;
