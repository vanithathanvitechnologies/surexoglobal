<?php
/**
 * Authentication & Token Helpers (Admin + Customer)
 *
 * Supports Authorization: Bearer <token>, X-Admin-Token / X-Customer-Token / X-Auth-Token headers
 * (resilient on cPanel shared hosting where Apache sometimes strips Authorization headers).
 */

require_once __DIR__ . '/response.php';

/**
 * Generate a cryptographically secure random hex token.
 */
function generateSecureToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Extract auth token from request headers safely.
 */
function extractAuthToken(array $customHeaders = ['HTTP_X_AUTH_TOKEN']): ?string
{
    // 1. Check explicit custom headers first (most reliable on cPanel)
    foreach ($customHeaders as $serverKey) {
        if (!empty($_SERVER[$serverKey])) {
            return trim((string)$_SERVER[$serverKey]);
        }
    }

    // 2. Check standard Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($authHeader === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                $lk = strtolower((string)$k);
                if ($lk === 'authorization') {
                    $authHeader = (string)$v;
                    break;
                }
            }
        }
    }

    if ($authHeader !== '' && preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        return trim($m[1]);
    }

    return null;
}

/**
 * Fetch currently authenticated admin from DB using token, or null if not authenticated.
 */
function getAuthenticatedAdmin(PDO $pdo): ?array
{
    $token = extractAuthToken(['HTTP_X_ADMIN_TOKEN', 'HTTP_X_AUTH_TOKEN']);
    if (!$token) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, name, email, role, status, auth_token, token_expires_at
         FROM admins
         WHERE auth_token = ? AND status = 'Active' AND (token_expires_at IS NULL OR token_expires_at > NOW())
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'id'          => (int)$row['id'],
        'name'        => (string)$row['name'],
        'email'       => (string)$row['email'],
        'role'        => (string)$row['role'],
        'isLoggedIn'  => true,
        'authVersion' => ADMIN_AUTH_VERSION,
        'token'       => (string)$row['auth_token'],
    ];
}

/**
 * Require a valid authenticated admin, else terminate with 401.
 */
function requireAdmin(PDO $pdo): array
{
    $admin = getAuthenticatedAdmin($pdo);
    if (!$admin) {
        jsonError('Unauthorized: Admin authentication required.', 401);
    }
    return $admin;
}

/**
 * Fetch currently authenticated customer from DB using token, or null.
 */
function getAuthenticatedCustomer(PDO $pdo): ?array
{
    $token = extractAuthToken(['HTTP_X_CUSTOMER_TOKEN', 'HTTP_X_AUTH_TOKEN']);
    if (!$token) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM customers
         WHERE auth_token = ? AND status = 'Active' AND (token_expires_at IS NULL OR token_expires_at > NOW())
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $customer = formatCustomer($row);
    $customer['isLoggedIn'] = true;
    $customer['token'] = (string)$row['auth_token'];
    return $customer;
}

/**
 * Require an authenticated customer, else terminate with 401.
 */
function requireCustomer(PDO $pdo): array
{
    $customer = getAuthenticatedCustomer($pdo);
    if (!$customer) {
        jsonError('Unauthorized: Customer login required.', 401);
    }
    return $customer;
}

/**
 * Resolve a cart/wishlist session key from authenticated customer or X-Session-Key header.
 */
function resolveSessionKey(PDO $pdo): string
{
    $customer = getAuthenticatedCustomer($pdo);
    if ($customer) {
        return 'cust:' . $customer['id'];
    }

    $headerKey = $_SERVER['HTTP_X_SESSION_KEY'] ?? ($_GET['sessionKey'] ?? '');
    $clean = preg_replace('/[^a-zA-Z0-9_\-:]/', '', (string)$headerKey);
    if ($clean !== '') {
        return substr($clean, 0, 100);
    }

    return 'guest:default';
}
