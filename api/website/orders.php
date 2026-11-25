<?php
/**
 * Website Orders API
 *
 * GET  api/website/orders.php          (List customer orders or track specific order via ?id=%231005)
 * POST api/website/orders.php          (Place a new checkout order)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    $customer = getAuthenticatedCustomer($pdo);
    $sessionKey = resolveSessionKey($pdo);

    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            $orderId = resolveOrderId($pdo, (string)$_GET['id']);
            if (!$orderId) {
                jsonError('Order not found.', 404);
            }
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
            $stmt->execute([$orderId]);
            jsonSuccess(formatOrder($stmt->fetch()), 'Order fetched');
        }

        $email = trim((string)($_GET['email'] ?? ($customer['email'] ?? '')));
        if ($email !== '') {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE customer_email = ? ORDER BY created_at DESC');
            $stmt->execute([$email]);
        } else {
            $stmt = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 50');
        }

        $orders = array_map('formatOrder', $stmt->fetchAll());
        jsonSuccess($orders, 'Orders fetched', 200, ['total' => count($orders)]);
    }

    if ($method === 'POST') {
        $input = getJsonInput();

        $customerName  = trim((string)($input['customerName'] ?? $input['fullName'] ?? ($customer['name'] ?? 'Valued Customer')));
        $customerEmail = trim((string)($input['customerEmail'] ?? $input['email'] ?? ($customer['email'] ?? 'customer@surexoglobal.com')));
        $customerPhone = trim((string)($input['customerPhone'] ?? $input['mobile'] ?? ($customer['mobile'] ?? '+91 98765 43210')));

        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        $firstItem = $items[0] ?? [];

        $productName = trim((string)($input['productName'] ?? ($firstItem['name'] ?? 'Shopping Bag Order')));
        if (count($items) > 1 && empty($input['productName'])) {
            $productName = ($firstItem['name'] ?? 'Order Item') . ' (+' . (count($items) - 1) . ' more)';
        }

        $category = trim((string)($input['category'] ?? ($firstItem['categoryName'] ?? ($firstItem['category'] ?? 'General'))));
        $quantity = (int)($input['quantity'] ?? 0);
        if ($quantity <= 0) {
            $quantity = 0;
            foreach ($items as $it) {
                $quantity += max(1, (int)($it['quantity'] ?? 1));
            }
            if ($quantity <= 0) {
                $quantity = 1;
            }
        }

        $subtotal = (float)($input['subtotal'] ?? 0);
        if ($subtotal <= 0 && !empty($items)) {
            foreach ($items as $it) {
                $subtotal += ((float)($it['price'] ?? 0)) * max(1, (int)($it['quantity'] ?? 1));
            }
        }
        $discountAmount = max(0.0, (float)($input['discountAmount'] ?? 0));
        $shippingCost   = max(0.0, (float)($input['shippingCost'] ?? 0));
        $taxAmount      = max(0.0, (float)($input['taxAmount'] ?? 0));
        $total          = (float)($input['totalPayable'] ?? $input['total'] ?? max(0.0, $subtotal - $discountAmount + $shippingCost + $taxAmount));

        $paymentMethod = strtolower(trim((string)($input['paymentMethod'] ?? 'upi')));
        $paymentStatus = ($paymentMethod === 'cod') ? 'Pending (COD)' : 'Paid';

        $addressRaw = $input['deliveryAddress'] ?? $input['address'] ?? '';
        if (is_array($addressRaw)) {
            $address = trim((string)($addressRaw['addressLine'] ?? implode(', ', array_filter($addressRaw))));
        } else {
            $address = trim((string)$addressRaw);
        }
        if ($address === '') {
            $address = 'Standard Delivery Address';
        }

        $deliveryType = trim((string)($input['deliveryType'] ?? 'standard'));
        $orderNotes   = trim((string)($input['orderNotes'] ?? ''));
        $couponCode   = !empty($input['couponCode']) ? strtoupper(trim((string)$input['couponCode'])) : null;
        $image        = trim((string)($input['image'] ?? ($firstItem['image'] ?? 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&q=80')));

        // Generate sequential readable Order ID like #1006, #1007...
        $maxNum = 1005;
        $idRows = $pdo->query("SELECT id FROM orders WHERE id LIKE '#%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($idRows as $existingId) {
            $num = (int)preg_replace('/[^0-9]/', '', (string)$existingId);
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
        $newOrderId = '#' . ($maxNum + 1);
        $orderDate  = date('M d, Y');

        // Match or link customer ID
        $customerId = $customer['id'] ?? null;
        if (!$customerId && $customerEmail !== '') {
            $cStmt = $pdo->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
            $cStmt->execute([$customerEmail]);
            $foundCid = $cStmt->fetchColumn();
            if ($foundCid !== false) {
                $customerId = (string)$foundCid;
            }
        }

        $pdo->beginTransaction();

        $ins = $pdo->prepare(
            'INSERT INTO orders (
                id, order_date, customer_id, customer_name, customer_email, customer_phone,
                product_name, category, quantity, subtotal, discount_amount, shipping_cost, tax_amount, total,
                coupon_code, payment_method, payment_status, order_status, image, tracking_step,
                items, address, delivery_type, order_notes, admin_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $newOrderId,
            $orderDate,
            $customerId,
            $customerName,
            $customerEmail,
            $customerPhone,
            $productName,
            $category,
            $quantity,
            $subtotal > 0 ? $subtotal : $total,
            $discountAmount,
            $shippingCost,
            $taxAmount,
            $total,
            $couponCode,
            $paymentMethod,
            $paymentStatus,
            'Processing',
            $image,
            2,
            json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $address,
            $deliveryType,
            $orderNotes,
            '',
        ]);

        // Update customer lifetime order stats if linked
        if ($customerId) {
            $updCust = $pdo->prepare('UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id = ?');
            $updCust->execute([$total, $customerId]);
        }

        // Increment coupon usage counter if applied
        if ($couponCode) {
            $updCpn = $pdo->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE code = ? AND status = 'Active'");
            $updCpn->execute([$couponCode]);
        }

        // Clear server-side synced cart for this session
        $clrCart = $pdo->prepare('DELETE FROM cart_items WHERE session_key = ?');
        $clrCart->execute([$sessionKey]);

        $pdo->commit();

        $fetch = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $fetch->execute([$newOrderId]);
        $order = formatOrder($fetch->fetch());

        jsonSuccess($order, "Order {$newOrderId} placed successfully!", 201);
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Orders API error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
