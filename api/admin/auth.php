<?php
/**
 * Admin Authentication API
 *
 * POST api/admin/auth.php?action=login   (Body: { email, password, remember })
 * POST api/admin/auth.php?action=logout
 * GET  api/admin/auth.php?action=me      (Verify current admin token)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();
$input  = getJsonInput();
$action = strtolower(trim((string)($_GET['action'] ?? ($input['action'] ?? ($method === 'GET' ? 'me' : 'login')))));

try {
    $pdo = Database::getConnection();

    // Verify current session
    if ($action === 'me' && $method === 'GET') {
        $admin = getAuthenticatedAdmin($pdo);
        if (!$admin) {
            jsonError('Admin session expired or not authenticated.', 401);
        }
        jsonSuccess($admin, 'Admin session valid');
    }

    // Login
    if ($action === 'login' && $method === 'POST') {
        $email    = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $remember = !isset($input['remember']) || filter_var($input['remember'], FILTER_VALIDATE_BOOLEAN);

        if ($email === '' || $password === '') {
            jsonError('Please enter your corporate email address and password.', 422);
        }

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE LOWER(email) = LOWER(?) AND status = 'Active' LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, (string)$row['password_hash'])) {
            jsonError('The email address or password is incorrect. Please try again.', 401);
        }

        $token = generateSecureToken();
        $ttlDays = $remember ? TOKEN_TTL_DAYS : 1;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttlDays} days"));

        $upd = $pdo->prepare('UPDATE admins SET auth_token = ?, token_expires_at = ?, last_login_at = NOW() WHERE id = ?');
        $upd->execute([$token, $expiresAt, $row['id']]);

        $adminData = [
            'id'          => (int)$row['id'],
            'name'        => (string)$row['name'],
            'email'       => (string)$row['email'],
            'role'        => (string)$row['role'],
            'isLoggedIn'  => true,
            'authVersion' => ADMIN_AUTH_VERSION,
            'token'       => $token,
        ];

        jsonSuccess($adminData, 'Admin logged in successfully!');
    }

    // Logout
    if ($action === 'logout' && $method === 'POST') {
        $admin = getAuthenticatedAdmin($pdo);
        if ($admin) {
            $upd = $pdo->prepare('UPDATE admins SET auth_token = NULL, token_expires_at = NULL WHERE id = ?');
            $upd->execute([$admin['id']]);
        }
        jsonSuccess(null, 'Admin logged out.');
    }

    jsonError('Invalid action or HTTP method.', 400);
} catch (Throwable $e) {
    jsonError('Admin auth error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
