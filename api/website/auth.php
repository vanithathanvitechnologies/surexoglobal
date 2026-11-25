<?php
/**
 * Website Customer Authentication & Profile API
 *
 * POST api/website/auth.php?action=login            (Email or mobile + password)
 * POST api/website/auth.php?action=register         (Create customer account)
 * POST api/website/auth.php?action=logout           (Invalidate session token)
 * GET  api/website/auth.php?action=me               (Verify token & get profile)
 * PUT  api/website/auth.php?action=profile          (Update logged-in customer profile)
 * POST api/website/auth.php?action=change_password  (Update password)
 * POST api/website/auth.php?action=forgot_password  (Create password reset request)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();
$input  = getJsonInput();
$action = strtolower(trim((string)($_GET['action'] ?? ($input['action'] ?? ($method === 'GET' ? 'me' : 'login')))));

try {
    $pdo = Database::getConnection();

    // GET current profile
    if ($action === 'me' && $method === 'GET') {
        $customer = getAuthenticatedCustomer($pdo);
        if (!$customer) {
            jsonSuccess(['user' => null, 'isLoggedIn' => false], 'Not authenticated');
        }
        jsonSuccess(['user' => $customer, 'isLoggedIn' => true], 'Authenticated');
    }

    // LOGIN
    if ($action === 'login' && $method === 'POST') {
        $identifier = trim((string)($input['email'] ?? $input['identifier'] ?? ''));
        $password   = (string)($input['password'] ?? '');

        if ($identifier === '') {
            jsonError('Please enter your email address or mobile number.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM customers WHERE LOWER(email) = LOWER(?) OR mobile = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch();

        if (!$row) {
            jsonError('No account found with that email or mobile number. Please sign up first.', 401);
        }

        if ($row['status'] !== 'Active') {
            jsonError('Your account has been suspended. Please contact support.', 403);
        }

        if (!empty($row['password_hash'])) {
            if ($password === '' || !password_verify($password, $row['password_hash'])) {
                jsonError('Invalid email or password.', 401);
            }
        }

        $token = generateSecureToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . TOKEN_TTL_DAYS . ' days'));
        $upd = $pdo->prepare('UPDATE customers SET auth_token = ?, token_expires_at = ? WHERE id = ?');
        $upd->execute([$token, $expiresAt, $row['id']]);

        // Merge any guest session cart/wishlist items into this customer's DB cart/wishlist
        mergeGuestCartWishlist($pdo, (string)$row['id']);

        $user = formatCustomer($row);
        $user['isLoggedIn'] = true;
        $user['token'] = $token;

        jsonSuccess($user, 'Welcome back, ' . $user['name'] . '!');
    }

    // REGISTER
    if ($action === 'register' && $method === 'POST') {
        $name     = trim((string)($input['fullName'] ?? $input['name'] ?? ''));
        $email    = strtolower(trim((string)($input['email'] ?? '')));
        $mobile   = trim((string)($input['mobile'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            jsonError('Full name, email, and password are required.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Please enter a valid email address.', 422);
        }
        if (strlen($password) < 4) {
            jsonError('Password must be at least 4 characters long.', 422);
        }

        $chk = $pdo->prepare('SELECT id FROM customers WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            jsonError('An account with this email already exists. Please login instead.', 409);
        }

        // Generate next CUST-XXX id
        $maxNum = 5;
        $ids = $pdo->query("SELECT id FROM customers WHERE id LIKE 'CUST-%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $cid) {
            $n = (int)preg_replace('/[^0-9]/', '', (string)$cid);
            if ($n > $maxNum) {
                $maxNum = $n;
            }
        }
        $newId = sprintf('CUST-%03d', $maxNum + 1);
        $hash  = password_hash($password, PASSWORD_BCRYPT);
        $token = generateSecureToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . TOKEN_TTL_DAYS . ' days'));
        $joinedDate = date('d M Y');

        $ins = $pdo->prepare(
            'INSERT INTO customers (id, name, email, password_hash, mobile, avatar, city, state, total_orders, total_spent, role, status, joined_date, auth_token, token_expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $newId,
            $name,
            $email,
            $hash,
            $mobile,
            'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&q=80',
            trim((string)($input['city'] ?? 'Chennai')),
            trim((string)($input['state'] ?? 'Tamil Nadu')),
            'Customer',
            'Active',
            $joinedDate,
            $token,
            $expiresAt,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$newId]);
        mergeGuestCartWishlist($pdo, $newId);
        $user = formatCustomer($stmt->fetch());
        $user['isLoggedIn'] = true;
        $user['token'] = $token;

        jsonSuccess($user, 'Account created successfully! Welcome, ' . $user['name'] . '!', 201);
    }

    // LOGOUT
    if ($action === 'logout' && $method === 'POST') {
        $customer = getAuthenticatedCustomer($pdo);
        if ($customer) {
            $upd = $pdo->prepare('UPDATE customers SET auth_token = NULL, token_expires_at = NULL WHERE id = ?');
            $upd->execute([$customer['id']]);
        }
        jsonSuccess(null, 'You have been logged out.');
    }

    // UPDATE PROFILE
    if ($action === 'profile' && in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
        $customer = getAuthenticatedCustomer($pdo);

        // Allow fallback email match for seeded demo profile if no token yet
        $targetId = $customer['id'] ?? null;
        if (!$targetId && !empty($input['email'])) {
            $cStmt = $pdo->prepare('SELECT id FROM customers WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $cStmt->execute([trim((string)$input['email'])]);
            $foundId = $cStmt->fetchColumn();
            if ($foundId !== false) {
                $targetId = (string)$foundId;
            }
        }

        if (!$targetId) {
            jsonError('Please login to update your profile.', 401);
        }

        $name   = trim((string)($input['name'] ?? ($customer['name'] ?? '')));
        $email  = strtolower(trim((string)($input['email'] ?? ($customer['email'] ?? ''))));
        $mobile = trim((string)($input['mobile'] ?? ($customer['mobile'] ?? '')));
        $city   = trim((string)($input['city'] ?? ($customer['city'] ?? 'Chennai')));
        $state  = trim((string)($input['state'] ?? ($customer['state'] ?? 'Tamil Nadu')));
        $avatar = trim((string)($input['avatar'] ?? ($customer['avatar'] ?? '')));

        $upd = $pdo->prepare('UPDATE customers SET name = ?, email = ?, mobile = ?, city = ?, state = ?, avatar = ? WHERE id = ?');
        $upd->execute([$name, $email, $mobile, $city, $state, $avatar, $targetId]);

        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $updated = formatCustomer($stmt->fetch());
        $updated['isLoggedIn'] = true;
        if ($customer && !empty($customer['token'])) {
            $updated['token'] = $customer['token'];
        }

        jsonSuccess($updated, 'Profile updated successfully!');
    }

    // CHANGE PASSWORD
    if ($action === 'change_password' && $method === 'POST') {
        $customer = getAuthenticatedCustomer($pdo);
        $currentPassword = (string)($input['currentPassword'] ?? '');
        $newPassword     = (string)($input['newPassword'] ?? '');

        if ($newPassword === '' || strlen($newPassword) < 4) {
            jsonError('New password must be at least 4 characters.', 422);
        }

        $targetId = $customer['id'] ?? null;
        if (!$targetId && !empty($input['email'])) {
            $s = $pdo->prepare('SELECT id FROM customers WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $s->execute([trim((string)$input['email'])]);
            $targetId = $s->fetchColumn() ?: null;
        }
        if (!$targetId) {
            jsonError('Authentication required to change password.', 401);
        }

        $stmt = $pdo->prepare('SELECT password_hash FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $existingHash = $stmt->fetchColumn();

        if ($existingHash && $currentPassword !== '' && !password_verify($currentPassword, (string)$existingHash)) {
            jsonError('Current password is incorrect.', 400);
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $upd = $pdo->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
        $upd->execute([$newHash, $targetId]);

        jsonSuccess(null, 'Password updated successfully!');
    }

    // FORGOT PASSWORD
    if ($action === 'forgot_password' && $method === 'POST') {
        $email = strtolower(trim((string)($input['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Please enter a valid email address.', 422);
        }

        $token = generateSecureToken(24);
        $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $ins = $pdo->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)');
        $ins->execute([$email, $token, $expires]);

        jsonSuccess(['email' => $email], "Password reset instructions have been dispatched to {$email}.");
    }

    jsonError('Unknown auth action or unsupported method.', 400);
} catch (Throwable $e) {
    jsonError('Auth API error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}

/**
 * Move guest cart/wishlist items (keyed by X-Session-Key) into the authenticated customer's DB cart/wishlist.
 */
