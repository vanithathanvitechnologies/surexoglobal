<?php
/**
 * Admin Customers Master CRUD API
 *
 * GET    api/admin/customers.php
 * POST   api/admin/customers.php
 * PUT    api/admin/customers.php?id=...
 * DELETE api/admin/customers.php?id=...
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    requireAdmin($pdo);

    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
            $stmt->execute([trim((string)$_GET['id'])]);
            $row = $stmt->fetch();
            if (!$row) {
                jsonError('Customer not found.', 404);
            }
            jsonSuccess(formatCustomer($row), 'Customer fetched');
        }

        $customers = array_map('formatCustomer', $pdo->query('SELECT * FROM customers ORDER BY created_at DESC')->fetchAll());
        jsonSuccess($customers, 'Customers fetched', 200, ['total' => count($customers)]);
    }

    if ($method === 'POST') {
        $input = getJsonInput();
        $name  = trim((string)($input['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));

        if ($name === '' || $email === '') {
            jsonError('Customer name and email are required.', 422);
        }

        $chk = $pdo->prepare('SELECT id FROM customers WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            jsonError('A customer with this email already exists.', 409);
        }

        $maxNum = 5;
        $ids = $pdo->query("SELECT id FROM customers WHERE id LIKE 'CUST-%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $cid) {
            $n = (int)preg_replace('/[^0-9]/', '', (string)$cid);
            if ($n > $maxNum) {
                $maxNum = $n;
            }
        }
        $id = sprintf('CUST-%03d', $maxNum + 1);

        $mobile      = trim((string)($input['mobile'] ?? ''));
        $avatar      = trim((string)($input['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&q=80'));
        $city        = trim((string)($input['city'] ?? 'Chennai'));
        $state       = trim((string)($input['state'] ?? 'Tamil Nadu'));
        $totalOrders = max(0, (int)($input['totalOrders'] ?? 0));
        $totalSpent  = max(0.0, (float)($input['totalSpent'] ?? 0));
        $role        = trim((string)($input['role'] ?? 'Customer'));
        $status      = in_array($input['status'] ?? 'Active', ['Active', 'Suspended'], true) ? $input['status'] : 'Active';
        $joinedDate  = date('d M Y');
        $pwHash      = !empty($input['password']) ? password_hash((string)$input['password'], PASSWORD_BCRYPT) : password_hash('customer123', PASSWORD_BCRYPT);

        $ins = $pdo->prepare(
            'INSERT INTO customers (id, name, email, password_hash, mobile, avatar, city, state, total_orders, total_spent, role, status, joined_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$id, $name, $email, $pwHash, $mobile, $avatar, $city, $state, $totalOrders, $totalSpent, $role, $status, $joinedDate]);

        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $created = formatCustomer($stmt->fetch());

        jsonSuccess($created, "Customer account for \"{$created['name']}\" created!", 201);
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Customer ID is required.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonError('Customer not found.', 404);
        }

        $name        = isset($input['name']) ? trim((string)$input['name']) : (string)$existing['name'];
        $email       = isset($input['email']) ? strtolower(trim((string)$input['email'])) : (string)$existing['email'];
        $mobile      = isset($input['mobile']) ? trim((string)$input['mobile']) : (string)$existing['mobile'];
        $avatar      = isset($input['avatar']) ? trim((string)$input['avatar']) : (string)$existing['avatar'];
        $city        = isset($input['city']) ? trim((string)$input['city']) : (string)$existing['city'];
        $state       = isset($input['state']) ? trim((string)$input['state']) : (string)$existing['state'];
        $totalOrders = isset($input['totalOrders']) ? max(0, (int)$input['totalOrders']) : (int)$existing['total_orders'];
        $totalSpent  = isset($input['totalSpent']) ? max(0.0, (float)$input['totalSpent']) : (float)$existing['total_spent'];
        $role        = isset($input['role']) ? trim((string)$input['role']) : (string)$existing['role'];
        $status      = (isset($input['status']) && in_array($input['status'], ['Active', 'Suspended'], true)) ? $input['status'] : (string)$existing['status'];

        $upd = $pdo->prepare(
            'UPDATE customers SET name = ?, email = ?, mobile = ?, avatar = ?, city = ?, state = ?, total_orders = ?, total_spent = ?, role = ?, status = ? WHERE id = ?'
        );
        $upd->execute([$name, $email, $mobile, $avatar, $city, $state, $totalOrders, $totalSpent, $role, $status, $id]);

        $stmt->execute([$id]);
        jsonSuccess(formatCustomer($stmt->fetch()), 'Customer profile updated!');
    }

    if ($method === 'DELETE') {
        $input = getJsonInput();
        $id = trim((string)($_GET['id'] ?? ($input['id'] ?? '')));
        if ($id === '') {
            jsonError('Customer ID is required.', 422);
        }

        $del = $pdo->prepare('DELETE FROM customers WHERE id = ?');
        $del->execute([$id]);
        if ($del->rowCount() === 0) {
            jsonError('Customer not found.', 404);
        }

        jsonSuccess(['id' => $id], 'Customer record removed.');
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Admin customers error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