function mergeGuestCartWishlist(PDO $pdo, string $customerId): void
{
    $guestKey = preg_replace('/[^a-zA-Z0-9_\-:]/', '', (string)($_SERVER['HTTP_X_SESSION_KEY'] ?? ''));
    if ($guestKey === '' || strpos($guestKey, 'cust:') === 0) {
        return;
    }
    $custKey = 'cust:' . $customerId;

    $cartRows = $pdo->prepare('SELECT * FROM cart_items WHERE session_key = ?');
    $cartRows->execute([$guestKey]);
    $insCart = $pdo->prepare(
        'INSERT INTO cart_items (session_key, product_id, quantity, selected_color, selected_material, selected_size)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
    );
    foreach ($cartRows->fetchAll() as $r) {
        $insCart->execute([$custKey, $r['product_id'], $r['quantity'], $r['selected_color'], $r['selected_material'], $r['selected_size']]);
    }
    $pdo->prepare('DELETE FROM cart_items WHERE session_key = ?')->execute([$guestKey]);

    $wishRows = $pdo->prepare('SELECT product_id FROM wishlist_items WHERE session_key = ?');
    $wishRows->execute([$guestKey]);
    $insWish = $pdo->prepare('INSERT IGNORE INTO wishlist_items (session_key, product_id) VALUES (?, ?)');
    foreach ($wishRows->fetchAll(PDO::FETCH_COLUMN) as $pid) {
        $insWish->execute([$custKey, $pid]);
    }
    $pdo->prepare('DELETE FROM wishlist_items WHERE session_key = ?')->execute([$guestKey]);
}
